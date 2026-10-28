@php
    $bg = $b['bg'] ?? null;
    $bgClass = $bg ? ' sa-bg '.(\App\Support\Colors::isDark($bg) ? 'sa-bg-dark' : 'sa-bg-light') : '';
@endphp

<div class="sa-cat{{ $b['full'] ? ' sa-full' : '' }}{{ $bgClass }}" style="--cat: {{ $b['accent'] }};@if($bg) background: {{ $bg }};@endif">
    @include(\App\Models\Category::HOME_VIEWS[$b['layout']], [
        'category' => $b['category'],
        'items'    => $b['items'],
        'wide'     => $b['full'],
        'limit'    => $b['limit'] ?? null,
    ])
</div>