window.initDashboard = function() {
    const logsData = [
        { id: '#JR-8921-TX', initials: 'JW', avatarClass: 'av-blue', name: 'James Wilson', state: 'Texas', date: 'Oct 24, 2023', status: 'Compliant', action: 'VIEW JOURNAL' },
        { id: '#JR-8920-CA', initials: 'SL', avatarClass: 'av-gray', name: 'Sarah Lopez', state: 'California', date: 'Oct 23, 2023', status: 'Missing Signature', action: 'VIEW JOURNAL' },
        { id: '#JR-8919-NY', initials: 'MR', avatarClass: 'av-indigo', name: 'Michael Reed', state: 'New York', date: 'Oct 22, 2023', status: 'Compliant', action: 'VIEW JOURNAL' },
        { id: '#JR-8918-FL', initials: 'ED', avatarClass: 'av-blue', name: 'Emily Davis', state: 'Florida', date: 'Oct 21, 2023', status: 'Compliant', action: 'VIEW JOURNAL' },
        { id: '#JR-8917-IL', initials: 'RB', avatarClass: 'av-gray', name: 'Robert Brown', state: 'Illinois', date: 'Oct 20, 2023', status: 'Missing Signature', action: 'VIEW JOURNAL' },
        { id: '#JR-8916-GA', initials: 'AM', avatarClass: 'av-indigo', name: 'Alice Moore', state: 'Georgia', date: 'Oct 19, 2023', status: 'Compliant', action: 'VIEW JOURNAL' }
    ];

    let currentPage = 1;
    const rowsPerPage = 3;
    const totalLogs = 1248;

    const tbody = document.getElementById('compliance-tbody');
    const btnPrevOld = document.getElementById('btn-prev');
    const btnNextOld = document.getElementById('btn-next');
    const infoText = document.getElementById('table-info');

    // Nếu HTML chưa tải xong thì dừng lại
    if (!tbody || !btnPrevOld || !btnNextOld) return;

    // QUAN TRỌNG: Đưa logic nhân bản (clone) lên TRƯỚC khi định nghĩa hàm renderTable
    // Để gỡ sự kiện click cũ, thay bằng các nút mới hoàn toàn
    const btnPrev = btnPrevOld.cloneNode(true);
    btnPrevOld.parentNode.replaceChild(btnPrev, btnPrevOld);

    const btnNext = btnNextOld.cloneNode(true);
    btnNextOld.parentNode.replaceChild(btnNext, btnNextOld);

    function renderTable() {
        tbody.innerHTML = '';
        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const pageData = logsData.slice(start, end);

        pageData.forEach(row => {
            const statusClass = row.status === 'Compliant' ? 'pill-compliant' : 'pill-missing';
            const tr = `
                <tr>
                    <td><strong>${row.id}</strong></td>
                    <td>
                        <div class="notary-cell">
                            <div class="avatar ${row.avatarClass}">${row.initials}</div>
                            ${row.name}
                        </div>
                    </td>
                    <td>${row.state}</td>
                    <td>${row.date}</td>
                    <td><span class="status-pill ${statusClass}">${row.status}</span></td>
                    <td><a href="#" class="action-link">${row.action}</a></td>
                </tr>
            `;
            tbody.insertAdjacentHTML('beforeend', tr);
        });

        infoText.innerText = `Showing ${start + 1}-${Math.min(end, logsData.length)} of ${totalLogs.toLocaleString()} logs`;
        
        // Trạng thái nút bây giờ sẽ được cập nhật chính xác trên các nút "mới"
        btnPrev.disabled = currentPage === 1;
        btnNext.disabled = end >= logsData.length;
    }

    // Gắn sự kiện click cho các nút mới
    btnPrev.addEventListener('click', () => {
        if (currentPage > 1) { 
            currentPage--; 
            renderTable(); 
        }
    });

    btnNext.addEventListener('click', () => {
        if ((currentPage * rowsPerPage) < logsData.length) { 
            currentPage++; 
            renderTable(); 
        }
    });

    // In bảng lần đầu
    renderTable(); 
};