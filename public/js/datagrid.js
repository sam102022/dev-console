document.addEventListener('DOMContentLoaded', function() {
    // Initialisation des tooltips Bootstrap si disponibles
    if (typeof $ !== 'undefined' && $.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
    }

    const tbody = document.getElementById('projects-tbody');
    if (!tbody) return;

    const filterInputs = document.querySelectorAll('.filter-input');
    const filterDomain = document.getElementById('filter_domain');
    const filterSf = document.getElementById('filter_sf');
    const btnResetFilters = document.getElementById('btn-reset-filters');
    
    // --- GESTION DES COLONNES ---
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

        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page') || 'monitoring';

        try {
            await fetch(`?page=${page}&action=saveColumnsPrefs`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ columns: prefs })
            });
        } catch (error) {
            console.error('Erreur lors de la sauvegarde des prefs de colonnes:', error);
        }
    }

    if (columnToggles.length > 0) {
        if (typeof window.columnsPrefs === 'undefined') {
            window.columnsPrefs = {};
        }

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

    function updateSfFilterDropdown(allowedSfs) {
        if (!filterSf) return;
        
        const currentSelectedSf = filterSf.value;

        filterSf.innerHTML = '';
        originalSfOptions.forEach(opt => {
            if (opt.value === 'all' || !allowedSfs || allowedSfs.includes(opt.value)) {
                const newOpt = document.createElement('option');
                newOpt.value = opt.value;
                newOpt.textContent = opt.text;
                filterSf.appendChild(newOpt);
            }
        });

        const hasSelected = Array.from(filterSf.options).some(opt => opt.value === currentSelectedSf);
        filterSf.value = hasSelected ? currentSelectedSf : 'all';
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

    // --- PAGINATION & AJAX ---
    const rowsPerPageSelect = document.getElementById('rows_per_page');
    const paginationContainer = document.getElementById('pagination-container');
    const paginationInfo = document.getElementById('pagination-info');

    let currentPage = 1;
    let rowsPerPage = rowsPerPageSelect ? parseInt(rowsPerPageSelect.value) : 15;
    let totalRows = 0;

    async function fetchData() {
        const urlParams = new URLSearchParams(window.location.search);
        const pageName = urlParams.get('page') || 'index';

        const params = new URLSearchParams();
        params.set('action', 'getDatagridRows');
        params.set('page', pageName);
        params.set('p', currentPage);
        params.set('rows_per_page', rowsPerPage);
        params.set('sort_column', currentSortColumn);
        params.set('sort_dir', currentSortDirection);

        filterInputs.forEach(input => {
            const key = getFilterKey(input);
            const val = input.value;
            if (val !== 'all' && val !== '') {
                params.set('filter_' + key, val);
            }
        });

        try {
            const response = await fetch('?' + params.toString());
            const data = await response.json();

            if (data.success) {
                tbody.innerHTML = data.html;
                totalRows = data.totalRows;

                if (data.allowedSfs) {
                    updateSfFilterDropdown(data.allowedSfs);
                }

                updatePaginationControls();

                // Réappliquer la visibilité des colonnes pour le nouveau DOM
                columnToggles.forEach(toggle => setColumnVisibility(toggle.dataset.column, toggle.checked));

                // Déclencher un événement global pour d'autres scripts (comme la sélection globale)
                document.dispatchEvent(new CustomEvent('datagrid.filtered', { detail: { totalRows: totalRows } }));
            }
        } catch (error) {
            console.error('Erreur fetchData datagrid:', error);
        }
    }

    function updatePaginationControls() {
        if (!paginationContainer || !paginationInfo) return;

        const totalPages = Math.ceil(totalRows / rowsPerPage);

        if (currentPage < 1) currentPage = 1;
        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;

        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;

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
                    fetchData();
                }
            }
        });
    }

    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', function() {
            rowsPerPage = parseInt(this.value);
            currentPage = 1;
            fetchData();
        });
    }

    // --- TRI ---
    let currentSortColumn = '';
    let currentSortDirection = 'asc';
    const sortHeaders = document.querySelectorAll('.sortable-header');

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

            currentPage = 1;
            fetchData();
        });
    });

    // --- FILTRES ---
    let filterDebounceTimeout = null;

    window.applyDatagridFilters = function() {
        currentPage = 1;
        updateURLFromFilters();
        fetchData();
    };

    filterInputs.forEach(input => {
        const eventType = input.tagName === 'SELECT' ? 'change' : 'input';
        
        input.addEventListener(eventType, function() {
            if (eventType === 'input') {
                clearTimeout(filterDebounceTimeout);
                filterDebounceTimeout = setTimeout(() => {
                    window.applyDatagridFilters();
                }, 300); // 300ms debounce
            } else {
                window.applyDatagridFilters();
            }
        });
    });

    if (btnResetFilters) {
        btnResetFilters.addEventListener('click', function() {
            filterInputs.forEach(input => {
                if (input.tagName === 'SELECT') input.value = 'all';
                else input.value = '';
            });
            window.applyDatagridFilters();
        });
    }

    // --- START ---
    applyFiltersFromURL();
    window.applyDatagridFilters();
});