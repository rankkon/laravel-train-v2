@extends('layouts.app')

@section('title', 'Danh sách người dùng')
@section('body-class', 'leave-list-page leave-list-screen user-list-screen')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/leave_list.css') }}">
    <link rel="stylesheet" href="{{ asset('css/user_list.css') }}">
@endsection

@section('content')
    <main class="leave-list">
        <p id="user-message" role="alert" hidden></p>
        <div id="user-list" class="leave-list-content" data-bulk-url="{{ route('users.bulk-lock') }}">
            @include('users.partials.list')
        </div>
    </main>
@endsection

@section('scripts')
    <script src="{{ asset('js/users.js') }}" defer></script>
@endsection
