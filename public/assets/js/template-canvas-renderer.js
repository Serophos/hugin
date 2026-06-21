(() => {
    const shapeTypes = ['square', 'circle', 'triangle', 'diamond', 'star', 'hexagon', 'pentagon', 'arrow'];
    const dateTimeModes = ['clock', 'date'];
    const timeFormats = ['24h', 'ampm'];
    const dynamicTextModes = ['carousel', 'marquee', 'typewriter', 'push_carousel', 'word_reveal'];
    const dynamicTextDirections = ['left', 'right'];
    const dropShadowDirections = ['top', 'top-right', 'right', 'bottom-right', 'bottom', 'bottom-left', 'left', 'top-left'];

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, Number(String(value).replace(',', '.')) || 0));
    }

    function pct(value) {
        return `${clamp(value, 0, 1) * 100}%`;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }

    function attr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function cssColor(value) {
        return String(value || '').trim() || 'transparent';
    }

    function truthy(value) {
        if (typeof value === 'boolean') return value;
        if (typeof value === 'number') return value !== 0;
        if (typeof value === 'string') return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase());
        return false;
    }

    function normalizeShapeType(shape) {
        const value = String(shape || '').trim().toLowerCase();
        return shapeTypes.includes(value) ? value : 'square';
    }

    function roundedShapeElement(element) {
        return element?.type === 'shape' && normalizeShapeType(element.style?.shape || 'square') === 'square';
    }

    function normalizeDateTimeMode(mode) {
        const value = String(mode || '').trim().toLowerCase();
        return dateTimeModes.includes(value) ? value : 'clock';
    }

    function normalizeTimeFormat(format) {
        const value = String(format || '').trim().toLowerCase();
        return timeFormats.includes(value) ? value : '24h';
    }

    function normalizeDropShadowDirection(direction) {
        const value = String(direction || '').trim().toLowerCase();
        return dropShadowDirections.includes(value) ? value : 'bottom-right';
    }

    function dropShadowSettings(element) {
        const style = element?.style || {};
        return {
            enabled: element?.type !== 'background' && truthy(style.dropShadow),
            offset: clamp(style.dropShadowOffset ?? 2, 0, 40),
            blur: clamp(style.dropShadowBlur ?? 4, 0, 60),
            color: cssColor(style.dropShadowColor || 'rgba(0, 0, 0, 0.35)'),
            direction: normalizeDropShadowDirection(style.dropShadowDirection || 'bottom-right'),
        };
    }

    function dropShadowVector(direction, offset) {
        const diagonal = Number((offset * 0.7071).toFixed(4));
        switch (normalizeDropShadowDirection(direction)) {
            case 'top': return [0, -offset];
            case 'top-right': return [diagonal, -diagonal];
            case 'right': return [offset, 0];
            case 'bottom': return [0, offset];
            case 'bottom-left': return [-diagonal, diagonal];
            case 'left': return [-offset, 0];
            case 'top-left': return [-diagonal, -diagonal];
            default: return [diagonal, diagonal];
        }
    }

    function cqwLength(value) {
        return `${Number(Number(value).toFixed(4))}cqw`;
    }

    function dropShadowCss(element, filter = false) {
        const shadow = dropShadowSettings(element);
        if (!shadow.enabled) return '';
        const [x, y] = dropShadowVector(shadow.direction, shadow.offset);
        const value = `${cqwLength(x)} ${cqwLength(y)} ${cqwLength(shadow.blur)} ${shadow.color}`;
        return filter ? `drop-shadow(${value})` : value;
    }

    function elementBoxShadow(element) {
        return dropShadowCss(element, false) || '0 0 0 rgba(0, 0, 0, 0)';
    }

    function normalizeFontFamily(value, fontOptions) {
        const raw = String(value || '').trim();
        return (fontOptions || []).some(option => option.value === raw) ? raw : '';
    }

    function fontCssForToken(value, fontOptions) {
        const normalized = normalizeFontFamily(value, fontOptions);
        if (normalized === '') return '';
        return (fontOptions || []).find(option => option.value === normalized)?.css || '';
    }

    function fieldMapFor(spec, fields) {
        const map = new Map();
        (Array.isArray(fields) ? fields : []).forEach(field => {
            const key = String(field?.key || '');
            if (key !== '') map.set(key, field);
        });
        if (map.size === 0) {
            (Array.isArray(spec?.fields) ? spec.fields : []).forEach(field => {
                const key = String(field?.key || '');
                if (key !== '') map.set(key, field);
            });
        }
        return map;
    }

    function valuePresent(value) {
        if (value === null || value === undefined) return false;
        return String(value).trim() !== '';
    }

    function fieldPlaceholder(field, options = {}) {
        const type = String(field?.type || 'text');
        if (type === 'url' || type === 'qr_url') return options.previewQrUrl || 'https://example.com';
        if (type === 'media_image' || type === 'media_video') return '';
        return String(field?.label || field?.key || '');
    }

    function resolvedFieldValue(field, values, options = {}) {
        const key = String(field?.key || '');
        if (key !== '' && Object.prototype.hasOwnProperty.call(values || {}, key) && valuePresent(values[key])) {
            return values[key];
        }
        if (valuePresent(field?.default)) {
            return field.default;
        }
        return fieldPlaceholder(field, options);
    }

    function fieldForElement(element, fieldMap) {
        const key = String(element?.field || '');
        return key !== '' ? fieldMap.get(key) || null : null;
    }

    function textElement(element) {
        return ['text', 'dynamic_text', 'datetime', 'countdown'].includes(element?.type || '');
    }

    function applyTextPreviewStyle(node, element, fontOptions) {
        if (element.style?.fontSize) node.style.fontSize = `clamp(0.7rem, ${Number(element.style.fontSize)}cqw, 4rem)`;
        if (element.style?.fontWeight) node.style.fontWeight = element.style.fontWeight;
        if (element.style?.align) node.style.textAlign = element.style.align;
        const fontFamily = fontCssForToken(element.style?.fontFamily || '', fontOptions);
        if (fontFamily) node.style.fontFamily = fontFamily;
    }

    function mediaMap(mediaAssets) {
        return new Map((Array.isArray(mediaAssets) ? mediaAssets : []).map(asset => [Number(asset.id), asset]));
    }

    function mediaAsset(id, mediaById) {
        return mediaById.get(Number(id || 0)) || null;
    }

    function svgNumber(value) {
        return String(Number(Number(value).toFixed(4)));
    }

    function svgPoints(points) {
        return points.map(point => `${svgNumber(point[0])},${svgNumber(point[1])}`).join(' ');
    }

    function regularPolygonPoints(sides, radius) {
        const start = -Math.PI / 2;
        const points = [];
        for (let index = 0; index < sides; index += 1) {
            const angle = start + ((2 * Math.PI * index) / sides);
            points.push([50 + (radius * Math.cos(angle)), 50 + (radius * Math.sin(angle))]);
        }
        return svgPoints(points);
    }

    function regularStarPoints(outerRadius, innerRadius) {
        const start = -Math.PI / 2;
        const points = [];
        for (let index = 0; index < 10; index += 1) {
            const radius = index % 2 === 0 ? outerRadius : innerRadius;
            const angle = start + ((Math.PI * index) / 5);
            points.push([50 + (radius * Math.cos(angle)), 50 + (radius * Math.sin(angle))]);
        }
        return svgPoints(points);
    }

    function shapeMarkup(shape, inset, radius) {
        const min = inset;
        const max = 100 - inset;
        const size = Math.max(0, max - min);
        const normalized = normalizeShapeType(shape);

        if (normalized === 'circle') {
            const r = Math.max(0, 50 - inset);
            return `<ellipse cx="50" cy="50" rx="${svgNumber(r)}" ry="${svgNumber(r)}"></ellipse>`;
        }
        if (normalized === 'square') {
            const cornerRadius = Math.max(0, Math.min(50, Number(radius) || 0));
            return `<rect x="${svgNumber(min)}" y="${svgNumber(min)}" width="${svgNumber(size)}" height="${svgNumber(size)}" rx="${svgNumber(cornerRadius)}" ry="${svgNumber(cornerRadius)}"></rect>`;
        }
        if (normalized === 'arrow') {
            return `<polygon points="${svgPoints([[min, 28], [56, 28], [56, min], [max, 50], [56, max], [56, 72], [min, 72]])}"></polygon>`;
        }
        if (normalized === 'triangle') return `<polygon points="${svgPoints([[50, min], [max, max], [min, max]])}"></polygon>`;
        if (normalized === 'diamond') return `<polygon points="${svgPoints([[50, min], [max, 50], [50, max], [min, 50]])}"></polygon>`;
        if (normalized === 'star') return `<polygon points="${regularStarPoints(Math.max(0, 50 - inset), Math.max(0, 22 - (inset / 2)))}"></polygon>`;
        return `<polygon points="${regularPolygonPoints(normalized === 'pentagon' ? 5 : 6, Math.max(0, 50 - inset))}"></polygon>`;
    }

    function shapeSvgMarkup(element, className = 'template-editor__shape-svg') {
        const style = element?.style || {};
        const shape = normalizeShapeType(style.shape || 'square');
        const strokeWidth = clamp(style.borderWidth || 0, 0, 40);
        const stroke = strokeWidth > 0 ? cssColor(style.borderColor || 'rgba(0, 0, 0, 0)') : 'none';
        const fill = cssColor(style.backgroundColor || 'rgba(0, 0, 0, 0)');
        const radius = roundedShapeElement(element) ? Number(style.radius || 0) : 0;
        const shadow = dropShadowCss(element, true);
        const styleAttr = shadow ? ` style="filter: ${attr(shadow)}"` : '';

        return `<svg class="${attr(className)} ${attr(`${className}--${shape}`)}" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false"${styleAttr} fill="${attr(fill)}" stroke="${attr(stroke)}" stroke-width="${attr(strokeWidth)}" stroke-linejoin="round" stroke-linecap="round">${shapeMarkup(shape, strokeWidth / 2, radius)}</svg>`;
    }

    function pad2(value) {
        return String(Math.max(0, Number(value) || 0)).padStart(2, '0');
    }

    function dateTimePreviewValue(element) {
        const now = new Date();
        if (normalizeDateTimeMode(element?.style?.dateTimeMode || 'clock') === 'date') {
            return `${pad2(now.getDate())}.${pad2(now.getMonth() + 1)}.${now.getFullYear()}`;
        }

        const minutes = pad2(now.getMinutes());
        if (normalizeTimeFormat(element?.style?.timeFormat || '24h') === 'ampm') {
            const hours24 = now.getHours();
            const hours12 = hours24 % 12 || 12;
            return `${pad2(hours12)}:${minutes} ${hours24 >= 12 ? 'PM' : 'AM'}`;
        }

        return `${pad2(now.getHours())}:${minutes}`;
    }

    function normalizeCountdownTarget(value) {
        const raw = String(value || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/.test(raw)) return '';
        const parsed = new Date(raw);
        return Number.isFinite(parsed.getTime()) ? raw.slice(0, 16) : '';
    }

    function countdownTargetMs(value) {
        const target = normalizeCountdownTarget(value);
        if (!target) return NaN;
        const parsed = new Date(target).getTime();
        return Number.isFinite(parsed) ? parsed : NaN;
    }

    function formatCountdownSeconds(totalSeconds) {
        let remaining = Math.max(0, Math.floor(Number(totalSeconds) || 0));
        const days = Math.floor(remaining / 86400);
        remaining %= 86400;
        const hours = Math.floor(remaining / 3600);
        remaining %= 3600;
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        return `${pad2(days)}d ${pad2(hours)}h ${pad2(minutes)}m ${pad2(seconds)}s`;
    }

    function countdownPreviewValue(element) {
        const targetMs = countdownTargetMs(element?.style?.countdownTarget || '');
        if (!Number.isFinite(targetMs)) return formatCountdownSeconds(0);
        return formatCountdownSeconds((targetMs - Date.now()) / 1000);
    }

    function normalizeDynamicTextMode(mode) {
        const value = String(mode || '').trim().toLowerCase();
        return dynamicTextModes.includes(value) ? value : 'carousel';
    }

    function normalizeDynamicTextDirection(direction) {
        const value = String(direction || '').trim().toLowerCase();
        return dynamicTextDirections.includes(value) ? value : 'left';
    }

    function dynamicTextLines(value) {
        const rawLines = Array.isArray(value)
            ? value.flatMap(line => String(line ?? '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n'))
            : String(value ?? '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');

        // Ignore empty textarea rows so accidental blank lines do not become blank carousel slides.
        return rawLines.map(line => String(line).trim()).filter(line => line !== '').slice(0, 100);
    }

    function dynamicTextSettings(element) {
        const input = element?.dynamicText && typeof element.dynamicText === 'object' ? element.dynamicText : {};
        return {
            lines: dynamicTextLines(input.lines || []),
            mode: normalizeDynamicTextMode(input.mode || 'carousel'),
            intervalMs: Math.round(clamp(input.intervalMs ?? 4000, 500, 60000)),
            transitionMs: Math.round(clamp(input.transitionMs ?? 400, 0, 5000)),
            marqueeDurationMs: Math.round(clamp(input.marqueeDurationMs ?? 12000, 1000, 120000)),
            direction: normalizeDynamicTextDirection(input.direction || 'left'),
            fade: input.fade === undefined ? true : truthy(input.fade),
        };
    }

    function dynamicTextPreviewLines(element, context, settings) {
        const field = fieldForElement(element, context.fieldMap);
        if (field) {
            return dynamicTextLines(resolvedFieldValue(field, context.values, context));
        }

        return settings.lines.length > 0 ? settings.lines : [elementLabel(element, context.i18n, context.fieldMap)];
    }

    function clearDynamicTextNodeRuntime(node) {
        if (!node) return;
        if (typeof node._huginDynamicTextCleanup === 'function') {
            node._huginDynamicTextCleanup();
            node._huginDynamicTextCleanup = null;
        }
        if (node._huginDynamicTextTimer) {
            window.clearInterval(node._huginDynamicTextTimer);
            node._huginDynamicTextTimer = 0;
        }
    }

    function setDynamicTextCleanup(node, cleanup) {
        clearDynamicTextNodeRuntime(node);
        node._huginDynamicTextCleanup = cleanup;
    }

    function clearDynamicTextRuntime(root) {
        root?.querySelectorAll?.('[data-template-editor-dynamic-text-runtime]').forEach(clearDynamicTextNodeRuntime);
    }

    function dynamicTextPrefersReducedMotion() {
        return !!window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
    }

    function dynamicTextTransitionMs(settings) {
        return Math.max(0, Math.min(5000, Math.round(Number(settings.transitionMs) || 0)));
    }

    function activeDynamicTextLineIndex(lines) {
        const index = lines.findIndex(line => line.classList.contains('is-active'));
        if (index >= 0) return index;
        lines[0]?.classList.add('is-active');
        return 0;
    }

    function startDynamicTextLineRotation(node, settings, push = false) {
        const lines = Array.from(node.querySelectorAll('.template-editor__dynamic-text-line'));
        if (lines.length <= 1) return;

        let index = activeDynamicTextLineIndex(lines);
        const exitTimers = new Set();
        const clearExitTimers = () => {
            exitTimers.forEach(timer => window.clearTimeout(timer));
            exitTimers.clear();
        };

        // Runtime animation state is deliberately attached to this rendered DOM node only.
        const timer = window.setInterval(() => {
            const previous = lines[index];
            index = (index + 1) % lines.length;
            const next = lines[index];

            if (push) {
                clearExitTimers();
                previous?.classList.add('is-exiting');
                previous?.classList.remove('is-active');
                next?.classList.add('is-active');
                const exitTimer = window.setTimeout(() => {
                    previous?.classList.remove('is-exiting');
                    exitTimers.delete(exitTimer);
                }, dynamicTextTransitionMs(settings));
                exitTimers.add(exitTimer);
                return;
            }

            previous?.classList.remove('is-active');
            next?.classList.add('is-active');
        }, settings.intervalMs);

        setDynamicTextCleanup(node, () => {
            window.clearInterval(timer);
            node._huginDynamicTextTimer = 0;
            clearExitTimers();
        });
        node._huginDynamicTextTimer = timer;
    }

    function startDynamicTextTypewriter(node, settings) {
        const lines = Array.from(node.querySelectorAll('.template-editor__dynamic-text-line'));
        if (lines.length === 0) return;

        const sourceLines = lines.map(line => line.textContent || '\u00a0');
        const timers = new Set();
        const schedule = (callback, delayMs) => {
            const timer = window.setTimeout(() => {
                timers.delete(timer);
                callback();
            }, Math.max(0, delayMs));
            timers.add(timer);
        };
        let lineIndex = activeDynamicTextLineIndex(lines);

        setDynamicTextCleanup(node, () => {
            timers.forEach(timer => window.clearTimeout(timer));
            timers.clear();
        });

        const showLine = () => {
            lines.forEach((line, index) => {
                line.classList.toggle('is-active', index === lineIndex);
                line.textContent = index === lineIndex ? '' : sourceLines[index];
            });

            const activeLine = lines[lineIndex];
            const characters = Array.from(sourceLines[lineIndex] || '\u00a0');
            const transitionMs = dynamicTextTransitionMs(settings);
            const stepMs = characters.length > 0 && transitionMs > 0 ? transitionMs / characters.length : 0;

            const reveal = characterIndex => {
                activeLine.textContent = characters.slice(0, characterIndex).join('') || '\u00a0';
                if (characterIndex >= characters.length) {
                    schedule(() => {
                        lineIndex = (lineIndex + 1) % lines.length;
                        showLine();
                    }, settings.intervalMs);
                    return;
                }
                schedule(() => reveal(characterIndex + 1), stepMs);
            };

            reveal(transitionMs > 0 ? 0 : characters.length);
        };

        showLine();
    }

    function dynamicTextWordParts(text) {
        const parts = String(text || '\u00a0').split(/(\s+)/).filter(part => part !== '');
        return parts.length > 0 ? parts.map(part => ({ text: part, word: !/^\s+$/.test(part) })) : [{ text: '\u00a0', word: false }];
    }

    function renderDynamicTextWords(line, parts, visibleWords) {
        line.textContent = '';
        let wordIndex = 0;
        parts.forEach(part => {
            if (!part.word) {
                line.appendChild(document.createTextNode(part.text));
                return;
            }

            wordIndex += 1;
            const word = document.createElement('span');
            word.className = `template-editor__dynamic-text-word ${wordIndex <= visibleWords ? 'is-visible' : ''}`.trim();
            word.textContent = part.text;
            line.appendChild(word);
        });
    }

    function startDynamicTextWordReveal(node, settings) {
        const lines = Array.from(node.querySelectorAll('.template-editor__dynamic-text-line'));
        if (lines.length === 0) return;

        const sourceLines = lines.map(line => line.textContent || '\u00a0');
        const timers = new Set();
        const schedule = (callback, delayMs) => {
            const timer = window.setTimeout(() => {
                timers.delete(timer);
                callback();
            }, Math.max(0, delayMs));
            timers.add(timer);
        };
        let lineIndex = activeDynamicTextLineIndex(lines);

        setDynamicTextCleanup(node, () => {
            timers.forEach(timer => window.clearTimeout(timer));
            timers.clear();
        });

        const showLine = () => {
            lines.forEach((line, index) => {
                line.classList.toggle('is-active', index === lineIndex);
                line.textContent = sourceLines[index];
            });

            const activeLine = lines[lineIndex];
            const parts = dynamicTextWordParts(sourceLines[lineIndex]);
            const wordCount = parts.filter(part => part.word).length;
            const transitionMs = dynamicTextTransitionMs(settings);
            const stepMs = wordCount > 0 && transitionMs > 0 ? transitionMs / wordCount : 0;

            const reveal = visibleWords => {
                renderDynamicTextWords(activeLine, parts, visibleWords);
                if (visibleWords >= wordCount) {
                    schedule(() => {
                        lineIndex = (lineIndex + 1) % lines.length;
                        showLine();
                    }, settings.intervalMs);
                    return;
                }
                schedule(() => reveal(visibleWords + 1), stepMs);
            };

            reveal(transitionMs > 0 ? 0 : wordCount);
        };

        showLine();
    }

    function startDynamicTextRuntime(node, settings) {
        if (!node || settings.mode === 'marquee' || dynamicTextPrefersReducedMotion()) return;
        if (settings.mode === 'carousel') {
            startDynamicTextLineRotation(node, settings, false);
        } else if (settings.mode === 'push_carousel') {
            startDynamicTextLineRotation(node, settings, true);
        } else if (settings.mode === 'typewriter') {
            startDynamicTextTypewriter(node, settings);
        } else if (settings.mode === 'word_reveal') {
            startDynamicTextWordReveal(node, settings);
        }
    }

    function elementLabel(element, i18n = {}, fieldMap = new Map()) {
        if (element?.type === 'background') return i18n.background || 'Background';
        if (element?.type === 'shape') {
            const shape = normalizeShapeType(element.style?.shape || 'square');
            return i18n[`shape_${shape}`] || shape;
        }
        if (element?.type === 'datetime') {
            const mode = normalizeDateTimeMode(element.style?.dateTimeMode || 'clock');
            return i18n[`datetime_mode_${mode}`] || mode;
        }
        if (element?.type === 'dynamic_text') return i18n.element_dynamic_text || 'Dynamic Text';
        if (element?.type === 'countdown') return i18n.element_countdown || 'Countdown';
        const field = fieldForElement(element, fieldMap);
        if (field) return field.label || field.key || element.field;
        return i18n[`element_${element?.type}`] || element?.type || '';
    }

    function textPreviewValue(element, fieldMap, values, options) {
        const field = fieldForElement(element, fieldMap);
        if (field) {
            return String(resolvedFieldValue(field, values, options) || elementLabel(element, options.i18n, fieldMap));
        }
        const staticText = String(element?.staticText ?? '').trim();
        return staticText || elementLabel(element, options.i18n, fieldMap);
    }

    function mediaPlaceholderLabel(element, fieldMap, options) {
        const field = fieldForElement(element, fieldMap);
        if (field) return field.label || field.key || elementLabel(element, options.i18n, fieldMap);
        return elementLabel(element, options.i18n, fieldMap);
    }

    function previewPlaceholder(label, modifier = '') {
        const node = document.createElement('span');
        node.className = `template-editor__element-placeholder ${modifier}`.trim();
        node.textContent = label;
        return node;
    }

    function renderMediaPreview(assetId, fit, emptyLabel, mediaById, i18n = {}) {
        const asset = mediaAsset(assetId, mediaById);
        if (!asset) {
            if (!emptyLabel) return document.createElement('span');
            return previewPlaceholder(emptyLabel);
        }

        const assetKind = asset.kind || asset.media_kind || '';
        const assetUrl = asset.url || asset.file_url || '';
        const previewUrl = asset.preview_url || asset.previewUrl || '';
        const label = asset.name || asset.original_name || '';

        if (assetKind === 'image' && assetUrl) {
            if (fit === 'contain-blur') {
                const wrapper = document.createElement('span');
                wrapper.className = 'template-editor__media-preview-stack';
                const blurred = document.createElement('img');
                blurred.className = 'template-editor__media-preview template-editor__media-preview--blurred';
                blurred.src = assetUrl;
                blurred.alt = '';
                blurred.setAttribute('aria-hidden', 'true');
                blurred.loading = 'lazy';
                blurred.decoding = 'async';
                blurred.style.objectFit = 'cover';
                const image = document.createElement('img');
                image.className = 'template-editor__media-preview template-editor__media-preview--contained';
                image.src = assetUrl;
                image.alt = label;
                image.loading = 'lazy';
                image.decoding = 'async';
                image.style.objectFit = 'contain';
                wrapper.append(blurred, image);
                return wrapper;
            }

            const image = document.createElement('img');
            image.className = 'template-editor__media-preview';
            image.src = assetUrl;
            image.alt = label;
            image.loading = 'lazy';
            image.decoding = 'async';
            image.style.objectFit = ['cover', 'contain'].includes(fit) ? fit : 'cover';
            return image;
        }

        if (assetKind === 'video' && previewUrl) {
            const image = document.createElement('img');
            image.className = 'template-editor__media-preview';
            image.src = previewUrl;
            image.alt = label;
            image.loading = 'lazy';
            image.decoding = 'async';
            image.style.objectFit = ['cover', 'contain'].includes(fit) ? fit : 'cover';
            return image;
        }

        const placeholder = document.createElement('span');
        placeholder.className = 'template-editor__video-placeholder';
        placeholder.innerHTML = `<span aria-hidden="true"></span><strong>${escapeHtml(i18n.media_video || 'Video')}</strong><small>${escapeHtml(label)}</small>`;
        return placeholder;
    }

    function renderDynamicTextPreview(element, context) {
        const settings = dynamicTextSettings(element);
        const lines = dynamicTextPreviewLines(element, context, settings);
        const preview = document.createElement('span');
        preview.className = 'template-editor__dynamic-text-preview';
        preview.dataset.templateEditorDynamicTextRuntime = '1';
        preview.style.setProperty('--template-dynamic-carousel-transition', `${settings.transitionMs}ms`);
        preview.style.setProperty('--template-dynamic-text-transition', `${settings.transitionMs}ms`);
        preview.style.setProperty('--template-dynamic-marquee-duration', `${settings.marqueeDurationMs}ms`);
        applyTextPreviewStyle(preview, element, context.fontOptions);

        if (settings.mode === 'marquee') {
            preview.classList.add('template-editor__dynamic-text-preview--marquee', `template-editor__dynamic-text-preview--marquee-${settings.direction}`);
            const track = document.createElement('span');
            track.className = 'template-editor__dynamic-text-track';
            const text = lines.join(' · ');
            [false, true].forEach(hidden => {
                const item = document.createElement('span');
                item.className = 'template-editor__dynamic-text-marquee-item';
                item.textContent = text;
                if (hidden) item.setAttribute('aria-hidden', 'true');
                track.appendChild(item);
            });
            preview.appendChild(track);
            return preview;
        }

        preview.classList.add(`template-editor__dynamic-text-preview--${settings.mode}`);
        if (settings.mode === 'carousel' && settings.fade) {
            preview.classList.add('template-editor__dynamic-text-preview--fade');
        }
        if (settings.mode === 'push_carousel') {
            preview.classList.add(`template-editor__dynamic-text-preview--push-${settings.direction}`);
        }
        lines.forEach((line, index) => {
            const item = document.createElement('span');
            item.className = `template-editor__dynamic-text-line ${index === 0 ? 'is-active' : ''}`.trim();
            item.textContent = line;
            preview.appendChild(item);
        });
        startDynamicTextRuntime(preview, settings);
        return preview;
    }

    function renderElementPreview(element, context) {
        const preview = document.createElement('span');
        preview.className = 'template-editor__element-preview';
        const fit = element.style?.fit || 'cover';
        const field = fieldForElement(element, context.fieldMap);

        if (element.type === 'background') {
            preview.appendChild(renderMediaPreview(element.style?.backgroundMediaAssetId || 0, fit, '', context.mediaById, context.i18n));
            return preview;
        }
        if (element.type === 'media') {
            const fieldValue = field ? resolvedFieldValue(field, context.values, context) : '';
            const mediaAssetId = field ? (fieldValue || 0) : (element.style?.mediaAssetId || 0);
            preview.appendChild(renderMediaPreview(mediaAssetId, fit, mediaPlaceholderLabel(element, context.fieldMap, context), context.mediaById, context.i18n));
            return preview;
        }
        if (element.type === 'qr') {
            const qr = document.createElement('canvas');
            qr.className = 'template-editor__qr-preview';
            qr.width = 1;
            qr.height = 1;
            qr.dataset.templateRendererQr = '1';
            qr.dataset.qrUrl = String(field ? resolvedFieldValue(field, context.values, context) : '') || context.previewQrUrl || 'https://example.com';
            qr.dataset.qrForeground = element.style?.color || 'rgba(15, 23, 42, 1)';
            qr.dataset.qrBackground = element.style?.backgroundColor || 'rgba(255, 255, 255, 1)';
            preview.appendChild(qr);
            return preview;
        }
        if (element.type === 'shape') {
            const shape = document.createElement('span');
            shape.className = 'template-editor__shape-preview';
            shape.innerHTML = shapeSvgMarkup(element);
            preview.appendChild(shape);
            return preview;
        }
        if (element.type === 'datetime') {
            const dateTime = document.createElement('span');
            dateTime.className = 'template-editor__datetime-preview';
            dateTime.textContent = dateTimePreviewValue(element);
            applyTextPreviewStyle(dateTime, element, context.fontOptions);
            preview.appendChild(dateTime);
            return preview;
        }
        if (element.type === 'countdown') {
            const countdown = document.createElement('span');
            countdown.className = 'template-editor__countdown-preview';
            countdown.textContent = countdownPreviewValue(element);
            applyTextPreviewStyle(countdown, element, context.fontOptions);
            preview.appendChild(countdown);
            return preview;
        }
        if (element.type === 'dynamic_text') {
            preview.appendChild(renderDynamicTextPreview(element, context));
            return preview;
        }
        if (element.type === 'text') {
            const text = document.createElement('span');
            text.className = 'template-editor__text-preview';
            text.textContent = textPreviewValue(element, context.fieldMap, context.values, context);
            applyTextPreviewStyle(text, element, context.fontOptions);
            preview.appendChild(text);
            return preview;
        }

        return preview;
    }

    function applyElementFrame(node, element) {
        node.style.left = pct(element.x);
        node.style.top = pct(element.y);
        node.style.width = pct(element.w);
        node.style.height = pct(element.h);
        node.style.zIndex = String(element.z || 0);
        node.style.color = element.style?.color || '';
        node.style.background = ['qr', 'shape'].includes(element.type)
            ? 'transparent'
            : (element.style?.backgroundColor || (element.type === 'background' ? '#0f172a' : 'rgba(255,255,255,0.18)'));
        node.style.borderRadius = element.type === 'shape' && !roundedShapeElement(element) ? '0' : `${Number(element.style?.radius || 0)}cqw`;
        node.style.setProperty('--template-editor-element-shadow', element.type === 'shape' ? '0 0 0 rgba(0, 0, 0, 0)' : elementBoxShadow(element));
    }

    function drawQrCodes(container, context) {
        container.querySelectorAll('[data-template-renderer-qr]').forEach(qr => {
            try {
                if (!window.HuginQr?.drawCanvas) throw new Error('QR renderer is unavailable.');
                window.HuginQr.drawCanvas(qr, qr.dataset.qrUrl || context.previewQrUrl || 'https://example.com', qr.dataset.qrForeground, qr.dataset.qrBackground);
            } catch (error) {
                qr.replaceWith(previewPlaceholder(qr.dataset.qrUrl || context.previewQrUrl || 'https://example.com', 'template-editor__element-placeholder--qr'));
            }
        });
    }

    function safeSpec(spec) {
        const canvas = spec?.canvas || {};
        return {
            canvas: {
                width: Math.max(1, Number(canvas.width || 1920)),
                height: Math.max(1, Number(canvas.height || 1080)),
            },
            fields: Array.isArray(spec?.fields) ? spec.fields : [],
            elements: Array.isArray(spec?.elements) ? spec.elements : [],
        };
    }

    function render(container, options = {}) {
        if (!container) return [];
        const spec = safeSpec(options.spec || {});
        const mode = options.mode === 'preview' ? 'preview' : 'editor';
        const ratioValue = spec.canvas.width / spec.canvas.height;
        const maxHeightVh = Number(options.maxHeightVh || 76);
        const fieldMap = fieldMapFor(spec, options.fields);
        const context = {
            fieldMap,
            values: options.values && typeof options.values === 'object' ? options.values : {},
            mediaById: mediaMap(options.mediaAssets),
            fontOptions: Array.isArray(options.fontOptions) ? options.fontOptions : [],
            i18n: options.i18n || {},
            previewQrUrl: options.previewQrUrl || 'https://example.com',
        };

        container.classList.toggle('template-editor__canvas--preview', mode === 'preview');
        container.classList.toggle('template-editor__canvas--editor', mode === 'editor');
        container.style.aspectRatio = `${spec.canvas.width} / ${spec.canvas.height}`;
        container.style.width = options.width || (ratioValue >= 1 ? '100%' : `min(100%, ${Number((ratioValue * maxHeightVh).toFixed(3))}vh)`);
        if (options.gridSteps) {
            container.style.setProperty('--template-grid-x', `${Number(options.gridSteps.x || 0) * 100}%`);
            container.style.setProperty('--template-grid-y', `${Number(options.gridSteps.y || 0) * 100}%`);
        }
        clearDynamicTextRuntime(container);
        container.innerHTML = '';

        const nodes = [];
        spec.elements.slice().sort((a, b) => (a.z || 0) - (b.z || 0)).forEach(element => {
            const node = document.createElement(mode === 'editor' ? 'button' : 'div');
            if (mode === 'editor') {
                node.type = 'button';
                if (options.ariaDescribedBy) {
                    node.setAttribute('aria-describedby', options.ariaDescribedBy);
                }
                const selected = element.id === options.selectedId;
                node.classList.toggle('is-selected', selected);
                node.setAttribute('aria-pressed', selected ? 'true' : 'false');
                if (typeof options.elementAriaLabel === 'function') {
                    node.setAttribute('aria-label', options.elementAriaLabel(element));
                }
            } else {
                node.setAttribute('aria-hidden', 'true');
            }

            node.className = `${node.className} template-editor__element template-editor__element--${element.type}`.trim();
            node.dataset.elementId = element.id || '';
            applyElementFrame(node, element);
            node.appendChild(renderElementPreview(element, context));
            container.appendChild(node);
            nodes.push(node);
        });

        drawQrCodes(container, context);
        return nodes;
    }

    window.HuginTemplateCanvasRenderer = {
        render,
        applyElementFrame,
        helpers: {
            normalizeShapeType,
            roundedShapeElement,
            normalizeDateTimeMode,
            normalizeTimeFormat,
            dropShadowSettings,
            dropShadowCss,
        },
    };
})();
