(() => {
    const Tabulator = window.Tabulator;

    if (!Tabulator) {
        return;
    }

    const sortableTableSelector = '[data-admin-table]';
    const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

    function buildColumns(table) {
        const headerRow = table.tHead?.rows[0];
        const filterRow = table.querySelector('.slide-library-filter-row');
        if (!headerRow) return [];

        const filterCells = filterRow ? Array.from(filterRow.cells) : [];

        return Array.from(headerRow.cells).map((headerCell, index) => {
            const sortButton = headerCell.querySelector('[data-admin-sort]');
            const filterControl = filterCells[index]?.querySelector('[data-admin-filter]');
            const isActionColumn = Array.from(table.tBodies).some(body =>
                Array.from(body.rows).some(row => row.cells[index]?.classList.contains('actions'))
            );
            const field = sortButton?.dataset.adminSort || `__column_${index}`;
            const title = (sortButton || headerCell).textContent.trim();
            const isSortable = Boolean(sortButton);
            const sortType = sortButton?.dataset.sortType || 'text';
            const isSelectFilter = filterControl?.tagName === 'SELECT';
            const definition = {
                title,
                field,
                formatter: 'html',
                headerSort: isSortable,
                sorter: isSortable ? (a, b, aRow, bRow) => {
                    const aValue = aRow.getData()[`_sort_${field}`] ?? a;
                    const bValue = bRow.getData()[`_sort_${field}`] ?? b;
                    const aEmpty = String(aValue).trim() === '';
                    const bEmpty = String(bValue).trim() === '';
                    if (aEmpty && bEmpty) return 0;
                    if (aEmpty) return 1;
                    if (bEmpty) return -1;

                    if (sortType === 'number') {
                        const result = Number.parseFloat(String(aValue).replace(',', '.')) - Number.parseFloat(String(bValue).replace(',', '.'));
                        return Number.isNaN(result) ? 0 : result;
                    }

                    return String(aValue).localeCompare(String(bValue), undefined, { sensitivity: 'base', numeric: true });
                } : undefined,
            };

            if (filterControl) {
                definition.headerFilter = isSelectFilter ? 'list' : 'input';
                definition.headerFilterPlaceholder = filterControl.getAttribute('placeholder') || '';
                definition.headerFilterFunc = (headerValue, rowValue, rowData) => {
                    const query = normalize(headerValue);
                    if (!query) return true;

                    const filterValue = normalize(rowData[`_filter_${field}`] ?? rowValue);
                    return isSelectFilter ? filterValue === query : filterValue.includes(query);
                };

                if (isSelectFilter) {
                    const values = {};
                    Array.from(filterControl.options).forEach((option) => {
                        values[option.value] = option.textContent.trim();
                    });
                    definition.headerFilterParams = { values };
                }
            }

            const columnClasses = [
                headerCell.className,
                isActionColumn ? 'admin-actions-column' : '',
            ].filter(Boolean).join(' ');
            if (columnClasses) {
                definition.cssClass = columnClasses;
                definition.headerCssClass = columnClasses;
            }

            return definition;
        });
    }

    function buildData(table) {
        const headerRow = table.tHead?.rows[0];
        const fields = headerRow ? Array.from(headerRow.cells).map((headerCell, index) => {
            const sortButton = headerCell.querySelector('[data-admin-sort]');
            return sortButton?.dataset.adminSort || `__column_${index}`;
        }) : [];

        return Array.from(table.tBodies[0]?.querySelectorAll('tr[data-admin-row]') || []).map((row, rowIndex) => {
            const record = { id: row.dataset.id || `row-${rowIndex}`, _originalIndex: rowIndex };
            record._rowDataset = { ...row.dataset };

            Array.from(row.cells).forEach((cell, index) => {
                const field = fields[index] || `__column_${index}`;
                record[field] = cell.innerHTML;
                record[`_sort_${field}`] = cell.dataset.sortValue ?? cell.textContent ?? '';
                record[`_filter_${field}`] = cell.dataset.filterValue ?? cell.textContent ?? '';
            });

            return record;
        });
    }

    function dispatchUpdate(table, tabulator) {
        const visible = tabulator.getData('active').length;
        const total = tabulator.getData().length;
        table.dispatchEvent(new CustomEvent('admin-table-updated', {
            bubbles: true,
            detail: { visible, total },
        }));
    }

    document.querySelectorAll(sortableTableSelector).forEach((table) => {
        if (table.querySelector('tbody.sortable-list')) {
            return;
        }

        const columns = buildColumns(table);
        const data = buildData(table);
        if (!columns.length) return;

        if (!table.caption) {
            const caption = document.createElement('caption');
            caption.className = 'visually-hidden';
            caption.textContent = table.dataset.tableLabel || document.title || 'Admin data table';
            table.prepend(caption);
        }

        const holder = document.createElement('div');
        holder.className = 'admin-data-table';
        holder.dataset.adminDataTable = '';
        if (table.hasAttribute('data-slide-library-table')) {
            holder.dataset.slideLibraryTable = '';
        }
        table.before(holder);

        const tabulator = new Tabulator(holder, {
            data,
            columns,
            layout: 'fitColumns',
            index: 'id',
            reactiveData: false,
            placeholder: table.dataset.emptyLabel || '',
            initialSort: [{ column: columns.find((column) => column.headerSort)?.field || columns[0].field, dir: 'asc' }],
            rowFormatter: (row) => {
                const rowDataset = row.getData()._rowDataset || {};
                Object.entries(rowDataset).forEach(([key, value]) => {
                    row.getElement().dataset[key] = value;
                });
            },
        });

        table.remove();

        holder.addEventListener('admin-table-reset', () => {
            tabulator.clearHeaderFilter();
            tabulator.clearFilter(true);
            const firstFilter = holder.querySelector('.tabulator-header-filter input, .tabulator-header-filter select');
            if (firstFilter instanceof HTMLElement) {
                firstFilter.focus();
            }
        });
        tabulator.on('tableBuilt', () => dispatchUpdate(holder, tabulator));
        tabulator.on('dataFiltered', () => dispatchUpdate(holder, tabulator));
        tabulator.on('dataSorted', () => dispatchUpdate(holder, tabulator));
    });
})();
