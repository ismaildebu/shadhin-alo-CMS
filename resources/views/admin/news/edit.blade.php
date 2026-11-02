<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">সংবাদ সম্পাদনা</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>সংবাদ সম্পাদনা করুন</h1>
            <div class="ad-tabs" style="margin-bottom:0;">
                <a href="{{ route('news.preview', $news) }}" target="_blank" class="ad-tab">প্রিভিউ</a>
                <a href="{{ route('news.revisions.index', $news) }}" class="ad-tab">সংস্করণ ইতিহাস</a>
                <a href="{{ route('news.card', $news) }}" class="ad-tab">ফটোকার্ড</a>

                <form action="{{ route('news.video.store', $news) }}" method="POST"
                      title="লোকাল FFmpeg দিয়ে ফেসবুক রিলস ভিডিও তৈরি হবে; প্রায় এক মিনিট লাগতে পারে"
                      onsubmit="const b = this.querySelector('button'); b.disabled = true; b.textContent = 'তৈরি হচ্ছে…';">
                    @csrf
                    <button type="submit" class="ad-tab is-video">ভিডিও তৈরি</button>
                </form>

                @if(\App\Support\NewsVideo::exists($news))
                    <a href="{{ route('news.video.show', $news) }}" target="_blank" class="ad-tab">ভিডিও দেখুন</a>
                    <a href="{{ route('news.video.show', ['news' => $news, 'download' => 1]) }}" class="ad-tab">ডাউনলোড</a>
                @endif

                <a href="{{ route('news.index') }}" class="ad-tab">← সংবাদ তালিকা</a>
            </div>
        </div>

        <p class="ad-note">
            লিংক: <code>/article/{{ $news->slug }}</code> (প্রকাশের পর পরিবর্তন হয় না)
        </p>

        <form action="{{ route('news.update', $news) }}" method="POST" enctype="multipart/form-data" class="ad-form">
            @csrf
            @method('PUT')
            @include('admin.news._form')
        </form>

    </div>
</x-app-layout>