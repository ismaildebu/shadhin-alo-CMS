@extends('frontend.layouts.app')

@section('content')

<div class="sa-block-head">
    <h2>অনলাইন ভোট</h2>
</div>

@if($polls->count())
    <div class="row g-4">
        @foreach($polls as $poll)
            <div class="col-md-6 col-lg-4">
                @include('frontend.partials.widgets.poll-card', ['poll' => $poll, 'withHead' => false])
            </div>
        @endforeach
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $polls->links() }}
    </div>
@else
    <p class="text-muted">এখনো কোনো ভোট প্রকাশিত হয়নি।</p>
@endif

@endsection
