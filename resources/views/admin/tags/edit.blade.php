<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ট্যাগ সম্পাদনা</h2>
    </x-slot>

    <div class="ad-page ad-narrow">

        <div class="ad-pagehead">
            <h1>ট্যাগ সম্পাদনা</h1>
            <a href="{{ route('tags.index') }}" class="ad-tab">← ট্যাগ তালিকা</a>
        </div>

        @if($errors->any())
            <div class="ad-alert ad-alert-err">
                <ul class="ad-errlist">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('tags.update', $tag) }}" method="POST" class="ad-form">
            @csrf
            @method('PUT')

            <div class="ad-panel">
                <div class="ad-panel-body">

                    <div class="ad-fgroup">
                        <label class="ad-label">নাম <span class="ad-req">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $tag->name) }}" maxlength="50" class="ad-field" required>
                    </div>

                    <div class="ad-fgroup">
                        <label class="ad-label">Slug</label>
                        <input type="text" name="slug" value="{{ old('slug', $tag->slug) }}" class="ad-field">
                        <p class="ad-hint">
                            এটি পাবলিক লিংকের অংশ (<code>/tag/{{ $tag->slug }}</code>)। নাম বদলালে slug বদলায় না; পুরনো লিংক বদলাতে চাইলেই শুধু এটি পরিবর্তন করুন।
                            এই ট্যাগে {{ $tag->all_news_count }}টি সংবাদ আছে।
                        </p>
                    </div>

                    <div class="ad-form-actions">
                        <button type="submit" class="ad-btn-solid">আপডেট করুন</button>
                        <a href="{{ route('tags.index') }}" class="ad-cancel">বাতিল</a>
                    </div>

                </div>
            </div>
        </form>

    </div>
</x-app-layout>