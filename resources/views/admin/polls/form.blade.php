@php
    $locked = $poll->exists && $poll->totalVotes() > 0;
    $oldOptions = old('options');

    if (is_array($oldOptions)) {
        $rows = array_values($oldOptions);
    } elseif ($poll->exists) {
        $rows = $poll->options->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'votes' => $o->votes_count])->all();
    } else {
        $rows = [['label' => 'হ্যাঁ ভোট'], ['label' => 'না ভোট'], ['label' => 'মন্তব্য নেই']];
    }

    $activeChecked = $errors->any() ? old('is_active') !== null : (bool) $poll->is_active;
    $publishedValue = old('published_at', $poll->published_at ? $poll->published_at->format('Y-m-d\TH:i') : '');
    $imageUrlValue = old('image_url', $poll->image && preg_match('#^https?://#i', $poll->image) ? $poll->image : '');
    $imagePreview = old('image_url') ?: ($poll->image ? $poll->imageUrl(600) : '');
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $poll->exists ? 'ভোট সম্পাদনা' : 'নতুন ভোট' }}</h2>
    </x-slot>

    <div class="ad-page">

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>{{ $poll->exists ? 'ভোট সম্পাদনা' : 'নতুন ভোট' }}</h1>
            <a href="{{ route('admin.polls.index') }}" class="ad-tab">সব ভোট</a>
        </div>

        <form method="POST" action="{{ $poll->exists ? route('admin.polls.update', $poll) : route('admin.polls.store') }}" class="ad-form" enctype="multipart/form-data">
            @csrf
            @if($poll->exists)
                @method('PUT')
            @endif

            <div class="ad-panel" style="padding:1rem;">

                <label for="poll-question"><b>প্রশ্ন</b></label>
                <textarea id="poll-question" name="question" rows="3" maxlength="500" class="ad-field" style="width:100%;margin:.4rem 0 1rem;" required>{{ old('question', $poll->question) }}</textarea>

                <label><b>ভোটের ছবি</b> <span class="ad-hint">(ঐচ্ছিক — সাইডবারের কার্ডে সুন্দরভাবে বসবে)</span></label>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-start;margin:.5rem 0 1.2rem;">
                    <div id="poll-image-preview" style="width:220px;aspect-ratio:16/9;border:1px dashed #cbd5e1;border-radius:.6rem;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#64748b;font-size:.85rem;">
                        @if($imagePreview)
                            <img src="{{ $imagePreview }}" alt="ভোটের ছবি" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            <span>ছবি নেই</span>
                        @endif
                    </div>

                    <div style="flex:1;min-width:240px;">
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="ad-field" style="width:100%;">
                        <div class="ad-hint">JPG, PNG বা WebP; সর্বোচ্চ ৪ মেগাবাইট। আপলোড করলে নিচের লিংকের চেয়ে এটিই অগ্রাধিকার পাবে।</div>

                        <input type="url" name="image_url" value="{{ $imageUrlValue }}" placeholder="অথবা ছবির লিংক: https://..." class="ad-field" style="width:100%;margin-top:.5rem;">

                        @if($poll->exists && $poll->image)
                            <label class="ad-check" style="margin-top:.45rem;">
                                <input type="checkbox" name="remove_image" value="1"> বর্তমান ছবিটি সরান
                            </label>
                        @endif
                    </div>
                </div>

                <label><b>অপশন</b> <span class="ad-hint">(২ থেকে ৬টি)</span></label>

                @if($locked)
                    <p class="ad-hint">এই ভোটে ইতিমধ্যে ভোট পড়েছে, তাই শুধু লেখা বদলানো যাবে; অপশন যোগ বা বাদ দেওয়া যাবে না।</p>
                @endif

                <div id="poll-options" style="margin:.4rem 0;">
                    @foreach($rows as $i => $row)
                        <div data-row style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem;">
                            <input type="hidden" name="options[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                            <input type="text" name="options[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}" maxlength="120" class="ad-field" style="flex:1;" required>
                            @if($locked)
                                <span class="ad-hint" style="margin:0;white-space:nowrap;">{{ \App\Support\BanglaTime::digits($row['votes'] ?? 0) }} ভোট</span>
                            @else
                                <button type="button" data-remove class="ad-tab" aria-label="অপশন বাদ দিন">&times;</button>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if(! $locked)
                    <button type="button" id="poll-add" class="ad-tab">+ অপশন যোগ করুন</button>

                    <template id="poll-opt-tpl">
                        <div data-row style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem;">
                            <input type="hidden" name="options[__I__][id]" value="">
                            <input type="text" name="options[__I__][label]" value="" maxlength="120" class="ad-field" style="flex:1;" required>
                            <button type="button" data-remove class="ad-tab" aria-label="অপশন বাদ দিন">&times;</button>
                        </div>
                    </template>
                @endif

                <div style="display:flex;gap:2rem;flex-wrap:wrap;margin-top:1.2rem;align-items:center;">
                    <label class="ad-check">
                        <input type="checkbox" name="is_active" value="1" @checked($activeChecked)> চালু (ভোট নেওয়া হবে ও সাইডবারে দেখাবে)
                    </label>

                    <label>প্রকাশের সময়
                        <input type="datetime-local" name="published_at" value="{{ $publishedValue }}" class="ad-field">
                    </label>
                </div>
                <p class="ad-hint">প্রকাশের সময় ফাঁকা রাখলে এখনই প্রকাশ হবে।</p>

            </div>

            <div class="ad-sticky-save">
                <button type="submit" class="ad-btn-solid">সংরক্ষণ করুন</button>
            </div>
        </form>

    </div>

    <script>
        (function () {
            var box = document.getElementById('poll-options');
            var add = document.getElementById('poll-add');
            var tpl = document.getElementById('poll-opt-tpl');

            if (! box || ! add || ! tpl) { return; }

            var next = box.children.length + 100;   // keeps new row indexes unique

            add.addEventListener('click', function () {
                if (box.children.length >= 6) { return; }
                box.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__I__/g, String(next++)));
            });

            box.addEventListener('click', function (event) {
                var button = event.target.closest('[data-remove]');

                if (! button || box.children.length <= 2) { return; }

                button.closest('[data-row]').remove();
            });
        })();
    </script>

    <script>
        (function () {
            var input = document.querySelector('input[name="image"]');
            var box = document.getElementById('poll-image-preview');

            if (! input || ! box) { return; }

            input.addEventListener('change', function () {
                var file = input.files && input.files[0];

                if (! file) { return; }

                var img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = 'ভোটের ছবি';
                img.style.cssText = 'width:100%;height:100%;object-fit:cover;';

                box.replaceChildren(img);
            });
        })();
    </script>
</x-app-layout>
