<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">নতুন সংবাদ</h2>
    </x-slot>

    <div class="ad-page">

        <div class="ad-pagehead">
            <h1>নতুন সংবাদ যোগ করুন</h1>
            <a href="{{ route('news.index') }}" class="ad-tab">← সংবাদ তালিকা</a>
        </div>

        <form action="{{ route('news.store') }}" method="POST" enctype="multipart/form-data" class="ad-form">
            @csrf
            @include('admin.news._form')
        </form>

    </div>
</x-app-layout>