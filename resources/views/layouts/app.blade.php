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

<body class="ad-body antialiased"
      x-data="{ menu: false }"
      x-on:keydown.escape.window="menu = false">

    @include('layouts.navigation')

    <div class="ad-backdrop" x-show="menu" x-cloak x-on:click="menu = false"></div>

    <div class="ad-main">

        <div class="ad-topbar">
            <button type="button" class="ad-burger" x-on:click="menu = true" aria-label="মেনু খুলুন">
                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div class="ad-topbar-spacer"></div>

            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ad-site-link">সাইট দেখুন</a>

            <div class="ad-user">
                <span class="ad-avatar">{{ \Illuminate\Support\Str::substr(Auth::user()->name, 0, 1) }}</span>
                <div class="ad-user-meta">
                    <div class="ad-user-name">{{ Auth::user()->name }}</div>
                    <div class="ad-user-role">{{ \App\Models\User::ROLES[Auth::user()->role] ?? Auth::user()->role }}</div>
                </div>
            </div>
        </div>

        @isset($header)
            <header class="ad-header">
                <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main>
            {{ $slot }}
        </main>

    </div>

</body>
</html>