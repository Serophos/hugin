(() => {
    /**
 * Hugin - Digital Signage System
 * Copyright (C) 2026 Thees Winkler
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Source code: https://github.com/Serophos/hugin
 */

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
