window.initDashboard = async function() {
    let stateChartInstance = null;
    let doughnutChartInstance = null;

    // Biến cho phân trang Compliance Logs
    let currentLogPage = 1;
    const logsPerPage = 5;

    // DOM Elements
    const filterState = document.getElementById('db-filter-state');
    const filterNotary = document.getElementById('db-filter-notary');
    const filterDate = document.getElementById('db-filter-date');
    const btnClear = document.getElementById('db-btn-clear');

    // ==========================================
    // 1. TẢI DỮ LIỆU KPI & BIỂU ĐỒ (CÓ BỘ LỌC)
    // ==========================================
    async function loadDashboardData() {
        const params = {};
        
        // Thu thập bộ lọc
        if (filterState && filterState.value) params.venue_state = filterState.value;
        if (filterNotary && filterNotary.value) params.notary_id = filterNotary.value;
        if (filterDate && filterDate.value) {
            const daysToSubtract = parseInt(filterDate.value);
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(endDate.getDate() - daysToSubtract);
            params.start_date = startDate.toISOString().split('T')[0];
            params.end_date = endDate.toISOString().split('T')[0];
        }

        // Truyền params vào API
        const response = await window.dashboardApi.getKpiSummary(params);
        if (!response || !response.success) return;

        const resData = response.data;
        const kpi = resData.kpi;
        const charts = resData.charts;

        // Render KPI Numbers
        document.getElementById('kpi-total').innerText = kpi.total_entries.toLocaleString();
        document.getElementById('kpi-incomplete').innerText = kpi.incomplete.toLocaleString();
        if (document.getElementById('kpi-active')) {
            document.getElementById('kpi-active').innerText = kpi.active_notaries.toLocaleString();
        }

        let compRate = 100;
        if (kpi.total_entries > 0) compRate = ((kpi.total_entries - kpi.incomplete) / kpi.total_entries) * 100;
        document.getElementById('kpi-rate').innerText = compRate.toFixed(1) + '%';

        // Render Trends
        renderTrendBadge('trend-total', kpi.trends.total);
        renderTrendBadge('trend-incomplete', kpi.trends.incomplete, true); 
        renderTrendBadge('trend-active', kpi.trends.active);

        // Render Dropdowns (Chỉ render lần đầu)
        if (filterState.options.length <= 1) {
            resData.filters_data.states.forEach(state => {
                if(state) filterState.insertAdjacentHTML('beforeend', `<option value="${state}">${state}</option>`);
            });
            resData.filters_data.notaries.forEach(notary => {
                filterNotary.insertAdjacentHTML('beforeend', `<option value="${notary.id}">${notary.full_name}</option>`);
            });
        }

        // Render Alerts
        document.getElementById('msg-signatures').innerText = `${resData.alerts.missing_signatures} entries are missing electronic signer signatures.`;
        document.getElementById('msg-thumbprints').innerText = `${resData.alerts.missing_thumbprints} entries require mandatory thumbprint verification.`;

        // Render Charts
        renderStateChart(charts.by_state);
        renderDoughnutChart(charts.doughnut, kpi.total_entries);
    }

    function renderTrendBadge(id, text, reverseColor = false) {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerText = text;
        const isPositive = text.includes('+');
        el.className = 'stat-badge'; 
        if ((isPositive && !reverseColor) || (!isPositive && reverseColor)) {
            el.classList.add('badge-green');
        } else {
            el.classList.add('badge-red');
        }
    }

    // ==========================================
    // 2. VẼ BIỂU ĐỒ (CHART.JS)
    // ==========================================
    function renderStateChart(stateData) {
        const ctx = document.getElementById('stateBarChart').getContext('2d');
        if (stateChartInstance) stateChartInstance.destroy(); 

        const labels = stateData.map(d => d.venue_state || 'Unknown');
        const dataValues = stateData.map(d => d.count);

        stateChartInstance = new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: 'Entries', data: dataValues, backgroundColor: '#6366f1', borderRadius: 4, barThickness: 30 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { borderDash: [4, 4] } }, x: { grid: { display: false } } } }
        });
    }

    function renderDoughnutChart(doughnutData, totalEntries) {
        const ctx = document.getElementById('statusDoughnutChart').getContext('2d');
        if (doughnutChartInstance) doughnutChartInstance.destroy();

        const labels = doughnutData.map(d => d.status ? d.status.toUpperCase() : 'UNKNOWN');
        const dataValues = doughnutData.map(d => d.count);
        const bgColors = labels.map(lbl => {
            if (lbl === 'COMPLETED') return '#10b981'; 
            if (lbl === 'DRAFT') return '#f59e0b'; 
            return '#ef4444'; 
        });

        doughnutChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: dataValues, backgroundColor: bgColors, borderWidth: 0, cutout: '70%' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const legendDiv = document.getElementById('doughnut-legend');
        legendDiv.innerHTML = '';
        labels.forEach((label, index) => {
            const count = dataValues[index];
            const pct = totalEntries > 0 ? Math.round((count / totalEntries) * 100) : 0;
            legendDiv.insertAdjacentHTML('beforeend', `<div class="legend-item"><span class="dot" style="background-color: ${bgColors[index]}"></span>${label}<small>${pct}% (${count})</small></div>`);
        });
    }

    // ==========================================
    // 3. TẢI COMPLIANCE LOGS & XỬ LÝ PHÂN TRANG (CÓ SỐ TRANG, KHÔNG VIỀN)
    // ==========================================
    async function loadComplianceLogs() {
        const params = { page: currentLogPage, limit: logsPerPage };
        const response = await window.dashboardApi.getComplianceLogs(params);
        
        const tbody = document.getElementById('compliance-tbody');
        const infoText = document.getElementById('table-info');
        const paginationContainer = document.querySelector('.table-pagination'); 

        if (!tbody || !paginationContainer) return;
        tbody.innerHTML = ''; 

        if (response && response.success && response.data.logs.length > 0) {
            response.data.logs.forEach(log => {
                const isError = log.flags === 'ERROR' || log.flags === 'WARNING';
                const statusClass = isError ? 'pill-missing' : 'pill-compliant';
                const formattedDate = new Date(log.timestamp).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const initials = log.notary ? log.notary.substring(0,2).toUpperCase() : 'SY';

                tbody.insertAdjacentHTML('beforeend', `
                    <tr>
                        <td><strong>...${log.id.substring(log.id.length - 6)}</strong></td>
                        <td><div class="notary-cell"><div class="avatar av-blue">${initials}</div>${log.notary || 'SYSTEM'}</div></td>
                        <td>${log.journal_id || 'N/A'}</td>
                        <td>${formattedDate}</td>
                        <td>${log.action}</td>
                        <td><span class="status-pill ${statusClass}">${log.flags || 'INFO'}</span></td>
                    </tr>
                `);
            });

            const meta = response.meta;
            const startItem = meta.total === 0 ? 0 : (meta.page - 1) * meta.limit + 1;
            const endItem = Math.min(meta.page * meta.limit, meta.total);
            infoText.innerText = `Showing ${startItem} to ${endItem} of ${meta.total} logs`;

            // THUẬT TOÁN TÍNH TOÁN CÁC SỐ TRANG
            const totalPages = meta.total_pages;
            let pages = [];
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= meta.page - 1 && i <= meta.page + 1)) {
                    pages.push(i);
                } else if (i === meta.page - 2 || i === meta.page + 2) {
                    pages.push('...');
                }
            }
            pages = pages.filter((item, pos, ary) => !pos || item !== ary[pos - 1]);

            // CSS CHUNG CHO TẤT CẢ CÁC NÚT BẤM (ĐÃ LOẠI BỎ VIỀN)
            // Thêm padding, border-radius và cursor để nút nhìn chuyên nghiệp hơn
            const commonButtonStyle = 'border: none; outline: none; background: transparent; cursor: pointer; padding: 0.4rem 0.8rem; border-radius: 4px;';

            // VẼ HTML CHO THANH PHÂN TRANG
            let html = `<button id="btn-prev" style="${commonButtonStyle} color: var(--text-main);" ${!meta.has_prev ? 'disabled' : ''}>&lt;</button>`;
            
            pages.forEach(p => {
                if (p === '...') {
                    html += `<span style="padding: 0.4rem 0.2rem; color: var(--text-muted);">...</span>`;
                } else {
                    const isActive = p === meta.page;
                    // Nếu là trang hiện tại thì bôi màu xanh, nếu không thì lấy màu chữ mặc định
                    const activeStyle = isActive ? 'background: var(--primary); color: white;' : 'color: var(--text-main);';
                    
                    html += `<button class="page-num" data-page="${p}" style="${commonButtonStyle}${activeStyle}">${p}</button>`;
                }
            });
            
            html += `<button id="btn-next" style="${commonButtonStyle} color: var(--text-main);" ${!meta.has_next ? 'disabled' : ''}>&gt;</button>`;
            
            paginationContainer.innerHTML = html;

            // GẮN SỰ KIỆN CLICK CHO TẤT CẢ CÁC NÚT
            document.getElementById('btn-prev').addEventListener('click', () => {
                if (meta.has_prev) { currentLogPage--; loadComplianceLogs(); }
            });
            
            document.getElementById('btn-next').addEventListener('click', () => {
                if (meta.has_next) { currentLogPage++; loadComplianceLogs(); }
            });
            
            document.querySelectorAll('.page-num').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    currentLogPage = parseInt(e.target.getAttribute('data-page'));
                    loadComplianceLogs();
                });
            });

        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No logs found.</td></tr>';
            infoText.innerText = '';
            paginationContainer.innerHTML = '';
        }
    }

    // ==========================================
    // 4. KÍCH HOẠT SỰ KIỆN BỘ LỌC
    // ==========================================
    const filters = [filterState, filterNotary, filterDate];
    filters.forEach(filter => {
        if (filter) {
            filter.addEventListener('change', loadDashboardData);
        }
    });

    if (btnClear) {
        btnClear.addEventListener('click', (e) => {
            e.preventDefault();
            filterState.value = '';
            filterNotary.value = '';
            filterDate.value = '30';
            loadDashboardData();
        });
    }

    // Khởi chạy
    loadDashboardData();
    loadComplianceLogs();
};