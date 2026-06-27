const CACHE_NAME = 'hugin-display-offline-v1';
const DISPLAY_PAGE_RE = /^\/display\/[^/]+$/;
const DISPLAY_MANIFEST_RE = /^\/display\/[^/]+\/offline-manifest$/;
const DISPLAY_STATE_RE = /^\/display\/[^/]+\/state$/;
const CACHEABLE_PATH_RE = /^(?:\/assets\/|\/uploads\/|\/plugin-assets\/|\/display-service-worker\.js$)/;
const CRITICAL_STATIC_RE = /^(?:\/assets\/(?:js\/slideshow\.js|css\/display\.css)|\/display-service-worker\.js$)/;
const VIDEO_DB_NAME = 'hugin-display-video-cache-v1';
const VIDEO_CHUNK_SIZE = 2 * 1024 * 1024;

self.addEventListener('install', event => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.filter(name => name.startsWith('hugin-display-offline-') && name !== CACHE_NAME).map(name => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener('message', event => {
    const data = event.data || {};
    if (data.type === 'CACHE_DISPLAY_MANIFEST') {
        const port = event.ports?.[0] || null;
        event.waitUntil(cacheDisplayManifest(data.manifest, Number(data.maxBytes || 0), progress => {
            port?.postMessage({ ok: true, type: 'CACHE_DISPLAY_MANIFEST_PROGRESS', progress });
        }).then(result => {
            port?.postMessage(Object.assign({ ok: true, type: 'CACHE_DISPLAY_MANIFEST_RESULT' }, result));
        }).catch(error => {
            port?.postMessage({ ok: false, type: 'CACHE_DISPLAY_MANIFEST_RESULT', error: String(error?.message || error) });
        }));
        return;
    }

    if (data.type === 'GET_CACHE_STATUS') {
        event.waitUntil(cacheStatus(Array.isArray(data.urls) ? data.urls : []).then(result => {
            event.ports?.[0]?.postMessage(Object.assign({ ok: true, type: 'GET_CACHE_STATUS_RESULT' }, result));
        }).catch(error => {
            event.ports?.[0]?.postMessage({ ok: false, type: 'GET_CACHE_STATUS_RESULT', error: String(error?.message || error) });
        }));
    }
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (DISPLAY_STATE_RE.test(url.pathname)) {
        event.respondWith(fetch(request));
        return;
    }

    if (DISPLAY_PAGE_RE.test(url.pathname) || DISPLAY_MANIFEST_RE.test(url.pathname)) {
        event.respondWith(networkFirst(request));
        return;
    }

    if (CRITICAL_STATIC_RE.test(url.pathname)) {
        event.respondWith(networkFirst(request));
        return;
    }

    if (CACHEABLE_PATH_RE.test(url.pathname)) {
        event.respondWith((async () => {
            if (!await isDisplayClientRequest(event)) {
                return fetch(request);
            }

            return cacheableAsset(request);
        })());
    }
});

async function isDisplayClientRequest(event) {
    const clientId = event.clientId || event.resultingClientId || '';
    if (!clientId) return false;

    try {
        const client = await self.clients.get(clientId);
        if (!client?.url) return false;
        return DISPLAY_PAGE_RE.test(new URL(client.url).pathname);
    } catch (error) {
        return false;
    }
}

async function networkFirst(request) {
    const cache = await caches.open(CACHE_NAME);
    try {
        const response = await fetch(request);
        if (canCache(response)) {
            await cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cached = await cache.match(request, { ignoreSearch: false });
        if (cached) return cached;
        throw error;
    }
}

async function cacheableAsset(request) {
    if (request.headers.has('Range')) {
        try {
            return await fetch(request);
        } catch (error) {
            const partial = await cachedRangeResponse(request) || await idbRangeResponse(request);
            if (partial) return partial;
            throw error;
        }
    }

    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request, { ignoreSearch: false });
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (canCache(response)) {
            await cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const fullVideo = await idbFullResponse(request);
        if (fullVideo) return fullVideo;
        throw error;
    }
}

async function cachedRangeResponse(request) {
    const range = request.headers.get('Range') || '';
    const match = range.match(/^bytes=(\d*)-(\d*)$/);
    if (!match) return null;

    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request.url, { ignoreSearch: false });
    if (!cached) return null;

    const blob = await cached.blob();
    const size = blob.size;
    let start = match[1] === '' ? null : Number(match[1]);
    let end = match[2] === '' ? null : Number(match[2]);

    if (start === null && end !== null) {
        start = Math.max(0, size - end);
        end = size - 1;
    } else {
        start = start ?? 0;
        end = end ?? (size - 1);
    }

    if (!Number.isFinite(start) || !Number.isFinite(end) || start < 0 || end < start || start >= size) {
        return new Response('', {
            status: 416,
            headers: { 'Content-Range': `bytes */${size}` },
        });
    }

    end = Math.min(end, size - 1);
    const body = blob.slice(start, end + 1, cached.headers.get('Content-Type') || 'application/octet-stream');
    return new Response(body, {
        status: 206,
        headers: {
            'Content-Type': cached.headers.get('Content-Type') || 'application/octet-stream',
            'Content-Length': String(end - start + 1),
            'Content-Range': `bytes ${start}-${end}/${size}`,
            'Accept-Ranges': 'bytes',
        },
    });
}

async function cacheDisplayManifest(manifest, maxBytes, onProgress = () => {}) {
    if (!manifest || manifest.ok !== true || !Array.isArray(manifest.assets)) {
        throw new Error('Invalid offline manifest.');
    }

    const cache = await caches.open(CACHE_NAME);
    const cachedUrls = [];
    const skippedUrls = [];
    let reservedBytes = 0;
    let completedAssets = 0;
    let cachedAssets = 0;

    const sortedAssets = manifest.assets.slice().sort((left, right) => priority(left) - priority(right));
    const totalAssets = sortedAssets.length;
    const report = stage => {
        try {
            onProgress({
                stage,
                completed: completedAssets,
                total: totalAssets,
                cached: cachedAssets,
                skipped: skippedUrls.length,
                bytesReserved: reservedBytes,
            });
        } catch (error) {}
    };

    report('preparing');
    for (const asset of sortedAssets) {
        const url = absoluteSameOriginUrl(asset?.url);
        if (!url) {
            completedAssets += 1;
            skippedUrls.push('');
            report('caching');
            continue;
        }

        const size = Math.max(0, Number(asset.size || 0));
        const budgeted = size > 0 ? size : 512 * 1024;
        if (maxBytes > 0 && reservedBytes + budgeted > maxBytes && asset.required !== true) {
            skippedUrls.push(url);
            completedAssets += 1;
            report('caching');
            continue;
        }
        if (maxBytes > 0 && reservedBytes + budgeted > maxBytes && asset.required === true && isLargeMedia(asset)) {
            skippedUrls.push(url);
            completedAssets += 1;
            report('caching');
            continue;
        }

        const request = new Request(url, { method: 'GET', credentials: 'same-origin', cache: 'reload' });
        if (asset.kind === 'video') {
            try {
                if (await cacheVideoAsset(url)) {
                    cachedUrls.push(url);
                    reservedBytes += budgeted;
                    cachedAssets += 1;
                } else {
                    skippedUrls.push(url);
                }
            } catch (error) {
                skippedUrls.push(url);
            }
            completedAssets += 1;
            report('caching');
            continue;
        }

        const alreadyCached = await cache.match(request, { ignoreSearch: false });
        if (alreadyCached) {
            cachedUrls.push(url);
            reservedBytes += budgeted;
            cachedAssets += 1;
            completedAssets += 1;
            report('caching');
            continue;
        }

        try {
            const response = await fetch(request);
            if (!canCache(response)) {
                skippedUrls.push(url);
                completedAssets += 1;
                report('caching');
                continue;
            }
            await cache.put(request, response.clone());
            cachedUrls.push(url);
            reservedBytes += budgeted;
            cachedAssets += 1;
        } catch (error) {
            skippedUrls.push(url);
        }
        completedAssets += 1;
        report('caching');
    }

    const allManifestUrls = manifest.assets.map(asset => absoluteSameOriginUrl(asset.url)).filter(Boolean);
    const status = await cacheStatus(allManifestUrls);
    report('complete');
    return {
        signature: String(manifest.signature || ''),
        cachedUrls: status.cachedUrls,
        skippedUrls: skippedUrls.filter(Boolean),
        bytesReserved: reservedBytes,
        totalAssets: allManifestUrls.length,
        cachedAssets: status.cachedUrls.length,
        skippedAssets: skippedUrls.filter(Boolean).length,
    };
}

async function cacheStatus(urls) {
    const cache = await caches.open(CACHE_NAME);
    const cachedUrls = [];
    for (const input of urls) {
        const url = absoluteSameOriginUrl(input);
        if (!url) continue;
        const cached = await cache.match(url, { ignoreSearch: false });
        if (cached || await hasVideoAsset(url)) cachedUrls.push(url);
    }
    return { cachedUrls };
}

async function cacheVideoAsset(url) {
    if (!self.indexedDB) return false;
    if (await hasVideoAsset(url)) return true;

    const request = new Request(url, { method: 'GET', credentials: 'same-origin', cache: 'reload' });
    const response = await fetch(request);
    if (!canCache(response)) return false;

    const contentType = response.headers.get('Content-Type') || 'application/octet-stream';
    await deleteVideoAsset(url);

    if (!response.body?.getReader) {
        const buffer = await response.arrayBuffer();
        let index = 0;
        for (let offset = 0; offset < buffer.byteLength; offset += VIDEO_CHUNK_SIZE) {
            await putVideoChunk(url, index, buffer.slice(offset, Math.min(offset + VIDEO_CHUNK_SIZE, buffer.byteLength)));
            index += 1;
        }
        await putVideoMeta({ url, size: buffer.byteLength, contentType, chunks: index, updatedAt: Date.now() });
        return true;
    }

    const reader = response.body.getReader();
    let pending = new Uint8Array(0);
    let size = 0;
    let index = 0;

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        const chunk = value instanceof Uint8Array ? value : new Uint8Array(value);
        const merged = new Uint8Array(pending.length + chunk.length);
        merged.set(pending, 0);
        merged.set(chunk, pending.length);
        pending = merged;

        while (pending.length >= VIDEO_CHUNK_SIZE) {
            const stored = pending.slice(0, VIDEO_CHUNK_SIZE);
            await putVideoChunk(url, index, stored.buffer);
            size += stored.byteLength;
            pending = pending.slice(VIDEO_CHUNK_SIZE);
            index += 1;
        }
    }

    if (pending.length > 0) {
        await putVideoChunk(url, index, pending.buffer.slice(pending.byteOffset, pending.byteOffset + pending.byteLength));
        size += pending.byteLength;
        index += 1;
    }

    await putVideoMeta({ url, size, contentType, chunks: index, updatedAt: Date.now() });
    return index > 0;
}

