(() => {
    document.querySelectorAll('[data-admin-table]').forEach((table) => {
        const tbody = table.tBodies[0];
        if (!tbody) return;

        const rows = Array.from(tbody.querySelectorAll('tr[data-admin-row]'));
        const sortButtons = Array.from(table.querySelectorAll('[data-admin-sort]'));
        const filterControls = Array.from(table.querySelectorAll('[data-admin-filter]'));
        const sortState = { key: '', direction: 'asc' };
        const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

        rows.forEach((row, index) => {
            row.dataset.originalIndex = String(index);
        });

        function cellFor(row, key) {
            return row.querySelector(`[data-admin-cell="${key}"]`);
        }

        function valueFor(row, key, valueType) {
            const cell = cellFor(row, key);
            if (!cell) return '';
            return cell.dataset[valueType] || cell.textContent || '';
        }

        function compareRows(a, b) {
            if (!sortState.key) {
                return Number(a.dataset.originalIndex || 0) - Number(b.dataset.originalIndex || 0);
            }

            const sortButton = sortButtons.find((button) => button.dataset.adminSort === sortState.key);
            const sortType = sortButton?.dataset.sortType || 'text';
            const direction = sortState.direction === 'desc' ? -1 : 1;
            const aValue = valueFor(a, sortState.key, 'sortValue');
            const bValue = valueFor(b, sortState.key, 'sortValue');
            const aEmpty = String(aValue).trim() === '';
            const bEmpty = String(bValue).trim() === '';
            if (aEmpty && bEmpty) return Number(a.dataset.originalIndex || 0) - Number(b.dataset.originalIndex || 0);
            if (aEmpty) return 1;
            if (bEmpty) return -1;

            let result;
            if (sortType === 'number') {
                result = Number.parseFloat(String(aValue).replace(',', '.')) - Number.parseFloat(String(bValue).replace(',', '.'));
            } else {
                result = String(aValue).localeCompare(String(bValue), undefined, { sensitivity: 'base', numeric: true });
            }

            if (Number.isNaN(result) || result === 0) {
                return Number(a.dataset.originalIndex || 0) - Number(b.dataset.originalIndex || 0);
            }
            return result * direction;
        }

        function rowMatchesFilters(row) {
            return filterControls.every((control) => {
                const query = normalize(control.value);
                if (!query) return true;
                const cellValue = normalize(valueFor(row, control.dataset.adminFilter || '', 'filterValue'));
                if (control.tagName === 'SELECT') {
                    return cellValue === query;
                }
                return cellValue.includes(query);
            });
        }

        function updateSortHeaders() {
            sortButtons.forEach((button) => {
                const isActive = button.dataset.adminSort === sortState.key;
                const th = button.closest('th');
                if (th) {
                    th.setAttribute('aria-sort', isActive ? (sortState.direction === 'asc' ? 'ascending' : 'descending') : 'none');
                }
                button.dataset.sortDirection = isActive ? sortState.direction : '';
            });
        }

        function updateTable() {
            rows.slice().sort(compareRows).forEach((row) => tbody.appendChild(row));
            rows.forEach((row) => {
                row.hidden = !rowMatchesFilters(row);
            });
            updateSortHeaders();
        }

        sortButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const nextKey = button.dataset.adminSort || '';
                if (sortState.key === nextKey) {
                    sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    sortState.key = nextKey;
                    sortState.direction = 'asc';
                }
                updateTable();
            });
        });

        filterControls.forEach((control) => {
            control.addEventListener('input', updateTable);
            control.addEventListener('change', updateTable);
        });

        updateTable();
    });
})();
