var invoizData_53410733c6 = JSON.parse(document.currentScript.dataset.config);

    function dashboardCharts() {
        return {
            selectedStatus: null,
            selectedStatusCount: null,
            statusLink: '#',
            selectedTrendDate: null,
            selectedTrendDetail: null,
            trendLink: '#',
            selectedCenter: null,
            selectedCenterDetail: null,
            centerLink: '#',
            showStatusModal: false,
            showTrendsModal: false,
            showCenterModal: false,
            trendsRange: invoizData_53410733c6[0],
            trendsRangeLabel: invoizData_53410733c6[1],
            statusChartInstance: null,
            trendsChartInstance: null,
            centerChartInstance: null,
            statusModalInstance: null,
            trendsModalInstance: null,
            centerModalInstance: null,
            initCharts() {
                Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
                Chart.defaults.font.size = 13;
                Chart.defaults.animation.duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250;
                this.$nextTick(() => this.renderCharts());
                this._themeObserver = new MutationObserver(() => this.renderCharts());
                this._themeObserver.observe(document.documentElement, {attributes:true, attributeFilter:['class']});
            },
            focusDialog(name) {
                this._dialogOpener = document.activeElement;
                this._previousOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                this.$nextTick(() => requestAnimationFrame(() => document.querySelector('[data-dialog="' + name + '"] [data-dialog-close]')?.focus()));
            },
            restoreDialogFocus() {
                document.body.style.overflow = this._previousOverflow || '';
                this._dialogOpener?.focus();
            },
            trapDialog(event) {
                const controls = [...event.currentTarget.querySelectorAll('button:not([disabled]), a[href], [tabindex="0"]')].filter(el => el.getClientRects().length);
                const first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
            },
            destroy() {
                this._themeObserver?.disconnect();
                ['statusChartInstance','trendsChartInstance','centerChartInstance','statusModalInstance','trendsModalInstance','centerModalInstance'].forEach(key => this.destroyChart(key));
            },
            destroyChart(key) {
                const chart = Alpine.raw(this[key]);
                if (chart) chart.destroy();
                this[key] = null;
            },
            chartTone(property) {
                return getComputedStyle(document.querySelector('.logistics-dashboard')).getPropertyValue(property).trim();
            },
            wrapChartLabel(label) {
                const lines = [''];
                label.split(' ').forEach(word => {
                    const i = lines.length - 1;
                    if (lines[i] && (lines[i] + ' ' + word).length > 20) lines.push(word);
                    else lines[i] += (lines[i] ? ' ' : '') + word;
                });
                return lines;
            },
            themeSeries(data) {
                if (document.documentElement.classList.contains('dark')) data.colors = {
                    created:this.chartTone('--ld-accent'), delivered:this.chartTone('--ld-positive'), failed:this.chartTone('--ld-danger')
                };
                return data;
            },
            renderCharts() {
                const theme = getComputedStyle(document.querySelector('.logistics-dashboard'));
                Chart.defaults.color = theme.getPropertyValue('--ld-muted').trim();
                Chart.defaults.borderColor = theme.getPropertyValue('--ld-rule').trim();
                // Status doughnut
                const statusEl = document.getElementById('statusChart');
                if (statusEl) {
                    const statusData = invoizData_53410733c6[2];
                    if (statusData.total > 0 && statusData.labels.length) {
                        this.destroyChart('statusChartInstance');
                        this.statusChartInstance = new Chart(statusEl, {
                            type: 'doughnut',
                            data: {
                                labels: statusData.labels,
                                datasets: [{
                                    data: statusData.data,
                                    backgroundColor: statusData.colors,
                                    borderWidth: 2,
                                    borderColor: '#ffffff',
                                    hoverOffset: 4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '64%',
                                layout: { padding: { top: 4, right: 4, bottom: 4, left: 4 } },
                                animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250 },
                                onClick: (e, els) => {
                                    if (els.length) {
                                        const idx = els[0].index;
                                        const label = statusData.labels[idx];
                                        const count = statusData.data[idx];
                                        this.selectedStatus = label;
                                        this.selectedStatusCount = count;
                                        const mapEl = document.getElementById('statusKeyMap');
                                        const map = mapEl ? JSON.parse(mapEl.dataset.map || '{}') : {};
                                        const key = map[label] || '';
                                        const params = new URLSearchParams(invoizData_53410733c6[3]);
                                        if (key) params.set('status', key);
                                        this.statusLink = invoizData_53410733c6[4] + '?' + params.toString();
                                    }
                                },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: '#0E4A57',
                                        titleFont: { size: 13 },
                                        bodyFont: { size: 13 },
                                        padding: 8,
                                        cornerRadius: 8,
                                        callbacks: {
                                            label: (ctx) => {
                                                const label = ctx.label || '';
                                                const value = ctx.parsed || 0;
                                                const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                                                const pct = total ? Math.round(value/total*100) : 0;
                                                return `${label}: ${value} (${pct}%)`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }
                }
                // Trends line
                const trendsEl = document.getElementById('trendsChart');
                if (trendsEl) {
                    const trends = this.themeSeries(invoizData_53410733c6[5]);
                    const hasData = trends && (trends.created.reduce((a,b)=>a+b,0) + trends.delivered.reduce((a,b)=>a+b,0) + trends.failed.reduce((a,b)=>a+b,0) > 0);
                    if (hasData) {
                        const is30 = trends.labels.length > 7;
                        this.destroyChart('trendsChartInstance');
                        this.trendsChartInstance = new Chart(trendsEl, {
                            type: 'line',
                            data: {
                                labels: trends.labels,
                                datasets: [
                                    { label: 'Created', data: trends.created, borderColor: trends.colors.created, backgroundColor: trends.colors.created + '1A', borderWidth: 1.8, tension: 0.35, fill: false, pointRadius: 2, pointHoverRadius: 4, pointBackgroundColor: trends.colors.created },
                                    { label: 'Delivered', borderDash:[6,4], pointStyle:'triangle', data: trends.delivered, borderColor: trends.colors.delivered, backgroundColor: trends.colors.delivered + '1A', borderWidth: 1.8, tension: 0.35, fill: false, pointRadius: 2, pointHoverRadius: 4, pointBackgroundColor: trends.colors.delivered },
                                    { label: 'Failed', borderDash:[2,4], pointStyle:'rect', data: trends.failed, borderColor: trends.colors.failed, backgroundColor: 'transparent', borderWidth: 1.8, tension: 0.35, fill: false, pointRadius: 2, pointHoverRadius: 4, pointBackgroundColor: trends.colors.failed }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                layout: { padding: { top: 4, right: 8, bottom: 0, left: 4 } },
                                animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250 },
                                onClick: (e, els) => {
                                    if (els.length) {
                                        const idx = els[0].index;
                                        const label = trends.labels[idx];
                                        const created = trends.created[idx] || 0;
                                        const delivered = trends.delivered[idx] || 0;
                                        const failed = trends.failed[idx] || 0;
                                        this.selectedTrendDate = label;
                                        this.selectedTrendDetail = `${created} created · ${delivered} delivered · ${failed} failed`;
                                        const dateStr = trends.dates ? trends.dates[idx] : '';
                                        this.trendLink = dateStr ? `${invoizData_53410733c6[6]}?date_from=${dateStr}&date_to=${dateStr}` : `${invoizData_53410733c6[7]}`;
                                    }
                                },
                                scales: {
                                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 13 }, color: this.chartTone('--ld-muted'), padding: 6 }, grid: { color: this.chartTone('--ld-rule') }, border: { display: false } },
                                    x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 13 }, color: this.chartTone('--ld-muted'), maxRotation: 0, autoSkip: true, maxTicksLimit: is30 ? 8 : 7, padding: 6 } }
                                },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: { backgroundColor: '#0E4A57', titleFont: { size: 13 }, bodyFont: { size: 13 }, padding: 8, cornerRadius: 8, mode: 'index', intersect: false }
                                }
                            }
                        });
                    }
                }
                // Center bar
                const centerEl = document.getElementById('centerChart');
                if (centerEl) {
                    const center = invoizData_53410733c6[8];
                    if (!center.empty && center.labels.length) {
                        const isHorizontal = true;
                        const displayLabels = center.labels.map(label => this.wrapChartLabel(label));
                        const baseOptions = {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            layout: { padding: { top: 4, right: 8, bottom: 4, left: 4 } },
                            animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250 },
                            onClick: (e, els) => {
                                if (els.length) {
                                    const idx = els[0].index;
                                    const label = center.labels[idx];
                                    const total = center.total[idx] || 0;
                                    const delivered = center.delivered[idx] || 0;
                                    this.selectedCenter = label;
                                    this.selectedCenterDetail = `${total} total · ${delivered} delivered`;
                                    this.centerLink = `${invoizData_53410733c6[9]}` + (label ? `?search=${encodeURIComponent(label)}` : '');
                                }
                            },
                            plugins: {
                                legend: { position: 'bottom', labels: { usePointStyle: true, pointStyleWidth: 8, padding: 14, font: { size: 13 }, color: this.chartTone('--ld-muted'), boxPadding: 4 } },
                                tooltip: {
                                    backgroundColor: '#0E4A57',
                                    titleFont: { size: 13 },
                                    bodyFont: { size: 13 },
                                    padding: 8,
                                    cornerRadius: 8,
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: { title: (items) => { const idx = items[0]?.dataIndex; return center.labels[idx] || ''; } }
                                }
                            }
                        };
                        const verticalScales = {
                            y: { beginAtZero: true, ticks: { precision: 0, font: { size: 13 }, color: this.chartTone('--ld-muted'), padding: 6 }, grid: { color: this.chartTone('--ld-rule') }, border: { display: false } },
                            x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 13 }, color: this.chartTone('--ld-muted'), maxRotation: 32, autoSkip: true, maxTicksLimit: 6, padding: 6, callback: function(v){ const l=this.getLabelForValue(v); return l.length>12?l.slice(0,12)+'…':l; } } }
                        };
                        const horizontalScales = {
                            x: { beginAtZero: true, ticks: { precision: 0, font: { size: 13 }, color: this.chartTone('--ld-muted'), padding: 6 }, grid: { color: this.chartTone('--ld-rule') }, border: { display: false } },
                            y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 13 }, color: this.chartTone('--ld-muted'), padding: 6 } }
                        };
                        this.destroyChart('centerChartInstance');
                        this.centerChartInstance = new Chart(centerEl, {
                            type: 'bar',
                            data: {
                                labels: displayLabels,
                                datasets: [
                                    { label: 'Total', data: center.total, backgroundColor: '#16697A', borderRadius: 5, barPercentage: 0.48, categoryPercentage: 0.58, borderSkipped: false },
                                    { label: 'Delivered', data: center.delivered, backgroundColor: '#287660', borderRadius: 5, barPercentage: 0.48, categoryPercentage: 0.58, borderSkipped: false },
                                    { label: 'Pending', data: center.pending, backgroundColor: '#8B620A', borderRadius: 5, barPercentage: 0.48, categoryPercentage: 0.58, borderSkipped: false },
                                    { label: 'Failed', data: center.failed, backgroundColor: '#A2373A', borderRadius: 5, barPercentage: 0.48, categoryPercentage: 0.58, borderSkipped: false }
                                ]
                            },
                            options: Object.assign({}, baseOptions, { indexAxis: isHorizontal ? 'y' : 'x', scales: isHorizontal ? horizontalScales : verticalScales })
                        });
                    }
                }
            }
            ,
            openStatusModal() {
                this.showStatusModal = true;
                this.focusDialog('status');
                this.$nextTick(() => {
                    const el = document.getElementById('statusChartModal');
                    if (!el) return;
                    const statusData = invoizData_53410733c6[10];
                    if (statusData.total === 0) return;
                    this.destroyChart('statusModalInstance');
                    this.statusModalInstance = new Chart(el, {
                        type: 'doughnut',
                        data: { labels: statusData.labels, datasets: [{ data: statusData.data, backgroundColor: statusData.colors, borderWidth: 2, borderColor: '#ffffff', hoverOffset: 6 }] },
                        options: { responsive: true, maintainAspectRatio: false, cutout: '58%', layout: { padding: 8 }, animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250 }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 13 } } }, tooltip: { backgroundColor: '#0E4A57', padding: 8, cornerRadius: 8 } } }
                    });
                });
            },
            closeStatusModal() { this.showStatusModal = false; this.restoreDialogFocus(); },
            openTrendsModal() {
                this.showTrendsModal = true;
                this.focusDialog('trends');
                this.$nextTick(() => {
                    const el = document.getElementById('trendsChartModal');
                    if (!el) return;
                    const trends = this.themeSeries(invoizData_53410733c6[11]);
                    const hasData = trends && (trends.created.reduce((a,b)=>a+b,0) + trends.delivered.reduce((a,b)=>a+b,0) + trends.failed.reduce((a,b)=>a+b,0) > 0);
                    if (!hasData) return;
                    this.destroyChart('trendsModalInstance');
                    this.trendsModalInstance = new Chart(el, {
                        type: 'line',
                        data: {
                            labels: trends.labels,
                            datasets: [
                                { label: 'Created', data: trends.created, borderColor: trends.colors.created, backgroundColor: trends.colors.created + '1A', borderWidth: 2, tension: 0.3, fill: false, pointRadius: 3, pointHoverRadius: 5 },
                                { label: 'Delivered', borderDash:[6,4], pointStyle:'triangle', data: trends.delivered, borderColor: trends.colors.delivered, backgroundColor: trends.colors.delivered + '1A', borderWidth: 2, tension: 0.3, fill: false, pointRadius: 3, pointHoverRadius: 5 },
                                { label: 'Failed', borderDash:[2,4], pointStyle:'rect', data: trends.failed, borderColor: trends.colors.failed, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3, fill: false, pointRadius: 3, pointHoverRadius: 5 }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, layout: { padding: 8 }, animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250 }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }, plugins: { legend: { position: 'bottom' } } }
                    });
                });
            },
            closeTrendsModal() { this.showTrendsModal = false; this.restoreDialogFocus(); },
            openCenterModal() {
                this.showCenterModal = true;
                this.focusDialog('center');
                this.$nextTick(() => {
                    const el = document.getElementById('centerChartModal');
                    if (!el) return;
                    const center = invoizData_53410733c6[12];
                    if (center.empty) return;
                    this.destroyChart('centerModalInstance');
                    const displayLabels = center.labels.map(label => this.wrapChartLabel(label));
                    this.centerModalInstance = new Chart(el, {
                        type: 'bar',
                        data: {
                            labels: displayLabels,
                            datasets: [
                                { label: 'Total', data: center.total, backgroundColor: '#16697A', borderRadius: 5 },
                                { label: 'Delivered', data: center.delivered, backgroundColor: '#287660', borderRadius: 5 },
                                { label: 'Pending', data: center.pending, backgroundColor: '#8B620A', borderRadius: 5 },
                                { label: 'Failed', data: center.failed, backgroundColor: '#A2373A', borderRadius: 5 }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', layout: { padding: 8 }, animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250 }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }, plugins: { legend: { position: 'bottom' } } }
                    });
                });
            },
            closeCenterModal() { this.showCenterModal = false; this.restoreDialogFocus(); },
            handleStatusClick(e) {
                // Handled via onClick in chart options
            }
        }
    }
    function detailedRecords() {
        return {
            expanded: false,
            search: '',
            statusFilter: '',
            centerFilter: '',
            sortBy: 'updated_desc',
            page: 1,
            perPage: 5,
            all: invoizData_53410733c6[13],
            filtered: [],
            paginated: [],
            totalPages: 1,
            statusOptions: invoizData_53410733c6[14],
            centerOptions: invoizData_53410733c6[15],
            init() {
                this.all = this.all.map(row => ({...row,
                    recipient: !row.recipient || row.recipient === '???' ? 'Not provided' : row.recipient,
                    tracking: row.tracking || 'Delivery #' + row.id,
                    center: row.center || 'Unassigned',
                    updated: row.updated || 'Not recorded'
                }));
                const centers = Array.from(new Set([...this.centerOptions.map(c=>c.value), ...this.all.map(r=>r.center)].filter(Boolean)));
                this.centerOptions = centers.map(c=>({value:c,label:c}));
                this.applyFilters();
            },
            applyFilters() {
                let rows = this.all.slice();
                const q = this.search.toLowerCase().trim();
                if (q) {
                    rows = rows.filter(r => (r.tracking + ' ' + r.recipient + ' ' + r.center).toLowerCase().includes(q));
                }
                if (this.statusFilter) {
                    rows = rows.filter(r => r.statusRaw === this.statusFilter);
                }
                if (this.centerFilter) {
                    rows = rows.filter(r => r.center === this.centerFilter);
                }
                rows.sort((a,b) => {
                    if (this.sortBy === 'updated_desc') return new Date(b.updated_at) - new Date(a.updated_at);
                    if (this.sortBy === 'updated_asc') return new Date(a.updated_at) - new Date(b.updated_at);
                    if (this.sortBy === 'tracking_asc') return a.tracking.localeCompare(b.tracking);
                    if (this.sortBy === 'status_asc') return a.status.localeCompare(b.status);
                    return 0;
                });
                this.filtered = rows;
                this.totalPages = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
                if (this.page > this.totalPages) this.page = this.totalPages;
                this.paginate();
            },
            paginate() {
                const start = (this.page - 1) * this.perPage;
                this.paginated = this.filtered.slice(start, start + this.perPage);
            },
            prev() { if (this.page > 1) { this.page--; this.paginate(); } },
            next() { if (this.page < this.totalPages) { this.page++; this.paginate(); } },
            clearFilters() { this.search=''; this.statusFilter=''; this.centerFilter=''; this.sortBy='updated_desc'; this.page=1; this.applyFilters(); },
            statusClass(raw) {
                // Mini-pill hues mirror the canonical status badge so the same
                // status reads identically in both places.
                if (raw === 'delivered') return 'ld-status--delivered';
                if (['delivery_failed', 'lost', 'damaged'].includes(raw)) return 'ld-status--failed';
                if (raw === 'waiting_for_rider') return 'ld-status--pending';
                if (['cancelled', 'returned_to_seller'].includes(raw)) return 'ld-status--neutral';
                return '';
            }
        }
    }
