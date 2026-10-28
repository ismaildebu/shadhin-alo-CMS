
@component('layouts.app')
<div class="container py-4 ad-module">

    <div class="ad-head">
        <div>
            <h1>বিজ্ঞাপন ডিজাইনার</h1>
            <p>স্বাধীন আলো · আলাদা বিজ্ঞাপন লাইব্রেরি</p>
        </div>

        <a class="ad-btn" href="{{ route('advertisements.create') }}">
            ＋ নতুন বিজ্ঞাপন
        </a>
    </div>

    @if(session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="ad-list">
        @forelse($advertisements as $ad)

            <article class="ad-item">

                <div class="ad-thumb">
                    @if($ad->export_url)
                        <img
                            src="{{ $ad->export_url }}"
                            alt="বিজ্ঞাপনের প্রিভিউ"
                        >
                    @else
                        <span>প্রিভিউ নেই</span>
                    @endif
                </div>

                <div class="ad-info">
                    <h3>{{ $ad->title }}</h3>

                    <p>
                        অবস্থান:
                        {{
                            [
                                'header' => 'হেডার',
                                'homepage' => 'হোমপেজ',
                                'sidebar' => 'সাইডবার',
                                'article' => 'সংবাদের পাতা',
                            ][$ad->placement] ?? $ad->placement
                        }}
                    </p>

                    <span class="ad-state {{ $ad->status ? 'on' : 'off' }}">
                        {{ $ad->status ? 'সক্রিয়' : 'বন্ধ' }}
                    </span>
                </div>

                <div class="ad-actions">
                    <a href="{{ route('advertisements.edit', $ad) }}">
                        সম্পাদনা
                    </a>

                    <form
                        method="POST"
                        action="{{ route('advertisements.destroy', $ad) }}"
                        onsubmit="return confirm('এই বিজ্ঞাপনটি মুছে ফেলবে?')"
                    >
                        @csrf
                        @method('DELETE')

                        <button type="submit">মুছুন</button>
                    </form>
                </div>

            </article>

        @empty

            <div class="ad-empty">
                এখনো কোনো বিজ্ঞাপন তৈরি করা হয়নি।
                নতুন বিজ্ঞাপন তৈরি করে শুরু করো।
            </div>

        @endforelse
    </div>

    {{ $advertisements->links() }}

</div>
@endcomponent
