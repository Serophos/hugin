(() => {
    const csrfInput = document.querySelector('input[name="_csrf"]');
    const csrfToken = csrfInput ? csrfInput.value : '';

    document.querySelectorAll('.sortable-list').forEach(list => {
        let dragged = null;

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
})();
