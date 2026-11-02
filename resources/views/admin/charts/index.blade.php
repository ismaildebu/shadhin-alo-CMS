<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">গ্রাফ ও চার্ট</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>গ্রাফ ও চার্ট</h1>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <a href="{{ route('frontend.charts.index') }}" target="_blank" rel="noopener" class="ad-tab">সব গ্রাফ দেখুন</a>
                <a href="{{ route('charts.create') }}" class="ad-tab">+ নতুন গ্রাফ</a>
            </div>
        </div>

        <p class="ad-note">
            প্রতিটি গ্রাফের তথ্য আলাদাভাবে সংরক্ষিত থাকে। <b>প্রকাশিত</b> গ্রাফগুলো ক্রম অনুযায়ী
            <a href="{{ route('frontend.charts.index') }}" target="_blank" rel="noopener">/graphs</a> পেজে একসাথে দেখায় —
            এভাবেই এক পেজে একাধিক গ্রাফ বসানো যায়। নির্দিষ্ট কোনো সংবাদে একটি গ্রাফ বসাতে চাইলে
            <code>[chart id="সংখ্যা"]</code> শর্টকোডটি লেখার ভেতরে পেস্ট করুন।
            পাঠকদের জন্য মেনুতে <code>/graphs</code> লিংক যোগ করতে <a href="{{ route('menu.edit') }}">মেনু সাজান</a> পেজ ব্যবহার করুন।
        </p>

        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>শিরোনাম</th>
                            <th>ধরন</th>
                            <th>অবস্থা</th>
                            <th class="ad-center">ক্রম</th>
                            <th>শর্টকোড</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($charts as $chart)
                            <tr>
                                <td data-label="শিরোনাম">
                                    <a href="{{ route('charts.edit', $chart) }}">{{ $chart->title }}</a>
                                    <div class="ad-hint" style="margin-top:2px;">{{ \App\Support\BanglaTime::date($chart->updated_at) }}</div>
                                </td>
                                <td data-label="ধরন">
                                    {{ $chart->typeLabel() }}
                                    <div class="ad-hint" style="margin-top:2px;">স্টাইল: {{ $chart->styleLabel() }}</div>
                                </td>
                                <td data-label="অবস্থা">
                                    @if($chart->is_published)
                                        <span style="color:#15803d;font-weight:600;">প্রকাশিত</span>
                                    @else
                                        <span style="color:#b45309;font-weight:600;">লুকানো</span>
                                    @endif
                                </td>
                                <td class="ad-center" data-label="ক্রম">{{ \App\Support\BanglaTime::digits($chart->serial) }}</td>
                                <td data-label="শর্টকোড">
                                    <code class="chart-code">[chart id="{{ $chart->id }}"]</code>
                                    <button type="button" class="ad-tab chart-copy" data-code="[chart id=&quot;{{ $chart->id }}&quot;]">কপি</button>
                                </td>
                                <td>
                                    <a href="{{ route('frontend.charts.index') }}#chart-{{ $chart->id }}" target="_blank" rel="noopener" class="ad-tab">দেখুন</a>
                                    <a href="{{ route('charts.edit', $chart) }}" class="ad-tab">সম্পাদনা</a>
                                    <form method="POST" action="{{ route('charts.destroy', $chart) }}" style="display:inline" onsubmit="return confirm('এই গ্রাফটি মুছে ফেলবেন?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ad-tab">মুছুন</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($charts->count() === 0)
            <p class="ad-hint">এখনো কোনো গ্রাফ নেই। ওপরের "নতুন গ্রাফ" দিয়ে শুরু করুন।</p>
        @endif

        <div class="mt-4">{{ $charts->links() }}</div>

    </div>

    <style>
        .chart-code { display: inline-block; padding: 2px 6px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 4px; font-size: .8rem; }
    </style>

    <script>
        (function () {
            var note = document.createElement('p');
            note.className = 'ad-hint';
            note.style.margin = '.4rem 0 0';

            document.querySelectorAll('.chart-copy').forEach(function (button) {
                button.addEventListener('click', function () {
                    var copy = function () {
                        note.textContent = 'কপি হয়েছে।';
                        button.after(note);
                    };

                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(button.dataset.code).then(copy);
                    } else {
                        var box = document.createElement('textarea');
                        box.value = button.dataset.code;
                        document.body.appendChild(box);
                        box.select();
                        document.execCommand('copy');
                        box.remove();
                        copy();
                    }
                });
            });
        })();
    </script>
</x-app-layout>
