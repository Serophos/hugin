(() => {
    const normalize = (value) => String(value || "").trim().toLocaleLowerCase();

    function compareValues(a, b, type) {
        const aEmpty = String(a).trim() === "";
        const bEmpty = String(b).trim() === "";
        if (aEmpty || bEmpty) return aEmpty === bEmpty ? 0 : (aEmpty ? 1 : -1);

        if (type === "number") {
            const result = Number.parseFloat(String(a).replace(",", ".")) - Number.parseFloat(String(b).replace(",", "."));
            return Number.isNaN(result) ? 0 : result;
        }

        return String(a).localeCompare(String(b), undefined, { sensitivity: "base", numeric: true });
    }

    function dispatchUpdate(table, rows) {
        table.dispatchEvent(new CustomEvent("admin-table-updated", {
            bubbles: true,
            detail: { visible: rows.filter(row => !row.hidden).length, total: rows.length },
        }));
    }

    document.querySelectorAll("[data-admin-table]").forEach((table) => {
        if (table.querySelector("tbody.sortable-list")) return;

        const headerRow = table.tHead?.rows[0];
        const tbody = table.tBodies[0];
        if (!headerRow || !tbody) return;

        const rows = Array.from(tbody.querySelectorAll("tr[data-admin-row]"));
        const filterCells = Array.from(table.querySelector(".slide-library-filter-row")?.cells || []);
        const columns = Array.from(headerRow.cells).map((headerCell, index) => {
            const sortButton = headerCell.querySelector("[data-admin-sort]");
            const filterControl = filterCells[index]?.querySelector("[data-admin-filter]");
            if (filterControl instanceof HTMLSelectElement) {
                filterControl.classList.add("form-select", "form-select-sm");
            } else if (filterControl instanceof HTMLInputElement) {
                filterControl.classList.add("form-control", "form-control-sm");
            }
            return {
                headerCell,
                sortButton,
                filterControl,
                sortType: sortButton?.dataset.sortType || "text",
            };
        });
        const stateKey = table.dataset.adminTableStateKey
            ? "hugin:admin-table:" + table.dataset.adminTableStateKey
            : "";
        let savedState = null;
        if (stateKey) {
            try {
                const parsed = JSON.parse(window.sessionStorage.getItem(stateKey) || "null");
                if (parsed && typeof parsed === "object") savedState = parsed;
            } catch (error) {}
        }

        const savedSortColumn = String(savedState?.sortColumn || "");
        let sortColumn = savedSortColumn
            ? columns.findIndex(column => column.sortButton?.dataset.adminSort === savedSortColumn)
            : columns.findIndex(column => column.sortButton);
        if (sortColumn < 0) sortColumn = columns.findIndex(column => column.sortButton);
        let sortDirection = savedState?.sortDirection === "desc" ? "desc" : "asc";

        const savedFilters = savedState?.filters && typeof savedState.filters === "object"
            ? savedState.filters
            : {};
        columns.forEach((column) => {
            const filterKey = column.filterControl?.dataset.adminFilter || "";
            if (filterKey && Object.prototype.hasOwnProperty.call(savedFilters, filterKey)) {
                column.filterControl.value = String(savedFilters[filterKey] ?? "");
            }
        });

        if (!table.caption) {
            const caption = document.createElement("caption");
            caption.className = "visually-hidden";
            caption.textContent = table.dataset.tableLabel || document.title || "Admin data table";
            table.prepend(caption);
        }

        function update() {
            rows.forEach((row) => {
                row.hidden = columns.some((column, index) => {
                    const query = normalize(column.filterControl?.value);
                    if (!query) return false;
                    const cell = row.cells[index];
                    const value = normalize(cell?.dataset.filterValue ?? cell?.textContent);
                    return column.filterControl instanceof HTMLSelectElement ? value !== query : !value.includes(query);
                });
            });

            if (sortColumn >= 0) {
                const column = columns[sortColumn];
                rows.sort((a, b) => {
                    const aCell = a.cells[sortColumn];
                    const bCell = b.cells[sortColumn];
                    const result = compareValues(
                        aCell?.dataset.sortValue ?? aCell?.textContent ?? "",
                        bCell?.dataset.sortValue ?? bCell?.textContent ?? "",
                        column.sortType,
                    );
                    return sortDirection === "asc" ? result : -result;
                });
                rows.forEach(row => tbody.append(row));
            }

            columns.forEach((column, index) => {
                column.headerCell.setAttribute("aria-sort", index === sortColumn
                    ? (sortDirection === "asc" ? "ascending" : "descending")
                    : "none");
            });
            if (stateKey) {
                const filters = {};
                columns.forEach((column) => {
                    const filterKey = column.filterControl?.dataset.adminFilter || "";
                    if (filterKey) filters[filterKey] = column.filterControl.value;
                });
                try {
                    window.sessionStorage.setItem(stateKey, JSON.stringify({
                        filters,
                        sortColumn: columns[sortColumn]?.sortButton?.dataset.adminSort || "",
                        sortDirection,
                    }));
                } catch (error) {}
            }
            dispatchUpdate(table, rows);
        }

        columns.forEach((column, index) => {
            column.sortButton?.addEventListener("click", () => {
                if (sortColumn === index) sortDirection = sortDirection === "asc" ? "desc" : "asc";
                else {
                    sortColumn = index;
                    sortDirection = "asc";
                }
                update();
            });
            column.filterControl?.addEventListener("input", update);
            column.filterControl?.addEventListener("change", update);
        });

        table.addEventListener("admin-table-reset", () => {
            columns.forEach((column) => {
                if (column.filterControl) column.filterControl.value = "";
            });
            update();
            columns.find(column => column.filterControl)?.filterControl?.focus();
        });

        update();
    });
})();
