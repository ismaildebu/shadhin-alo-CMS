<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ট্যাগ ব্যবস্থাপনা</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>ট্যাগ ({{ $total }})</h1>
            <div class="ad-tabs" style="margin-bottom:0;">
                <a href="{{ route('tags.index', array_filter(['q' => $term])) }}"
                   class="ad-tab {{ $sort === 'usage' ? 'is-active' : '' }}">ব্যবহার অনুযায়ী</a>
                <a href="{{ route('tags.index', array_filter(['q' => $term, 'sort' => 'name'])) }}"
                   class="ad-tab {{ $sort === 'name' ? 'is-active' : '' }}">নাম অনুযায়ী</a>
            </div>
        </div>

        <p class="ad-note">
            ট্যাগ তৈরি হয় সংবাদ লেখার সময়। এখানে নাম বদল, একই বিষয়ের একাধিক ট্যাগ <b>মার্জ</b> এবং অব্যবহৃত ট্যাগ মোছা যায়।
        </p>

        <form method="GET" class="ad-search">
            @if($sort === 'name')<input type="hidden" name="sort" value="name">@endif
            <input type="text" name="q" value="{{ $term }}" placeholder="ট্যাগ খুঁজুন…" class="ad-input">
            <button type="submit" class="ad-btn-dark">খুঁজুন</button>
        </form>

        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>ট্যাগ</th>
                            <th>Slug</th>
                            <th class="ad-num">প্রকাশিত সংবাদ</th>
                            <th class="ad-num">মোট সংবাদ</th>
                            <th>কাজ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($tags as $tag)
                        <tr>
                            <td>
                                <div class="ad-tag-name">{{ $tag->name }}</div>
                                <div class="ad-news-sub">ID: {{ $tag->id }}</div>
                            </td>
                            <td data-label="Slug"><span class="ad-slug">{{ $tag->slug }}</span></td>
                            <td class="ad-num" data-label="প্রকাশিত"><span class="ad-count">{{ $tag->published_count }}</span></td>
                            <td class="ad-num" data-label="মোট">{{ $tag->all_news_count }}</td>
                            <td>
                                <div class="ad-actions">
                                    <a href="{{ route('tags.edit', $tag) }}" class="ad-act act-edit">সম্পাদনা</a>

                                    @if($tag->published_count > 0)
                                        <a href="{{ route('frontend.tag.show', $tag->slug) }}" target="_blank" class="ad-act">দেখুন</a>
                                    @endif

                                    @if($total > 1)
                                        <details class="ad-details">
                                            <summary class="ad-act act-flow">মার্জ</summary>
                                            <form action="{{ route('tags.merge', $tag) }}" method="POST" class="ad-pop"
                                                  data-confirm="'{{ $tag->name }}' ট্যাগটি মুছে সব সংবাদ নির্বাচিত ট্যাগে যাবে। নিশ্চিত?"
                                                  onsubmit="return confirm(this.dataset.confirm)">
                                                @csrf
                                                <label>এই ট্যাগের সংবাদ যাবে (ট্যাগের ID):</label>
                                                <input type="number" name="into" min="1" required placeholder="ID" class="ad-field">
                                                <p>ID পেতে ট্যাগের নামের নিচে "ID" দেখুন।</p>
                                                <button type="submit" class="ad-act act-flow">মার্জ করুন</button>
                                            </form>
                                        </details>
                                    @endif

                                    <form action="{{ route('tags.destroy', $tag) }}" method="POST"
                                          data-confirm="ট্যাগটি মুছে {{ $tag->all_news_count }}টি সংবাদ থেকে সরানো হবে। নিশ্চিত?"
                                          onsubmit="return confirm(this.dataset.confirm)">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ad-act act-del">মুছুন</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ad-empty">কোনো ট্যাগ নেই।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($tags->hasPages())
                <div class="ad-pager">{{ $tags->links() }}</div>
            @endif
        </div>

    </div>
</x-app-layout>