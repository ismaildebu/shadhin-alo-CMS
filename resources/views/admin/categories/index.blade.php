<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ক্যাটাগরি ব্যবস্থাপনা</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>সকল ক্যাটাগরি</h1>
            <a href="{{ route('categories.create') }}" class="ad-btn-solid">+ নতুন ক্যাটাগরি</a>
        </div>

        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th class="ad-num">ক্রম</th>
                            <th>নাম</th>
                            <th>Slug</th>
                            <th class="ad-num">সংবাদ</th>
                            <th>কাজ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roots as $category)
                            @include('admin.categories._row', ['category' => $category, 'child' => false])
                            @foreach($children->get($category->id, collect()) as $sub)
                                @include('admin.categories._row', ['category' => $sub, 'child' => true])
                            @endforeach
                        @empty
                            <tr><td colspan="5" class="ad-empty">কোনো ক্যাটাগরি নেই।</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-app-layout>