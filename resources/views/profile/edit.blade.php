<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">আমার প্রোফাইল</h2>
    </x-slot>

    <div class="ad-page ad-narrow">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-head"><h2>প্রোফাইল ছবি</h2></div>
            <div class="ad-panel-body">
                @include('admin.avatar-form', [
                    'avatarUser' => $user,
                    'storeUrl' => route('profile.avatar.store'),
                    'destroyUrl' => route('profile.avatar.destroy'),
                ])
            </div>
        </div>

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="ad-panel">
            <div class="ad-panel-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>

    </div>
</x-app-layout>