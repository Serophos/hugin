(() => {
    const csrfInput = document.querySelector('input[name="_csrf"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    let liveRegion = document.querySelector('[data-sortable-live]');
    if (!liveRegion) {
        liveRegion = document.createElement('div');
        liveRegion.className = 'visually-hidden';
        liveRegion.dataset.sortableLive = '';
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        document.body.appendChild(liveRegion);
    }

    document.querySelectorAll('.sortable-list').forEach(list => {
        let dragged = null;
        enhanceKeyboardControls(list);

        list.querySelectorAll('tr[draggable="true"]').forEach(row => {
            row.addEventListener('dragstart', () => {
                dragged = row;
                row.classList.add('dragging');
            });

            row.addEventListener('dragend', () => {
                row.classList.remove('dragging');
                list.querySelectorAll('tr').forEach(item => item.classList.remove('drag-over'));
                sendOrder(list);
            });

            row.addEventListener('dragover', event => {
                event.preventDefault();
                if (!dragged || dragged === row) return;
                row.classList.add('drag-over');
            });

            row.addEventListener('dragleave', () => {
                row.classList.remove('drag-over');
            });

            row.addEventListener('drop', event => {
                event.preventDefault();
                row.classList.remove('drag-over');
                if (!dragged || dragged === row) return;

                const rows = Array.from(list.querySelectorAll('tr'));
                const draggedIndex = rows.indexOf(dragged);
                const targetIndex = rows.indexOf(row);

                if (draggedIndex < targetIndex) {
                    row.after(dragged);
                } else {
                    row.before(dragged);
                }
            });
        });
    });

    function sendOrder(list) {
        const endpoint = list.dataset.sortEndpoint;
        if (!endpoint) return;

        const params = new URLSearchParams();
        params.append('_csrf', csrfToken);

        if (list.dataset.extraName && list.dataset.extraValue) {
            params.append(list.dataset.extraName, list.dataset.extraValue);
        }

        list.querySelectorAll('tr[data-id]').forEach(row => {
            params.append('ids[]', row.dataset.id);
        });

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken,
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: params.toString()
        }).catch(() => {});
    }

    function enhanceKeyboardControls(list) {
        list.querySelectorAll('tr[data-id]').forEach(row => {
            const handle = row.querySelector('.handle');
            if (!handle || handle.dataset.sortableReady === '1') return;
            handle.dataset.sortableReady = '1';
            handle.textContent = '';
            handle.setAttribute('aria-label', 'Reorder');

            const moveUp = document.createElement('button');
            moveUp.type = 'button';
            moveUp.className = 'sortable-move-button';
            moveUp.dataset.sortMove = 'up';
            moveUp.textContent = '↑';
            moveUp.setAttribute('aria-label', 'Move row up');

            const moveDown = document.createElement('button');
            moveDown.type = 'button';
            moveDown.className = 'sortable-move-button';
            moveDown.dataset.sortMove = 'down';
            moveDown.textContent = '↓';
            moveDown.setAttribute('aria-label', 'Move row down');

            handle.append(moveUp, moveDown);
        });

        list.addEventListener('click', event => {
            const button = event.target instanceof Element ? event.target.closest('[data-sort-move]') : null;
            if (!button || !list.contains(button)) return;
            const row = button.closest('tr[data-id]');
            if (!row) return;
            const direction = button.dataset.sortMove;
            const sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling || !(sibling instanceof HTMLTableRowElement)) return;
            if (direction === 'up') {
                sibling.before(row);
            } else {
                sibling.after(row);
            }
            sendOrder(list);
            liveRegion.textContent = direction === 'up' ? 'Row moved up.' : 'Row moved down.';
            row.querySelector(`[data-sort-move="${direction}"]`)?.focus();
        });
    }
})();
