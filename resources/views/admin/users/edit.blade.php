<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ইউজার সম্পাদনা</h2>
    </x-slot>

    <div class="ad-page ad-narrow">

        <div class="ad-pagehead">
            <h1>ইউজার সম্পাদনা</h1>
            <a href="{{ route('users.index') }}" class="ad-tab">← ইউজার তালিকা</a>
        </div>

        <form action="{{ route('users.update', $user) }}" method="POST" class="ad-form">
            @csrf
            @method('PUT')
            <div class="ad-panel">
                <div class="ad-panel-body">
                    @include('admin.users._form')
                </div>
            </div>
        </form>

    </div>
</x-app-layout>