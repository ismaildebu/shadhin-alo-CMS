{{-- Clickable division map. Needs: $divisions (Location collection of the 8 divisions). --}}
@php
    $byKey = [];

    foreach ($divisions as $division) {
        $key = \App\Support\BdMap::keyFor($division->slug ?? null, $division->name_en ?? null);

        if ($key) {
            $byKey[$key] = $division;
        }
    }
@endphp
<svg class="sa-bdmap" viewBox="{{ \App\Support\BdMap::viewBox() }}" role="group" aria-label="Bangladesh divisions">
    @foreach(\App\Support\BdMap::SHAPES as $key => $shape)
        @if(isset($byKey[$key]))
            <a href="{{ $byKey[$key]->url() }}" class="sa-bdmap-div sa-bdmap-{{ $key }}">
                <title>{{ $byKey[$key]->name_bn }}</title>
                <path d="{{ $shape['d'] }}"></path>
                <text x="{{ $shape['x'] }}" y="{{ $shape['y'] }}" text-anchor="middle">{{ $byKey[$key]->name_bn }}</text>
            </a>
        @else
            <path d="{{ $shape['d'] }}" class="sa-bdmap-off"></path>
        @endif
    @endforeach
</svg>