async function idbRangeResponse(request) {
    const range = request.headers.get('Range') || '';
    const match = range.match(/^bytes=(\d*)-(\d*)$/);
    if (!match) return null;

    const url = absoluteSameOriginUrl(request.url);
    const meta = await getVideoMeta(url);
    if (!meta) return null;

    let start = match[1] === '' ? null : Number(match[1]);
    let end = match[2] === '' ? null : Number(match[2]);
    if (start === null && end !== null) {
        start = Math.max(0, meta.size - end);
        end = meta.size - 1;
    } else {
        start = start ?? 0;
        end = end ?? (meta.size - 1);
    }

    if (!Number.isFinite(start) || !Number.isFinite(end) || start < 0 || end < start || start >= meta.size) {
        return new Response('', { status: 416, headers: { 'Content-Range': `bytes */${meta.size}` } });
    }

    end = Math.min(end, meta.size - 1);
    const body = await videoBlobForRange(url, start, end, meta.contentType);
    return new Response(body, {
        status: 206,
        headers: {
            'Content-Type': meta.contentType || 'application/octet-stream',
            'Content-Length': String(end - start + 1),
            'Content-Range': `bytes ${start}-${end}/${meta.size}`,
            'Accept-Ranges': 'bytes',
        },
    });
}

async function idbFullResponse(request) {
    const url = absoluteSameOriginUrl(request.url);
    const meta = await getVideoMeta(url);
    if (!meta) return null;
    const body = await videoBlobForRange(url, 0, meta.size - 1, meta.contentType);
    return new Response(body, {
        status: 200,
        headers: {
            'Content-Type': meta.contentType || 'application/octet-stream',
            'Content-Length': String(meta.size),
            'Accept-Ranges': 'bytes',
        },
    });
}

