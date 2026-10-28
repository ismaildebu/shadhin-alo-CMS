<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">বিজ্ঞাপন সম্পাদনা</h2>
    </x-slot>

    <div class="ad-page ad-narrow">

        <div class="ad-pagehead">
            <h1>বিজ্ঞাপন সম্পাদনা</h1>
            <a href="{{ route('ads.index') }}" class="ad-tab">← বিজ্ঞাপন তালিকা</a>
        </div>

        <form method="POST" action="{{ route('ads.update', $ad) }}" enctype="multipart/form-data" class="ad-form">
            @csrf
            @method('PUT')
            <div class="ad-panel">
                <div class="ad-panel-body">
                    @include('admin.ads._form')
                </div>
            </div>
        </form>

    </div>
</x-app-layout>