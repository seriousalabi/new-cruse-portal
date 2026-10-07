<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'New Cruse Academy Portal')</title>
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body>
    @yield('content')
</body>
</html>
