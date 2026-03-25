window.initJournalManager = function() {
    // Dữ liệu thô dựa chính xác theo hình ảnh
    const journalData = [
        { id: '#8829', alert: null, date: 'Oct 24, 10:30 AM', notary: 'Jane Doe', type: 'Acknowledgment', signer: 'John Smith', fee: '$25.00', status: 'Completed', stClass: 'st-completed', risk: 'None', rkClass: '' },
        { id: '#8828', alert: 'warning', date: 'Oct 24, 09:15 AM', notary: 'Jane Doe', type: 'Jurat', signer: 'Mary Ellis', fee: '$15.00', status: 'Draft', stClass: 'st-draft', risk: 'Warning', rkClass: 'rk-warning' },
        { id: '#8827', alert: null, date: 'Oct 23, 04:45 PM', notary: 'Robert Fox', type: 'Oaths', signer: 'Alice Wong', fee: '-', status: 'Locked', stClass: 'st-locked', risk: 'None', rkClass: '' },
        { id: '#8826', alert: null, date: 'Oct 23, 02:00 PM', notary: 'Jane Doe', type: 'Deed', signer: 'Charles Reed', fee: '$50.00', status: 'Completed', stClass: 'st-completed', risk: 'None', rkClass: '' },
        { id: '#8825', alert: 'error', date: 'Oct 22, 11:30 AM', notary: 'Robert Fox', type: 'Deed', signer: 'Linda White', fee: '$25.00', status: 'Action Required', stClass: 'st-action', risk: 'Warning', rkClass: 'rk-warning' },
        // Thêm 1 dòng phụ để test phân trang
        { id: '#8824', alert: null, date: 'Oct 21, 09:00 AM', notary: 'Alice Smith', type: 'Jurat', signer: 'Tom Hardy', fee: '$15.00', status: 'Completed', stClass: 'st-completed', risk: 'None', rkClass: '' }
    ];

    let currentPage = 1;
    const rowsPerPage = 5; // Hiển thị 5 dòng như ảnh
    const totalEntries = 1248;

    const tbody = document.getElementById('jm-tbody');
    const btnPrevOld = document.getElementById('jm-btn-prev');
    const btnNextOld = document.getElementById('jm-btn-next');
    const infoText = document.getElementById('jm-table-info');

    if (!tbody || !btnPrevOld || !btnNextOld) return;

    // Thay thế nút để reset event listener (Tránh click đúp)
    const btnPrev = btnPrevOld.cloneNode(true);
    btnPrevOld.parentNode.replaceChild(btnPrev, btnPrevOld);
    const btnNext = btnNextOld.cloneNode(true);
    btnNextOld.parentNode.replaceChild(btnNext, btnNextOld);

    function renderTable() {
        tbody.innerHTML = '';
        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const pageData = journalData.slice(start, end);

        pageData.forEach(row => {
            // Icon cảnh báo cạnh ID
            let alertIcon = '';
            if (row.alert === 'warning') alertIcon = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`;
            if (row.alert === 'error') alertIcon = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`;

            // Xử lý riêng cho trạng thái Locked (Có icon ổ khóa thay vì dấu chấm)
            let statusHtml = `<span class="jm-status-pill ${row.stClass}">${row.status}</span>`;
            if (row.status === 'Locked') {
                statusHtml = `<span class="jm-status-pill ${row.stClass}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>${row.status}</span>`;
            }

            // Xử lý Risk Flags
            let riskHtml = row.risk === 'None' ? 'None' : `<span class="jm-risk-pill ${row.rkClass}">${row.risk}</span>`;

            const tr = `
                <tr>
                    <td><span class="jm-id">${row.id} ${alertIcon}</span></td>
                    <td>${row.date}</td>
                    <td>${row.notary}</td>
                    <td><span class="jm-type-pill">${row.type}</span></td>
                    <td>${row.signer}</td>
                    <td>${row.fee}</td>
                    <td>${statusHtml}</td>
                    <td>${riskHtml}</td>
                    <td><a href="#" class="jm-action-link"><span>View</span><span>Details</span></a></td>
                </tr>
            `;
            tbody.insertAdjacentHTML('beforeend', tr);
        });

        infoText.innerText = `Showing ${start + 1} to ${Math.min(end, journalData.length)} of ${totalEntries.toLocaleString()} entries`;
    }

    btnPrev.addEventListener('click', () => {
        if (currentPage > 1) { currentPage--; renderTable(); }
    });

    btnNext.addEventListener('click', () => {
        if ((currentPage * rowsPerPage) < journalData.length) { currentPage++; renderTable(); }
    });

    renderTable(); // Vẽ bảng lần đầu
};