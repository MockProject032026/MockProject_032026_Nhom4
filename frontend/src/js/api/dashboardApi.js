// Cấu hình URL gốc của Backend Laravel
const BASE_URL = 'http://localhost:8000/api/v1';

window.dashboardApi = {
    // 1. Lấy dữ liệu thống kê (KPI Summary)
    getKpiSummary: async function() {
        try {
            const response = await fetch(`${BASE_URL}/dashboard/kpi-summary`);
            if (!response.ok) throw new Error('Network response was not ok');
            return await response.json();
        } catch (error) {
            console.error('Lỗi khi gọi API getKpiSummary:', error);
            return null;
        }
    },

    // 2. Lấy danh sách Logs (Compliance Logs)
    getComplianceLogs: async function(limit = 5) {
        try {
            const response = await fetch(`${BASE_URL}/dashboard/compliance-logs?limit=${limit}`);
            if (!response.ok) throw new Error('Network response was not ok');
            return await response.json();
        } catch (error) {
            console.error('Lỗi khi gọi API getComplianceLogs:', error);
            return null;
        }
    },

    // 3. Gửi email nhắc nhở (Missing Signatures)
    sendSignatureReminders: async function() {
        try {
            const response = await fetch(`${BASE_URL}/notaries/reminders/missing-signatures`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                    // Nếu API yêu cầu token, thêm Authorization: `Bearer ${token}` vào đây
                },
                body: JSON.stringify({ target: "ALL" })
            });
            return await response.json();
        } catch (error) {
            console.error('Lỗi khi gọi API sendSignatureReminders:', error);
            return null;
        }
    }
};