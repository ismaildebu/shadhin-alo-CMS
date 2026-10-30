@if(($by['name'] ?? '') !== '')
<div class="sa-author">

    @if($by['photo'])
        <img src="{{ $by['photo'] }}" alt="{{ $by['name'] }}" class="sa-author-photo" loading="lazy">
    @else
        <span class="sa-author-photo sa-author-ph">{{ \Illuminate\Support\Str::substr($by['name'], 0, 1) }}</span>
    @endif

    <div>
        <div class="sa-author-label">লেখক</div>
        <div class="sa-author-name">{{ $by['name'] }}</div>

        @if($by['bio'] !== '')
            <p class="sa-author-bio">{{ $by['bio'] }}</p>
        @endif
    </div>

</div>
@endif