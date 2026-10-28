@php
    $user = auth()->user();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">সংবাদ ব্যবস্থাপনা</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>{{ $user->isEditorOrAdmin() ? 'সব সংবাদ' : 'আমার সংবাদ' }}</h1>
            <a href="{{ route('news.create') }}" class="ad-btn-solid">+ নতুন সংবাদ</a>
        </div>

        {{-- status tabs --}}
        <div class="ad-tabs">
            <a href="{{ route('news.index', array_filter(['q' => $term])) }}"
               class="ad-tab {{ ! $status ? 'is-active' : '' }}">
                সব <span class="ad-tab-count">({{ $counts->sum() }})</span>
            </a>

            @foreach(\App\Models\News::STATUS_LABELS as $key => $label)
                <a href="{{ route('news.index', array_filter(['status' => $key, 'q' => $term])) }}"
                   class="ad-tab {{ $status === $key ? 'is-active' : '' }}">
                    {{ $label }} <span class="ad-tab-count">({{ (int) ($counts[$key] ?? 0) }})</span>
                </a>
            @endforeach

            @if($user->isEditorOrAdmin())
                <a href="{{ route('news.index', ['status' => 'trashed']) }}"
                   class="ad-tab {{ $trashed ? 'is-active' : '' }}">
                    ট্র্যাশ <span class="ad-tab-count">({{ $trashedCount }})</span>
                </a>
            @endif
        </div>

        <form method="GET" class="ad-search">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <input type="text" name="q" value="{{ $term }}" placeholder="শিরোনামে খুঁজুন…" class="ad-input">
            <button type="submit" class="ad-btn-dark">খুঁজুন</button>
        </form>

        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>সংবাদ</th>
                            <th>অবস্থা</th>
                            <th>কাজ</th>
                        </tr>
                    </thead>

                    <tbody>
                    @forelse($news as $item)
                        @php
                            $targets = $trashed ? [] : \App\Services\NewsWorkflow::allowedTargets($user, $item);
                        @endphp
                        <tr>
                            <td>
                                <div class="ad-news-cell">
                                    @if($item->image)
                                        <img src="{{ asset('uploads/news/'.$item->image) }}" alt="" class="ad-thumb" loading="lazy">
                                    @else
                                        <span class="ad-thumb ad-thumb-empty">
                                            <svg class="ad-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 9"/></svg>
                                        </span>
                                    @endif

                                    <div>
                                        <div class="ad-news-title">
                                            {{ $item->title }}
                                            @if($item->breaking)<span class="ad-flag flag-breaking">ব্রেকিং</span>@endif
                                            @if($item->featured)<span class="ad-flag flag-featured">ফিচার্ড</span>@endif
                                            @if($item->is_lead)<span class="ad-flag flag-lead">লিড</span>@endif
                                        </div>
                                        <div class="ad-news-sub">
                                            #{{ $item->id }}
                                            &middot; {{ $item->category->name ?? '—' }}
                                            &middot; {{ $item->author->name ?? $item->reporter ?? '—' }}
                                            &middot; {{ $item->display_date->format('d M Y') }}
                                            &middot; পঠিত {{ number_format((int) $item->view_count) }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="ad-badge st-{{ $item->status }}">{{ $item->status_label }}</span>
                                @if($item->status === 'scheduled' && $item->published_at)
                                    <div class="ad-news-sub">{{ $item->published_at->timezone(config('app.timezone'))->format('d M, h:i A') }}</div>
                                @endif
                            </td>

                            <td>
                                <div class="ad-actions">
                                @if($trashed)
                                    <form action="{{ route('news.restore', $item) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="ad-act act-ok">পুনরুদ্ধার</button>
                                    </form>
                                @else
                                    @can('update', $item)
                                        <a href="{{ route('news.edit', $item) }}" class="ad-act act-edit">সম্পাদনা</a>
                                    @endcan

                                    @can('view', $item)
                                        <a href="{{ route('news.preview', $item) }}" target="_blank" class="ad-act">প্রিভিউ</a>
                                    @endcan

                                    @can('view', $item)
                                        <a href="{{ route('news.card', $item) }}" class="ad-act">ফটোকার্ড</a>
                                    @endcan

                                    @foreach($targets as $to)
                                        @continue($to === 'scheduled')
                                        @php
                                            $label = match (true) {
                                                $to === 'pending' => 'পর্যালোচনায় পাঠান',
                                                $to === 'published' => 'প্রকাশ',
                                                $to === 'archived' => 'আর্কাইভ',
                                                $to === 'draft' && $item->status === 'pending' => ($item->author_id === $user->id ? 'ফেরত নিন' : 'ফেরত পাঠান'),
                                                default => 'খসড়ায় নিন',
                                            };
                                            $askNote = $to === 'draft' && $item->status === 'pending' && $item->author_id !== $user->id;
                                        @endphp
                                        <form action="{{ route('news.transition', $item) }}" method="POST"
                                              @if($askNote) onsubmit="const n = prompt('ফেরত পাঠানোর কারণ লিখুন'); if (n === null || n.trim() === '') return false; this.note.value = n; return true;" @endif>
                                            @csrf
                                            <input type="hidden" name="to" value="{{ $to }}">
                                            <input type="hidden" name="note" value="">
                                            <button type="submit" class="ad-act {{ $to === 'published' ? 'act-ok' : 'act-flow' }}">{{ $label }}</button>
                                        </form>
                                    @endforeach

                                    @can('delete', $item)
                                        <form action="{{ route('news.destroy', $item) }}" method="POST"
                                              onsubmit="return confirm('সংবাদটি ট্র্যাশে সরাতে চান?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ad-act act-del">মুছুন</button>
                                        </form>
                                    @endcan
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ad-empty">কোনো সংবাদ পাওয়া যায়নি।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($news->hasPages())
                <div class="ad-pager">{{ $news->links() }}</div>
            @endif
        </div>

    </div>
</x-app-layout>