{{-- Laravel truyền $paginator vào view khi gọi links('components.pagination'). --}}
@php
    $page        = $paginator->currentPage();
    $totalPages  = $paginator->lastPage();

    // Hiển thị tối đa hai trang trước và hai trang sau trang hiện tại.
    $nearby      = 1;
    $pageNumbers = [1];

    // Chỉ thêm các trang ở giữa. Trang đầu và trang cuối được thêm riêng để tránh trùng.
    for ($number = max(2, $page - $nearby); $number <= min($totalPages - 1, $page + $nearby); $number++) {
        $pageNumbers[] = $number;
    }

    // Nếu chỉ có một trang, mảng đã chứa trang 1.
    if ($totalPages > 1) {
        $pageNumbers[] = $totalPages;
    }

    // Lưu số trang vừa hiển thị để phát hiện khoảng trống giữa hai số.
    $previousNumber = 0;
@endphp

<nav class="leave-pagination">
    {{-- Trang đầu không có URL trang trước, nên Previous được hiển thị bằng span. --}}
    @if ($paginator->previousPageUrl() !== null)
        <a href="{{ $paginator->previousPageUrl() }}" class="leave-page-previous">← Previous</a>
    @else
        <span class="leave-page-previous leave-page-disabled">← Previous</span>
    @endif

    @foreach ($pageNumbers as $number)
        {{-- Chèn dấu … khi có trang bị lược, ví dụ từ trang 1 nhảy đến trang 4. --}}
        @if ($number > $previousNumber + 1)
            <span class="leave-page-gap">…</span>
        @endif

        {{-- Trang hiện tại chỉ hiện số. Các trang còn lại có link do paginator tạo. --}}
        @if ($number === $page)
            <span class="leave-page-current">{{ $number }}</span>
        @else
            <a href="{{ $paginator->url($number) }}">{{ $number }}</a>
        @endif
        @php $previousNumber = $number; @endphp
    @endforeach

    {{-- Trang cuối không có URL trang sau, nên Next được hiển thị bằng span. --}}
    @if ($paginator->nextPageUrl() !== null)
        <a href="{{ $paginator->nextPageUrl() }}" class="leave-page-next">Next →</a>
    @else
        <span class="leave-page-next leave-page-disabled">Next →</span>
    @endif
</nav>
