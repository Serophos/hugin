(() => {
    const board = document.querySelector('[data-group-layout]');
    if (!board) return;

    const canvas = board.querySelector('[data-layout-canvas]');
    const saveButton = document.querySelector('[data-save-layout]');
    const message = document.querySelector('[data-layout-message]');
    const csrfInput = document.querySelector('input[name="_csrf"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    let dirty = false;
    let activeDrag = null;

    board.querySelectorAll('[data-display-tile]').forEach(tile => {
        tile.addEventListener('pointerdown', event => {
            if (event.button !== 0) return;

            const left = parseInt(tile.style.left || '0', 10);
            const top = parseInt(tile.style.top || '0', 10);
            activeDrag = {
                tile,
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                left: Number.isFinite(left) ? left : 0,
                top: Number.isFinite(top) ? top : 0,
            };
            tile.setPointerCapture(event.pointerId);
            tile.classList.add('is-dragging');
        });

        tile.addEventListener('pointermove', event => {
            if (!activeDrag || activeDrag.tile !== tile) return;

            const nextLeft = activeDrag.left + event.clientX - activeDrag.startX;
            const nextTop = activeDrag.top + event.clientY - activeDrag.startY;
            setTilePosition(tile, nextLeft, nextTop);
            setDirty(true);
        });

        tile.addEventListener('pointerup', endDrag);
        tile.addEventListener('pointercancel', endDrag);
    });

    if (saveButton) {
        saveButton.addEventListener('click', () => {
            const endpoint = board.dataset.saveEndpoint;
            if (!endpoint) return;

            const params = new URLSearchParams();
            params.append('_csrf', csrfToken);
            params.append('items', JSON.stringify(collectLayout()));
            setMessage(saveButton.dataset.savingLabel || 'Saving...');
            saveButton.disabled = true;

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken,
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: params.toString(),
            })
                .then(response => response.json())
                .then(payload => {
                    if (!payload.ok) {
                        throw new Error(payload.message || 'Save failed.');
                    }
                    setDirty(false);
                    setMessage(payload.message || saveButton.dataset.savedLabel || 'Saved.');
                })
                .catch(error => {
                    setMessage(error.message || saveButton.dataset.saveFailedLabel || 'Save failed.');
                })
                .finally(() => {
                    saveButton.disabled = false;
                });
        });
    }

    function endDrag(event) {
        if (!activeDrag || activeDrag.tile !== event.currentTarget) return;

        event.currentTarget.releasePointerCapture(activeDrag.pointerId);
        event.currentTarget.classList.remove('is-dragging');
        activeDrag = null;
    }

    function setTilePosition(tile, left, top) {
        const maxLeft = Math.max(0, canvas.clientWidth - tile.offsetWidth);
        const maxTop = Math.max(0, canvas.clientHeight - tile.offsetHeight);
        tile.style.left = `${Math.round(Math.min(maxLeft, Math.max(0, left)))}px`;
        tile.style.top = `${Math.round(Math.min(maxTop, Math.max(0, top)))}px`;
    }

    function collectLayout() {
        return Array.from(board.querySelectorAll('[data-display-tile]')).map(tile => ({
            display_id: Number(tile.dataset.displayId || 0),
            x: parseInt(tile.style.left || '0', 10) || 0,
            y: parseInt(tile.style.top || '0', 10) || 0,
            width: Math.round(tile.offsetWidth),
            height: Math.round(tile.offsetHeight),
            rotation: Number(tile.dataset.rotation || 0),
        }));
    }

    function setDirty(value) {
        dirty = value;
        board.classList.toggle('has-unsaved-layout', dirty);
        if (dirty) {
            setMessage(saveButton?.dataset.unsavedLabel || 'Unsaved changes');
        }
    }

    function setMessage(text) {
        if (message) {
            message.textContent = text;
        }
    }
})();
