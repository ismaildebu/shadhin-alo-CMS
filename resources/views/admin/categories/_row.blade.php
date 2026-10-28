<tr class="{{ $child ? 'is-sub' : '' }}">
    <td class="ad-num" data-label="ক্রম">{{ $category->serial }}</td>

    <td>
        @if($child)<span class="ad-sub-mark">↳</span>@endif
        <span class="ad-tag-name">{{ $category->name }}</span>
        @unless($category->status)<span class="ad-flag flag-breaking">নিষ্ক্রিয়</span>@endunless
        @unless($category->show_in_menu)<span class="ad-flag st-draft">মেনুতে নেই</span>@endunless
    </td>

    <td data-label="Slug"><span class="ad-slug">{{ $category->slug }}</span></td>
    <td class="ad-num" data-label="সংবাদ"><span class="ad-count">{{ $category->all_news_count }}</span></td>

    <td>
        <div class="ad-actions">
            <a href="{{ route('categories.edit', $category) }}" class="ad-act act-edit">সম্পাদনা</a>

            @if($category->status)
                <a href="{{ route('frontend.category.show', $category->slug) }}" target="_blank" class="ad-act">দেখুন</a>
            @endif

            @if(! $child && $children->has($category->id))
                <span class="ad-news-sub" title="আগে উপ-ক্যাটাগরিগুলো সরান বা মুছুন">মুছতে উপ-ক্যাটাগরি সরান</span>
            @elseif($category->all_news_count > 0)
                <details class="ad-details">
                    <summary class="ad-act act-del">সংবাদ সরিয়ে মুছুন</summary>
                    <form action="{{ route('categories.destroy', $category) }}" method="POST" class="ad-pop"
                          onsubmit="return confirm('{{ $category->all_news_count }}টি সংবাদ সরিয়ে ক্যাটাগরিটি মুছে ফেলতে চান?')">
                        @csrf
                        @method('DELETE')
                        <label>সংবাদগুলো যাবে:</label>
                        <select name="move_to" class="ad-field" required>
                            <option value="">— বেছে নিন —</option>
                            @foreach($all as $target)
                                <option value="{{ $target->id }}">{{ $target->parent_id ? '– ' : '' }}{{ $target->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="ad-act act-del">নিশ্চিত করুন</button>
                    </form>
                </details>
            @else
                <form action="{{ route('categories.destroy', $category) }}" method="POST"
                      onsubmit="return confirm('এই ক্যাটাগরিটি মুছে ফেলতে চান?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ad-act act-del">মুছুন</button>
                </form>
            @endif
        </div>
    </td>
</tr>