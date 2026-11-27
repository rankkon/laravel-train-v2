@extends('layouts.app')

@section('title', __('messages.page_error'))
@section('body-class', 'error-page')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/error.css') }}">
@endsection

@section('content')
    {{-- Chỉ hiển thị thông báo lỗi chung, mã HTTP và header được Handler quyết định. --}}
    <main class="error-content">
        <h1>{{ __('messages.page_error') }}</h1>
    </main>
@endsection
