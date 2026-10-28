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
                <p class="ad-hint" style="width:100%;margin:0;">jpg, png, webp; প্রতিটি সর্বোচ্চ ২ MB; একবারে সর্বোচ্চ ১০টি।</p>
            </form>
        </div>

        <form method="GET" class="ad-search">
            <input type="text" name="q" value="{{ $term }}" placeholder="ফাইলের নামে খুঁজুন…" class="ad-input">
            <button type="submit" class="ad-btn-dark">খুঁজুন</button>
        </form>

        <div class="ad-panel">
            @if($media->isEmpty())
                <div class="ad-empty">গ্যালারিতে কোনো ছবি নেই।</div>
            @else
                <div class="ad-media-grid">
                    @foreach($media as $item)
                        @php $count = (int) ($usage[$item->filename] ?? 0); @endphp
                        <div class="ad-media-card">
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

                                    @if($isEditor && $count === 0)
                                        <form action="{{ route('media.destroy', $item) }}" method="POST"
                                              onsubmit="return confirm('ছবিটি গ্যালারি থেকে মুছে ফেলতে চান?')">
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
</x-app-layout>