async function videoBlobForRange(url, start, end, contentType) {
    const firstChunk = Math.floor(start / VIDEO_CHUNK_SIZE);
    const lastChunk = Math.floor(end / VIDEO_CHUNK_SIZE);
    const parts = [];
    for (let index = firstChunk; index <= lastChunk; index += 1) {
        const buffer = await getVideoChunk(url, index);
        if (!buffer) throw new Error('Cached video chunk is missing.');
        const localStart = index === firstChunk ? start % VIDEO_CHUNK_SIZE : 0;
        const localEnd = index === lastChunk ? (end % VIDEO_CHUNK_SIZE) + 1 : buffer.byteLength;
        parts.push(buffer.slice(localStart, localEnd));
    }
    return new Blob(parts, { type: contentType || 'application/octet-stream' });
}

async function hasVideoAsset(url) {
    return Boolean(await getVideoMeta(absoluteSameOriginUrl(url)));
}

function openVideoDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(VIDEO_DB_NAME, 1);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains('meta')) {
                db.createObjectStore('meta', { keyPath: 'url' });
            }
            if (!db.objectStoreNames.contains('chunks')) {
                db.createObjectStore('chunks', { keyPath: ['url', 'index'] });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error || new Error('IndexedDB open failed.'));
    });
}

async function idbRequest(storeName, mode, callback) {
    const db = await openVideoDb();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, mode);
        const store = transaction.objectStore(storeName);
        const request = callback(store);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error || new Error('IndexedDB request failed.'));
        transaction.oncomplete = () => db.close();
        transaction.onerror = () => {
            db.close();
            reject(transaction.error || new Error('IndexedDB transaction failed.'));
        };
    });
}

