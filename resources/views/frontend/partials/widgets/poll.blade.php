{{-- Sidebar widget: the newest open poll (nothing is drawn when there is none). --}}
@php $sidebarPoll = \App\Models\Poll::latestOpen(); @endphp
@if($sidebarPoll)
    <div class="sa-poll-widget">
        @include('frontend.partials.widgets.poll-card', [
            'poll' => $sidebarPoll,
            'layout' => $layout ?? null,
            'accent' => $accent ?? null,
        ])
    </div>
@endif
