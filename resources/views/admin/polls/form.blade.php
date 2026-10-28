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

        <form method="POST" action="{{ $poll->exists ? route('admin.polls.update', $poll) : route('admin.polls.store') }}" class="ad-form">
            @csrf
            @if($poll->exists)
                @method('PUT')
            @endif

            <div class="ad-panel" style="padding:1rem;">

                <label for="poll-question"><b>প্রশ্ন</b></label>
                <textarea id="poll-question" name="question" rows="3" maxlength="500" class="ad-field" style="width:100%;margin:.4rem 0 1rem;" required>{{ old('question', $poll->question) }}</textarea>

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
</x-app-layout>
