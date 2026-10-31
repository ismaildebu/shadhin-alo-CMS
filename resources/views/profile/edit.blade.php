<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">আমার প্রোফাইল</h2>
    </x-slot>

    @php
        // The super administrator account is permanent: only its password can
        // be changed, so the photo, details and delete sections are not shown.
        $isSuper = $user->isSuperAdmin();
    @endphp

    <div class="ad-page ad-narrow">

        @if(session('success'))
            <div class="ad-alert ad-alert-ok">{{ session('success') }}</div>
        @endif

        @if($isSuper)
            <div class="ad-panel" style="margin-bottom:20px;">
                <div class="ad-panel-head"><h2>সুপার অ্যাডমিন অ্যাকাউন্ট</h2></div>
                <div class="ad-panel-body">
                    <p class="ad-hint">
                        এই অ্যাকাউন্ট স্থায়ী। শুধু পাসওয়ার্ড বদলানো যায়; নাম, ইমেইল, ছবি ও অন্য তথ্য পরিবর্তন করা যায় না এবং অ্যাকাউন্ট মোছা যায় না।
                    </p>
                </div>
            </div>
        @endif

        @unless($isSuper)
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
        @endunless

        <div class="ad-panel" style="margin-bottom:20px;">
            <div class="ad-panel-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        @unless($isSuper)
            <div class="ad-panel">
                <div class="ad-panel-body">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        @endunless

    </div>
</x-app-layout>