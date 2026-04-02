// Cấu hình URL gốc của Backend Laravel
const BASE_URL = 'http://localhost:8000/api/v1';

window.journalApi = {
    /**
     * Gọi API lấy danh sách Journal Entries (có phân trang và bộ lọc)
     * @param {Object} params - Các tham số như { page: 1, limit: 5, search: '...', status: '...', venue_state: '...' }
     */
    getJournals: async function(params = {}) {
        try {
            // Chuyển đổi Object params thành query string (vd: ?page=1&limit=5&search=abc)
            const queryString = new URLSearchParams(params).toString();
            const url = `${BASE_URL}/journals${queryString ? '?' + queryString : ''}`;

            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok');
            
            return await response.json();
        } catch (error) {
            console.error('Lỗi khi gọi API getJournals:', error);
            return null;
        }
    }
};