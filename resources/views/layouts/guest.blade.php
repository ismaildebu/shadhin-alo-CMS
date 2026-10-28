<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'স্বাধীন আলো') }} CMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>

<body class="ad-auth-body antialiased">

    <div class="ad-auth-wrap">

        <div class="ad-auth-brand">
            <a href="{{ route('home') }}" class="ad-auth-logo">
                <img src="{{ \App\Support\Settings::logoUrl() }}" alt="{{ \App\Support\Settings::get('site_name') }}">
            </a>
            <h1>{{ \App\Support\Settings::get('site_name') }} CMS</h1>
            <p>সংবাদ ব্যবস্থাপনা প্যানেল</p>
        </div>

        <div class="ad-auth-card">
            {{ $slot }}
        </div>

        <div class="ad-auth-foot">
            <a href="{{ route('home') }}">← সাইটে ফিরে যান</a>
        </div>

    </div>

</body>
</html>