async function getVideoMeta(url) {
    if (!url || !self.indexedDB) return null;
    return await idbRequest('meta', 'readonly', store => store.get(url));
}

async function putVideoMeta(meta) {
    return await idbRequest('meta', 'readwrite', store => store.put(meta));
}

async function getVideoChunk(url, index) {
    const row = await idbRequest('chunks', 'readonly', store => store.get([url, index]));
    return row?.data || null;
}

async function putVideoChunk(url, index, data) {
    return await idbRequest('chunks', 'readwrite', store => store.put({ url, index, data }));
}

async function deleteVideoAsset(url) {
    const meta = await getVideoMeta(url);
    if (!meta) return;
    for (let index = 0; index < Number(meta.chunks || 0); index += 1) {
        await idbRequest('chunks', 'readwrite', store => store.delete([url, index]));
    }
    await idbRequest('meta', 'readwrite', store => store.delete(url));
}

function canCache(response) {
    return response && response.ok && (response.type === 'basic' || response.type === 'default');
}

function priority(asset) {
    if (asset?.type === 'shell') return 0;
    if (asset?.type === 'manifest') return 1;
    if (['style', 'script', 'font'].includes(asset?.kind)) return 2;
    if (asset?.kind === 'image') return 3;
    if (asset?.kind === 'video') return 4;
    return 5;
}

function isLargeMedia(asset) {
    return ['video', 'image'].includes(asset?.kind) || asset?.type === 'media';
}

function absoluteSameOriginUrl(input) {
    if (!input) return '';
    try {
        const url = new URL(String(input), self.location.href);
        if (url.origin !== self.location.origin) return '';
        url.hash = '';
        return url.toString();
    } catch (error) {
        return '';
    }
}
