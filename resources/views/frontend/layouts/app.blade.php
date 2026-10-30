<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>@yield('title', \App\Support\Settings::get('site_name'))</title>
    @if($favicon = \App\Support\Settings::faviconUrl())
        <link rel="icon" href="{{ $favicon }}">
    @endif

    @yield('meta')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

body{
    background:#fff;
    font-family:"Noto Sans Bengali",sans-serif;
}

.topbar{
    background:#f5f5f5;
    border-bottom:1px solid #ddd;
    padding:8px 0;
    font-size:15px;
}

.site-header{
    padding:20px 0;
}

.site-logo{
    max-height:90px;
}

.ad-box{
    height:90px;
    border:1px solid #ddd;
    background:#fafafa;
    display:flex;
    justify-content:center;
    align-items:center;
    color:#777;
    font-size:22px;
}

.navbar-custom{
    background:#123b68;
    padding:0;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
    position:sticky;
    top:0;
    z-index:999;
}

.navbar-custom .navbar-nav{
    width:100%;
    row-gap:2px;
}

.navbar-custom .nav-item{
    white-space:nowrap;
}

.navbar-custom .nav-link{
    color:#fff !important;
    padding:12px 15px;
    font-size:15px;
    font-weight:600;
    transition:.3s;
}

.navbar-custom .nav-link:hover{
    background:#0b2745;
}

.navbar-custom .navbar-toggler{
    border-color:#fff;
}

.navbar-custom .navbar-toggler-icon{
    filter:brightness(0) invert(1);
}

.breaking{
    background:#fff8e1;
    border-top:1px solid #ddd;
    border-bottom:1px solid #ddd;
    padding:8px 0;
}

.breaking-news{
    background:#fff;
    border-top:1px solid #ddd;
    border-bottom:1px solid #ddd;
    margin-bottom:20px;
}

.breaking-label{
    background:#d60000;
    color:#fff;
    font-weight:700;
    text-align:center;
    padding:12px 10px;
    font-size:16px;
}

.breaking-ticker{
    background:#fafafa;
    padding:10px 15px;
    overflow:hidden;
}

.breaking-ticker a{
    color:#222;
    font-size:15px;
}

.breaking-ticker a:hover{
    color:#d60000;
}


/* sub-categories: hover dropdown on desktop, always-open indented list on mobile */
@media (min-width:992px){
    .navbar-custom .nav-item.dropdown:hover > .dropdown-menu{
        display:block !important;
        visibility:visible !important;
        opacity:1 !important;
        margin-top:0;
    }
}
@media (max-width:991.98px){
    .navbar-custom .nav-item.dropdown > .dropdown-menu{
        display:block; position:static; background:#0e2d50; border:0; border-radius:0;
    }
    .navbar-custom .dropdown-item{ color:#fff; padding-left:2rem; }
    .navbar-custom .dropdown-item:hover{ background:#0b2745; color:#fff; }
}

</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/frontend.css') }}">
<link rel="stylesheet" href="{{ asset('css/news-fallback.css') }}">
</head>
<body>

@include('frontend.partials.bd-map-defs')
@include('frontend.partials.header')

<div class="container mt-4">
    @yield('content')
</div>

@include('frontend.partials.footer')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
