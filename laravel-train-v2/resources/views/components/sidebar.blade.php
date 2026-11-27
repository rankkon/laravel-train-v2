<aside class="sidebar">
    <nav class="menu-list">
        <a href="{{ route('dashboard') }}" class="menu-item{{ request()->routeIs('dashboard') ? ' menu-active' : '' }}">
            <span class="menu-label">Dashboard</span>
        </a>
        <a href="{{ route('users.index') }}" class="menu-item{{ request()->routeIs('users.*') ? ' menu-active' : '' }}">
            <span class="menu-label">Quản lý người dùng</span>
        </a>
        <a href="{{ route('leaves.index') }}" class="menu-item{{ request()->routeIs('leaves.*') ? ' menu-active' : '' }}">
            <span class="menu-label">Quản lý đơn</span>
        </a>
        <button type="button" id="logout-button" class="menu-item">
            <span class="menu-label">Đăng xuất</span>
        </button>
    </nav>
</aside>
<form id="logout-form" method="post" action="{{ route('logout') }}" hidden>
    @csrf
</form>
