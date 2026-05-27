(() => {
    const slideshow = document.getElementById('slideshow');
    if (!slideshow) return;

    const slides = Array.from(document.querySelectorAll('.slide'));
    if (slides.length === 0) {
        slideshow.classList.remove('is-startup-sync-pending');
        return;
    }

    let index = Math.max(0, slides.findIndex(slide => slide.classList.contains('is-active')));
    let timer = null;
    let heartbeatTimer = null;
    let stateTimer = null;
    let scheduleStateTimer = null;
    let watchdogTimer = null;
    let pendingReloadTimer = null;
    let pendingReload = null;
    let nextSlideDueAt = 0;
    let startupComplete = false;
    let stateRequestInFlight = false;
    let currentSignature = slideshow.dataset.stateSignature || '';
    const MINUTE_MS = 60000;
    const SYNC_RELOAD_MIN_LEAD_MS = 3000;
    const SCHEDULED_SYNC_RELOAD_KEY = 'huginScheduledSyncReload';
    const SCHEDULED_SYNC_RELOAD_MAX_AGE_MS = 120000;
    const requestFrame = window.requestAnimationFrame
        ? window.requestAnimationFrame.bind(window)
        : (callback => window.setTimeout(callback, 16));

    const bindMediaFallback = element => {
        if (!element || element.dataset.fallbackBound) return;
        element.dataset.fallbackBound = '1';
        element.addEventListener('error', () => {
            element.classList.add('is-media-error');
        });
    };

    const restartTextCardAnimation = slide => {
        const textSlide = slide?.querySelector('.text-slide[data-text-animation]');
        if (!textSlide) return;

        slide.classList.remove('is-text-card-animating');
        if ((textSlide.dataset.textAnimation || 'none') === 'none') {
            return;
        }

        void textSlide.offsetWidth;
        requestFrame(() => {
            slide.classList.add('is-text-card-animating');
        });
    };

    const resolveEndpointUrl = value => {
        if (!value) return '';

        try {
            const endpoint = new URL(value, window.location.href);
            if (endpoint.origin !== window.location.origin) {
                endpoint.protocol = window.location.protocol;
                endpoint.host = window.location.host;
            }
            return endpoint.toString();
        } catch (error) {
            return value;
        }
    };

    const heartbeatIntervalMs = () => {
        const seconds = parseInt(slideshow.dataset.heartbeatInterval || '90', 10);
        return Math.max(seconds || 90, 30) * 1000;
    };

    const stateCheckIntervalMs = () => {
        const seconds = parseInt(slideshow.dataset.stateCheckInterval || '60', 10);
        return Math.max(seconds || 60, 5) * 1000;
    };

    const durationForSlide = slide => {
        const seconds = parseInt(slide?.dataset.duration || slideshow.dataset.defaultDuration || '8', 10);
        return Math.max(seconds || 8, 1) * 1000;
    };

    const nextIndex = (fromIndex, offset = 1) => (fromIndex + offset + slides.length) % slides.length;

    const logReload = (message, context = {}) => {
        if (window.console?.info) {
            window.console.info(`[Hugin display] ${message}`, context);
        }
    };

    const displayGroupFromDataset = () => ({
        id: slideshow.dataset.displayGroupId || '',
        name: slideshow.dataset.displayGroupName || '',
        sync_enabled: slideshow.dataset.syncReloadToFullMinute === '1' ? 1 : 0,
        sync_mode: slideshow.dataset.displayGroupSyncMode || 'independent',
        sync_reload_to_full_minute: slideshow.dataset.syncReloadToFullMinute === '1',
    });

    const displayGroupFromState = stateData => {
        const group = stateData?.display_group || null;
        if (!group) return displayGroupFromDataset();

        return {
            id: group.id || '',
            name: group.name || '',
            sync_enabled: group.sync_enabled ? 1 : 0,
            sync_mode: group.sync_mode || 'independent',
            sync_reload_to_full_minute: Boolean(group.sync_reload_to_full_minute),
        };
    };

    const shouldUseSyncedGroupReload = stateData => displayGroupFromState(stateData).sync_reload_to_full_minute === true;

    const computeNextFullMinuteActivation = (nowMs = Date.now()) => {
        const msIntoMinute = nowMs % MINUTE_MS;
        const nextMinute = msIntoMinute === 0 ? nowMs + MINUTE_MS : nowMs + (MINUTE_MS - msIntoMinute);

        if (nextMinute - nowMs < SYNC_RELOAD_MIN_LEAD_MS) {
            return nextMinute + MINUTE_MS;
        }

        return nextMinute;
    };

    const readScheduledSyncReload = () => {
        try {
            const raw = window.sessionStorage.getItem(SCHEDULED_SYNC_RELOAD_KEY);
            const data = raw ? JSON.parse(raw) : null;
            const ageMs = Date.now() - Number(data?.at || 0);

            if (data?.reason === 'sync-group-config-reload' && ageMs >= 0 && ageMs <= SCHEDULED_SYNC_RELOAD_MAX_AGE_MS) {
                window.sessionStorage.removeItem(SCHEDULED_SYNC_RELOAD_KEY);
                return data;
            }

            if (raw) {
                window.sessionStorage.removeItem(SCHEDULED_SYNC_RELOAD_KEY);
            }
        } catch (error) {
            try {
                window.sessionStorage.removeItem(SCHEDULED_SYNC_RELOAD_KEY);
            } catch (storageError) {}
        }

        document.documentElement.classList.remove('hugin-scheduled-sync-reload');
        return null;
    };

    const markScheduledSyncReload = () => {
        try {
            window.sessionStorage.setItem(SCHEDULED_SYNC_RELOAD_KEY, JSON.stringify({
                at: Date.now(),
                reason: 'sync-group-config-reload',
            }));
        } catch (error) {}
    };

    const reloadImmediately = (reason, stateData = null) => {
        if (pendingReloadTimer) {
            clearTimeout(pendingReloadTimer);
        }
        pendingReloadTimer = null;
        pendingReload = null;

        logReload('Applying immediate reload', {
            reason,
            displayGroup: displayGroupFromState(stateData),
            signature: stateData?.signature || '',
        });
        window.location.reload();
    };

    const applyPendingReload = () => {
        if (!pendingReload) return;

        const reload = pendingReload;
        pendingReload = null;
        pendingReloadTimer = null;

        logReload('Applying synchronized reload', {
            reason: reload.reason,
            displayGroup: reload.displayGroup,
            signature: reload.signature,
            activateAt: new Date(reload.activateAtMs).toISOString(),
        });

        markScheduledSyncReload();
        window.location.reload();
    };

    const scheduleSyncedReload = (reason, stateData) => {
        const signature = stateData?.signature || '';
        const displayGroup = displayGroupFromState(stateData);

        if (pendingReload?.signature === signature && pendingReloadTimer) {
            logReload('Synchronized reload already pending', {
                reason,
                displayGroup,
                signature,
                activateAt: new Date(pendingReload.activateAtMs).toISOString(),
            });
            return;
        }

        const activateAtMs = computeNextFullMinuteActivation(Date.now());
        const replaced = Boolean(pendingReloadTimer);
        if (pendingReloadTimer) {
            clearTimeout(pendingReloadTimer);
        }

        pendingReload = {
            reason,
            stateData,
            signature,
            displayGroup,
            activateAtMs,
        };
        pendingReloadTimer = window.setTimeout(applyPendingReload, Math.max(0, activateAtMs - Date.now()));

        logReload(replaced ? 'Replaced pending synchronized reload' : 'Scheduled synchronized reload', {
            reason,
            displayGroup,
            signature,
            activateAt: new Date(activateAtMs).toISOString(),
        });
    };

    const qrRsBlocksL = [
        null,
        [[1, 26, 19]],
        [[1, 44, 34]],
        [[1, 70, 55]],
        [[1, 100, 80]],
        [[1, 134, 108]],
        [[2, 86, 68]],
        [[2, 98, 78]],
        [[2, 121, 97]],
        [[2, 146, 116]],
        [[2, 86, 68], [2, 87, 69]],
    ];
    const qrAlignmentCenters = [
        null,
        [],
        [6, 18],
        [6, 22],
        [6, 26],
        [6, 30],
        [6, 34],
        [6, 22, 38],
        [6, 24, 42],
        [6, 26, 46],
        [6, 28, 50],
    ];
    const qrGaloisExp = new Array(512);
    const qrGaloisLog = new Array(256);
    let qrGaloisValue = 1;
    for (let i = 0; i < 255; i += 1) {
        qrGaloisExp[i] = qrGaloisValue;
        qrGaloisLog[qrGaloisValue] = i;
        qrGaloisValue <<= 1;
        if (qrGaloisValue & 0x100) {
            qrGaloisValue ^= 0x11d;
        }
    }
    for (let i = 255; i < 512; i += 1) {
        qrGaloisExp[i] = qrGaloisExp[i - 255];
    }

    const encodeUtf8Bytes = value => {
        if (window.TextEncoder) {
            return Array.from(new TextEncoder().encode(value));
        }

        return Array.from(unescape(encodeURIComponent(value))).map(char => char.charCodeAt(0));
    };

    const qrDataCountForVersion = version => qrRsBlocksL[version].reduce((sum, group) => sum + (group[0] * group[2]), 0);

    const chooseQrVersion = bytes => {
        for (let version = 1; version < qrRsBlocksL.length; version += 1) {
            const characterCountBits = version < 10 ? 8 : 16;
            const requiredBits = 4 + characterCountBits + (bytes.length * 8);
            if (requiredBits <= qrDataCountForVersion(version) * 8) {
                return version;
            }
        }

        throw new Error('QR URL is too long for the built-in renderer.');
    };

    const qrPutBits = (bits, value, length) => {
        for (let i = length - 1; i >= 0; i -= 1) {
            bits.push(((value >>> i) & 1) === 1);
        }
    };

    const qrGaloisMultiply = (left, right) => {
        if (left === 0 || right === 0) return 0;
        return qrGaloisExp[qrGaloisLog[left] + qrGaloisLog[right]];
    };

    const qrPolynomialMultiply = (left, right) => {
        const result = new Array(left.length + right.length - 1).fill(0);
        left.forEach((leftValue, leftIndex) => {
            right.forEach((rightValue, rightIndex) => {
                result[leftIndex + rightIndex] ^= qrGaloisMultiply(leftValue, rightValue);
            });
        });
        return result;
    };

    const qrGeneratorPolynomial = degree => {
        let polynomial = [1];
        for (let i = 0; i < degree; i += 1) {
            polynomial = qrPolynomialMultiply(polynomial, [1, qrGaloisExp[i]]);
        }
        return polynomial;
    };

    const qrErrorCorrectionBytes = (data, ecCount) => {
        const generator = qrGeneratorPolynomial(ecCount);
        const result = new Array(ecCount).fill(0);

        data.forEach(byte => {
            const factor = byte ^ result.shift();
            result.push(0);
            if (factor === 0) return;

            for (let i = 0; i < ecCount; i += 1) {
                result[i] ^= qrGaloisMultiply(generator[i + 1], factor);
            }
        });

        return result;
    };

    const createQrData = (bytes, version) => {
        const totalDataCount = qrDataCountForVersion(version);
        const totalBits = totalDataCount * 8;
        const bits = [];
        qrPutBits(bits, 0x4, 4);
        qrPutBits(bits, bytes.length, version < 10 ? 8 : 16);
        bytes.forEach(byte => qrPutBits(bits, byte, 8));

        if (bits.length > totalBits) {
            throw new Error('QR data exceeds selected version capacity.');
        }

        qrPutBits(bits, 0, Math.min(4, totalBits - bits.length));
        while (bits.length % 8 !== 0) {
            bits.push(false);
        }

        const dataCodewords = [];
        for (let i = 0; i < bits.length; i += 8) {
            let codeword = 0;
            for (let j = 0; j < 8; j += 1) {
                codeword = (codeword << 1) | (bits[i + j] ? 1 : 0);
            }
            dataCodewords.push(codeword);
        }

        const padBytes = [0xec, 0x11];
        let padIndex = 0;
        while (dataCodewords.length < totalDataCount) {
            dataCodewords.push(padBytes[padIndex % 2]);
            padIndex += 1;
        }

        const blocks = [];
        let offset = 0;
        qrRsBlocksL[version].forEach(group => {
            const [count, totalCount, dataCount] = group;
            for (let i = 0; i < count; i += 1) {
                const data = dataCodewords.slice(offset, offset + dataCount);
                const errorCorrection = qrErrorCorrectionBytes(data, totalCount - dataCount);
                blocks.push({ data, errorCorrection });
                offset += dataCount;
            }
        });

        const result = [];
        const maxDataCount = Math.max(...blocks.map(block => block.data.length));
        const maxEcCount = Math.max(...blocks.map(block => block.errorCorrection.length));

        for (let i = 0; i < maxDataCount; i += 1) {
            blocks.forEach(block => {
                if (i < block.data.length) result.push(block.data[i]);
            });
        }
        for (let i = 0; i < maxEcCount; i += 1) {
            blocks.forEach(block => {
                if (i < block.errorCorrection.length) result.push(block.errorCorrection[i]);
            });
        }

        return result;
    };

    const qrBchDigit = value => {
        let digit = 0;
        while (value !== 0) {
            digit += 1;
            value >>>= 1;
        }
        return digit;
    };

    const qrBchTypeInfo = data => {
        let value = data << 10;
        const generator = 0x537;
        while (qrBchDigit(value) - qrBchDigit(generator) >= 0) {
            value ^= generator << (qrBchDigit(value) - qrBchDigit(generator));
        }
        return ((data << 10) | value) ^ 0x5412;
    };

    const qrBchTypeNumber = version => {
        let value = version << 12;
        const generator = 0x1f25;
        while (qrBchDigit(value) - qrBchDigit(generator) >= 0) {
            value ^= generator << (qrBchDigit(value) - qrBchDigit(generator));
        }
        return (version << 12) | value;
    };

    const qrMask = (pattern, row, col) => {
        switch (pattern) {
            case 0: return (row + col) % 2 === 0;
            case 1: return row % 2 === 0;
            case 2: return col % 3 === 0;
            case 3: return (row + col) % 3 === 0;
            case 4: return (Math.floor(row / 2) + Math.floor(col / 3)) % 2 === 0;
            case 5: return ((row * col) % 2) + ((row * col) % 3) === 0;
            case 6: return (((row * col) % 2) + ((row * col) % 3)) % 2 === 0;
            case 7: return (((row + col) % 2) + ((row * col) % 3)) % 2 === 0;
            default: return false;
        }
    };

    const createQrBaseMatrix = version => {
        const size = 21 + ((version - 1) * 4);
        const modules = Array.from({ length: size }, () => new Array(size).fill(null));
        const reserved = Array.from({ length: size }, () => new Array(size).fill(false));
        const setModule = (row, col, dark, isReserved = true) => {
            if (row < 0 || col < 0 || row >= size || col >= size) return;
            modules[row][col] = dark ? 1 : 0;
            if (isReserved) reserved[row][col] = true;
        };
        const placeFinder = (row, col) => {
            for (let y = -1; y <= 7; y += 1) {
                for (let x = -1; x <= 7; x += 1) {
                    const targetRow = row + y;
                    const targetCol = col + x;
                    if (targetRow < 0 || targetCol < 0 || targetRow >= size || targetCol >= size) continue;

                    const inPattern = y >= 0 && y <= 6 && x >= 0 && x <= 6;
                    const dark = inPattern && (y === 0 || y === 6 || x === 0 || x === 6 || (y >= 2 && y <= 4 && x >= 2 && x <= 4));
                    setModule(targetRow, targetCol, dark);
                }
            }
        };
        const placeAlignment = (row, col) => {
            if (reserved[row]?.[col]) return;

            for (let y = -2; y <= 2; y += 1) {
                for (let x = -2; x <= 2; x += 1) {
                    const distance = Math.max(Math.abs(y), Math.abs(x));
                    setModule(row + y, col + x, distance === 2 || distance === 0);
                }
            }
        };

        placeFinder(0, 0);
        placeFinder(size - 7, 0);
        placeFinder(0, size - 7);

        for (let i = 8; i < size - 8; i += 1) {
            setModule(6, i, i % 2 === 0);
            setModule(i, 6, i % 2 === 0);
        }

        (qrAlignmentCenters[version] || []).forEach(row => {
            (qrAlignmentCenters[version] || []).forEach(col => placeAlignment(row, col));
        });

        setModule((4 * version) + 9, 8, true);

        return { modules, reserved, setModule };
    };

    const setupQrFormatInfo = (matrix, maskPattern) => {
        const { modules, setModule } = matrix;
        const size = modules.length;
        const bits = qrBchTypeInfo((1 << 3) | maskPattern);

        for (let i = 0; i < 15; i += 1) {
            const dark = ((bits >> i) & 1) === 1;
            if (i < 6) {
                setModule(i, 8, dark);
            } else if (i < 8) {
                setModule(i + 1, 8, dark);
            } else {
                setModule(size - 15 + i, 8, dark);
            }

            if (i < 8) {
                setModule(8, size - i - 1, dark);
            } else if (i < 9) {
                setModule(8, 15 - i, dark);
            } else {
                setModule(8, 15 - i - 1, dark);
            }
        }

        setModule(size - 8, 8, true);
    };

    const setupQrVersionInfo = (matrix, version) => {
        if (version < 7) return;

        const { modules, setModule } = matrix;
        const size = modules.length;
        const bits = qrBchTypeNumber(version);

        for (let i = 0; i < 18; i += 1) {
            const dark = ((bits >> i) & 1) === 1;
            setModule(Math.floor(i / 3), (i % 3) + size - 11, dark);
            setModule((i % 3) + size - 11, Math.floor(i / 3), dark);
        }
    };

    const buildQrMatrix = (version, data, maskPattern) => {
        const matrix = createQrBaseMatrix(version);
        const { modules, reserved } = matrix;
        const size = modules.length;
        setupQrFormatInfo(matrix, maskPattern);
        setupQrVersionInfo(matrix, version);

        let bitIndex = 0;
        let direction = -1;
        for (let col = size - 1; col > 0; col -= 2) {
            if (col === 6) col -= 1;

            for (let rowOffset = 0; rowOffset < size; rowOffset += 1) {
                const row = direction === -1 ? size - 1 - rowOffset : rowOffset;
                for (let c = 0; c < 2; c += 1) {
                    const targetCol = col - c;
                    if (reserved[row][targetCol]) continue;

                    const byte = data[Math.floor(bitIndex / 8)] || 0;
                    const dark = bitIndex < data.length * 8 && (((byte >>> (7 - (bitIndex % 8))) & 1) === 1);
                    modules[row][targetCol] = dark !== qrMask(maskPattern, row, targetCol) ? 1 : 0;
                    reserved[row][targetCol] = true;
                    bitIndex += 1;
                }
            }

            direction *= -1;
        }

        return modules;
    };

    const qrPenalty = modules => {
        const size = modules.length;
        let penalty = 0;
        const penaltyLine = getValue => {
            let score = 0;
            for (let outer = 0; outer < size; outer += 1) {
                let runColor = getValue(outer, 0);
                let runLength = 1;
                for (let inner = 1; inner < size; inner += 1) {
                    const value = getValue(outer, inner);
                    if (value === runColor) {
                        runLength += 1;
                    } else {
                        if (runLength >= 5) score += 3 + (runLength - 5);
                        runColor = value;
                        runLength = 1;
                    }
                }
                if (runLength >= 5) score += 3 + (runLength - 5);
            }
            return score;
        };

        penalty += penaltyLine((row, col) => modules[row][col]);
        penalty += penaltyLine((col, row) => modules[row][col]);

        for (let row = 0; row < size - 1; row += 1) {
            for (let col = 0; col < size - 1; col += 1) {
                const value = modules[row][col];
                if (value === modules[row + 1][col] && value === modules[row][col + 1] && value === modules[row + 1][col + 1]) {
                    penalty += 3;
                }
            }
        }

        const pattern = '10111010000';
        const reversePattern = '00001011101';
        for (let row = 0; row < size; row += 1) {
            let rowBits = '';
            let colBits = '';
            for (let col = 0; col < size; col += 1) {
                rowBits += modules[row][col] ? '1' : '0';
                colBits += modules[col][row] ? '1' : '0';
            }
            for (let i = 0; i <= size - 11; i += 1) {
                const rowSlice = rowBits.slice(i, i + 11);
                const colSlice = colBits.slice(i, i + 11);
                if (rowSlice === pattern || rowSlice === reversePattern) penalty += 40;
                if (colSlice === pattern || colSlice === reversePattern) penalty += 40;
            }
        }

        let darkCount = 0;
        modules.forEach(row => row.forEach(value => {
            if (value) darkCount += 1;
        }));
        penalty += Math.floor(Math.abs(((darkCount * 100) / (size * size)) - 50) / 5) * 10;

        return penalty;
    };

    const createQrMatrix = text => {
        const bytes = encodeUtf8Bytes(text);
        const version = chooseQrVersion(bytes);
        const data = createQrData(bytes, version);
        let bestMatrix = null;
        let bestPenalty = Infinity;

        for (let maskPattern = 0; maskPattern < 8; maskPattern += 1) {
            const matrix = buildQrMatrix(version, data, maskPattern);
            const penalty = qrPenalty(matrix);
            if (penalty < bestPenalty) {
                bestPenalty = penalty;
                bestMatrix = matrix;
            }
        }

        return bestMatrix;
    };

    const drawQrCanvas = (canvas, text, foreground, background) => {
        const matrix = createQrMatrix(text);
        const moduleCount = matrix.length;
        const ratio = Math.max(1, window.devicePixelRatio || 1);
        const box = canvas.getBoundingClientRect();
        const parentBox = canvas.parentElement?.getBoundingClientRect();
        const effectiveWidth = Math.max(
            1,
            box.width,
            parentBox?.width || 0,
            canvas.clientWidth || 0,
            canvas.parentElement?.clientWidth || 0,
            180
        );
        const cssSize = Math.round(effectiveWidth);
        const pixelSize = Math.max(1, Math.round(cssSize * ratio));
        const quietModules = 4;
        const moduleSize = Math.max(1, Math.floor(pixelSize / (moduleCount + (quietModules * 2))));
        const qrPixelSize = moduleSize * (moduleCount + (quietModules * 2));
        const offset = Math.floor((pixelSize - qrPixelSize) / 2);
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Canvas is unavailable.');

        canvas.width = pixelSize;
        canvas.height = pixelSize;
        context.imageSmoothingEnabled = false;
        context.clearRect(0, 0, pixelSize, pixelSize);
        context.fillStyle = 'rgba(0,0,0,0)'; //background || 'rgba(255, 255, 255, 1)';
        context.fillRect(0, 0, pixelSize, pixelSize);
        context.fillStyle = foreground || 'rgba(15, 23, 42, 1)';

        matrix.forEach((row, rowIndex) => {
            row.forEach((dark, colIndex) => {
                if (!dark) return;
                context.fillRect(
                    offset + ((colIndex + quietModules) * moduleSize),
                    offset + ((rowIndex + quietModules) * moduleSize),
                    moduleSize,
                    moduleSize
                );
            });
        });
    };

    const renderTextSlideQrCodes = () => {
        document.querySelectorAll('.text-slide-qr[data-qr-url]').forEach(qr => {
            const canvas = qr.querySelector('canvas');
            const qrUrl = qr.dataset.qrUrl || '';
            if (!canvas || !qrUrl) return;

            try {
                drawQrCanvas(canvas, qrUrl, qr.dataset.qrForeground, qr.dataset.qrBackground);
                qr.classList.remove('is-qr-fallback');
            } catch (error) {
                qr.classList.add('is-qr-fallback');
            }
        });
    };

    const msUntilNextMinuteTick = () => {
        const now = new Date();
        const msIntoMinute = (now.getSeconds() * 1000) + now.getMilliseconds();
        const targetMs = 50;

        if (msIntoMinute < targetMs) {
            return targetMs - msIntoMinute;
        }

        return MINUTE_MS - msIntoMinute + targetMs;
    };

    const shouldWaitForStartupSync = () => {
        const scheduledReload = readScheduledSyncReload();
        if (scheduledReload) {
            logReload('Skipping startup sync after scheduled synchronized reload', scheduledReload);
            return false;
        }

        const key = slideshow.dataset.startupSyncKey || `hugin:slideshow-started:${window.location.pathname}`;

        try {
            if (window.sessionStorage.getItem(key)) {
                return false;
            }
            window.sessionStorage.setItem(key, String(Date.now()));
            return true;
        } catch (error) {
            const navEntry = window.performance?.getEntriesByType?.('navigation')?.[0];
            const isReload = navEntry?.type === 'reload' || window.performance?.navigation?.type === 1;
            return !isReload;
        }
    };

    const waitForStartupSync = () => {
        if (!shouldWaitForStartupSync()) {
            return Promise.resolve();
        }

        return new Promise(resolve => {
            window.setTimeout(resolve, msUntilNextMinuteTick());
        });
    };

    const ensureMediaLoaded = slide => {
        if (!slide) return;

        slide.querySelectorAll('img[data-src], video[data-src], iframe[data-src]').forEach(element => {
            const source = element.dataset.src;
            if (!source || element.getAttribute('src')) return;

            bindMediaFallback(element);
            element.classList.remove('is-media-error');
            element.setAttribute('src', source);
            if (element.tagName === 'VIDEO') {
                element.load();
            }
        });
    };

    const unloadHeavyMedia = slide => {
        if (!slide) return;

        slide.querySelectorAll('video[data-src]').forEach(video => {
            video.pause();
            video.removeAttribute('src');
            video.load();
        });

        slide.querySelectorAll('iframe[data-src]').forEach(iframe => {
            iframe.removeAttribute('src');
        });
    };

    const prepareMediaAround = activeIndex => {
        ensureMediaLoaded(slides[activeIndex]);
        if (slides.length > 1) {
            ensureMediaLoaded(slides[nextIndex(activeIndex)]);
        }
    };

    const cleanupFarMedia = activeIndex => {
        const keep = new Set([activeIndex]);
        if (slides.length > 1) {
            keep.add(nextIndex(activeIndex));
        }

        slides.forEach((slide, slideIndex) => {
            if (!keep.has(slideIndex)) {
                unloadHeavyMedia(slide);
            }
        });
    };

    const stopVideo = slide => {
        const video = slide?.querySelector('video');
        if (video) {
            video.pause();
            video.currentTime = 0;
        }
    };

    const startVideo = slide => {
        const video = slide?.querySelector('video');
        if (video) {
            ensureMediaLoaded(slide);
            video.currentTime = 0;
            video.play().catch(() => {});
        }
    };

    const ua = navigator.userAgent || '';
    const parseBrowser = () => {
        const checks = [
            { name: 'Edge', regex: /(Edg|Edge)\/([\d.]+)/i },
            { name: 'Opera', regex: /(OPR)\/([\d.]+)/i },
            { name: 'Chrome', regex: /(Chrome)\/([\d.]+)/i },
            { name: 'Firefox', regex: /(Firefox)\/([\d.]+)/i },
            { name: 'Safari', regex: /Version\/([\d.]+).*Safari/i },
        ];
        for (const item of checks) {
            const match = ua.match(item.regex);
            if (match) return { browserName: item.name, browserVersion: match[2] || match[1] || '' };
        }
        return { browserName: 'Unknown', browserVersion: '' };
    };

    const parseOs = () => {
        const platform = navigator.platform || '';
        const list = [
            { name: 'Windows', regex: /Windows NT ([\d.]+)/i },
            { name: 'Android', regex: /Android ([\d.]+)/i },
            { name: 'iOS', regex: /OS ([\d_]+) like Mac OS X/i, transform: v => v.replace(/_/g, '.') },
            { name: 'macOS', regex: /Mac OS X ([\d_]+)/i, transform: v => v.replace(/_/g, '.') },
            { name: 'Linux', regex: /Linux/i },
            { name: 'CrOS', regex: /CrOS [^ ]+ ([\d.]+)/i },
        ];
        for (const item of list) {
            const match = ua.match(item.regex);
            if (match) {
                return {
                    osName: item.name,
                    osVersion: match[1] ? (item.transform ? item.transform(match[1]) : match[1]) : '',
                    platform,
                };
            }
        }
        return { osName: platform || 'Unknown', osVersion: '', platform };
    };

    const collectHeartbeatPayload = () => {
        const browser = parseBrowser();
        const os = parseOs();
        const screenOrientation = screen.orientation?.type || (window.innerHeight > window.innerWidth ? 'portrait' : 'landscape');
        return {
            seenAt: new Date().toISOString(),
            browserName: browser.browserName,
            browserVersion: browser.browserVersion,
            osName: os.osName,
            osVersion: os.osVersion,
            platform: navigator.platform || os.platform || '',
            language: navigator.language || '',
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
            screenWidth: Number(screen.width || 0),
            screenHeight: Number(screen.height || 0),
            availScreenWidth: Number(screen.availWidth || 0),
            availScreenHeight: Number(screen.availHeight || 0),
            viewportWidth: Number(window.innerWidth || document.documentElement.clientWidth || 0),
            viewportHeight: Number(window.innerHeight || document.documentElement.clientHeight || 0),
            devicePixelRatio: Number(window.devicePixelRatio || 1),
            colorDepth: Number(screen.colorDepth || 0),
            maxTouchPoints: Number(navigator.maxTouchPoints || 0),
            hardwareConcurrency: Number(navigator.hardwareConcurrency || 0),
            deviceMemory: navigator.deviceMemory ? Number(navigator.deviceMemory) : null,
            screenOrientation,
            online: typeof navigator.onLine === 'boolean' ? navigator.onLine : null,
            cookieEnabled: typeof navigator.cookieEnabled === 'boolean' ? navigator.cookieEnabled : null,
            userAgent: ua,
        };
    };

    const sendHeartbeatBeacon = (url, payload) => {
        if (!navigator.sendBeacon) return false;
        const blob = new Blob([payload], { type: 'application/json' });
        return navigator.sendBeacon(url, blob);
    };

    const sendHeartbeat = (options = {}) => {
        const url = resolveEndpointUrl(slideshow.dataset.heartbeatUrl);
        if (!url) return;

        const payload = JSON.stringify(collectHeartbeatPayload());

        if (options.preferBeacon && sendHeartbeatBeacon(url, payload)) {
            return;
        }

        if (!window.fetch) {
            sendHeartbeatBeacon(url, payload);
            return;
        }

        fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: payload,
            cache: 'no-store',
            credentials: 'same-origin',
            keepalive: true,
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Heartbeat failed with HTTP ${response.status}`);
                }
            })
            .catch(() => {
                sendHeartbeatBeacon(url, payload);
            });
    };

    const reloadIfChanged = () => {
        const url = resolveEndpointUrl(slideshow.dataset.stateUrl);
        if (!url || !window.fetch || stateRequestInFlight) return Promise.resolve();

        stateRequestInFlight = true;

        return fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        })
            .then(response => {
                if (response.status === 404 || response.status === 409) {
                    const stateData = { signature: `state-http-${response.status}` };
                    if (shouldUseSyncedGroupReload(stateData)) {
                        scheduleSyncedReload(`state-http-${response.status}`, stateData);
                    } else {
                        reloadImmediately(`state-http-${response.status}`, stateData);
                    }
                    return null;
                }

                return response.ok ? response.json() : null;
            })
            .then(data => {
                if (!data) return;
                if (data.ok === false) {
                    const stateData = Object.assign({ signature: 'state-error' }, data);
                    if (shouldUseSyncedGroupReload(stateData)) {
                        scheduleSyncedReload('state-error', stateData);
                    } else {
                        reloadImmediately('state-error', stateData);
                    }
                    return;
                }
                if (!data.signature) return;
                if (!currentSignature) {
                    currentSignature = data.signature;
                    return;
                }
                if (data.signature !== currentSignature) {
                    const useSyncedReload = shouldUseSyncedGroupReload(data);
                    logReload('Config change detected', {
                        currentSignature,
                        nextSignature: data.signature,
                        displayGroup: displayGroupFromState(data),
                        synchronizedGroup: useSyncedReload,
                    });

                    if (useSyncedReload) {
                        scheduleSyncedReload('signature-changed', data);
                        return;
                    }

                    reloadImmediately('signature-changed', data);
                }
            })
            .catch(() => {})
            .then(() => {
                stateRequestInFlight = false;
            });
    };

    const queueNext = () => {
        clearTimeout(timer);

        if (!startupComplete || slides.length <= 1) {
            return;
        }

        const delay = durationForSlide(slides[index]);
        nextSlideDueAt = Date.now() + delay;
        timer = window.setTimeout(() => {
            activate(nextIndex(index));
        }, delay);
    };

    const activate = nextSlideIndex => {
        if (!startupComplete) return;

        const current = slides[index];
        const next = slides[nextSlideIndex];
        if (!next || next === current) {
            queueNext();
            return;
        }

        ensureMediaLoaded(next);

        requestFrame(() => {
            stopVideo(current);
            current.classList.remove('is-active');
            current.classList.remove('is-text-card-animating');
            next.classList.add('is-active');
            index = nextSlideIndex;
            restartTextCardAnimation(next);
            startVideo(next);
            prepareMediaAround(index);
            window.setTimeout(() => cleanupFarMedia(index), 1300);
            queueNext();
        });
    };

    const queueHeartbeat = () => {
        clearInterval(heartbeatTimer);
        sendHeartbeat();
        heartbeatTimer = setInterval(sendHeartbeat, heartbeatIntervalMs());
    };

    const queueStateCheck = () => {
        clearInterval(stateTimer);
        stateTimer = setInterval(reloadIfChanged, stateCheckIntervalMs());
    };

    const queueMinuteAlignedStateCheck = () => {
        clearTimeout(scheduleStateTimer);
        scheduleStateTimer = window.setTimeout(() => {
            reloadIfChanged();
            queueMinuteAlignedStateCheck();
        }, msUntilNextMinuteTick());
    };

    const queueWatchdog = () => {
        clearInterval(watchdogTimer);
        watchdogTimer = setInterval(() => {
            if (!startupComplete || slides.length <= 1 || !nextSlideDueAt) return;

            const lateBy = Date.now() - nextSlideDueAt;
            if (lateBy > Math.max(5000, durationForSlide(slides[index]))) {
                activate(nextIndex(index));
            }
        }, 5000);
    };

    const startSlideshow = () => {
        startupComplete = true;
        slideshow.classList.remove('is-startup-sync-pending');
        document.documentElement.classList.remove('hugin-scheduled-sync-reload');
        prepareMediaAround(index);
        startVideo(slides[index]);
        restartTextCardAnimation(slides[index]);
        cleanupFarMedia(index);
        reloadIfChanged();
        queueStateCheck();
        queueMinuteAlignedStateCheck();
        queueWatchdog();
        queueNext();
    };

    window.addEventListener('online', () => {
        sendHeartbeat();
        reloadIfChanged();
    });
    window.addEventListener('pagehide', () => sendHeartbeat({ preferBeacon: true }));
    window.addEventListener('resize', () => {
        clearTimeout(window.__huginResizeHeartbeat);
        window.__huginResizeHeartbeat = setTimeout(sendHeartbeat, 600);
        clearTimeout(window.__huginQrResize);
        window.__huginQrResize = setTimeout(renderTextSlideQrCodes, 250);
    });
    window.addEventListener('focus', () => {
        if (nextSlideDueAt && Date.now() >= nextSlideDueAt) {
            activate(nextIndex(index));
        }
        reloadIfChanged();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && nextSlideDueAt && Date.now() >= nextSlideDueAt) {
            activate(nextIndex(index));
        }
    });
    if (screen.orientation?.addEventListener) {
        screen.orientation.addEventListener('change', sendHeartbeat);
    }

    renderTextSlideQrCodes();
    prepareMediaAround(index);
    queueHeartbeat();
    waitForStartupSync().then(startSlideshow);
})();
