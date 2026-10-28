<x-guest-layout>

    @if(session('status'))
        <div class="ad-alert ad-alert-ok">{{ session('status') }}</div>
    @endif

    <h2>লগইন করুন</h2>

    <form method="POST" action="{{ route('login') }}" class="ad-form">
        @csrf

        <div class="ad-fgroup">
            <label class="ad-label" for="email">ইমেইল</label>
            <input id="email" class="ad-field" type="email" name="email"
                   value="{{ old('email') }}" required autofocus autocomplete="username">
            @foreach($errors->get('email') as $message)
                <p class="ad-field-error">{{ $message }}</p>
            @endforeach
        </div>

        <div class="ad-fgroup">
            <label class="ad-label" for="password">পাসওয়ার্ড</label>
            <input id="password" class="ad-field" type="password" name="password"
                   required autocomplete="current-password">
            @foreach($errors->get('password') as $message)
                <p class="ad-field-error">{{ $message }}</p>
            @endforeach
        </div>

        <div class="ad-auth-row">
            <label class="ad-check">
                <input id="remember_me" type="checkbox" name="remember">
                আমাকে মনে রাখুন
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">পাসওয়ার্ড ভুলে গেছেন?</a>
            @endif
        </div>

        <button type="submit" class="ad-btn-solid ad-btn-block">লগইন</button>
    </form>

</x-guest-layout>