{{-- CSS vẽ vòng quay. Dialog mặc định đóng và dùng đoạn "Đang xử lý" làm tên. --}}
<dialog id="loading-dialog" class="loading-dialog" aria-labelledby="loading-message">
    <span class="loading-spinner"></span>
    <p id="loading-message" role="status">{{ __('messages.processing') }}</p>
</dialog>

<script>
    const loadingDialog = document.getElementById('loading-dialog');

    // Bật hoặc tắt hộp loading. Trả false nếu hộp đã mở khi có yêu cầu bật.
    function setLoading(active) {
        if (active) {
            if (loadingDialog.open) return false;
            loadingDialog.showModal();
        } else {
            loadingDialog.close();
        }
        return true;
    }

    // Bọc thao tác do nơi gọi truyền vào
    async function withLoading(action) {
        if (!setLoading(true)) return;

        // Ghi nhớ các nút đang bật để chỉ khôi phục đúng những nút này sau đó.
        const buttons = [...document.querySelectorAll('button:enabled, input[type="submit"]:enabled')];
        buttons.forEach(button => button.disabled = true);

        try {
            return await action();
        } finally {
            // Bật lại các nút và đóng hộp loading dù thao tác thành công hay báo lỗi.
            buttons.forEach(button => button.disabled = false);
            setLoading(false);
        }
    }

    // Chặn Escape đóng hộp loading khi thao tác vẫn đang chạy.
    loadingDialog.addEventListener('cancel', event => event.preventDefault());

    // Bật loading cho form POST thường. Form AJAX tự gọi withLoading().
    // Nếu hộp đã mở, chặn lần submit tiếp theo để tránh gửi form hai lần.
    document.addEventListener('submit', event => {
        if (!event.defaultPrevented && event.target.method === 'post' && !setLoading(true)) {
            event.preventDefault();
        }
    });

    // Khi Back hoặc Forward khôi phục trang từ cache, đóng hộp loading còn mở.
    window.addEventListener('pageshow', event => {
        if (event.persisted) setLoading(false);
    });
</script>
