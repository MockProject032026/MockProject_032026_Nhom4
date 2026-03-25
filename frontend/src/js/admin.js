// Hàm tải nội dung trang
async function loadAdminPage(pageUrl) {
  try {
    // ĐÂY LÀ DÒNG ÉP TRÌNH DUYỆT LUÔN TẢI BẢN HTML MỚI NHẤT
    const noCacheUrl = pageUrl + '?v=' + new Date().getTime();
    
    // Sử dụng url mới để fetch
    const response = await fetch(noCacheUrl);
    
    if (!response.ok) throw new Error('Không thể tải trang');
    const html = await response.text();
    
    // Đổ nội dung vào main content
    document.getElementById('app-content').innerHTML = html;

    setTimeout(() => {
        if (pageUrl.includes('dashboard.html') && typeof window.initDashboard === 'function') {
            window.initDashboard(); // Vẽ bảng phân trang
        }
        if (pageUrl.includes('journal-manager.html') && typeof window.initJournalManager === 'function') {
            window.initJournalManager();
        }
    }, 100);
  } catch (error) {
    console.error('Lỗi Routing:', error);
    document.getElementById('app-content').innerHTML = `<h2>Lỗi tải dữ liệu. Vui lòng kiểm tra kết nối.</h2>`;
  }
}

// Khi trang vừa load lên, mặc định tải Dashboard
document.addEventListener('DOMContentLoaded', () => {
  loadAdminPage('dashboard.html');
});

// Xử lý sự kiện click trên thanh điều hướng
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('nav-link')) {
    e.preventDefault();
    const pageUrl = e.target.getAttribute('data-page');
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    e.target.classList.add('active');
    loadAdminPage(pageUrl);
  }
});