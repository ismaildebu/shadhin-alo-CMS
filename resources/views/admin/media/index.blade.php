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

                            <img src="{{ \App\Models\Media::thumbUrl($item->filename, 400) }}" alt="" class="ad-media-img" loading="lazy">
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

                                    <button type="button" class="ad-act mm-open"
                                            data-url="{{ route('media.update', $item) }}"
                                            data-img="{{ \App\Models\Media::thumbUrl($item->filename, 800) }}"
                                            data-name="{{ $item->original_name ?: $item->filename }}"
                                            data-alt="{{ $item->alt }}"
                                            data-fx="{{ $item->focus_x }}"
                                            data-fy="{{ $item->focus_y }}">
                                        বিবরণ ও ফোকাস{{ $item->alt ? ' ✓' : '' }}
                                    </button>

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
    {{-- ছবির বিবরণ (alt) ও ফোকাস পয়েন্ট সম্পাদনার পর্দা --}}
    <div id="media-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:1000;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;border-radius:8px;max-width:780px;width:100%;max-height:94vh;overflow:auto;padding:18px;">
            <h3 id="mm-title" style="margin:0 0 6px;font-weight:700;font-size:1.05rem;word-break:break-all;"></h3>

            <p class="ad-hint" style="margin:0 0 8px;">ছবির যে অংশ সবসময় দেখাতে চান (যেমন মুখ), সেখানে ক্লিক করুন। কার্ডে ছবি কাটা পড়লেও ওই অংশ থাকবে।</p>

            <div id="mm-stage" style="position:relative;display:inline-block;cursor:crosshair;max-width:100%;line-height:0;">
                <img id="mm-img" src="" alt="" style="display:block;max-width:100%;max-height:48vh;">
                <span id="mm-dot" style="display:none;position:absolute;width:24px;height:24px;margin:-12px 0 0 -12px;border:3px solid #fff;border-radius:50%;box-shadow:0 0 0 2px #e11d48,0 0 6px rgba(0,0,0,.6);pointer-events:none;"></span>
            </div>

            <div style="display:flex;gap:14px;align-items:flex-end;margin:10px 0;flex-wrap:wrap;">
                <div>
                    <div class="ad-hint" style="margin:0 0 3px;">চওড়া কার্ড (16:9) নমুনা</div>
                    <img id="mm-prev1" src="" alt="" style="width:176px;height:99px;object-fit:cover;border:1px solid #ddd;border-radius:4px;">
                </div>
                <div>
                    <div class="ad-hint" style="margin:0 0 3px;">বর্গাকার (1:1) নমুনা</div>
                    <img id="mm-prev2" src="" alt="" style="width:99px;height:99px;object-fit:cover;border:1px solid #ddd;border-radius:4px;">
                </div>
            </div>

            <label for="mm-alt" style="display:block;font-weight:600;margin-top:6px;">ছবির বিবরণ (alt)</label>
            <textarea id="mm-alt" rows="2" maxlength="255" class="ad-input" style="width:100%;" placeholder="ছবিতে কী দেখা যাচ্ছে, এক বাক্যে লিখুন"></textarea>
            <p class="ad-hint" style="margin:4px 0 0;">যারা ছবি দেখতে পান না তাদের জন্য এবং গুগলের জন্য। খালি রাখলে সংবাদের শিরোনাম ব্যবহার হবে।</p>

            <div id="mm-msg" style="min-height:20px;margin-top:8px;color:#b91c1c;"></div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
                <button type="button" id="mm-save" class="ad-btn-solid">সংরক্ষণ করুন</button>
                <button type="button" id="mm-clear" class="ad-btn-dark">ফোকাস মুছুন</button>
                <button type="button" id="mm-cancel" class="ad-btn-dark">বাতিল</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('media-modal');
            if (!modal) { return; }

            var csrf = @js(csrf_token());
            var img = document.getElementById('mm-img');
            var stage = document.getElementById('mm-stage');
            var dot = document.getElementById('mm-dot');
            var prev1 = document.getElementById('mm-prev1');
            var prev2 = document.getElementById('mm-prev2');
            var altEl = document.getElementById('mm-alt');
            var msg = document.getElementById('mm-msg');
            var title = document.getElementById('mm-title');
            var saveBtn = document.getElementById('mm-save');
            var clearBtn = document.getElementById('mm-clear');
            var cancelBtn = document.getElementById('mm-cancel');

            var current = null;
            var fx = null;
            var fy = null;

            function paint() {
                var pos = (fx === null || fy === null) ? '50% 50%' : fx + '% ' + fy + '%';
                prev1.style.objectPosition = pos;
                prev2.style.objectPosition = pos;

                if (fx === null || fy === null) {
                    dot.style.display = 'none';
                    return;
                }

                dot.style.display = 'block';
                dot.style.left = fx + '%';
                dot.style.top = fy + '%';
            }

            function close() {
                modal.style.display = 'none';
                current = null;
            }

            Array.prototype.forEach.call(document.querySelectorAll('.mm-open'), function (btn) {
                btn.addEventListener('click', function () {
                    current = btn;
                    title.textContent = btn.dataset.name || '';
                    img.src = btn.dataset.img;
                    prev1.src = btn.dataset.img;
                    prev2.src = btn.dataset.img;
                    altEl.value = btn.dataset.alt || '';
                    fx = btn.dataset.fx === '' ? null : Number(btn.dataset.fx);
                    fy = btn.dataset.fy === '' ? null : Number(btn.dataset.fy);
                    msg.textContent = '';
                    saveBtn.disabled = false;
                    paint();
                    modal.style.display = 'flex';
                });
            });

            stage.addEventListener('click', function (e) {
                var r = img.getBoundingClientRect();

                if (!r.width || !r.height) { return; }

                fx = Math.min(100, Math.max(0, Math.round((e.clientX - r.left) / r.width * 100)));
                fy = Math.min(100, Math.max(0, Math.round((e.clientY - r.top) / r.height * 100)));
                paint();
            });

            clearBtn.addEventListener('click', function () {
                fx = null;
                fy = null;
                paint();
            });

            cancelBtn.addEventListener('click', close);

            modal.addEventListener('click', function (e) {
                if (e.target === modal) { close(); }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && current) { close(); }
            });

            saveBtn.addEventListener('click', function () {
                if (!current) { return; }

                saveBtn.disabled = true;
                msg.style.color = '#374151';
                msg.textContent = 'সংরক্ষণ হচ্ছে…';

                fetch(current.dataset.url, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ alt: altEl.value, focus_x: fx, focus_y: fy })
                })
                    .then(function (res) {
                        return res.json().then(function (body) { return { ok: res.ok, body: body }; });
                    })
                    .then(function (r) {
                        if (r.ok) {
                            location.reload();
                            return;
                        }

                        var text = 'সংরক্ষণ ব্যর্থ হয়েছে।';

                        if (r.body && r.body.errors) {
                            var first = Object.keys(r.body.errors)[0];
                            text = r.body.errors[first][0];
                        } else if (r.body && r.body.message) {
                            text = r.body.message;
                        }

                        msg.style.color = '#b91c1c';
                        msg.textContent = text;
                        saveBtn.disabled = false;
                    })
                    .catch(function () {
                        msg.style.color = '#b91c1c';
                        msg.textContent = 'সংরক্ষণ ব্যর্থ হয়েছে। ইন্টারনেট সংযোগ দেখুন।';
                        saveBtn.disabled = false;
                    });
            });
        })();
    </script>
</x-app-layout>