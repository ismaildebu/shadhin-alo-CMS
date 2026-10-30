<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ইউজার ব্যবস্থাপনা</h2>
    </x-slot>

    <div class="ad-page">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ad-alert ad-alert-err">{{ $errors->first() }}</div>
        @endif

        <div class="ad-pagehead">
            <h1>ইউজার</h1>
            <a href="{{ route('users.create') }}" class="ad-btn-solid">+ নতুন ইউজার</a>
        </div>

        <form method="GET" class="ad-search">
            <input type="text" name="q" value="{{ $term }}" placeholder="নাম বা ইমেইল…" class="ad-input">
            <button type="submit" class="ad-btn-dark">খুঁজুন</button>
        </form>

        <div class="ad-panel">
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>ইউজার</th>
                            <th>রোল</th>
                            <th>অবস্থা</th>
                            <th class="ad-num">সংবাদ</th>
                            <th>কাজ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td>
                                <div class="ad-user-cell">
                                    
                                    <span class="ad-avatar">
                                        @if($rowAvatar = \App\Support\Avatars::url($u))
                                            <img src="{{ $rowAvatar }}" alt="">
                                        @else
                                            {{ \Illuminate\Support\Str::substr($u->name, 0, 1) }}
                                        @endif
                                    </span>
                                    <div>
                                        <div class="ad-tag-name">{{ $u->name }}</div>
                                        <div class="ad-news-sub">{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>

                            <td data-label="রোল">
                                <span class="ad-badge st-draft role-{{ $u->role }}">
                                    {{ explode(' (', \App\Models\User::ROLES[$u->role] ?? $u->role)[0] }}
                                </span>
                            </td>

                            <td data-label="অবস্থা">
                                <span class="ad-badge {{ $u->is_active ? 'st-published' : 'st-off' }}">
                                    {{ $u->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়' }}
                                </span>
                            </td>

                            <td class="ad-num" data-label="সংবাদ"><span class="ad-count">{{ $u->news_count }}</span></td>

                            <td>
                                <div class="ad-actions">
                                    <a href="{{ route('users.edit', $u) }}" class="ad-act act-edit">সম্পাদনা</a>
                                    @unless($u->is(auth()->user()))
                                        <form action="{{ route('users.destroy', $u) }}" method="POST"
                                              onsubmit="return confirm('ইউজারটি মুছে ফেলতে চান?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ad-act act-del">মুছুন</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ad-empty">কোনো ইউজার পাওয়া যায়নি।</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="ad-pager">{{ $users->links() }}</div>
            @endif
        </div>

    </div>
</x-app-layout>