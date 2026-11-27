<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @yield('head')
    <title>@yield('title') | Sanshin</title>
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">
    @yield('styles')
</head>
<body class="@yield('body-class')" @yield('body-attributes')>
    @yield('body')
    @include('components.loading')
    @yield('scripts')
</body>
</html>
