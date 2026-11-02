@php
    $user = auth()->user();
    $cols = 4;
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">সংবাদ ব্যবস্থাপনা</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>{{ $user->isEditorOrAdmin() ? 'সব সংবাদ' : 'আমার সংবাদ' }}</h1>
            <a href="{{ route('news.create') }}" class="ad-btn-solid">+ নতুন সংবাদ</a>
        </div>

        {{-- status tabs --}}
        <div class="ad-tabs">
            <a href="{{ route('news.index', array_filter(['q' => $term, 'category' => $categoryId])) }}"
               class="ad-tab {{ ! $status ? 'is-active' : '' }}">
                সব <span class="ad-tab-count">({{ $counts->sum() }})</span>
            </a>

            @foreach(\App\Models\News::STATUS_LABELS as $key => $label)
                <a href="{{ route('news.index', array_filter(['status' => $key, 'q' => $term, 'category' => $categoryId])) }}"
                   class="ad-tab {{ $status === $key ? 'is-active' : '' }}">
                    {{ $label }} <span class="ad-tab-count">({{ (int) ($counts[$key] ?? 0) }})</span>
                </a>
            @endforeach

            @if($user->isEditorOrAdmin())
                <a href="{{ route('news.index', array_filter(['status' => 'trashed', 'category' => $categoryId])) }}"
                   class="ad-tab {{ $trashed ? 'is-active' : '' }}">
                    ট্র্যাশ <span class="ad-tab-count">({{ $trashedCount }})</span>
                </a>
            @endif
        </div>

        <form method="GET" class="ad-search">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <input type="text" name="q" value="{{ $term }}" placeholder="শিরোনামে খুঁজুন…" class="ad-input">
            <select name="category" class="ad-select">
                <option value="">সব ক্যাটাগরি</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="ad-btn-dark">খুঁজুন</button>
        </form>

        <div class="ad-panel">
            @if($news->isNotEmpty())
                {{-- একসাথে সর্বোচ্চ ১০০টি সংবাদ। প্রতিটি সারির checkbox form="bulk-form" দিয়ে এর সাথে যুক্ত। --}}
                <form id="bulk-form"
                      action="{{ $trashed ? route('news.bulk-force-delete') : route('news.bulk-destroy') }}"
                      method="POST" data-mode="{{ $trashed ? 'force' : 'trash' }}"
                      style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:10px 0 14px;">
                    @csrf
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" id="select-all">
                        <span>এই পাতার সব বাছুন (সর্বোচ্চ ১০০)</span>
                    </label>
                    <span id="selected-count" class="ad-hint" style="margin:0;">০টি বাছা হয়েছে</span>
                    @if($trashed)
                        <button type="submit" id="bulk-delete" class="ad-btn-dark ad-btn-danger" disabled>স্থায়ীভাবে মুছুন</button>
                    @else
                        <button type="submit" id="bulk-delete" class="ad-btn-dark" disabled>বাছাইগুলো ট্র্যাশে পাঠান</button>
                    @endif
                </form>
            @endif

            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th>সংবাদ</th>
                            <th>অবস্থা</th>
                            <th>কাজ</th>
                        </tr>
                    </thead>

                    <tbody>
                    @forelse($news as $item)
                        @php
                            $targets = $trashed ? [] : \App\Services\NewsWorkflow::allowedTargets($user, $item);
                        @endphp
                        <tr>
                            <td>
                                @if($trashed)
                                    <input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-form"
                                           class="news-check">
                                @else
                                    @can('delete', $item)
                                        <input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-form"
                                               class="news-check" data-published="{{ $item->status === 'published' ? 1 : 0 }}">
                                    @endcan
                                @endif
                            </td>

                            <td>
                                <div class="ad-news-cell">
                                    @if($item->image)
                                        <img src="{{ \App\Models\Media::thumbUrl($item->image, 400) }}" alt="" class="ad-thumb" loading="lazy">
                                    @else
                                        <span class="ad-thumb ad-thumb-empty">
                                            <svg class="ad-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 9"/></svg>
                                        </span>
                                    @endif

                                    <div>
                                        <div class="ad-news-title">
                                            {{ $item->title }}
                                            @if($item->breaking)<span class="ad-flag flag-breaking">ব্রেকিং</span>@endif
                                            @if($item->featured)<span class="ad-flag flag-featured">ফিচার্ড</span>@endif
                                            @if($item->is_lead)<span class="ad-flag flag-lead">লিড</span>@endif
                                        </div>
                                        <div class="ad-news-sub">
                                            #{{ $item->id }}
                                            &middot; {{ $item->category->name ?? '—' }}
                                            &middot; {{ $item->author->name ?? $item->reporter ?? '—' }}
                                            &middot; {{ $item->display_date->format('d M Y') }}
                                            &middot; পঠিত {{ number_format((int) $item->view_count) }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="ad-badge st-{{ $item->status }}">{{ $item->status_label }}</span>
                                @if($item->status === 'scheduled' && $item->published_at)
                                    <div class="ad-news-sub">{{ $item->published_at->timezone(config('app.timezone'))->format('d M, h:i A') }}</div>
                                @endif
                            </td>

                            <td>
                                <div class="ad-actions">
                                @if($trashed)
                                    <form action="{{ route('news.restore', $item) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="ad-act act-ok">পুনরুদ্ধার</button>
                                    </form>
                                @else
                                    @can('update', $item)
                                        <a href="{{ route('news.edit', $item) }}" class="ad-act act-edit">সম্পাদনা</a>
                                        <button type="button" class="ad-act" data-cat-toggle="{{ $item->id }}">ক্যাটাগরি</button>
                                    @endcan

                                    @can('view', $item)
                                        <a href="{{ route('news.preview', $item) }}" target="_blank" class="ad-act">প্রিভিউ</a>
                                    @endcan

                                    @can('view', $item)
                                        <a href="{{ route('news.card', $item) }}" class="ad-act">ফটোকার্ড</a>

                                        <form action="{{ route('news.video.store', $item) }}" method="POST"
                                              title="লোকাল FFmpeg দিয়ে ফেসবুক রিলস ভিডিও তৈরি হবে; প্রায় এক মিনিট লাগতে পারে"
                                              onsubmit="const b = this.querySelector('button'); b.disabled = true; b.textContent = 'তৈরি হচ্ছে…';">
                                            @csrf
                                            <button type="submit" class="ad-act act-video">ভিডিও তৈরি</button>
                                        </form>

                                        @if(\App\Support\NewsVideo::exists($item))
                                            <a href="{{ route('news.video.show', $item) }}" target="_blank" class="ad-act">ভিডিও দেখুন</a>
                                            <a href="{{ route('news.video.show', ['news' => $item, 'download' => 1]) }}" class="ad-act">ডাউনলোড</a>
                                        @endif
                                    @endcan

                                    @foreach($targets as $to)
                                        @continue($to === 'scheduled')
                                        @php
                                            $label = match (true) {
                                                $to === 'pending' => 'পর্যালোচনায় পাঠান',
                                                $to === 'published' => 'প্রকাশ',
                                                $to === 'archived' => 'আর্কাইভ',
                                                $to === 'draft' && $item->status === 'pending' => ($item->author_id === $user->id ? 'ফেরত নিন' : 'ফেরত পাঠান'),
                                                default => 'খসড়ায় নিন',
                                            };
                                            $askNote = $to === 'draft' && $item->status === 'pending' && $item->author_id !== $user->id;
                                        @endphp
                                        <form action="{{ route('news.transition', $item) }}" method="POST"
                                              @if($askNote) onsubmit="const n = prompt('ফেরত পাঠানোর কারণ লিখুন'); if (n === null || n.trim() === '') return false; this.note.value = n; return true;" @endif>
                                            @csrf
                                            <input type="hidden" name="to" value="{{ $to }}">
                                            <input type="hidden" name="note" value="">
                                            <button type="submit" class="ad-act {{ $to === 'published' ? 'act-ok' : 'act-flow' }}">{{ $label }}</button>
                                        </form>
                                    @endforeach

                                    @can('delete', $item)
                                        <form action="{{ route('news.destroy', $item) }}" method="POST"
                                              onsubmit="return confirm('সংবাদটি ট্র্যাশে সরাতে চান?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ad-act act-del">মুছুন</button>
                                        </form>
                                    @endcan
                                @endif
                                </div>
                            </td>
                        </tr>

                        @unless($trashed)
                            @can('update', $item)
                                <tr id="cat-row-{{ $item->id }}" class="ad-cat-row" hidden>
                                    <td colspan="{{ $cols }}">
                                        <form action="{{ route('news.category', $item) }}" method="POST" class="ad-cat-form">
                                            @csrf
                                            <strong>ক্যাটাগরি বাছুন:</strong>

                                            <div class="ad-category-checkboxes">
                                                @foreach($categories as $category)
                                                    <label class="ad-category-checkbox">
                                                        <input
                                                            type="checkbox"
                                                            name="category_ids[]"
                                                            value="{{ $category->id }}"
                                                            @checked($item->categories->contains('id', $category->id))
                                                        >
                                                        <span>{{ $category->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>

                                            <button type="submit" class="ad-btn-solid">সেভ</button>
                                            <button type="button" class="ad-btn-dark" data-cat-close="{{ $item->id }}">বাতিল</button>
                                        </form>
                                    </td>
                                </tr>
                            @endcan
                        @endunless
                    @empty
                        <tr><td colspan="{{ $cols }}" class="ad-empty">কোনো সংবাদ পাওয়া যায়নি।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($news->hasPages())
                <div class="ad-pager">{{ $news->links() }}</div>
            @endif
        </div>

    </div>

    <script>
            (function () {
                var form = document.getElementById('bulk-form');
                if (!form) { return; }

                var force = form.dataset.mode === 'force';
                var all = document.getElementById('select-all');
                var countEl = document.getElementById('selected-count');
                var button = document.getElementById('bulk-delete');

                function boxes() {
                    return Array.prototype.slice.call(document.querySelectorAll('input.news-check'));
                }

                function refresh() {
                    var selected = boxes().filter(function (b) { return b.checked; });
                    countEl.textContent = selected.length + 'টি বাছা হয়েছে';
                    button.disabled = selected.length === 0;
                    all.checked = selected.length > 0 && selected.length === boxes().length;
                    all.indeterminate = selected.length > 0 && selected.length < boxes().length;
                }

                all.addEventListener('change', function () {
                    boxes().forEach(function (b) { b.checked = all.checked; });
                    refresh();
                });

                boxes().forEach(function (b) { b.addEventListener('change', refresh); });

                form.addEventListener('submit', function (e) {
                    var selected = boxes().filter(function (b) { return b.checked; });

                    if (selected.length === 0) {
                        e.preventDefault();
                        return;
                    }

                    var message;

                    if (force) {
                        message = selected.length + 'টি সংবাদ স্থায়ীভাবে মুছে ফেলা হবে।'
                            + '\n\nএই কাজ ফিরিয়ে আনা যাবে না। চালিয়ে যাবেন?';
                    } else {
                        var published = selected.filter(function (b) { return Number(b.dataset.published) === 1; }).length;
                        message = selected.length + 'টি সংবাদ ট্র্যাশে সরানো হবে।';

                        if (published > 0) {
                            message += '\n\nএর মধ্যে ' + published + 'টি প্রকাশিত সংবাদ। ট্র্যাশে গেলে সেগুলো ওয়েবসাইট থেকে সরে যাবে।';
                        }

                        message += '\n\nপরে ট্র্যাশ থেকে পুনরুদ্ধার করা যাবে। চালিয়ে যাবেন?';
                    }

                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });

                refresh();
            })();

            (function () {
                // একটি সারির «ক্যাটাগরি» চাপলে শুধু সেই সারির নিচে ক্যাটাগরির ফর্ম খোলে; পাতা বদলায় না।
                document.querySelectorAll('[data-cat-toggle]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var row = document.getElementById('cat-row-' + button.dataset.catToggle);
                        if (!row) { return; }

                        var opening = row.hidden;

                        document.querySelectorAll('tr.ad-cat-row').forEach(function (other) { other.hidden = true; });
                        row.hidden = !opening;

                        if (opening) { row.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
                    });
                });

                document.querySelectorAll('[data-cat-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var row = document.getElementById('cat-row-' + button.dataset.catClose);
                        if (row) { row.hidden = true; }
                    });
                });
            })();
        </script>
</x-app-layout>