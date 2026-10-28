@php
    $site = \App\Support\Settings::all();
    $extraLinks = \App\Support\Settings::footerLinks();
    $footerCategories = $menuCategories->take(max(0, (int) $site['footer_categories']));

    $social = array_filter([
        'Facebook' => $site['social_facebook'],
        'YouTube' => $site['social_youtube'],
        'X' => $site['social_x'],
        'Instagram' => $site['social_instagram'],
    ]);

    $hasLinks = $footerCategories->isNotEmpty() || ! empty($extraLinks);
    $hasContact = $site['footer_email'] !== '' || $site['footer_phone'] !== '' || $site['footer_address'] !== '' || ! empty($social);

    $columns = 1 + ($hasLinks ? 1 : 0) + ($hasContact ? 1 : 0);
    $colClass = 'col-lg-'.intdiv(12, $columns);
@endphp

<footer class="sa-footer">

    <div class="container py-5">
        <div class="row g-4">

            <div class="{{ $colClass }}">
                <h3 class="sa-footer-title">{{ $site['site_name'] }}</h3>

                @if($site['site_tagline'] !== '')
                    <p><strong class="text-white">{{ $site['site_tagline'] }}</strong></p>
                @endif

                @if($site['footer_about'] !== '')
                    <p>{{ $site['footer_about'] }}</p>
                @endif
            </div>

            @if($hasLinks)
                <div class="{{ $colClass }}">
                    @if($footerCategories->isNotEmpty())
                        <h3 class="sa-footer-title">গুরুত্বপূর্ণ বিভাগ</h3>
                        <ul class="sa-footer-links">
                            @foreach($footerCategories as $category)
                                <li><a href="{{ route('frontend.category.show', $category->slug) }}">{{ $category->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif

                    @if(! empty($extraLinks))
                        <h3 class="sa-footer-title" @if($footerCategories->isNotEmpty()) style="margin-top:24px;" @endif>তথ্য</h3>
                        <ul class="sa-footer-links">
                            @foreach($extraLinks as $link)
                                <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if($hasContact)
                <div class="{{ $colClass }}">
                    <h3 class="sa-footer-title">যোগাযোগ</h3>

                    @if($site['footer_email'] !== '')
                        <p>ইমেইল: <a href="mailto:{{ $site['footer_email'] }}">{{ $site['footer_email'] }}</a></p>
                    @endif

                    @if($site['footer_phone'] !== '')
                        <p>ফোন: {{ $site['footer_phone'] }}</p>
                    @endif

                    @if($site['footer_address'] !== '')
                        <p>ঠিকানা: {{ $site['footer_address'] }}</p>
                    @endif

                    @if(! empty($social))
                        <div>
                            @foreach($social as $name => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="sa-social">{{ $name }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>

    <div class="sa-footer-bottom">
        <div class="container">
            © {{ date('Y') }} <strong class="text-white">{{ $site['site_name'] }}</strong>@if($site['footer_copyright'] !== '') | {{ $site['footer_copyright'] }}@endif
            @if($site['footer_credit'] !== '')
                <span class="mx-2">|</span>
                Developed by <strong class="text-white">{{ $site['footer_credit'] }}</strong>
            @endif
        </div>
    </div>

</footer>