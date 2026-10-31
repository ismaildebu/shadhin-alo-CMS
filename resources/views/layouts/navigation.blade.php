<aside class="ad-sidebar" x-bind:class="{ 'is-open': menu }">

    <div class="ad-brand">
        <span class="ad-logo">
            
    <img src="{{ \App\Support\Settings::logoUrl() }}" alt="{{ \App\Support\Settings::get('site_name') }}">   
        </span>
        <h2 class="ad-brand-name">{{ \App\Support\Settings::get('site_name') }}</h2>
        <div class="ad-brand-sub">CMS v2</div>
    </div>

    <nav class="ad-nav">

        <a href="{{ route('dashboard') }}"
           class="ad-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
            <svg class="ad-ico" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            ড্যাসবোর্ড
        </a>

        <div class="ad-nav-label">কনটেন্ট</div>

        <a href="{{ route('news.index') }}"
           class="ad-link {{ request()->routeIs('news.*') ? 'is-active' : '' }}">
            <svg class="ad-ico" viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
            সংবাদ
        </a>

        @can('manage-categories')
            <a href="{{ route('categories.index') }}"
               class="ad-link {{ request()->routeIs('categories.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                ক্যাটাগরি
            </a>
        @endcan

        @can('manage-tags')
            <a href="{{ route('tags.index') }}"
               class="ad-link {{ request()->routeIs('tags.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M3 12V4h8l10 10-8 8L3 12z"/><circle cx="7.5" cy="8.5" r="1.2"/></svg>
                ট্যাগ
            </a>
        @endcan

        @can('manage-layout')
            <a href="{{ route('home-layout.edit') }}"
               class="ad-link {{ request()->routeIs('home-layout.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                হোমপেজ সাজান
            </a>
        @endcan

        @can('manage-layout')
            <a href="{{ route('article-design.edit') }}"
               class="ad-link {{ request()->routeIs('article-design.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M6 3h8l5 5v13H6z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>
                সংবাদ পেজ ডিজাইন
            </a>
        @endcan

        @can('manage-layout')
            <a href="{{ route('visual-builder.index') }}"
               class="ad-link {{ request()->routeIs('visual-builder.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg>
                ডেটা ভিজ্যুয়াল বিল্ডার
            </a>
        @endcan


        @can('manage-layout')
            <a href="{{ route('menu.edit') }}"
               class="ad-link {{ request()->routeIs('menu.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                মেনু সাজান
            </a>
        @endcan

        @can('manage-layout')
            <a href="{{ route('sidebar.edit') }}"
               class="ad-link {{ request()->routeIs('sidebar.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M15 3v18"/></svg>
                সাইডবার সাজান
            </a>
        @endcan  

        @can('manage-polls')
            <a href="{{ route('admin.polls.index') }}"
               class="ad-link {{ request()->routeIs('admin.polls.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M5 20V11M12 20V4M19 20v-6M3 20h18"/></svg>
                অনলাইন ভোট
            </a>
        @endcan

        @can('manage-photocards')
            <a href="{{ route('photocards.index') }}"
               class="ad-link {{ request()->routeIs('photocards.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15l3-4 3 3 2-2 3 3"/></svg>
                ফটোকার্ড
            </a>
        @endcan

        
        @can('manage-ads')
            <a href="{{ route('ads.index') }}"
               class="ad-link {{ request()->routeIs('ads.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M3 11v2a1 1 0 001 1h2l5 4V6L6 10H4a1 1 0 00-1 1z"/><path d="M15 9a4 4 0 010 6M18 6a8 8 0 010 12"/></svg>
                বিজ্ঞাপন
            </a>
        @endcan

            <a href="{{ route('media.index') }}"
           class="ad-link {{ request()->routeIs('media.*') ? 'is-active' : '' }}">
            <svg class="ad-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 9"/></svg>
            ফটো গ্যালারি
        </a>

        @can('manage-videos')
            <a href="{{ route('videos.index') }}"
               class="ad-link {{ request()->routeIs('videos.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9l5 3-5 3V9z"/></svg>
                ভিডিও গ্যালারি
            </a>
        @endcan

        @can('manage-users')
            <div class="ad-nav-label">সিস্টেম</div>

            <a href="{{ route('users.index') }}"
               class="ad-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.7a3.5 3.5 0 010 6.6M18 14.3c2.2.8 3.5 2.7 3.5 5.7"/></svg>
                ইউজার
            </a>

            @can('manage-roles')
                <a href="{{ route('roles.edit') }}"
                   class="ad-link {{ request()->routeIs('roles.*') ? 'is-active' : '' }}">
                    <svg class="ad-ico" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 4.4-3.4 8.2-8 9-4.6-.8-8-4.6-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/></svg>
                    রোল ও অনুমতি
                </a>
            @endcan

            <a href="{{ route('activity.index') }}"
               class="ad-link {{ request()->routeIs('activity.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                কার্যকলাপ লগ
            </a>
            <a href="{{ route('site-settings.edit') }}"
               class="ad-link {{ request()->routeIs('site-settings.*') ? 'is-active' : '' }}">
                <svg class="ad-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3h0a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5h0a1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8v0a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/></svg>
                সাইট সেটিং
            </a> 
        @endcan

        <div class="ad-nav-label">অ্যাকাউন্ট</div>

        <a href="{{ route('profile.edit') }}"
           class="ad-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
            <svg class="ad-ico" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
            আমার প্রোফাইল
        </a>

        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ad-link">
            <svg class="ad-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg>
            সাইট দেখুন
        </a>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="ad-link is-danger">
                <svg class="ad-ico" viewBox="0 0 24 24"><path d="M9 4H5a2 2 0 00-2 2v12a2 2 0 002 2h4M16 8l4 4-4 4M20 12H9"/></svg>
                লগআউট
            </button>
        </form>

    </nav>

</aside>