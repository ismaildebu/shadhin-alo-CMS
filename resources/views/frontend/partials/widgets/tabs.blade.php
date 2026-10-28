@php
    $styles = array_keys(\App\Models\SidebarWidget::META['tabs']['layouts']);
    $style = in_array($layout ?? null, $styles, true) ? $layout : 'numbered';
    $count = max(3, min(15, (int) ($limit ?? 5)));
    $tint = \App\Support\Colors::isHex($accent ?? null) ? $accent : null;

    // স্ক্রল উচ্চতা: ফাঁকা হলে স্টাইলের ডিফল্ট (ছবিসহ তালিকায় ৪২০), ০ হলে স্ক্রল নেই
    $bh = $boxHeight ?? null;
    $height = $bh === null ? ($style === 'thumbs' ? 420 : 0) : (int) $bh;

    $tabs = [
        ['id' => 'latestNews', 'label' => 'সর্বশেষ', 'items' => $latestNews->take($count)],
        ['id' => 'popularNews', 'label' => 'সর্বাধিক পঠিত', 'items' => $popularNews->take($count)],
    ];
@endphp

<div class="sa-widget sa-tw sa-tw-{{ $style }}" @if($tint) style="--tw: {{ $tint }};" @endif>

    <ul class="nav {{ $style === 'numbered' ? 'sa-tabs' : 'sa-tw-tabs' }}" role="tablist">
        @foreach($tabs as $tab)
            <li class="nav-item flex-fill d-flex">
                <button class="nav-link w-100 {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab"
                        data-bs-target="#{{ $tab['id'] }}" type="button">{{ $tab['label'] }}</button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content sa-tw-body" @if($height > 0) style="max-height: {{ $height }}px;" @endif>
        @foreach($tabs as $tab)
            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $tab['id'] }}">

                @foreach($tab['items'] as $item)

                    @if($style === 'thumbs')

                        @include('frontend.partials.widgets.tabs_item', ['item' => $item, 'withDate' => false])

                    @elseif($style === 'pills')

                        @include('frontend.partials.widgets.tabs_item', ['item' => $item, 'withDate' => true])

                    @elseif($style === 'feature')

                        @if($loop->first)
                            <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-tw-feat">
                                @if($item->image)
                                    <img src="{{ asset('uploads/news/'.$item->image) }}" alt="{{ $item->title }}" loading="lazy">
                                @endif
                                <span class="sa-tw-feat-title">{{ \Illuminate\Support\Str::limit($item->title, 90) }}</span>
                            </a>
                        @else
                            @include('frontend.partials.widgets.tabs_item', ['item' => $item, 'withDate' => false])
                        @endif

                    @elseif($style === 'underline')

                        <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-tw-line">
                            <span class="sa-tw-title">{{ \Illuminate\Support\Str::limit($item->title, 90) }}</span>
                            <small class="sa-tw-date">{{ $item->display_date->format('d M Y') }}</small>
                        </a>

                    @elseif($style === 'compact')

                        <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-tw-row">
                            <small class="sa-tw-day">{{ $item->display_date->format('d M') }}</small>
                            <span class="sa-tw-title">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</span>
                        </a>

                    @else

                        <a href="{{ route('frontend.news.show', $item->slug) }}" class="sa-rank">
                            <span class="sa-rank-no">{{ $loop->iteration }}</span>
                            <span>
                                <span class="sa-rank-title">{{ $item->title }}</span>
                                <small>{{ $item->display_date->format('d M Y') }}</small>
                            </span>
                        </a>

                    @endif

                @endforeach

            </div>
        @endforeach
    </div>

</div>