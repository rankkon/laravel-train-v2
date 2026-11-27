@extends('layouts.app')

@section('title', 'Dashboard')
@section('body-class', 'leave-list-page leave-list-screen')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/leave_list.css') }}">
@endsection

@section('content')
    <main class="leave-list">
        <div class="leave-list-toolbar"></div>

        <div class="leave-table-container">
            <div class="leave-table-scroll">
                <table class="leave-table">
                    <thead>
                        <tr>
                            <th scope="col">STT</th>
                            <th scope="col">Người dùng</th>
                            <th scope="col">Loại đơn</th>
                            <th scope="col">Ngày lập</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Ngày duty</th>
                            <th scope="col">Mô tả</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaves as $leave)
                            <tr class="leave-row leave-status-{{ $leave->status }}">
                                <td>{{ $leaves->firstItem() + $loop->index }}</td> {{-- STT tiếp tục theo số trang đang xem. --}}
                                <td>{{ $leave->full_name }}</td>
                                <td>{{ $leaveTypes[$leave->leave_type] }}</td>
                                <td>{{ $leave->created_at->toDateString() }}</td>
                                <td>{{ $leaveStatuses[$leave->status] }}</td>
                                <td>{{ $leave->getRawOriginal('end_date') }}</td>
                                <td>
                                    <div class="leave-description">
                                        <span class="leave-title" title="{{ $leave->title }}">{{ $leave->title }}</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7">{{ __('messages.no_leaves_found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $leaves->links('components.pagination') }} {{-- Dùng giao diện phân trang chung. --}}
    </main>
@endsection
