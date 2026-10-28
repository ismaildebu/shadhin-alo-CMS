{{--
    Division shapes from App\Support\BdMap, defined once per page so many
    "no image" cards can reuse them with <use href="#sa-bd-{key}"> instead of
    repeating the path data. Include this once on any page that shows them.
--}}
@once
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <defs>
            @foreach(\App\Support\BdMap::SHAPES as $key => $shape)
                <path id="sa-bd-{{ $key }}" d="{{ $shape['d'] }}"/>
            @endforeach
        </defs>
    </svg>
@endonce
