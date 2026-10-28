@extends('frontend.layouts.app')

@section('content')

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        @include('frontend.partials.widgets.poll-card', ['poll' => $poll, 'withHead' => false])

        <p class="text-center mt-3">
            <a href="{{ route('frontend.polls.index') }}">সব ভোট দেখুন</a>
        </p>
    </div>
</div>

@endsection
