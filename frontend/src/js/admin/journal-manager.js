window.initJournalManager = async function() {
    // 1. Khai báo các biến trạng thái
    let currentPage = 1;
    let rowsPerPage = 5; // Số dòng trên 1 trang (limit)
    let searchTimeout = null; // Dùng để delay tìm kiếm khi gõ phím (Debounce)

    // 2. DOM Elements
    const tbody = document.getElementById('jm-tbody');
    const btnPrev = document.getElementById('jm-btn-prev');
    const btnNext = document.getElementById('jm-btn-next');
    const infoText = document.getElementById('jm-table-info');
    const pageNumbersContainer = document.querySelector('.jm-pagination'); // Chứa các nút phân trang

    // Bộ lọc
    const searchInput = document.getElementById('jm-search');
    const filterStatus = document.getElementById('jm-filter-status');
    const filterDate = document.getElementById('jm-filter-date');
    const filterState = document.getElementById('jm-filter-state');
    const btnClear = document.getElementById('jm-btn-clear');

    if (!tbody) return;

    // ==========================================
    // HÀM GỌI API & RENDER BẢNG
    // ==========================================
    async function loadTableData() {
        // Hiển thị trạng thái đang tải
        tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 2rem;">Loading data...</td></tr>';

        // Thu thập các tham số bộ lọc
        const params = {
            page: currentPage,
            limit: rowsPerPage,
            search: searchInput.value.trim(),
            status: filterStatus.value,
            venue_state: filterState.value,
            start_date: filterDate.value || '', // Nếu có date filter
        };

        // Xóa các param rỗng để URL gọn hơn
        Object.keys(params).forEach(key => {
            if (params[key] === '' || params[key] === null) delete params[key];
        });

        // Gọi API
        const response = await window.journalApi.getJournals(params);

        if (response && response.success) {
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
            // Format ngày giờ: 2023-10-24 10:30:00 -> Oct 24, 10:30 AM
            const dateObj = new Date(row.execution_date);
            const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

            // Format Trạng thái (Status)
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

            // Format Risk Flags & Icon
            let riskHtml = 'None';
            let alertIcon = '';
            if (row.risk_flag && row.risk_flag.toLowerCase() === 'warning') {
                riskHtml = `<span class="jm-risk-pill rk-warning">Warning</span>`;
                alertIcon = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`;
            }

            // Lưu ý: Backend API index hiện tại chưa select 'document_description' (Act Type), 
            // nên ta để mặc định hoặc lấy từ biến khác nếu có.
            const actType = row.document_description || 'General Act';
            
            // Xử lý ID ngắn gọn (nếu là UUID thì lấy 6 ký tự cuối)
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
    // HÀM CẬP NHẬT GIAO DIỆN PHÂN TRANG
    // ==========================================
    function updatePagination(meta) {
        if (!meta) return;

        // Cập nhật text "Showing X to Y of Z entries"
        const startItem = (meta.page - 1) * meta.limit + 1;
        const endItem = Math.min(meta.page * meta.limit, meta.total);
        infoText.innerText = `Showing ${meta.total === 0 ? 0 : startItem} to ${endItem} of ${meta.total.toLocaleString()} entries`;

        // Bật/tắt nút Prev, Next
        btnPrev.disabled = !meta.has_prev;
        btnNext.disabled = !meta.has_next;

        // Xóa event listener cũ
        const newBtnPrev = btnPrev.cloneNode(true);
        btnPrev.parentNode.replaceChild(newBtnPrev, btnPrev);
        const newBtnNext = btnNext.cloneNode(true);
        btnNext.parentNode.replaceChild(newBtnNext, btnNext);

        // Gắn sự kiện mới
        newBtnPrev.addEventListener('click', () => {
            if (meta.has_prev) {
                currentPage--;
                loadTableData();
            }
        });

        newBtnNext.addEventListener('click', () => {
            if (meta.has_next) {
                currentPage++;
                loadTableData();
            }
        });
    }

    // ==========================================
    // SỰ KIỆN TÌM KIẾM VÀ LỌC (EVENTS)
    // ==========================================
    
    // Tìm kiếm: Dùng Debounce (đợi người dùng gõ xong 500ms mới gọi API để đỡ lag server)
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1; // Khi search thì phải quay về trang 1
                loadTableData();
            }, 500); 
        });
    }

    // Lọc theo Status, State, Date
    const filters = [filterStatus, filterDate, filterState];
    filters.forEach(filter => {
        if (filter) {
            filter.addEventListener('change', () => {
                currentPage = 1;
                loadTableData();
            });
        }
    });

    // Nút Clear All Filters
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

    // Chạy lần đầu tiên khi mở trang
    loadTableData();
};