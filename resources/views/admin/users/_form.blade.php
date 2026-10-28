@php
    $isEdit = $user->exists;
    $isSelf = $isEdit && $user->is(auth()->user());
    $val = fn (string $field, $default = '') => old($field, $isEdit ? ($user->{$field} ?? $default) : $default);
@endphp

@if($errors->any())
    <div class="ad-alert ad-alert-err">
        <ul class="ad-errlist">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="ad-two ad-fgroup">
    <div>
        <label class="ad-label">নাম <span class="ad-req">*</span></label>
        <input type="text" name="name" value="{{ $val('name') }}" class="ad-field" required>
    </div>
    <div>
        <label class="ad-label">ইমেইল <span class="ad-req">*</span></label>
        <input type="email" name="email" value="{{ $val('email') }}" class="ad-field" required>
    </div>
</div>

<div class="ad-two ad-fgroup">
    <div>
        <label class="ad-label">ফোন</label>
        <input type="text" name="phone" value="{{ $val('phone') }}" class="ad-field">
    </div>
    <div>
        <label class="ad-label">পাসওয়ার্ড @unless($isEdit)<span class="ad-req">*</span>@endunless</label>
        <input type="password" name="password" autocomplete="new-password" class="ad-field" @unless($isEdit) required @endunless>
        @if($isEdit)<p class="ad-hint">ফাঁকা রাখলে বর্তমান পাসওয়ার্ড থাকবে।</p>@endif
    </div>
</div>

<div class="ad-two ad-fgroup">
    <div>
        <label class="ad-label">রোল</label>
        <select name="role" class="ad-field" @if($isSelf) disabled @endif>
            @foreach(\App\Models\User::ROLES as $key => $label)
                <option value="{{ $key }}" @selected($val('role', 'reporter') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @if($isSelf)
            <input type="hidden" name="role" value="{{ $user->role }}">
            <p class="ad-hint">নিজের রোল বদলানো যায় না।</p>
        @endif
    </div>
    <div class="ad-check-field">
        <label class="ad-check">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $isEdit ? $user->is_active : true)) @if($isSelf) onclick="return false" @endif>
            সক্রিয় (নিষ্ক্রিয় ইউজার লগইন করতে পারে না)
        </label>
    </div>
</div>

<div class="ad-fgroup" id="user-district-field">
    <label class="ad-label">জেলা প্রতিনিধি <span class="ad-hint" style="display:inline;">(শুধু রিপোর্টারের জন্য)</span></label>
    <select name="district_id" class="ad-field">
        @foreach($districts as $district)
            <option value="{{ $district->id }}" @selected((string) $val('district_id') === (string) $district->id)>{{ $district->parent?->name_bn }} › {{ $district->name_bn }}</option>
        @endforeach
    </select>
    <p class="ad-hint">নির্বাচন করলে এই রিপোর্টার শুধু ওই জেলা ও তার উপজেলার সংবাদ লিখতে পারবেন।</p>
</div>

<div class="ad-fgroup">
    <label class="ad-label">পরিচিতি (Bio)</label>
    <textarea name="bio" rows="3" class="ad-field">{{ $val('bio') }}</textarea>
</div>

<div class="ad-form-actions">
    <button type="submit" class="ad-btn-solid">{{ $isEdit ? 'আপডেট করুন' : 'ইউজার তৈরি করুন' }}</button>
    <a href="{{ route('users.index') }}" class="ad-cancel">বাতিল</a>
</div>