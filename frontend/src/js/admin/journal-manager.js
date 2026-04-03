window.initJournalManager = async function() {
    // 1. Khai báo các biến trạng thái
    let currentPage = 1;
    let rowsPerPage = 5; // Số dòng trên 1 trang (limit)
    let searchTimeout = null; 

    let currentTableData = [];

    // 2. DOM Elements
    const tbody = document.getElementById('jm-tbody');
    const infoText = document.getElementById('jm-table-info');
    
    // Lấy thẻ chứa toàn bộ cụm phân trang
    const paginationContainer = document.querySelector('.jm-pagination'); 

    const btnExport = document.querySelector('.jm-btn-outline');

    // Bộ lọc
    const searchInput = document.getElementById('jm-search');
    const filterStatus = document.getElementById('jm-filter-status');
    const filterDate = document.getElementById('jm-filter-date');
    const filterState = document.getElementById('jm-filter-state');
    const btnClear = document.getElementById('jm-btn-clear');

    if (!tbody || !paginationContainer) return;

    // ==========================================
    // HÀM GỌI API & RENDER BẢNG
    // ==========================================
    async function loadTableData() {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 2rem;">Loading data...</td></tr>';

        const params = {
            page: currentPage,
            limit: rowsPerPage,
            search: searchInput.value.trim(),
            status: filterStatus.value,
            venue_state: filterState.value,
            start_date: filterDate.value || '',
            end_date: filterDate.value || '',
        };

        Object.keys(params).forEach(key => {
            if (params[key] === '' || params[key] === null) delete params[key];
        });

        const response = await window.journalApi.getJournals(params);

        if (response && response.success) {
            currentTableData = response.data;
            renderRows(response.data);
            updatePagination(response.meta);
        } else {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: red;">Failed to load data.</td></tr>';
        }
    }

    // ==========================================
    // HÀM RENDER DÒNG HTML
    // ==========================================
    function renderRows(data) {
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align: center;">No entries found.</td></tr>';
            return;
        }

        data.forEach(row => {
            const dateObj = new Date(row.execution_date);
            const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

            let stClass = 'st-completed';
            let stText = row.status || 'Completed';
            let statusHtml = '';
            
            if (stText.toLowerCase() === 'draft') stClass = 'st-draft';
            if (stText.toLowerCase() === 'action_required' || stText.toLowerCase() === 'action required') {
                stClass = 'st-action';
                stText = 'Action Required';
            }
            if (stText.toLowerCase() === 'locked') {
                stClass = 'st-locked';
                statusHtml = `<span class="jm-status-pill ${stClass}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>${stText}</span>`;
            } else {
                statusHtml = `<span class="jm-status-pill ${stClass}">${stText}</span>`;
            }

            let riskHtml = 'None';
            let alertIcon = '';
            if (row.risk_flag && row.risk_flag.toLowerCase() === 'warning') {
                riskHtml = `<span class="jm-risk-pill rk-warning">Warning</span>`;
                alertIcon = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`;
            }

            // Lấy cột act_type từ database của bạn
            const actType = row.act_type || row.document_description || 'General Act';
            
            const shortId = typeof row.entry_id === 'string' && row.entry_id.length > 10 
                ? '#' + row.entry_id.substring(row.entry_id.length - 6).toUpperCase() 
                : '#' + row.entry_id;

            const tr = `
                <tr>
                    <td><span class="jm-id">${shortId} ${alertIcon}</span></td>
                    <td>${formattedDate}</td>
                    <td>${row.notary_name || 'System'}</td>
                    <td><span class="jm-type-pill">${actType}</span></td>
                    <td>${row.signer_name || 'N/A'}</td>
                    <td>${row.fee ? '$' + row.fee : '-'}</td>
                    <td>${statusHtml}</td>
                    <td>${riskHtml}</td>
                    <td><a href="#" class="jm-action-link"><span>View</span><span>Details</span></a></td>
                </tr>
            `;
            tbody.insertAdjacentHTML('beforeend', tr);
        });
    }

    // ==========================================
    // HÀM CẬP NHẬT GIAO DIỆN PHÂN TRANG (TỰ ĐỘNG)
    // ==========================================
    function updatePagination(meta) {
        if (!meta) return;

        // 1. Cập nhật text "Showing X to Y..."
        const startItem = meta.total === 0 ? 0 : (meta.page - 1) * meta.limit + 1;
        const endItem = Math.min(meta.page * meta.limit, meta.total);
        infoText.innerText = `Showing ${startItem} to ${endItem} of ${meta.total.toLocaleString()} entries`;

        // 2. Tính toán các nút số trang cần hiển thị (Ví dụ: 1 2 3 ... 10)
        const totalPages = meta.total_pages;
        let pages = [];
        for (let i = 1; i <= totalPages; i++) {
            // Chỉ hiển thị trang đầu, trang cuối, và các trang xung quanh trang hiện tại
            if (i === 1 || i === totalPages || (i >= meta.page - 1 && i <= meta.page + 1)) {
                pages.push(i);
            } else if (i === meta.page - 2 || i === meta.page + 2) {
                pages.push('...');
            }
        }
        // Loại bỏ các dấu '...' trùng lặp nhau
        pages = pages.filter((item, pos, ary) => !pos || item !== ary[pos - 1]);

        // 3. Render HTML cho toàn bộ thanh phân trang
        let html = '';
        
        // Nút Previous
        html += `<button id="jm-btn-prev" class="jm-page-btn" ${!meta.has_prev ? 'disabled' : ''}>&lt;</button>`;
        
        // Các nút số trang
        pages.forEach(p => {
            if (p === '...') {
                html += `<span class="jm-dots">...</span>`;
            } else {
                const activeClass = (p === meta.page) ? 'active' : '';
                html += `<button class="jm-page-btn jm-page-num ${activeClass}" data-page="${p}">${p}</button>`;
            }
        });

        // Nút Next
        html += `<button id="jm-btn-next" class="jm-page-btn" ${!meta.has_next ? 'disabled' : ''}>&gt;</button>`;

        // Đổ HTML vào UI
        paginationContainer.innerHTML = html;

        // 4. Gắn sự kiện click cho các nút MỚI VỪA TẠO
        document.getElementById('jm-btn-prev').addEventListener('click', () => {
            if (meta.has_prev) {
                currentPage--;
                loadTableData();
            }
        });

        document.getElementById('jm-btn-next').addEventListener('click', () => {
            if (meta.has_next) {
                currentPage++;
                loadTableData();
            }
        });

        // Gắn sự kiện cho các con số trang
        const pageNumBtns = document.querySelectorAll('.jm-page-num');
        pageNumBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                currentPage = parseInt(e.target.getAttribute('data-page'));
                loadTableData();
            });
        });
    }

    if (btnExport) {
        // Gỡ sự kiện cũ (tránh click đúp khi chuyển tab)
        const newBtnExport = btnExport.cloneNode(true);
        btnExport.parentNode.replaceChild(newBtnExport, btnExport);

        newBtnExport.addEventListener('click', () => {
            if (currentTableData.length === 0) {
                alert("No data available to export!");
                return;
            }

            // Tạo Header cho file CSV
            const headers = ["ENTRY ID", "DATE/TIME", "NOTARY", "ACT TYPE", "SIGNER NAME", "FEE", "STATUS", "RISK FLAGS"];
            let csvContent = headers.join(',') + '\n';

            // Lặp qua dữ liệu đang có để tạo dòng CSV
            currentTableData.forEach(row => {
                const actType = row.act_type || row.document_description || 'General Act';
                
                // Format lại ngày, bỏ dấu phẩy để không bị lỗi cột CSV
                const dateObj = new Date(row.execution_date);
                const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).replace(/,/g, ''); 

                const rowData = [
                    row.entry_id,
                    formattedDate,
                    row.notary_name || 'System',
                    actType,
                    row.signer_name || 'N/A',
                    row.fee ? '$' + row.fee : '0',
                    row.status || 'Completed',
                    row.risk_flag || 'None'
                ];

                // Bọc từng giá trị trong dấu ngoặc kép để an toàn
                csvContent += rowData.map(val => `"${val}"`).join(',') + '\n';
            });

            // Kích hoạt tải xuống trình duyệt
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", `Journal_Entries_Page_${currentPage}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    // ==========================================
    // SỰ KIỆN TÌM KIẾM VÀ LỌC
    // ==========================================
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1; 
                loadTableData();
            }, 500); 
        });
    }

    const filters = [filterStatus, filterDate, filterState];
    filters.forEach(filter => {
        if (filter) {
            filter.addEventListener('change', () => {
                currentPage = 1;
                loadTableData();
            });
        }
    });

    if (btnClear) {
        btnClear.addEventListener('click', () => {
            searchInput.value = '';
            filterStatus.value = '';
            filterDate.value = '';
            filterState.value = '';
            currentPage = 1;
            loadTableData();
        });
    }

    // Load dữ liệu lần đầu tiên
    loadTableData();
};