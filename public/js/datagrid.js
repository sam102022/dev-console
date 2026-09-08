document.addEventListener('alpine:init', () => {
    // Initialisation des tooltips Bootstrap si disponibles
    if (typeof $ !== 'undefined' && $.fn.tooltip) {
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();
        });
    }

    Alpine.data('datagrid', (config) => ({
        pageName: config.pageName,
        currentPage: 1,
        rowsPerPage: 15,
        totalRows: 0,
        totalPages: 0,
        sortColumn: '',
        sortDirection: 'asc',
        filters: {},
        columns: {},
        rowsHtml: '',
        allowedSfs: null,
        originalSfOptions: [],
        checkedProjects: [],
        checkingAll: false,
        isChecking: false,
        abortController: null,

        init() {
            // Read initial columns prefs from window or fallback
            const defaultCols = {
                domain: true,
                sf: true,
                techno: true,
                archived: true,
                gcp: true,
                dev: true,
                rec: true,
                pp: true,
                prod: true
            };

            const savedPrefs = window.columnsPrefs || {};
            Object.keys(defaultCols).forEach(col => {
                this.columns[col] = savedPrefs[col] !== undefined ? savedPrefs[col] : defaultCols[col];
            });

            // Capture original SF options if dropdown exists
            const filterSf = document.getElementById('filter_sf');
            if (filterSf) {
                this.originalSfOptions = Array.from(filterSf.options).map(opt => ({
                    value: opt.value,
                    text: opt.textContent
                }));
            }

            // Load filters from URL / SessionStorage
            const params = new URLSearchParams(window.location.search);
            const defaultFilters = config.defaultFilters || {};
            
            const filterInputs = document.querySelectorAll('.filter-input');
            filterInputs.forEach(input => {
                const key = this.getFilterKey(input);
                let val = params.get(key) || sessionStorage.getItem('app_filter_' + this.pageName + '_' + key);
                if (val === null) {
                    if (key === 'archived' && this.pageName === 'monitoring') {
                        val = 'non';
                    } else {
                        val = defaultFilters[key] || (input.tagName === 'SELECT' ? 'all' : '');
                    }
                }
                this.filters[key] = val;
            });

            // Read pagination config if available
            const rowsPerPageSelect = document.getElementById('rows_per_page');
            if (rowsPerPageSelect) {
                this.rowsPerPage = parseInt(rowsPerPageSelect.value) || 15;
            }

            // Click delegation for New Relic links and Bootstrap tooltips
            document.addEventListener('click', (e) => {
                const link = e.target.closest('.new-relic-link');
                if (link) {
                    e.preventDefault();
                    const project = link.getAttribute('data-project');
                    const env = link.getAttribute('data-env');
                    this.openNewRelic(project, env, link);
                }
            });

            // Sync initial state and do first fetch
            this.syncFiltersToUI();
            this.fetchData();

            // Watch columns visibility to apply changes and save preferences
            this.$watch('columns', () => {
                this.applyColumnVisibility();
                this.saveColumnPrefs();
            });
        },

        getFilterKey(input) {
            return input.getAttribute('data-filter-column') || input.id.replace('filter_', '').replace('project_name', 'name').replace('springboot_version', 'springboot_version').replace('java_version', 'java_version').replace('mdm_version', 'mdm_version');
        },

        syncFiltersToUI() {
            const params = new URLSearchParams(window.location.search);
            Object.entries(this.filters).forEach(([key, val]) => {
                if (val !== 'all' && val !== '') {
                    params.set(key, val);
                    sessionStorage.setItem('app_filter_' + this.pageName + '_' + key, val);
                } else {
                    params.delete(key);
                    sessionStorage.removeItem('app_filter_' + this.pageName + '_' + key);
                }
            });
            const newRelativePathQuery = window.location.pathname + '?' + params.toString();
            history.replaceState(null, '', newRelativePathQuery);
        },

        async fetchData() {
            const params = new URLSearchParams();
            params.set('action', 'getDatagridRows');
            params.set('page', this.pageName);
            params.set('p', this.currentPage);
            params.set('rows_per_page', this.rowsPerPage);
            params.set('sort_column', this.sortColumn);
            params.set('sort_dir', this.sortDirection);

            Object.entries(this.filters).forEach(([key, val]) => {
                if (val !== 'all' && val !== '') {
                    params.set('filter_' + key, val);
                }
            });

            try {
                const response = await fetch('?' + params.toString());
                const data = await response.json();

                if (data.success) {
                    this.rowsHtml = data.html;
                    this.totalRows = data.totalRows;
                    this.totalPages = Math.ceil(this.totalRows / this.rowsPerPage);

                    if (data.allowedSfs) {
                        this.allowedSfs = data.allowedSfs;
                        this.updateSfFilterDropdown();
                    } else {
                        this.allowedSfs = null;
                    }

                    this.checkedProjects = [];
                    this.checkingAll = false;

                    this.$nextTick(() => {
                        this.applyColumnVisibility();
                        if (typeof $ !== 'undefined' && $.fn.tooltip) {
                            $('[data-toggle="tooltip"]').tooltip('dispose').tooltip();
                        }
                    });
                }
            } catch (error) {
                console.error('Erreur fetchData:', error);
            }
        },

        setPage(page) {
            if (page < 1 || page > this.totalPages) return;
            this.currentPage = page;
            this.fetchData();
        },

        getPagesToShow() {
            const pages = [];
            const delta = 2;
            const left = this.currentPage - delta;
            const right = this.currentPage + delta + 1;
            const range = [];
            const rangeWithDots = [];
            let l;

            for (let i = 1; i <= this.totalPages; i++) {
                if (i === 1 || i === this.totalPages || (i >= left && i < right)) {
                    range.push(i);
                }
            }

            for (const i of range) {
                if (l) {
                    if (i - l === 2) {
                        rangeWithDots.push(l + 1);
                    } else if (i - l !== 1) {
                        rangeWithDots.push('...');
                    }
                }
                rangeWithDots.push(i);
                l = i;
            }

            return rangeWithDots;
        },

        sortBy(col) {
            if (this.sortColumn === col) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = col;
                this.sortDirection = 'asc';
            }
            this.currentPage = 1;
            this.fetchData();
        },

        getSortIconClass(col) {
            if (this.sortColumn !== col) return 'fa-solid fa-sort sort-icon';
            return this.sortDirection === 'asc' ? 'fa-solid fa-sort-up sort-icon' : 'fa-solid fa-sort-down sort-icon';
        },

        getSortIconStyle(col) {
            return this.sortColumn === col ? 'opacity: 1;' : 'opacity: 0.5;';
        },

        onFilterChange() {
            this.currentPage = 1;
            this.syncFiltersToUI();
            this.fetchData();
        },

        resetFilters() {
            Object.keys(this.filters).forEach(key => {
                if (key === 'archived' && this.pageName === 'monitoring') {
                    this.filters[key] = 'non';
                } else {
                    const input = document.getElementById('filter_' + key) || document.getElementById('filter_project_' + key) || document.getElementById('filter_springboot_' + key) || document.getElementById('filter_springboot_version') || document.getElementById('filter_java_version') || document.getElementById('filter_mdm_version');
                    this.filters[key] = (input && input.tagName === 'SELECT') ? 'all' : '';
                }
            });
            this.currentPage = 1;
            this.syncFiltersToUI();
            this.fetchData();
        },

        updateSfFilterDropdown() {
            const filterSf = document.getElementById('filter_sf');
            if (!filterSf) return;

            const currentSelectedSf = this.filters.sf;

            filterSf.innerHTML = '';
            this.originalSfOptions.forEach(opt => {
                if (opt.value === 'all' || !this.allowedSfs || this.allowedSfs.includes(opt.value)) {
                    const newOpt = document.createElement('option');
                    newOpt.value = opt.value;
                    newOpt.textContent = opt.text;
                    filterSf.appendChild(newOpt);
                }
            });

            const hasSelected = Array.from(filterSf.options).some(opt => opt.value === currentSelectedSf);
            this.filters.sf = hasSelected ? currentSelectedSf : 'all';
        },

        applyColumnVisibility() {
            Object.entries(this.columns).forEach(([col, visible]) => {
                const cells = document.querySelectorAll(`[data-column="${col}"]`);
                cells.forEach(cell => {
                    cell.style.display = visible ? '' : 'none';
                });
            });
        },

        async saveColumnPrefs() {
            try {
                await fetch(`?page=${this.pageName}&action=saveColumnsPrefs`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ columns: this.columns })
                });
            } catch (error) {
                console.error('Erreur saveColumnPrefs:', error);
            }
        },

        toggleAllCheckboxes() {
            this.checkingAll = !this.checkingAll;
            const checkBoxes = document.querySelectorAll('.row-checkbox');
            this.checkedProjects = [];
            checkBoxes.forEach(cb => {
                cb.checked = this.checkingAll;
                if (this.checkingAll) {
                    const row = cb.closest('.project-row');
                    const projName = row.getAttribute('data-project-name');
                    if (projName) {
                        this.checkedProjects.push(projName);
                    }
                }
            });
        },

        updateCheckedProjects() {
            const checkBoxes = document.querySelectorAll('.row-checkbox:checked');
            this.checkedProjects = Array.from(checkBoxes).map(cb => {
                const row = cb.closest('.project-row');
                return row.getAttribute('data-project-name');
            }).filter(Boolean);
            
            const totalCheckBoxes = document.querySelectorAll('.row-checkbox').length;
            this.checkingAll = totalCheckBoxes > 0 && this.checkedProjects.length === totalCheckBoxes;
        },

        // --- HEALTH CHECK MONITORING ---
        async checkHealth(mode, specificProjectName = null) {
            let targetProjects = [];
            if (mode === 'single') {
                targetProjects = [specificProjectName];
            } else if (mode === 'selected') {
                targetProjects = [...this.checkedProjects];
            }

            if (targetProjects.length === 0) {
                alert("Aucun projet sélectionné.");
                return;
            }

            this.isChecking = true;
            this.abortController = new AbortController();
            const signal = this.abortController.signal;

            const environmentsToProcess = this.getVisibleEnvironments();

            // Set loading spinner state on badges
            targetProjects.forEach(projName => {
                environmentsToProcess.forEach(env => {
                    const cell = document.querySelector(`[data-project-name="${projName}"] .col-${env}`);
                    if (cell) {
                        const urlHealthCheck = cell.getAttribute('data-url-health');
                        const urlActuatorInfo = cell.getAttribute('data-url-actuator-info');

                        if (urlHealthCheck) {
                            const badge = cell.querySelector('.health-badge');
                            if (badge) {
                                badge.className = 'health-badge badge badge-warning';
                                badge.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                            }
                        }
                        if (urlActuatorInfo) {
                            const badge = cell.querySelector('.version-badge');
                            if (badge) {
                                badge.className = 'version-badge badge badge-warning';
                                badge.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                            }
                        }
                    }
                });
            });

            try {
                for (const projName of targetProjects) {
                    if (signal.aborted) break;

                    await Promise.all(
                        environmentsToProcess.map(env => this.checkEnvironment(projName, env, signal))
                    );
                }
            } catch (error) {
                if (error.name !== 'AbortError') console.error("Erreur inattendue:", error);
            } finally {
                this.isChecking = false;
                if (signal.aborted) {
                    this.resetPendingBadges(targetProjects, environmentsToProcess);
                }
                this.abortController = null;
            }
        },

        stopHealthCheck() {
            if (this.abortController) {
                this.abortController.abort();
            }
        },

        getVisibleEnvironments() {
            const visibleEnvs = [];
            ['dev', 'rec', 'pp', 'prod'].forEach(env => {
                if (this.columns[env] !== false) {
                    visibleEnvs.push(env);
                }
            });
            return visibleEnvs;
        },

        async checkEnvironment(projectName, env, signal) {
            const cell = document.querySelector(`[data-project-name="${projectName}"] .col-${env}`);
            if (!cell) return;

            const urlHealthCheck = cell.getAttribute('data-url-health');
            const urlActuatorInfo = cell.getAttribute('data-url-actuator-info');
            const healthBadge = cell.querySelector('.health-badge');
            const versionBadge = cell.querySelector('.version-badge');

            if (!urlHealthCheck && !urlActuatorInfo) {
                if (healthBadge) this.updateBadge(healthBadge, 'N/A', 'secondary', '#');
                if (versionBadge) this.updateVersionBadge(versionBadge, 'N/A', 'secondary', '#');
                return;
            }

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 20000);
            
            const combinedController = new AbortController();
            const onAbort = () => combinedController.abort();
            signal.addEventListener('abort', onAbort);
            controller.signal.addEventListener('abort', onAbort);

            try {
                const actionUrl = `?action=getMonitoringData&project=${encodeURIComponent(projectName)}&env=${encodeURIComponent(env)}`;
                const response = await fetch(actionUrl, { signal: combinedController.signal });
                clearTimeout(timeoutId);
                const data = await response.json();

                if (data.success) {
                    if (healthBadge) {
                        if (data.health.status === 'UP') {
                            this.updateBadge(healthBadge, '<i class="fa-solid fa-check"></i>', 'success', data.urls.healthCheckUrl);
                        } else {
                            this.updateBadge(healthBadge, '<i class="fa-regular fa-thumbs-down"></i>', 'danger', data.urls.healthCheckUrl);
                        }
                    }
                    if (versionBadge) {
                        this.updateVersionBadge(versionBadge, data.actuatorInfo.version, 'info', data.urls.actuatorInfoUrl);
                    }
                } else {
                    if (healthBadge) this.updateBadge(healthBadge, 'ERR', 'danger', urlHealthCheck || '#');
                    if (versionBadge) this.updateVersionBadge(versionBadge, 'ERR', 'danger', urlActuatorInfo || '#');
                }
            } catch (error) {
                clearTimeout(timeoutId);
                if (error.name === 'AbortError') {
                    if (controller.signal.aborted) {
                        if (healthBadge) this.updateBadge(healthBadge, 'T/O', 'warning', urlHealthCheck || '#');
                        if (versionBadge) this.updateVersionBadge(versionBadge, 'T/O', 'warning', urlActuatorInfo || '#');
                    } else {
                        throw error;
                    }
                } else {
                    if (healthBadge) this.updateBadge(healthBadge, 'ERR', 'danger', urlHealthCheck || '#');
                    if (versionBadge) this.updateVersionBadge(versionBadge, 'ERR', 'danger', urlActuatorInfo || '#');
                }
            } finally {
                signal.removeEventListener('abort', onAbort);
                controller.signal.removeEventListener('abort', onAbort);
            }
        },

        updateBadge(badge, text, type, url) {
            if (badge) {
                badge.className = `health-badge badge badge-${type}`;
                badge.innerHTML = `<a href="${url}" target="_blank" class="text-white text-decoration-none" title="${url}">${text}</a>`;
            }
        },

        updateVersionBadge(badge, text, type, url) {
            if (badge) {
                badge.className = `version-badge badge badge-${type}`;
                badge.innerHTML = `<a href="${url}" target="_blank" class="text-white text-decoration-none" title="${url}">${text}</a>`;
            }
        },

        resetPendingBadges(targetProjects, environmentsToProcess) {
            targetProjects.forEach(projName => {
                environmentsToProcess.forEach(env => {
                    const cell = document.querySelector(`[data-project-name="${projName}"] .col-${env}`);
                    if (cell) {
                        const healthBadge = cell.querySelector('.health-badge');
                        const versionBadge = cell.querySelector('.version-badge');
                        if (healthBadge && healthBadge.innerHTML.includes('spinner-border')) {
                            healthBadge.className = 'health-badge badge badge-secondary';
                            healthBadge.innerHTML = '<i class="fa-solid fa-ban"></i>';
                        }
                        if (versionBadge && versionBadge.innerHTML.includes('spinner-border')) {
                            versionBadge.className = 'version-badge badge badge-secondary';
                            versionBadge.innerHTML = '<i class="fa-solid fa-ban"></i>';
                        }
                    }
                });
            });
        },

        async openNewRelic(project, env, link) {
            const icon = link.querySelector('i');
            if (icon) {
                icon.className = 'fa-solid fa-spinner fa-spin';
            }

            try {
                const response = await fetch(`?action=get_new_relic_url&project=${encodeURIComponent(project)}&env=${encodeURIComponent(env)}`);
                const data = await response.json();

                if (data.url) {
                    window.open(data.url, '_blank');
                } else {
                    alert('Erreur: Impossible de récupérer l\'URL New Relic.');
                }
            } catch (error) {
                console.error('Erreur New Relic:', error);
                alert('Une erreur est survenue.');
            } finally {
                if (icon) {
                    icon.className = 'fa-solid fa-chart-line text-info';
                }
            }
        }
    }));
});

window.checkSingleRow = function(btn) {
    const row = btn.closest('.project-row');
    const projectName = row.getAttribute('data-project-name');
    const gridEl = document.getElementById('projects-card');
    if (gridEl && typeof Alpine !== 'undefined') {
        const data = Alpine.$data(gridEl);
        if (data && data.checkHealth) {
            data.checkHealth('single', projectName);
        }
    }
};
