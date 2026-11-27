const userList = document.getElementById('user-list');
const message  = document.getElementById('user-message');
const selectableUser = 'input[name="user_ids[]"]:not(:disabled)';

// Báo lỗi chung trên trang danh sách người dùng.
function showError() {
    message.textContent = confirmation.dataset.error;
    message.hidden      = false;
}

// Đồng bộ ô Chọn tất cả và các nút khóa theo những dòng đang được chọn.
function updateSelection() {
    const checkboxes = [...userList.querySelectorAll(selectableUser)];
    const selected   = checkboxes.filter(checkbox => checkbox.checked);
    const selectAll  = document.getElementById('select-all-users');
    const bulkButton = document.getElementById('user-bulk-lock');
    checkboxes.forEach(checkbox => {
        const lock = checkbox.closest('tr').querySelector('[data-lock]');
        if (lock) lock.disabled = !checkbox.checked;
    });
    selectAll.disabled      = checkboxes.length === 0;
    selectAll.checked       = selected.length > 0 && selected.length === checkboxes.length;
    selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
    if (bulkButton) bulkButton.disabled = selected.length === 0;
}

// Tải HTML danh sách mới và cập nhật lại trạng thái chọn dòng.
async function loadList(url) {
    try {
        await refreshList(url, userList);
        message.hidden = true;
        updateSelection();
    } catch {
        showError();
    }
}

// Gửi POST để khóa hoặc mở khóa, rồi tải lại danh sách sau khi thành công.
async function changeStatus(url, userIds = null) {
    try {
        await withLoading(async () => {
            const options = {method: 'POST'};
            if (userIds) {
                options.headers = {'Content-Type': 'application/json'};
                options.body    = JSON.stringify({user_ids: userIds});
            }
            const response = await sendRequest(url, options);
            const result   = await response.json();
            if (result.redirect) {
                window.location.assign(result.redirect);
                return;
            }
            const listUrl = new URL(window.location.href);
            if (listUrl.searchParams.has('sort')) listUrl.searchParams.delete('page');
            listUrl.searchParams.delete('sort');
            listUrl.searchParams.delete('direction');
            await loadList(listUrl);
        });
    } catch {
        showError();
    }
}

// Đưa từ khóa và bộ lọc vào URL để tải kết quả bằng AJAX.
userList.addEventListener('submit', event => {
    event.preventDefault();
    const url  = new URL(event.target.action);
    url.search = new URLSearchParams(new FormData(event.target));
    loadList(url);
});
// Xử lý sắp xếp, phân trang và xác nhận các thao tác khóa tài khoản.
userList.addEventListener('click', event => {
    const link = event.target.closest('.leave-sort-link, .leave-pagination a');
    if (link) {
        event.preventDefault();
        loadList(link.href);
        return;
    }
    const button = event.target.closest('[data-status-url]');
    if (button) {
        showConfirmation(button.hasAttribute('data-lock') ? confirmation.dataset.lock : confirmation.dataset.unlock,
            () => changeStatus(button.dataset.statusUrl));
    }
    if (event.target.closest('#user-bulk-lock')) {
        const ids = Array.from(userList.querySelectorAll(`${selectableUser}:checked`), input => input.value);
        showConfirmation(confirmation.dataset.bulkLock, () => changeStatus(userList.dataset.bulkUrl, ids));
    }
});
// Cập nhật nhãn bộ lọc và trạng thái các checkbox trong bảng.
userList.addEventListener('change', event => {
    if (event.target.name === 'status') {
        const filter                                = event.target.closest('details');
        filter.querySelector('summary').textContent = event.target.nextElementSibling.textContent;
        filter.open                                 = false;
    }
    if (event.target.id === 'select-all-users') {
        userList.querySelectorAll(selectableUser).forEach(input => { input.checked = event.target.checked; });
    }
    updateSelection();
});
// Đóng menu lọc khi bấm ra ngoài hoặc nhấn Escape.
document.addEventListener('click', event => {
    const filter = document.getElementById('user-status');
    if (!filter.contains(event.target) || event.target.matches('input[name="status"]')) filter.open = false;
});
userList.addEventListener('keydown', event => {
    const filter = document.getElementById('user-status');
    if (event.key === 'Escape' && filter.open) {
        filter.open = false;
        filter.querySelector('summary').focus();
    }
});
window.addEventListener('pageshow', updateSelection);
