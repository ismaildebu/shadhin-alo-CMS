@php
    $isAdmin = auth()->user()->isAdmin();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">বিজ্ঞাপন ব্যবস্থাপনা</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>বিজ্ঞাপন</h1>
            <a href="{{ route('ads.create') }}" class="ad-btn-solid">+ নতুন বিজ্ঞাপন</a>
        </div>

        {{-- Slot overview --}}
        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-head">
                <h2>বিজ্ঞাপনের জায়গা</h2>
                <span class="ad-panel-note">কোথায় এখন কয়টি বিজ্ঞাপন চলছে</span>
            </div>
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>জায়গা</th>
                            <th>প্রস্তাবিত মাপ</th>
                            <th>এখন চলছে</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach(\App\Models\Ad::SLOTS as $key => $slot)
                        @php $live = (int) ($liveBySlot[$key] ?? 0); @endphp
                        <tr>
                            <td>{{ $slot['label'] }}</td>
                            <td data-label="মাপ"><span class="ad-slug">{{ $slot['size'] }}</span></td>
                            <td data-label="চলছে">
                                <span class="ad-badge {{ $live > 0 ? 'st-published' : 'st-draft' }}">
                                    {{ $live > 0 ? $live.'টি' : 'খালি' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Ad list --}}
        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>বিজ্ঞাপন</th>
                            <th>জায়গা</th>
                            <th>অবস্থা ও সময়</th>
                            <th class="ad-num">ক্লিক</th>
                            <th>কাজ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($ads as $ad)
                        @php
                            [$stateLabel, $stateClass] = $ad->state();
                            $canEdit = $isAdmin || $ad->type === 'image';
                        @endphp
                        <tr>
                            <td>
                                <div class="ad-news-cell">
                                    @if($ad->type === 'image' && $ad->image_url)
                                        <img src="{{ $ad->image_url }}" alt="" class="ads-thumb" loading="lazy">
                                    @else
                                        <span class="ads-code">&lt;/&gt;</span>
                                    @endif
                                    <div>
                                        <div class="ad-news-title">{{ $ad->title }}</div>
                                        <div class="ad-news-sub">
                                            {{ \App\Models\Ad::TYPES[$ad->type] ?? $ad->type }}
                                            @if($ad->creator) &middot; {{ $ad->creator->name }} @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td data-label="জায়গা">
                                {{ \App\Models\Ad::SLOTS[$ad->slot]['label'] ?? $ad->slot }}
                            </td>

                            <td data-label="অবস্থা">
                                <span class="ad-badge {{ $stateClass }}">{{ $stateLabel }}</span>
                                <div class="ad-news-sub">
                                    @if($ad->starts_at) শুরু: {{ $ad->starts_at->timezone(config('app.timezone'))->format('d M, h:i A') }}<br>@endif
                                    @if($ad->ends_at) শেষ: {{ $ad->ends_at->timezone(config('app.timezone'))->format('d M, h:i A') }} @endif
                                    @if(! $ad->starts_at && ! $ad->ends_at) সময়সীমা নেই @endif
                                </div>
                            </td>

                            <td class="ad-num" data-label="ক্লিক"><span class="ad-count">{{ $ad->clicks }}</span></td>

                            <td>
                                <div class="ad-actions">
                                    @if($canEdit)
                                        <a href="{{ route('ads.edit', $ad) }}" class="ad-act act-edit">সম্পাদনা</a>
                                        <form action="{{ route('ads.destroy', $ad) }}" method="POST"
                                              onsubmit="return confirm('বিজ্ঞাপনটি মুছে ফেলতে চান?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ad-act act-del">মুছুন</button>
                                        </form>
                                    @else
                                        <span class="ad-news-sub">শুধু অ্যাডমিন</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ad-empty">কোনো বিজ্ঞাপন নেই। প্রথমটি যোগ করুন।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($ads->hasPages())
                <div class="ad-pager">{{ $ads->links() }}</div>
            @endif
        </div>

    </div>
</x-app-layout>