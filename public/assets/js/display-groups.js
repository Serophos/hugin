(() => {
    const board = document.querySelector('[data-group-layout]');
    if (!board) return;

    const GRID_SIZE = 24;
    const SNAP_DISTANCE = 10;
    const KEYBOARD_FINE_STEP = 1;
    const KEYBOARD_COARSE_STEP = 24;
    const canvas = board.querySelector('[data-layout-canvas]');
    const editor = board.closest('.group-layout-main') || document;
    const saveButton = editor.querySelector('[data-save-layout]');
    const message = editor.querySelector('[data-layout-message]');
    const snapToggle = editor.querySelector('[data-layout-snap-toggle]');
    const removeButton = editor.querySelector('[data-remove-selected-display]');
    const primaryButton = editor.querySelector('[data-toggle-primary-display]');
    const layoutEmpty = canvas.querySelector('[data-layout-empty]');
    const groupSettingsForm = document.querySelector('[data-group-settings-form]');
    const primaryInput = document.querySelector('[data-primary-display-input]');
    const removedDisplayInputs = document.querySelector('[data-removed-display-inputs]');
    const groupDisplayList = document.querySelector('[data-group-display-list]');
    const groupDisplayEmpty = document.querySelector('[data-group-display-empty]');
    const unassignedDialog = document.querySelector('[data-unassigned-display-dialog]');
    const openUnassignedButton = document.querySelector('[data-open-unassigned-display-dialog]');
    const unassignedForm = unassignedDialog?.querySelector('[data-unassigned-display-form]');
    const unassignedOptions = Array.from(unassignedDialog?.querySelectorAll('[data-unassigned-display-option]') || []);
    const unassignedInput = unassignedDialog?.querySelector('[data-unassigned-display-input]');
    const unassignedSubmit = unassignedDialog?.querySelector('[data-unassigned-display-submit]');
    const unassignedEmpty = unassignedDialog?.querySelector('[data-unassigned-display-empty]');
    const unassignedCloseButtons = Array.from(unassignedDialog?.querySelectorAll('[data-unassigned-display-close]') || []);
    const csrfInput = document.querySelector('input[name="_csrf"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    const removedDisplayIds = new Set();
    let primaryDisplayId = normalizeDisplayId(primaryInput?.value || '');
    let dirty = false;
    let activeDrag = null;
    let selectedTile = null;
    let selectedUnassignedOption = unassignedOptions.find(option => option.getAttribute('aria-checked') === 'true') || unassignedOptions[0] || null;
    let unassignedDialogOpener = null;

    canvas.addEventListener('pointerdown', event => {
        if (event.button !== 0) return;
        if (event.target === canvas || event.target === layoutEmpty) {
            clearSelectedTile();
        }
    });

    editor.addEventListener('keydown', event => {
        if (!isDeselectShortcut(event) || !selectedTile || isEditableControl(event.target)) return;

        event.preventDefault();
        clearSelectedTile();
    });

    board.querySelectorAll('[data-display-tile]').forEach(tile => {
        tile.addEventListener('pointerdown', event => {
            if (event.button !== 0) return;

            selectTile(tile);
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

        tile.addEventListener('focus', () => {
            selectTile(tile);
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
        tile.addEventListener('keydown', event => {
            if (isDeselectShortcut(event)) {
                event.preventDefault();
                clearSelectedTile();
                return;
            }

            const movement = keyboardMovement(event);
            if (!movement) return;

            event.preventDefault();
            moveTileByKeyboard(tile, movement.x, movement.y);
        });
    });

    if (removeButton) {
        removeButton.addEventListener('click', () => {
            removeSelectedDisplay();
        });
    }

    if (primaryButton) {
        primaryButton.addEventListener('click', () => {
            togglePrimaryDisplay();
        });
    }

    if (groupSettingsForm) {
        groupSettingsForm.addEventListener('submit', () => {
            syncPrimaryDisplayInput();
            syncRemovedDisplayInputs();
        });
    }

    setupUnassignedDisplayDialog();

    if (saveButton) {
        saveButton.addEventListener('click', () => {
            const endpoint = board.dataset.saveEndpoint;
            if (!endpoint) return;

            const params = new URLSearchParams();
            params.append('_csrf', csrfToken);
            params.append('items', JSON.stringify(collectLayout()));
            params.append('removed_display_ids', JSON.stringify(Array.from(removedDisplayIds)));
            params.append('primary_display_id', primaryDisplayId);
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
                    finalizeRemovedDisplays();
                    removedDisplayIds.clear();
                    syncRemovedDisplayInputs();
                    updateEmptyStates();
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

    function selectTile(tile) {
        if (!tile || tile.dataset.pendingRemoval === 'true') return;

        if (selectedTile && selectedTile !== tile) {
            selectedTile.classList.remove('is-selected');
        }

        selectedTile = tile;
        selectedTile.classList.add('is-selected');
        updateSelectionControls();
    }

    function clearSelectedTile() {
        if (selectedTile) {
            selectedTile.classList.remove('is-selected');
        }

        selectedTile = null;
        updateSelectionControls();
    }

    function isDeselectShortcut(event) {
        return event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey && isSpaceKey(event);
    }

    function isSpaceKey(event) {
        return event.key === ' ' || event.key === 'Spacebar' || event.code === 'Space';
    }

    function isEditableControl(target) {
        if (!(target instanceof Element)) {
            return false;
        }

        return Boolean(target.closest('input, textarea, select, [contenteditable="true"]'));
    }

    function updateSelectionControls() {
        updateRemoveButton();
        updatePrimaryButton();
    }

    function updateRemoveButton() {
        if (removeButton) {
            removeButton.disabled = !selectedTile;
        }
    }

    function updatePrimaryButton() {
        if (!primaryButton) return;

        const selectedDisplayId = selectedTile ? displayIdForTile(selectedTile) : '';
        const selectedIsPrimary = selectedDisplayId !== '' && selectedDisplayId === primaryDisplayId;
        const label = selectedDisplayId === ''
            ? primaryButton.dataset.primaryLabel || ''
            : selectedIsPrimary
                ? primaryButton.dataset.primaryClearLabel || primaryButton.dataset.primaryLabel || ''
                : primaryButton.dataset.primarySetLabel || primaryButton.dataset.primaryLabel || '';

        primaryButton.disabled = selectedDisplayId === '';
        primaryButton.classList.toggle('is-active', selectedIsPrimary);
        primaryButton.setAttribute('aria-pressed', selectedIsPrimary ? 'true' : 'false');

        if (label !== '') {
            primaryButton.dataset.toolbarTooltip = label;
            primaryButton.setAttribute('aria-label', label);
            primaryButton.title = label;
        }
    }

    function togglePrimaryDisplay() {
        if (!selectedTile) return;

        const selectedDisplayId = displayIdForTile(selectedTile);
        if (selectedDisplayId === '') return;

        primaryDisplayId = primaryDisplayId === selectedDisplayId ? '' : selectedDisplayId;
        syncPrimaryDisplayInput();
        updatePrimaryDisplayTiles();
        updatePrimaryButton();
        setDirty(true);
    }

    function setupUnassignedDisplayDialog() {
        if (!unassignedDialog || !openUnassignedButton || !unassignedForm) return;

        if (unassignedEmpty) {
            unassignedEmpty.hidden = unassignedOptions.length > 0;
        }

        selectUnassignedOption(selectedUnassignedOption);

        openUnassignedButton.addEventListener('click', () => {
            openUnassignedDialog(openUnassignedButton);
        });

        unassignedOptions.forEach(option => {
            option.addEventListener('click', () => selectUnassignedOption(option));
            option.addEventListener('dblclick', () => {
                selectUnassignedOption(option);
                submitUnassignedForm();
            });
            option.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectUnassignedOption(option);
                    return;
                }
                moveUnassignedSelection(event);
            });
        });

        unassignedCloseButtons.forEach(button => {
            button.addEventListener('click', closeUnassignedDialog);
        });

        unassignedForm.addEventListener('submit', event => {
            if (!selectedUnassignedOption) {
                event.preventDefault();
            }
        });

        unassignedDialog.addEventListener('cancel', event => {
            event.preventDefault();
            closeUnassignedDialog();
        });

        unassignedDialog.addEventListener('click', event => {
            if (event.target === unassignedDialog) {
                closeUnassignedDialog();
            }
        });

        unassignedDialog.addEventListener('close', () => {
            unassignedDialogOpener?.focus?.({preventScroll: true});
            unassignedDialogOpener = null;
        });
    }

    function openUnassignedDialog(opener) {
        if (!unassignedDialog) return;

        unassignedDialogOpener = opener || document.activeElement;
        if (typeof unassignedDialog.showModal === 'function') {
            unassignedDialog.showModal();
        } else {
            unassignedDialog.setAttribute('open', '');
        }

        window.setTimeout(() => {
            (selectedUnassignedOption || unassignedDialog.querySelector('[data-unassigned-display-close]') || unassignedDialog)
                .focus?.({preventScroll: true});
        }, 0);
    }

    function closeUnassignedDialog() {
        if (!unassignedDialog) return;

        if (typeof unassignedDialog.close === 'function' && unassignedDialog.open) {
            unassignedDialog.close();
        } else {
            unassignedDialog.removeAttribute('open');
            unassignedDialogOpener?.focus?.({preventScroll: true});
            unassignedDialogOpener = null;
        }
    }

    function selectUnassignedOption(option, focus = false) {
        selectedUnassignedOption = option || null;
        unassignedOptions.forEach(item => {
            const selected = item === selectedUnassignedOption;
            item.setAttribute('aria-pressed', selected ? 'true' : 'false');
            item.setAttribute('aria-checked', selected ? 'true' : 'false');
            item.tabIndex = selected ? 0 : -1;
        });

        if (unassignedInput) {
            unassignedInput.value = selectedUnassignedOption?.dataset.displayId || '';
        }
        if (unassignedSubmit) {
            unassignedSubmit.disabled = !selectedUnassignedOption;
        }
        if (focus && selectedUnassignedOption) {
            selectedUnassignedOption.focus({preventScroll: true});
        }
    }

    function moveUnassignedSelection(event) {
        if (!['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;

        event.preventDefault();
        if (unassignedOptions.length === 0) return;

        const current = Math.max(0, unassignedOptions.indexOf(selectedUnassignedOption));
        const last = unassignedOptions.length - 1;
        let next = current;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = last;
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = current >= last ? 0 : current + 1;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = current <= 0 ? last : current - 1;
        selectUnassignedOption(unassignedOptions[next], true);
    }

    function submitUnassignedForm() {
        if (!unassignedForm || !selectedUnassignedOption) return;

        if (typeof unassignedForm.requestSubmit === 'function') {
            unassignedForm.requestSubmit(unassignedSubmit || undefined);
            return;
        }

        unassignedForm.submit();
    }

    function removeSelectedDisplay() {
        if (!selectedTile) return;

        const displayId = selectedTile.dataset.displayId || '';
        if (displayId === '') return;

        if (primaryDisplayId === displayId) {
            primaryDisplayId = '';
            syncPrimaryDisplayInput();
            updatePrimaryDisplayTiles();
        }

        removedDisplayIds.add(displayId);
        selectedTile.dataset.pendingRemoval = 'true';
        selectedTile.hidden = true;
        selectedTile.tabIndex = -1;
        selectedTile.classList.remove('is-selected');
        hideGroupDisplayRow(displayId);
        clearSelectedTile();
        syncRemovedDisplayInputs();
        updateEmptyStates();
        setDirty(true);
    }

    function hideGroupDisplayRow(displayId) {
        if (!groupDisplayList) return;

        groupDisplayList.querySelectorAll('[data-group-display-row]').forEach(row => {
            if (row.dataset.displayId === displayId) {
                row.dataset.pendingRemoval = 'true';
                row.hidden = true;
            }
        });
    }

    function syncRemovedDisplayInputs() {
        if (!removedDisplayInputs) return;

        removedDisplayInputs.replaceChildren();
        removedDisplayIds.forEach(displayId => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'removed_display_ids[]';
            input.value = displayId;
            removedDisplayInputs.append(input);
        });
    }

    function syncPrimaryDisplayInput() {
        if (primaryInput) {
            primaryInput.value = primaryDisplayId;
        }
    }

    function updatePrimaryDisplayTiles() {
        board.querySelectorAll('[data-display-tile]').forEach(tile => {
            const isPrimary = tile.dataset.pendingRemoval !== 'true' && displayIdForTile(tile) === primaryDisplayId;
            tile.dataset.isPrimaryDisplay = isPrimary ? '1' : '0';
            tile.classList.toggle('is-primary', isPrimary);
        });
    }

    function finalizeRemovedDisplays() {
        board.querySelectorAll('[data-display-tile][data-pending-removal="true"]').forEach(tile => {
            tile.remove();
        });

        document.querySelectorAll('[data-group-display-row][data-pending-removal="true"]').forEach(row => {
            row.remove();
        });
    }

    function updateEmptyStates() {
        if (layoutEmpty) {
            layoutEmpty.hidden = visibleDisplayTiles().length > 0;
        }

        if (groupDisplayEmpty && groupDisplayList) {
            groupDisplayEmpty.hidden = visibleGroupDisplayRows().length > 0;
        }
    }

    function visibleDisplayTiles() {
        return Array.from(board.querySelectorAll('[data-display-tile]'))
            .filter(tile => tile.dataset.pendingRemoval !== 'true');
    }

    function visibleGroupDisplayRows() {
        if (!groupDisplayList) return [];

        return Array.from(groupDisplayList.querySelectorAll('[data-group-display-row]'))
            .filter(row => row.dataset.pendingRemoval !== 'true');
    }

    function displayIdForTile(tile) {
        return normalizeDisplayId(tile?.dataset.displayId || '');
    }

    function normalizeDisplayId(value) {
        const displayId = String(value || '').trim();
        return /^\d+$/.test(displayId) && Number(displayId) > 0 ? displayId : '';
    }

    function setTilePosition(tile, left, top) {
        const nextPosition = snapToLayoutGuides(tile, left, top);
        const maxLeft = Math.max(0, canvas.clientWidth - tile.offsetWidth);
        const maxTop = Math.max(0, canvas.clientHeight - tile.offsetHeight);
        tile.style.left = `${Math.round(Math.min(maxLeft, Math.max(0, nextPosition.left)))}px`;
        tile.style.top = `${Math.round(Math.min(maxTop, Math.max(0, nextPosition.top)))}px`;
    }

    function moveTileByKeyboard(tile, deltaX, deltaY) {
        const left = parseInt(tile.style.left || '0', 10) || 0;
        const top = parseInt(tile.style.top || '0', 10) || 0;

        setTilePosition(tile, left + deltaX, top + deltaY);

        const nextLeft = parseInt(tile.style.left || '0', 10) || 0;
        const nextTop = parseInt(tile.style.top || '0', 10) || 0;
        if (nextLeft !== left || nextTop !== top) {
            setDirty(true);
        }
    }

    function keyboardMovement(event) {
        if (event.altKey || event.ctrlKey || event.metaKey) {
            return null;
        }

        const step = keyboardStep(event);

        switch (event.key) {
            case 'ArrowLeft':
                return {x: -step, y: 0};
            case 'ArrowRight':
                return {x: step, y: 0};
            case 'ArrowUp':
                return {x: 0, y: -step};
            case 'ArrowDown':
                return {x: 0, y: step};
            default:
                return null;
        }
    }

    function keyboardStep(event) {
        if (snapToggle && snapToggle.checked) {
            return event.shiftKey ? GRID_SIZE * 5 : GRID_SIZE;
        }

        return event.shiftKey ? KEYBOARD_COARSE_STEP : KEYBOARD_FINE_STEP;
    }

    function snapToLayoutGuides(tile, left, top) {
        const bounded = constrainPosition(tile, left, top);
        if (!snapToggle || !snapToggle.checked) {
            return bounded;
        }

        const xGuides = horizontalSnapGuides(tile);
        const yGuides = verticalSnapGuides(tile);
        const snappedLeft = closestGuide(bounded.left, xGuides, SNAP_DISTANCE) ?? snapToGrid(bounded.left);
        const snappedTop = closestGuide(bounded.top, yGuides, SNAP_DISTANCE) ?? snapToGrid(bounded.top);

        return constrainPosition(tile, snappedLeft, snappedTop);
    }

    function constrainPosition(tile, left, top) {
        const maxLeft = Math.max(0, canvas.clientWidth - tile.offsetWidth);
        const maxTop = Math.max(0, canvas.clientHeight - tile.offsetHeight);

        return {
            left: Math.min(maxLeft, Math.max(0, left)),
            top: Math.min(maxTop, Math.max(0, top)),
        };
    }

    function snapToGrid(value) {
        return Math.round(value / GRID_SIZE) * GRID_SIZE;
    }

    function closestGuide(value, guides, maxDistance) {
        let closest = null;
        let closestDistance = maxDistance + 1;

        guides.forEach(guide => {
            const distance = Math.abs(value - guide);
            if (distance <= maxDistance && distance < closestDistance) {
                closest = guide;
                closestDistance = distance;
            }
        });

        return closest;
    }

    function horizontalSnapGuides(tile) {
        const maxLeft = Math.max(0, canvas.clientWidth - tile.offsetWidth);
        const guides = [0, maxLeft, maxLeft / 2];

        displayRects(tile).forEach(rect => {
            guides.push(
                rect.left,
                rect.right - tile.offsetWidth,
                rect.right,
                rect.left - tile.offsetWidth
            );
        });

        return guides;
    }

    function verticalSnapGuides(tile) {
        const maxTop = Math.max(0, canvas.clientHeight - tile.offsetHeight);
        const guides = [0, maxTop, maxTop / 2];

        displayRects(tile).forEach(rect => {
            guides.push(
                rect.top,
                rect.bottom - tile.offsetHeight,
                rect.bottom,
                rect.top - tile.offsetHeight
            );
        });

        return guides;
    }

    function displayRects(exceptTile) {
        return Array.from(board.querySelectorAll('[data-display-tile]'))
            .filter(tile => tile !== exceptTile && tile.dataset.pendingRemoval !== 'true')
            .map(tile => {
                const left = parseInt(tile.style.left || '0', 10) || 0;
                const top = parseInt(tile.style.top || '0', 10) || 0;

                return {
                    left,
                    top,
                    right: left + tile.offsetWidth,
                    bottom: top + tile.offsetHeight,
                };
            });
    }

    function collectLayout() {
        return visibleDisplayTiles().map(tile => ({
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
