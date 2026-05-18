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
            roots.set(root, { frame: 0, fitting: false });
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

            return Math.abs(titleCenter - itemCenter) > tolerance;
        });
    };

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
            names = ['c', 'b', 'a'];
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
        root.classList.add('tl1menu-list--measuring');

        const range = preferredFontRange(root, rect);
        const modes = orderedModes(root, rect);
        let chosen = null;

        for (let fontSize = range.max; fontSize >= range.min && chosen === null; fontSize -= 1) {
            for (const mode of modes) {
                applyMode(root, mode, fontSize);
                if (!hasOverflow(root) && !hasHeadingOrphans(root)) {
                    chosen = { mode, fontSize };
                    break;
                }
            }
        }

        if (chosen === null) {
            const compact = { ...MODES[2], image: false };
            for (let fontSize = range.min - 1; fontSize >= 7 && chosen === null; fontSize -= 1) {
                applyMode(root, compact, fontSize);
                if (!hasOverflow(root) && !hasHeadingOrphans(root)) {
                    chosen = { mode: compact, fontSize };
                }
            }
            if (chosen === null) {
                chosen = { mode: compact, fontSize: 7 };
            }
        }

        applyMode(root, chosen.mode, chosen.fontSize);
        root.classList.toggle('tl1menu-list--overflow-warning', hasOverflow(root) || hasHeadingOrphans(root));
        root.classList.remove('tl1menu-list--measuring');
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
            const mutationObserver = new MutationObserver(() => schedule(root));
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
