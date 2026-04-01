window.initDashboard = async function() {
    
    // ==========================================
    // 1. GỌI API & RENDER SỐ LIỆU KPI
    // ==========================================
    async function loadKpiData() {
        const response = await window.dashboardApi.getKpiSummary();
        
        if (response && response.success) {
            const data = response.data;
            
            // Render Total & Incomplete
            document.getElementById('kpi-total').innerText = data.total_entries.toLocaleString();
            document.getElementById('kpi-incomplete').innerText = data.incomplete.toLocaleString();
            
            // Tính tỷ lệ Compliance (Tỉ lệ phần trăm hồ sơ hoàn thành)
            let compRate = 100;
            if (data.total_entries > 0) {
                compRate = ((data.total_entries - data.incomplete) / data.total_entries) * 100;
            }
            document.getElementById('kpi-rate').innerText = compRate.toFixed(1) + '%';

            // Render Warnings (Alerts)
            document.getElementById('msg-signatures').innerText = 
                `${data.alerts.missing_signatures} entries are missing electronic signer signatures.`;
            document.getElementById('msg-thumbprints').innerText = 
                `${data.alerts.missing_thumbprints} entries require mandatory thumbprint verification.`;
        } else {
            console.error("Không tải được dữ liệu KPI.");
        }
    }

    // ==========================================
    // 2. GỌI API & RENDER BẢNG COMPLIANCE LOGS
    // ==========================================
    async function loadComplianceLogs() {
        const response = await window.dashboardApi.getComplianceLogs(5); // Gọi 5 records mới nhất
        const tbody = document.getElementById('compliance-tbody');
        const infoText = document.getElementById('table-info');

        if (!tbody) return;
        tbody.innerHTML = ''; // Clear bảng

        if (response && response.success && response.data.logs.length > 0) {
            const logs = response.data.logs;
            
            logs.forEach(log => {
                // Xác định màu sắc dựa trên FLAGS (tùy thuộc vào dữ liệu backend trả về)
                const isError = log.flags === 'ERROR' || log.flags === 'WARNING';
                const statusClass = isError ? 'pill-missing' : 'pill-compliant';
                const statusText = isError ? 'Action Needed' : 'Compliant';

                // Format ngày giờ đẹp hơn
                const dateObj = new Date(log.timestamp);
                const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                // Sinh avatar bằng 2 chữ cái đầu
                const initials = log.notary ? log.notary.substring(0,2).toUpperCase() : 'SY';

                const tr = `
                    <tr>
                        <td><strong>...${log.id.substring(log.id.length - 6)}</strong></td>
                        <td>
                            <div class="notary-cell">
                                <div class="avatar av-blue">${initials}</div>
                                ${log.notary || 'SYSTEM'}
                            </div>
                        </td>
                        <td>${log.journal_id || 'N/A'}</td>
                        <td>${formattedDate}</td>
                        <td>${log.action}</td>
                        <td><span class="status-pill ${statusClass}">${log.flags || statusText}</span></td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', tr);
            });

            infoText.innerText = `Showing ${logs.length} latest compliance logs`;
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No compliance logs found.</td></tr>';
            infoText.innerText = '';
        }
    }

    // ==========================================
    // 3. GẮN SỰ KIỆN NÚT "EMAIL NOTARIES"
    // ==========================================
    const btnEmailNotaries = document.getElementById('btn-email-notaries');
    if (btnEmailNotaries) {
        // Gỡ sự kiện cũ tránh click đúp
        const newBtn = btnEmailNotaries.cloneNode(true);
        btnEmailNotaries.parentNode.replaceChild(newBtn, btnEmailNotaries);

        newBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            newBtn.innerText = "SENDING...";
            newBtn.style.pointerEvents = 'none'; // Khóa nút trong lúc gửi

            const res = await window.dashboardApi.sendSignatureReminders();
            
            if (res && res.success) {
                alert(`Thành công! Đã gửi ${res.data.emails_sent_count} email nhắc nhở.`);
                // Tải lại KPI sau khi gửi email
                await loadKpiData();
            } else {
                alert('Lỗi: Không thể gửi email. Vui lòng thử lại sau.');
            }

            newBtn.innerText = "EMAIL NOTARIES";
            newBtn.style.pointerEvents = 'auto'; // Mở khóa nút
        });
    }

    // ==========================================
    // CHẠY CÁC HÀM KHI KHỞI TẠO DASHBOARD
    // ==========================================
    await loadKpiData();
    await loadComplianceLogs();
};