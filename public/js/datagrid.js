document.addEventListener('DOMContentLoaded', function() {
    // Initialisation des tooltips Bootstrap si disponibles
    if (typeof $ !== 'undefined' && $.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
    }

    const tbody = document.getElementById('projects-tbody');
    if (!tbody) return;

    let rows = Array.from(document.querySelectorAll('.project-row'));
    const filterInputs = document.querySelectorAll('.filter-input');
    const filterDomain = document.getElementById('filter_domain');
    const filterSf = document.getElementById('filter_sf');
    const btnResetFilters = document.getElementById('btn-reset-filters');
    
    // --- GESTION DES COLONNES (Si applicable) ---
    const columnToggles = document.querySelectorAll('.column-toggle');
    const table = document.getElementById('projects-table');
    
    function setColumnVisibility(column, visible) {
        if (!table) return;
        const cells = table.querySelectorAll(`[data-column="${column}"]`);
        cells.forEach(cell => {
            cell.style.display = visible ? '' : 'none';
        });
    }

    async function saveColumnPrefs() {
        const prefs = {};
        columnToggles.forEach(toggle => {
            prefs[toggle.dataset.column] = toggle.checked;
        });

        try {
            await fetch('?action=saveColumnsPrefs', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ columns: prefs })
            });
        } catch (error) {
            console.error('Erreur lors de la sauvegarde des prefs de colonnes:', error);
        }
    }

    if (columnToggles.length > 0 && typeof window.columnsPrefs !== 'undefined') {
        columnToggles.forEach(toggle => {
            const column = toggle.dataset.column;
            if (window.columnsPrefs[column] !== undefined) {
                toggle.checked = window.columnsPrefs[column];
            }
            setColumnVisibility(column, toggle.checked);

            toggle.addEventListener('change', function() {
                setColumnVisibility(column, this.checked);
                saveColumnPrefs();
            });
        });

        const columnsMenu = document.getElementById('columns-menu');
        if (columnsMenu) {
            columnsMenu.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
    }

    // --- FILTRAGE SF DYNAMIQUE ---
    let originalSfOptions = [];
    if (filterSf) {
        originalSfOptions = Array.from(filterSf.options).map(opt => ({
            value: opt.value,
            text: opt.textContent
        }));
    }

    function updateSfFilter() {
        if (!filterDomain || !filterSf) return;
        
        const selectedDomain = filterDomain.value;
        const currentSelectedSf = filterSf.value;

        let allowedSfs = null;
        if (selectedDomain !== 'all') {
            allowedSfs = new Set();
            rows.forEach(row => {
                const domainCol = row.querySelector('.col-domain');
                const sfCol = row.querySelector('.col-sf');
                if (domainCol && sfCol) {
                    const domain = domainCol.getAttribute('data-value');
                    const sf = sfCol.getAttribute('data-value');
                    if (domain === selectedDomain && sf) {
                        allowedSfs.add(sf);
                    }
                }
            });
        }

        filterSf.innerHTML = '';
        originalSfOptions.forEach(opt => {
            if (opt.value === 'all' || !allowedSfs || allowedSfs.has(opt.value)) {
                const newOpt = document.createElement('option');
                newOpt.value = opt.value;
                newOpt.textContent = opt.text;
                filterSf.appendChild(newOpt);
            }
        });

        const hasSelected = Array.from(filterSf.options).some(opt => opt.value === currentSelectedSf);
        filterSf.value = hasSelected ? currentSelectedSf : 'all';
    }

    if (filterDomain) {
        filterDomain.addEventListener('change', updateSfFilter);
    }

    // --- URL ET SESSION STORAGE ---
    function getFilterKey(input) {
        return input.getAttribute('data-filter-column') || input.id.replace('filter_', '').replace('project_name', 'name');
    }

    function updateURLFromFilters() {
        const params = new URLSearchParams(window.location.search);

        filterInputs.forEach(input => {
            const key = getFilterKey(input);
            const value = input.value;
            if (value !== 'all' && value !== '') {
                params.set(key, value);
                sessionStorage.setItem('app_filter_' + key, value);
            } else {
                params.delete(key);
                sessionStorage.removeItem('app_filter_' + key);
            }
        });

        const newRelativePathQuery = window.location.pathname + '?' + params.toString();
        history.replaceState(null, '', newRelativePathQuery);
    }

    function applyFiltersFromURL() {
        const params = new URLSearchParams(window.location.search);
        
        const getFilterValue = (key) => {
            if (params.has(key)) return params.get(key);
            return sessionStorage.getItem('app_filter_' + key);
        };

        filterInputs.forEach(input => {
            const key = getFilterKey(input);
            const val = getFilterValue(key);
            if (val) {
                input.value = val;
            }
        });
    }

    // --- PAGINATION ---
    const rowsPerPageSelect = document.getElementById('rows_per_page');
    const paginationContainer = document.getElementById('pagination-container');
    const paginationInfo = document.getElementById('pagination-info');

    let currentPage = 1;
    let rowsPerPage = rowsPerPageSelect ? parseInt(rowsPerPageSelect.value) : 15;
    let filteredRows = [...rows];
    window.filteredRows = filteredRows; // Expose for external checkboxes

    function updatePagination() {
        if (!paginationContainer || !paginationInfo) return;
        
        const totalRows = window.filteredRows.length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);

        if (currentPage < 1) currentPage = 1;
        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;

        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;

        rows.forEach(row => row.style.display = 'none');
        const displayedRows = window.filteredRows.slice(startIndex, endIndex);
        displayedRows.forEach(row => row.style.display = '');

        const endDisplayIndex = Math.min(endIndex, totalRows);
        const startDisplayIndex = totalRows === 0 ? 0 : startIndex + 1;
        paginationInfo.textContent = `Affichage de ${startDisplayIndex} à ${endDisplayIndex} sur ${totalRows}`;

        paginationContainer.innerHTML = '';
        if (totalPages <= 1) return;

        paginationContainer.insertAdjacentHTML('beforeend', `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a></li>`);
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                paginationContainer.insertAdjacentHTML('beforeend', `<li class="page-item ${currentPage === i ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                paginationContainer.insertAdjacentHTML('beforeend', `<li class="page-item disabled"><a class="page-link" href="#">...</a></li>`);
            }
        }
        paginationContainer.insertAdjacentHTML('beforeend', `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a></li>`);
    }

    if (paginationContainer) {
        paginationContainer.addEventListener('click', function(e) {
            if (e.target.tagName === 'A') {
                e.preventDefault();
                const page = parseInt(e.target.getAttribute('data-page'));
                if (!isNaN(page)) {
                    currentPage = page;
                    updatePagination();
                }
            }
        });
    }

    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', function() {
            rowsPerPage = parseInt(this.value);
            currentPage = 1;
            updatePagination();
        });
    }

    // --- TRI ---
    let currentSortColumn = '';
    let currentSortDirection = 'asc';
    const sortHeaders = document.querySelectorAll('.sortable-header');

    function getColumnValue(row, column) {
        const cell = row.querySelector(`[data-column="${column}"]`);
        if (cell) {
            if (cell.hasAttribute('data-value')) return cell.getAttribute('data-value');
            return cell.textContent.trim();
        }
        const genericCell = row.querySelector(`.col-${column}`);
        if (genericCell) {
            if (genericCell.hasAttribute('data-value')) return genericCell.getAttribute('data-value');
            return genericCell.textContent.trim();
        }
        return '';
    }

    sortHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            if (currentSortColumn === column) {
                currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortColumn = column;
                currentSortDirection = 'asc';
            }

            sortHeaders.forEach(h => {
                const icon = h.querySelector('.sort-icon');
                if (icon) {
                    icon.className = 'fa-solid fa-sort sort-icon';
                    icon.style.opacity = '0.5';
                }
            });
            const currentIcon = this.querySelector('.sort-icon');
            if (currentIcon) {
                currentIcon.className = currentSortDirection === 'asc' ? 'fa-solid fa-sort-up sort-icon' : 'fa-solid fa-sort-down sort-icon';
                currentIcon.style.opacity = '1';
            }

            rows.sort((a, b) => {
                let valA = getColumnValue(a, column);
                let valB = getColumnValue(b, column);
                const numA = parseFloat(valA);
                const numB = parseFloat(valB);
                if (!isNaN(numA) && !isNaN(numB) && isFinite(valA) && isFinite(valB)) {
                    valA = numA;
                    valB = numB;
                } else {
                    valA = valA.toString().toLowerCase();
                    valB = valB.toString().toLowerCase();
                }
                if (valA < valB) return currentSortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return currentSortDirection === 'asc' ? 1 : -1;
                return 0;
            });

            rows.forEach(row => tbody.appendChild(row));
            window.applyDatagridFilters();
        });
    });

    // --- FILTRES ---
    window.applyDatagridFilters = function() {
        window.filteredRows = rows.filter(row => {
            let match = true;
            filterInputs.forEach(input => {
                const val = input.value.toLowerCase();
                if (val === 'all' || val === '') return;

                const key = getFilterKey(input);
                let cellVal = getColumnValue(row, key).toLowerCase();
                
                if (input.tagName === 'SELECT') {
                    if (cellVal !== val) match = false;
                } else {
                    if (!cellVal.includes(val)) match = false;
                }
            });
            return match;
        });

        window.filteredRows.forEach((row, index) => {
            const idxCol = row.querySelector('.col-index');
            if (idxCol) idxCol.textContent = index + 1;
        });

        // Trigger custom global events for views that need extra behavior (like unchecking checkboxes)
        document.dispatchEvent(new CustomEvent('datagrid.filtered', { detail: { filteredRows: window.filteredRows } }));

        currentPage = 1;
        updatePagination();
        updateURLFromFilters();
    };

    filterInputs.forEach(input => {
        const eventType = input.tagName === 'SELECT' ? 'change' : 'input';
        input.addEventListener(eventType, window.applyDatagridFilters);
    });

    if (btnResetFilters) {
        btnResetFilters.addEventListener('click', function() {
            filterInputs.forEach(input => {
                if (input.tagName === 'SELECT') input.value = 'all';
                else input.value = '';
            });
            updateSfFilter();
            window.applyDatagridFilters();
        });
    }

    // --- START ---
    applyFiltersFromURL();
    updateSfFilter();
    window.applyDatagridFilters();
});