@php
    $isEditor = auth()->user()->isEditorOrAdmin();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ফটো গ্যালারি</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>ফটো গ্যালারি <span class="ad-panel-note">({{ $total }}টি ছবি)</span></h1>
        </div>

        <div class="ad-panel" style="margin-bottom:20px;">
            <form action="{{ route('media.store') }}" method="POST" enctype="multipart/form-data" class="ad-upload ad-form">
                @csrf
                <input type="file" name="files[]" multiple required accept="image/jpeg,image/png,image/webp">
                <button type="submit" class="ad-btn-solid">আপলোড করুন</button>
                <p class="ad-hint" style="width:100%;margin:0;">jpg, png, webp; প্রতিটি সর্বোচ্চ ২০ MB (আপলোডের সময় নিজে থেকে ১২০০×৮০০ মাপে এবং ১ MB-এর নিচে নামানো হয়); একবারে সর্বোচ্চ ১০টি।</p>
            </form>
        </div>

        <form method="GET" class="ad-search">
            <input type="text" name="q" value="{{ $term }}" placeholder="ফাইলের নামে খুঁজুন…" class="ad-input">
            <button type="submit" class="ad-btn-dark">খুঁজুন</button>
        </form>

        <div class="ad-panel">
            @if($media->isNotEmpty() && $isEditor)
                {{-- একসাথে অনেক ছবি মোছার ফর্ম। প্রতিটি ছবির checkbox form="bulk-form" দিয়ে এর সাথে যুক্ত। --}}
                <form id="bulk-form" action="{{ route('media.bulk-destroy') }}" method="POST"
                      style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:10px 0 14px;">
                    @csrf
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" id="select-all">
                        <span>এই পাতার সব বাছুন</span>
                    </label>
                    <span id="selected-count" class="ad-hint" style="margin:0;">০টি বাছা হয়েছে</span>
                    <button type="submit" id="bulk-delete" class="ad-btn-dark" disabled>বাছাইগুলো মুছুন</button>
                </form>
            @endif

            @if($media->isEmpty())
                <div class="ad-empty">গ্যালারিতে কোনো ছবি নেই।</div>
            @else
                <div class="ad-media-grid">
                    @foreach($media as $item)
                        @php
                            $count = (int) ($usage[$item->filename] ?? 0);
                            $confirmMessage = $count > 0
                                ? 'ছবিটি '.$count.'টি সংবাদে ব্যবহৃত হচ্ছে। মুছলে ওই সংবাদগুলোর প্রধান ছবি ফাঁকা হবে এবং লেখার ভেতরের ছবি ভেঙে যাবে। মুছে ফেলতে চান?'
                                : 'ছবিটি গ্যালারি থেকে মুছে ফেলতে চান?';
                        @endphp
                        <div class="ad-media-card" style="position:relative;">
                            @if($isEditor)
                                <label style="position:absolute;top:8px;left:8px;z-index:2;background:#fff;padding:5px;border-radius:4px;line-height:0;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.35);">
                                    <input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-form"
                                           class="media-check" data-used="{{ $count }}">
                                </label>
                            @endif

                            <img src="{{ $item->url }}" alt="" class="ad-media-img" loading="lazy">
                            <div class="ad-media-info">
                                <div class="ad-media-name" title="{{ $item->original_name }}">{{ $item->original_name ?: $item->filename }}</div>
                                <div class="ad-media-meta">
                                    {{ $item->size_label }} &middot; {{ $item->created_at->format('d M Y') }}
                                    @if($item->uploader) &middot; {{ $item->uploader->name }} @endif
                                </div>

                                <div class="ad-media-foot">
                                    @if($count > 0)
                                        <span class="ad-badge st-published">{{ $count }}টি সংবাদে</span>
                                    @else
                                        <span class="ad-badge st-draft">ব্যবহার হয়নি</span>
                                    @endif

                                    @if($isEditor)
                                        <form action="{{ route('media.destroy', $item) }}" method="POST"
                                              onsubmit="return confirm(@js($confirmMessage))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ad-act act-del">মুছুন</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($media->hasPages())
                <div class="ad-pager">{{ $media->links() }}</div>
            @endif
        </div>

    </div>

    @if($isEditor)
        <script>
            (function () {
                var form = document.getElementById('bulk-form');
                if (!form) { return; }

                var all = document.getElementById('select-all');
                var countEl = document.getElementById('selected-count');
                var button = document.getElementById('bulk-delete');

                function boxes() {
                    return Array.prototype.slice.call(document.querySelectorAll('input.media-check'));
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

                    var used = selected.filter(function (b) { return Number(b.dataset.used) > 0; }).length;
                    var message = selected.length + 'টি ছবি স্থায়ীভাবে মুছে ফেলা হবে।';

                    if (used > 0) {
                        message += '\n\nএর মধ্যে ' + used + 'টি ছবি সংবাদে ব্যবহৃত। ওই সংবাদগুলোর প্রধান ছবি ফাঁকা হবে এবং লেখার ভেতরের ছবি ভেঙে যাবে।';
                    }

                    message += '\n\nমুছলে আর ফেরানো যাবে না। চালিয়ে যাবেন?';

                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });

                refresh();
            })();
        </script>
    @endif
</x-app-layout>