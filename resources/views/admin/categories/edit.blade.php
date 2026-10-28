<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ক্যাটাগরি সম্পাদনা</h2>
    </x-slot>

    <div class="ad-page ad-narrow">

        <div class="ad-pagehead">
            <h1>ক্যাটাগরি সম্পাদনা</h1>
            <a href="{{ route('categories.index') }}" class="ad-tab">← ক্যাটাগরি তালিকা</a>
        </div>

        <form method="POST" action="{{ route('categories.update', $category) }}" class="ad-form">
            @csrf
            @method('PUT')
            <div class="ad-panel">
                <div class="ad-panel-body">
                    @include('admin.categories._form')
                </div>
            </div>
        </form>

    </div>
</x-app-layout>