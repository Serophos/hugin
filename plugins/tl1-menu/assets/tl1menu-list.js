(() => {
    const roots = new Map();
    const requestFrame = window.requestAnimationFrame
        ? window.requestAnimationFrame.bind(window)
        : callback => window.setTimeout(callback, 16);

    const MODES = [
        { name: 'a', columns: 1, image: true, compactness: 1 },
        { name: 'b', columns: 2, image: true, compactness: 2 },
        { name: 'c', columns: 3, image: false, compactness: 3 },
    ];

    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

    const stateFor = root => {
        if (!roots.has(root)) {
            roots.set(root, { frame: 0, fitting: false, ignoreMutations: false });
        }
        return roots.get(root);
    };

    const applyMode = (root, mode, fontSize) => {
        root.dataset.layoutMode = mode.name;
        root.dataset.foodColumns = String(mode.columns);
        root.style.setProperty('--tl1menu-list-food-columns', String(mode.columns));
        root.style.setProperty('--tl1menu-list-font-size', `${fontSize}px`);
        root.classList.toggle('tl1menu-list--with-side-image', mode.image && root.dataset.hasImage === '1');
        root.classList.toggle('tl1menu-list--without-side-image', !mode.image || root.dataset.hasImage !== '1');
        root.classList.toggle('tl1menu-list--tight', fontSize <= 12 || mode.compactness >= 3);
    };

    const relevantBoxes = root => [
        root,
        root.querySelector('.tl1menu-list__stage'),
        root.querySelector('.tl1menu-list__food'),
        root.querySelector('.tl1menu-list__flow'),
        ...root.querySelectorAll('.tl1menu-list__column'),
    ].filter(Boolean);

    const hasOverflow = root => relevantBoxes(root).some(element => (
        element.scrollHeight > element.clientHeight + 2
        || element.scrollWidth > element.clientWidth + 2
    ));

    const hasHeadingOrphans = root => {
        const columns = parseInt(root.dataset.foodColumns || "1", 10) || 1;
        if (columns <= 1) return false;

        const flow = root.querySelector(".tl1menu-list__flow");
        if (!flow) return false;

        const flowRect = flow.getBoundingClientRect();
        const columnWidth = flowRect.width / columns;
        const tolerance = Math.max(4, columnWidth * 0.08);

        return Array.from(root.querySelectorAll(".tl1menu-list__category")).some(category => {
            const title = category.querySelector(".tl1menu-list__category-title");
            const firstItem = category.querySelector(".tl1menu-list__item");
            if (!title || !firstItem) return false;

            const titleRect = title.getBoundingClientRect();
            const itemRect = firstItem.getBoundingClientRect();
            const titleCenter = titleRect.left + titleRect.width / 2;
            const itemCenter = itemRect.left + itemRect.width / 2;
            const hasRepeatWithItem = Array.from(category.querySelectorAll(".tl1menu-list__category-title--repeat")).some(repeat => {
                const repeatRect = repeat.getBoundingClientRect();
                const repeatCenter = repeatRect.left + repeatRect.width / 2;
                return Math.abs(repeatCenter - itemCenter) <= tolerance;
            });

            return Math.abs(titleCenter - itemCenter) > tolerance && !hasRepeatWithItem;
        });
    };

    const hasSplitItems = root => Array.from(root.querySelectorAll(".tl1menu-list__item")).some(item => item.getClientRects().length > 1);

    const usedFoodColumns = root => {
        const columns = parseInt(root.dataset.foodColumns || "1", 10) || 1;
        if (columns <= 1) return columns;

        const explicitColumns = Array.from(root.querySelectorAll(".tl1menu-list__column"));
        if (explicitColumns.length > 0) {
            return explicitColumns.reduce((used, column, index) => (
                column.querySelector(".tl1menu-list__category-title, .tl1menu-list__item")
                    ? Math.max(used, index + 1)
                    : used
            ), 0);
        }

        const flow = root.querySelector(".tl1menu-list__flow");
        if (!flow) return 0;

        const flowRect = flow.getBoundingClientRect();
        const columnWidth = flowRect.width / columns;
        if (columnWidth <= 0) return 0;

        let used = 0;
        root.querySelectorAll(".tl1menu-list__category-title, .tl1menu-list__item").forEach(element => {
            const rect = element.getBoundingClientRect();
            if (rect.width <= 0 || rect.height <= 0) return;
            const center = rect.left + rect.width / 2;
            const index = clamp(Math.floor((center - flowRect.left) / columnWidth) + 1, 1, columns);
            used = Math.max(used, index);
        });

        return used;
    };

    const directChildWithClass = (element, className) => Array.from(element.children)
        .find(child => child.classList?.contains(className));

    const categoryTitle = category => directChildWithClass(category, "tl1menu-list__category-title");
    const categoryItemsContainer = category => directChildWithClass(category, "tl1menu-list__items");
    const categoryItems = category => {
        const container = categoryItemsContainer(category);
        return container
            ? Array.from(container.children).filter(child => child.classList?.contains("tl1menu-list__item"))
            : [];
    };

    const captureSourceCategories = root => {
        const state = stateFor(root);
        if (state.sourceCategories) return state.sourceCategories;

        const flow = root.querySelector(".tl1menu-list__flow");
        if (!flow) return [];

        state.sourceCategories = Array.from(flow.children)
            .filter(child => child.classList?.contains("tl1menu-list__category"))
            .map(category => ({
                className: category.className,
                title: categoryTitle(category)?.cloneNode(true) || null,
                items: categoryItems(category),
            }))
            .filter(category => category.title && category.items.length > 0);

        return state.sourceCategories;
    };

    const removeChildren = element => {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }
    };

    const createColumn = index => {
        const column = document.createElement("div");
        column.className = "tl1menu-list__column";
        column.dataset.tl1menuListColumn = String(index + 1);
        return column;
    };

    const createCategorySection = (category, repeat) => {
        const section = document.createElement("section");
        section.className = category.className;
        if (repeat) {
            section.classList.add("tl1menu-list__category--continuation");
        }

        const title = category.title.cloneNode(true);
        if (repeat) {
            title.classList.add("tl1menu-list__category-title--repeat");
        }

        const items = document.createElement("div");
        items.className = "tl1menu-list__items";
        section.append(title, items);
        return { section, items };
    };

    const columnOverflows = (column, flow) => (
        column.scrollHeight > flow.clientHeight + 2
        || column.scrollWidth > column.clientWidth + 2
    );

    const buildExplicitColumns = root => {
        const state = stateFor(root);
        const sourceCategories = captureSourceCategories(root);
        const flow = root.querySelector(".tl1menu-list__flow");
        const columnCount = parseInt(root.dataset.foodColumns || "1", 10) || 1;
        if (!flow || sourceCategories.length === 0) return;

        state.ignoreMutations = true;
        root.classList.add("tl1menu-list--explicit-columns");
        removeChildren(flow);

        const columns = Array.from({ length: Math.max(1, columnCount) }, (_, index) => createColumn(index));
        columns.forEach(column => flow.appendChild(column));

        let currentColumnIndex = 0;
        let currentColumn = columns[currentColumnIndex];

        const nextColumn = () => {
            if (currentColumnIndex < columns.length - 1) {
                currentColumnIndex += 1;
                currentColumn = columns[currentColumnIndex];
                return true;
            }
            return false;
        };

        sourceCategories.forEach(category => {
            let segment = createCategorySection(category, false);
            let segmentHadItems = false;
            const previousColumnChildCount = currentColumn.children.length;
            currentColumn.appendChild(segment.section);

            category.items.forEach(item => {
                segment.items.appendChild(item);

                if (!columnOverflows(currentColumn, flow)) {
                    segmentHadItems = true;
                    return;
                }

                if (!segmentHadItems && previousColumnChildCount > 0 && nextColumn()) {
                    segment.items.removeChild(item);
                    currentColumn.appendChild(segment.section);
                    segment.items.appendChild(item);
                    segmentHadItems = true;
                    return;
                }

                if (segmentHadItems && nextColumn()) {
                    segment.items.removeChild(item);
                    segment = createCategorySection(category, true);
                    currentColumn.appendChild(segment.section);
                    segment.items.appendChild(item);
                    segmentHadItems = true;
                    return;
                }

                segmentHadItems = true;
            });

            if (segment.items.children.length === 0) {
                segment.section.remove();
            }
        });

        requestFrame(() => { state.ignoreMutations = false; });
    };

    const hasLayoutProblems = root => hasOverflow(root) || hasHeadingOrphans(root) || hasSplitItems(root);

    const preferredFontRange = (root, rect) => {
        const itemCount = parseInt(root.dataset.itemCount || '0', 10) || 0;
        const shortSide = Math.max(1, Math.min(rect.width, rect.height));
        const longSide = Math.max(rect.width, rect.height);
        let maxFont = 24;

        if (itemCount <= 2) {
            maxFont = clamp(shortSide / 9, 32, 78);
        } else if (itemCount <= 5) {
            maxFont = clamp(shortSide / 16, 24, 46);
        } else if (itemCount <= 9) {
            maxFont = clamp(longSide / 70, 16, 30);
        } else if (itemCount <= 14) {
            maxFont = clamp(longSide / 92, 13, 23);
        } else {
            maxFont = clamp(longSide / 122, 11, 18);
        }

        return {
            max: Math.round(maxFont),
            min: itemCount > 16 ? 8 : 10,
        };
    };

    const orderedModes = (root, rect) => {
        const itemCount = parseInt(root.dataset.itemCount || '0', 10) || 0;
        const hasImage = root.dataset.hasImage === '1';
        const portrait = rect.height > rect.width * 1.05;
        let names;

        if (itemCount <= 2) {
            names = portrait ? ['a', 'c', 'b'] : ['a', 'b', 'c'];
        } else if (itemCount <= 6) {
            names = portrait ? ['b', 'a', 'c'] : ['a', 'b', 'c'];
        } else if (itemCount <= 11) {
            names = portrait ? ['c', 'b', 'a'] : ['b', 'c', 'a'];
        } else {
            names = hasImage && !portrait ? ['b', 'c', 'a'] : ['c', 'b', 'a'];
        }

        const modes = names.map(name => ({ ...MODES.find(mode => mode.name === name) }));
        if (!hasImage) {
            modes.forEach(mode => { mode.image = false; });
        }
        return modes;
    };

    const fit = root => {
        const state = stateFor(root);
        if (state.fitting || !root.isConnected) return;

        const rect = root.getBoundingClientRect();
        if (rect.width < 20 || rect.height < 20) return;

        state.fitting = true;
        root.classList.add("tl1menu-list--measuring");

        const range = preferredFontRange(root, rect);
        const modes = orderedModes(root, rect);
        const modeOrder = new Map(modes.map((mode, index) => [mode.name, index]));
        const candidates = [];

        for (const mode of modes) {
            for (let fontSize = range.max; fontSize >= range.min; fontSize -= 1) {
                applyMode(root, mode, fontSize);
                buildExplicitColumns(root);
                if (!hasLayoutProblems(root)) {
                    candidates.push({ mode, fontSize, usedColumns: usedFoodColumns(root) });
                    break;
                }
            }
        }

        let chosen = null;
        if (candidates.length > 0) {
            const bestFont = Math.max(...candidates.map(candidate => candidate.fontSize));
            const imageTwoColumn = candidates.find(candidate => candidate.mode.name === "b" && candidate.mode.image && root.dataset.hasImage === "1");
            const compactNoImage = candidates.find(candidate => candidate.mode.name === "c");
            const compactLeavesEmptyColumn = compactNoImage && compactNoImage.usedColumns < compactNoImage.mode.columns;
            const preferTwoColumnImage = modes[0]?.name !== "a";

            if (preferTwoColumnImage && imageTwoColumn && (compactLeavesEmptyColumn || imageTwoColumn.fontSize >= bestFont - 2)) {
                chosen = imageTwoColumn;
            } else {
                chosen = candidates.reduce((best, candidate) => {
                    if (candidate.fontSize !== best.fontSize) {
                        return candidate.fontSize > best.fontSize ? candidate : best;
                    }
                    return (modeOrder.get(candidate.mode.name) || 0) < (modeOrder.get(best.mode.name) || 0) ? candidate : best;
                });
            }
        }

        if (chosen === null) {
            const compact = { ...MODES[2], image: false };
            for (let fontSize = range.min - 1; fontSize >= 7 && chosen === null; fontSize -= 1) {
                applyMode(root, compact, fontSize);
                buildExplicitColumns(root);
                if (!hasLayoutProblems(root)) {
                    chosen = { mode: compact, fontSize };
                }
            }
            if (chosen === null) {
                chosen = { mode: compact, fontSize: 7 };
            }
        }

        applyMode(root, chosen.mode, chosen.fontSize);
        buildExplicitColumns(root);
        root.classList.toggle("tl1menu-list--overflow-warning", hasLayoutProblems(root));
        root.classList.remove("tl1menu-list--measuring");
        state.fitting = false;
    };

    const schedule = root => {
        const state = stateFor(root);
        if (state.frame) {
            window.cancelAnimationFrame?.(state.frame);
        }
        state.frame = requestFrame(() => {
            state.frame = 0;
            fit(root);
        });
    };

    const observe = root => {
        if (root.dataset.listObserverBound === '1') return;
        root.dataset.listObserverBound = '1';

        if (window.ResizeObserver) {
            const resizeObserver = new ResizeObserver(() => schedule(root));
            resizeObserver.observe(root);
            const stage = root.querySelector('.tl1menu-list__stage');
            if (stage) resizeObserver.observe(stage);
        }

        if (window.MutationObserver) {
            const mutationObserver = new MutationObserver(() => {
                if (stateFor(root).ignoreMutations) return;
                schedule(root);
            });
            mutationObserver.observe(root, { childList: true, characterData: true, subtree: true });
        }

        schedule(root);
    };

    const init = () => {
        document.querySelectorAll('[data-tl1menu-list]').forEach(observe);
    };

    window.addEventListener('resize', init);
    window.addEventListener('orientationchange', init);
    window.addEventListener('load', init);
    document.addEventListener('DOMContentLoaded', init);
    if (document.fonts?.ready) {
        document.fonts.ready.then(init).catch(() => {});
    }
    init();
})();
