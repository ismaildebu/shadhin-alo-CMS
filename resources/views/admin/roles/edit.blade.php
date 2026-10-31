<x-app-layout>
    <x-slot name="header">
        <div class="rp-head">
            <h1>রোল ও অনুমতি</h1>
            <p>কোন রোল কী করতে পারবে, এখান থেকে ঠিক করুন।</p>
        </div>
    </x-slot>

    @php
        // Bengali digits for the counters.
        $bn = fn ($n) => strtr((string) $n, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);

        // How the roles are grouped in the left list. A role that is not
        // listed here (e.g. one added later) still shows, under "অন্যান্য".
        $roleGroups = [
            'সম্পাদকীয়' => ['admin', 'deputy-editor', 'news-editor', 'joint-news-editor', 'editor', 'sub-editor'],
            'রিপোর্টিং' => ['chief-reporter', 'special-reporter', 'staff-reporter', 'reporter'],
            'প্রোডাকশন' => ['media-editor', 'designer', 'ad-manager'],
        ];

        $grouped = [];
        $placed = [];

        foreach ($roleGroups as $title => $slugs) {
            foreach ($slugs as $slug) {
                if (isset($roles[$slug])) {
                    $grouped[$title][] = $slug;
                    $placed[] = $slug;
                }
            }
        }

        $rest = array_values(array_diff(array_keys($roles), $placed));

        if ($rest !== []) {
            $grouped['অন্যান্য'] = $rest;
        }

        $total = count($permissions);
        $count = fn ($role) => count(array_filter($matrix[$role] ?? []));
    @endphp

    <style>
        [x-cloak] { display: none !important; }

        .rp-head h1 { margin: 0; font-size: 1.3rem; font-weight: 700; line-height: 1.3; }
        .rp-head p { margin: 2px 0 0; font-size: .88rem; color: #6b7280; }

        .rp-wrap {
            --rp-accent: #1d4ed8;
            --rp-accent-soft: #eef3ff;
            --rp-ink: #111827;
            --rp-muted: #6b7280;
            --rp-line: #e5e7eb;
            --rp-surface: #ffffff;
            --rp-warn: #b45309;
            --rp-warn-soft: #fffbeb;
            --rp-ok: #15803d;
            --rp-ok-soft: #e8f6ee;
            max-width: 1120px;
            margin: 0 auto;
            padding: 22px 16px 40px;
            color: var(--rp-ink);
        }

        .rp-flash, .rp-error { margin: 0 0 16px; padding: 11px 14px; border-radius: 10px; font-size: .92rem; }
        .rp-flash { background: var(--rp-ok-soft); color: #14532d; border: 1px solid #b7e4c7; }
        .rp-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .rp-note {
            margin: 0 0 20px; padding: 12px 14px; border-radius: 10px;
            background: var(--rp-accent-soft); color: #1e3a8a; font-size: .88rem; line-height: 1.65;
        }

        .rp-layout { display: grid; grid-template-columns: 250px minmax(0, 1fr); gap: 20px; align-items: start; }

        /* ---------- role list ---------- */
        .rp-roles {
            position: sticky; top: 16px;
            background: var(--rp-surface); border: 1px solid var(--rp-line); border-radius: 14px;
            padding: 8px; max-height: calc(100vh - 32px); overflow-y: auto;
        }
        .rp-roles-title {
            padding: 12px 10px 5px; font-size: .72rem; font-weight: 700; letter-spacing: .04em; color: var(--rp-muted);
        }
        .rp-roles-title:first-child { padding-top: 6px; }
        .rp-role {
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            padding: 9px 10px; border-radius: 9px; text-decoration: none; color: var(--rp-ink);
            font-size: .92rem; border: 1px solid transparent;
        }
        .rp-role:hover { background: #f3f4f6; }
        .rp-role.is-on { background: var(--rp-accent-soft); color: var(--rp-accent); font-weight: 700; border-color: #c7d6fb; }
        .rp-role-count {
            flex: none; font-size: .72rem; font-weight: 600; color: var(--rp-muted);
            background: #f3f4f6; border-radius: 999px; padding: 2px 8px;
        }
        .rp-role.is-on .rp-role-count { background: #fff; color: var(--rp-accent); }

        /* ---------- editor card ---------- */
        .rp-card { background: var(--rp-surface); border: 1px solid var(--rp-line); border-radius: 14px; overflow: hidden; }
        .rp-card-head {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
            padding: 18px 20px 16px; border-bottom: 1px solid var(--rp-line);
        }
        .rp-card-head h2 { margin: 0; font-size: 1.15rem; font-weight: 700; }
        .rp-card-head p { margin: 3px 0 0; font-size: .85rem; color: var(--rp-muted); }
        .rp-dirty {
            flex: none; font-size: .78rem; font-weight: 600; color: var(--rp-warn);
            background: var(--rp-warn-soft); border: 1px solid #fde68a; border-radius: 999px; padding: 4px 10px;
        }

        .rp-group { padding: 6px 20px 4px; }
        .rp-group + .rp-group { border-top: 1px solid #f1f5f9; }
        .rp-group-title { margin: 14px 0 4px; font-size: .74rem; font-weight: 700; letter-spacing: .04em; color: var(--rp-muted); }

        .rp-perm {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
            padding: 12px 0; cursor: pointer;
        }
        .rp-perm + .rp-perm { border-top: 1px dashed #eef0f3; }
        .rp-perm.is-fixed { cursor: default; }
        .rp-perm.is-danger {
            margin: 6px -12px; padding: 12px; border: 1px solid #fde68a; border-radius: 10px; background: var(--rp-warn-soft);
        }
        .rp-perm-text { display: block; min-width: 0; }
        .rp-perm-label { display: block; font-size: .96rem; font-weight: 600; line-height: 1.5; }
        .rp-perm-help { display: block; margin-top: 3px; font-size: .84rem; color: var(--rp-muted); line-height: 1.6; }
        .rp-perm.is-danger .rp-perm-help { color: var(--rp-warn); }
        .rp-tag {
            display: inline-flex; align-items: center; gap: 4px; margin-left: 8px; vertical-align: 1px;
            font-size: .7rem; font-weight: 600; color: #475569; background: #eef2f7; border-radius: 999px; padding: 2px 8px;
        }
        .rp-tag svg { width: 11px; height: 11px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

        /* toggle switch (selectors are specific so the Tailwind forms plugin cannot restyle it) */
        input.rp-switch[type='checkbox'] {
            -webkit-appearance: none; appearance: none; flex: none; position: relative;
            width: 44px; height: 25px; margin: 2px 0 0; padding: 0; border: 0; border-radius: 999px;
            background-color: #cbd5e1; background-image: none; box-shadow: none; cursor: pointer;
            transition: background-color .15s ease; color: transparent;
        }
        input.rp-switch[type='checkbox']::after {
            content: ''; position: absolute; top: 3px; left: 3px; width: 19px; height: 19px; border-radius: 50%;
            background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .3); transition: transform .15s ease;
        }
        input.rp-switch[type='checkbox']:checked { background-color: var(--rp-accent); background-image: none; border: 0; }
        input.rp-switch[type='checkbox']:checked::after { transform: translateX(19px); }
        input.rp-switch[type='checkbox']:focus-visible { outline: 2px solid var(--rp-accent); outline-offset: 2px; box-shadow: none; }
        input.rp-switch[type='checkbox']:disabled { cursor: not-allowed; opacity: .55; }

        /* save bar */
        .rp-bar {
            position: sticky; bottom: 0; z-index: 5; display: flex; align-items: center; justify-content: flex-end; gap: 14px;
            padding: 14px 20px; background: rgba(255, 255, 255, .96); border-top: 1px solid var(--rp-line);
            backdrop-filter: blur(6px);
        }
        .rp-bar-hint { margin-right: auto; font-size: .84rem; color: var(--rp-muted); }
        .rp-btn {
            padding: 10px 22px; border-radius: 10px; border: 1px solid transparent; font-weight: 600; font-size: .94rem;
            cursor: pointer; font-family: inherit; line-height: 1.2;
        }
        .rp-btn-primary { background: var(--rp-accent); color: #fff; }
        .rp-btn-primary:hover { background: #1e40af; }
        .rp-btn-primary:disabled { background: #cbd5e1; color: #fff; cursor: not-allowed; }

        .rp-reset { display: flex; flex-wrap: wrap; gap: 6px 18px; margin: 14px 4px 0; }
        .rp-link { background: none; border: 0; padding: 4px 0; font: inherit; font-size: .86rem; color: var(--rp-muted); cursor: pointer; text-decoration: underline; text-underline-offset: 3px; }
        .rp-link:hover { color: var(--rp-ink); }
        .rp-link.is-danger:hover { color: #b91c1c; }

        /* ---------- overview table ---------- */
        .rp-overview { margin-top: 28px; }
        .rp-overview h2 { margin: 0 0 4px; font-size: 1.05rem; }
        .rp-overview > p { margin: 0 0 12px; font-size: .85rem; color: var(--rp-muted); }
        .rp-scroll { overflow-x: auto; background: var(--rp-surface); border: 1px solid var(--rp-line); border-radius: 14px; }
        .rp-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 780px; }
        .rp-table th, .rp-table td { padding: 9px 8px; text-align: center; border-bottom: 1px solid #f1f5f9; font-size: .82rem; }
        .rp-table thead th { background: #f8fafc; font-weight: 700; color: #334155; white-space: nowrap; position: sticky; top: 0; }
        .rp-table tbody tr:last-child td { border-bottom: 0; }
        .rp-table th:first-child, .rp-table td:first-child {
            position: sticky; left: 0; z-index: 1; text-align: left; white-space: nowrap; padding-left: 14px; background: #fff;
            border-right: 1px solid #eef0f3;
        }
        .rp-table thead th:first-child { background: #f8fafc; }
        .rp-table tr.is-sel td { background: var(--rp-accent-soft); }
        .rp-table td:first-child a { color: var(--rp-ink); text-decoration: none; font-weight: 600; }
        .rp-table td:first-child a:hover { color: var(--rp-accent); }
        .rp-dot { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; font-size: .72rem; font-weight: 700; }
        .rp-dot.is-on { background: var(--rp-ok-soft); color: var(--rp-ok); }
        .rp-dot.is-off { color: #cbd5e1; }

        /* ---------- small screens ---------- */
        @media (max-width: 859px) {
            .rp-layout { grid-template-columns: 1fr; gap: 16px; }
            .rp-roles { position: static; max-height: none; display: flex; flex-wrap: wrap; gap: 6px; padding: 10px; }
            .rp-roles-title { display: none; }
            .rp-role { border: 1px solid var(--rp-line); padding: 7px 12px; font-size: .88rem; }
            .rp-role-count { display: none; }
            .rp-card-head, .rp-bar { padding-left: 16px; padding-right: 16px; }
            .rp-group { padding-left: 16px; padding-right: 16px; }
            .rp-perm.is-danger { margin-left: -8px; margin-right: -8px; }
            .rp-dirty { display: none; }
        }
    </style>

    <div class="rp-wrap">

        @if (session('success'))
            <div class="rp-flash">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="rp-error">{{ $errors->first() }}</div>
        @endif

        <p class="rp-note">
            বাঁ দিক থেকে একটি রোল বেছে তার অনুমতি চালু বা বন্ধ করুন। সংরক্ষণের পর সাথে সাথে কার্যকর হয়।
            সুপার অ্যাডমিন সবসময় সব অনুমতি পায়, তাই তালিকায় নেই। অ্যাডমিনের "প্যানেলে প্রবেশ" ও "ইউজার ম্যানেজমেন্ট" বন্ধ করা যায় না, যাতে কেউ নিজেকে লক আউট না করে।
        </p>

        <div class="rp-layout">

            <nav class="rp-roles" aria-label="রোল">
                @foreach ($grouped as $title => $slugs)
                    <div class="rp-roles-title">{{ $title }}</div>

                    @foreach ($slugs as $slug)
                        <a href="{{ route('roles.edit', ['role' => $slug]) }}"
                           class="rp-role {{ $slug === $selected ? 'is-on' : '' }}"
                           aria-current="{{ $slug === $selected ? 'page' : 'false' }}">
                            <span>{{ $roles[$slug] }}</span>
                            <span class="rp-role-count">{{ $bn($count($slug)) }}/{{ $bn($total) }}</span>
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <div>
                <form method="POST"
                      action="{{ route('roles.update') }}"
                      class="rp-card"
                      x-data="{ init: '', dirty: false, snap() { return JSON.stringify(Array.from(this.$el.querySelectorAll('input[type=checkbox]:not(:disabled)')).map(c => c.checked)); }, check() { this.dirty = this.snap() !== this.init; } }"
                      x-init="init = snap()"
                      x-on:change="check()">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="role" value="{{ $selected }}">

                    <header class="rp-card-head">
                        <div>
                            <h2>{{ $roles[$selected] }}</h2>
                            <p>মোট {{ $bn($total) }}টির মধ্যে {{ $bn($count($selected)) }}টি অনুমতি চালু আছে</p>
                        </div>
                        <span class="rp-dirty" x-show="dirty" x-cloak>অসংরক্ষিত পরিবর্তন</span>
                    </header>

                    @foreach ($groups as $groupKey => $groupLabel)
                        @php
                            $items = collect($permissions)->filter(fn ($meta) => $meta['group'] === $groupKey);
                        @endphp

                        @if ($items->isNotEmpty())
                            <section class="rp-group">
                                <h3 class="rp-group-title">{{ $groupLabel }}</h3>

                                @foreach ($items as $key => $meta)
                                    @php
                                        $locked = in_array($selected, $meta['locked'], true);
                                        $isImplied = $implied[$key] ?? false;
                                        $fixed = $locked || $isImplied;
                                    @endphp

                                    <label class="rp-perm {{ $meta['danger'] ? 'is-danger' : '' }} {{ $fixed ? 'is-fixed' : '' }}">
                                        <span class="rp-perm-text">
                                            <span class="rp-perm-label">
                                                {{ $meta['label'] }}
                                                @if ($locked)
                                                    <span class="rp-tag"><svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg>সবসময় চালু</span>
                                                @elseif ($isImplied)
                                                    <span class="rp-tag">অন্য অনুমতির অন্তর্ভুক্ত</span>
                                                @endif
                                            </span>
                                            <span class="rp-perm-help">{{ $meta['help'] }}</span>
                                        </span>

                                        <input
                                            type="checkbox"
                                            class="rp-switch"
                                            name="perm[{{ $key }}]"
                                            value="1"
                                            @checked($effective[$key] ?? false)
                                            @disabled($fixed)
                                        >
                                    </label>
                                @endforeach
                            </section>
                        @endif
                    @endforeach

                    <div class="rp-bar">
                        <span class="rp-bar-hint" x-show="dirty" x-cloak>সংরক্ষণ না করলে পরিবর্তন হারাবে।</span>
                        <button type="submit" class="rp-btn rp-btn-primary" x-bind:disabled="! dirty">সংরক্ষণ করুন</button>
                    </div>
                </form>

                <div class="rp-reset">
                    <form method="POST" action="{{ route('roles.reset') }}"
                          onsubmit="return confirm('এই রোলটি ডিফল্ট অনুমতিতে ফিরবে। নিশ্চিত?')">
                        @csrf
                        <input type="hidden" name="role" value="{{ $selected }}">
                        <button type="submit" class="rp-link">এই রোল ডিফল্টে ফেরান</button>
                    </form>

                    <form method="POST" action="{{ route('roles.reset') }}"
                          onsubmit="return confirm('সব রোল ডিফল্ট অনুমতিতে ফিরবে। নিশ্চিত?')">
                        @csrf
                        <button type="submit" class="rp-link is-danger">সব রোল ডিফল্টে ফেরান</button>
                    </form>
                </div>
            </div>
        </div>

        <section class="rp-overview">
            <h2>সব রোলের সারসংক্ষেপ</h2>
            <p>এক নজরে কোন রোলের কী অনুমতি আছে। রোলের নামে ক্লিক করে সম্পাদনা করুন।</p>

            <div class="rp-scroll">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>রোল</th>
                            @foreach ($permissions as $meta)
                                <th>{{ $meta['short'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grouped as $title => $slugs)
                            @foreach ($slugs as $slug)
                                <tr class="{{ $slug === $selected ? 'is-sel' : '' }}">
                                    <td><a href="{{ route('roles.edit', ['role' => $slug]) }}">{{ $roles[$slug] }}</a></td>
                                    @foreach ($permissions as $key => $meta)
                                        <td>
                                            @if ($matrix[$slug][$key] ?? false)
                                                <span class="rp-dot is-on" title="আছে">✓</span>
                                            @else
                                                <span class="rp-dot is-off" title="নেই">–</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</x-app-layout>