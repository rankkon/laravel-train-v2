@use('App\Constants\AppConstants')
@use('App\Models\User')

@php
    $statusOptions = array_reverse(AppConstants::USER_STATUSES, true) + ['' => 'Tất cả'];
@endphp

<div class="leave-list-toolbar" data-page="{{ $users->currentPage() }}">
    {{-- Tìm kiếm theo mã hoặc tên và lọc tài khoản hoạt động, đã khóa hoặc tất cả. --}}
    <form method="get" action="{{ route('users.index') }}" id="user-search-form" class="leave-search user-search">
        @if ($sortColumn !== '')
            <input type="hidden" name="sort" value="{{ $sortColumn }}">
            <input type="hidden" name="direction" value="{{ $sortDirection }}">
        @endif
        <label for="user-search">Mã/Tên user</label>
        <input type="text" id="user-search" name="search" value="{{ $search }}">
        <details id="user-status" class="user-status-filter">
            <summary title="Trạng thái người dùng">{{ $statusOptions[$statusFilter] ?? $statusOptions[''] }}</summary>
            <div class="user-status-options">
                @foreach ($statusOptions as $value => $label)
                    <label>
                        <input type="radio" name="status" value="{{ $value }}" @checked($statusFilter === (string) $value)>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </details>
    </form>
    <div class="leave-list-actions user-list-actions">
        <button type="submit" form="user-search-form" class="leave-search-button">Tìm kiếm</button>
        @can('create', User::class)
            <a href="{{ route('users.create') }}" class="leave-add">Thêm mới</a>
        @endcan
        @can('bulkLock', User::class)
            <button type="button" id="user-bulk-lock" class="leave-add leave-cancel" disabled>Khóa nhiều</button>
        @endcan
    </div>
</div>
<div class="leave-table-container">
    <div class="leave-table-scroll">
        <table class="leave-table user-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-users"></th>
                    @foreach ([
                        'id'         => 'Mã người dùng',
                        'full_name'  => 'Tên người dùng',
                        'created_at' => 'Ngày lập',
                        'status'     => 'Trạng thái',
                    ] as $column => $label)
                        @php
                            $isSorted      = $sortColumn === $column;
                            $nextDirection = $isSorted ? ($sortDirection === 'asc' ? 'desc' : '') : 'asc';
                            $sortIcon      = $isSorted ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕';
                            $parameters    = ['search' => $search, 'status' => $statusFilter];
                            if ($nextDirection !== '') {
                                $parameters['sort']      = $column;
                                $parameters['direction'] = $nextDirection;
                            }
                        @endphp
                        <th>
                            <a href="{{ route('users.index', $parameters) }}" @class(['leave-sort-link', 'leave-sort-active' => $isSorted])>
                                <span>{{ $label }}</span>
                                <span class="leave-sort-icon">{{ $sortIcon }}</span>
                            </a>
                        </th>
                    @endforeach
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                {{-- Chỉ hiển thị thao tác trên những tài khoản mà người xem có quyền quản lý. --}}
                @forelse ($users as $user)
                    @php
                        $canManage = auth()->user()->can('update', $user);
                        $isActive = $user->status === AppConstants::STATUS_ACTIVE;
                    @endphp
                    <tr>
                        <td><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" @disabled(! $canManage)></td>
                        <td>{{ str_pad((string) $user->id, AppConstants::USER_CODE_LENGTH, '0', STR_PAD_LEFT) }}</td>
                        <td title="{{ $user->full_name }}">{{ $user->full_name }}</td>
                        <td>{{ $user->created_at->format('Y-m-d') }}</td>
                        <td>{{ AppConstants::USER_STATUSES[$user->status] }}</td>
                        <td>
                            @if ($canManage)
                                <div class="user-row-actions">
                                    <a href="{{ route('users.edit', $user) }}" class="user-edit" title="Sửa">✎</a>
                                    <button type="button" @class(['user-cancel' => $isActive, 'user-activate' => ! $isActive])
                                        title="{{ $isActive ? 'Khóa' : 'Mở khóa' }}"
                                        data-status-url="{{ route($isActive ? 'users.lock' : 'users.unlock', $user) }}"
                                        @if ($isActive) data-lock disabled @endif>
                                        <svg class="user-status-icon" viewBox="0 0 24 24">
                                            <rect x="4" y="11" width="16" height="10" rx="2"></rect>
                                            <path d="{{ $isActive ? 'M7 11V7a5 5 0 0 1 10 0v4' : 'M7 11V7a5 5 0 0 1 9.9-1' }}"></path>
                                        </svg>
                                    </button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">{{ __('messages.no_users_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $users->links('components.pagination') }}
