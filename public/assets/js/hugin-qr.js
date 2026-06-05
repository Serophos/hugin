(() => {
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

    const drawCanvas = (canvas, text, foreground, background) => {
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
        context.fillStyle = background || 'rgba(0,0,0,0)';
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

    window.HuginQr = {
        createMatrix: createQrMatrix,
        drawCanvas,
    };
})();
