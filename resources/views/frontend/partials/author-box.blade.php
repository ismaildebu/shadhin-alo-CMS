@if(($by['name'] ?? '') !== '')
<div class="sa-author">

    {{-- লেখকের ছবি না থাকলে ছবির জায়গায় সাইটের লোগো --}}
    <img
        src="{{ $by['photo'] ?: \App\Support\Settings::logoUrl() }}"
        alt="{{ $by['photo'] ? $by['name'] : \App\Support\Settings::get('site_name') }}"
        @class(['sa-author-photo', 'is-logo' => ! $by['photo']])
        loading="lazy"
    >

    <div>
        <div class="sa-author-label">লেখক</div>
        <div class="sa-author-name">{{ $by['name'] }}</div>

        @if($by['bio'] !== '')
            <p class="sa-author-bio">{{ $by['bio'] }}</p>
        @endif
    </div>

</div>
@endif