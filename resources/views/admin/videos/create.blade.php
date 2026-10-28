<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">নতুন ভিডিও</h2>
    </x-slot>

    <div class="ad-page ad-narrow">

        <div class="ad-pagehead">
            <h1>নতুন ভিডিও</h1>
            <a href="{{ route('videos.index') }}" class="ad-tab">← ভিডিও তালিকা</a>
        </div>

        <form method="POST" action="{{ route('videos.store') }}" class="ad-form">
            @csrf
            <div class="ad-panel">
                <div class="ad-panel-body">
                    @include('admin.videos._form')
                </div>
            </div>
        </form>

    </div>
</x-app-layout>