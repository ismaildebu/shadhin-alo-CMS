@php
    $isEdit = $ad->exists;
    $isAdmin = auth()->user()->isAdmin();
    $type = old('type', $ad->type ?: 'image');
    $slot = old('slot', $ad->slot);
    $fmt = fn ($d) => $d ? $d->timezone(config('app.timezone'))->format('Y-m-d\TH:i') : '';
    $starts = old('starts_at', $fmt($ad->starts_at));
    $ends = old('ends_at', $fmt($ad->ends_at));
    $status = (int) old('status', $isEdit ? (int) $ad->status : 1);
@endphp

@if($errors->any())
    <div class="ad-alert ad-alert-err">
        <ul class="ad-errlist">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="ad-fgroup">
    <label class="ad-label">বিজ্ঞাপনের নাম <span class="ad-req">*</span></label>
    <input type="text" name="title" value="{{ old('title', $ad->title) }}" class="ad-field" required>
    <p class="ad-hint">শুধু আপনার চেনার জন্য, সাইটে দেখাবে না (ছবির বিজ্ঞাপনে এটি ছবির বিকল্প লেখা হিসেবে যায়)।</p>
</div>

<div class="ad-two ad-fgroup">
    <div>
        <label class="ad-label">কোথায় দেখাবে <span class="ad-req">*</span></label>
        <select name="slot" class="ad-field" required>
            @foreach(\App\Models\Ad::SLOTS as $key => $s)
                <option value="{{ $key }}" @selected($slot === $key)>{{ $s['label'] }} ({{ $s['size'] }})</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="ad-label">ধরন</label>
        <select name="type" id="ad-type" class="ad-field">
            <option value="image" @selected($type === 'image')>{{ \App\Models\Ad::TYPES['image'] }}</option>
            @if($isAdmin)
                <option value="code" @selected($type === 'code')>{{ \App\Models\Ad::TYPES['code'] }}</option>
            @endif
        </select>
    </div>
</div>

<div id="ad-image-box">
    <div class="ad-fgroup">
        <label class="ad-label">বিজ্ঞাপনের ছবি</label>

        <img id="ad-preview" class="ads-preview" alt=""
             @if($isEdit && $ad->image_url) src="{{ $ad->image_url }}" @else hidden @endif>

        <input type="file" name="image" id="ad-file" accept="image/jpeg,image/png,image/webp,image/gif" class="ad-file">
        <p class="ad-hint">jpg, png, webp বা gif; সর্বোচ্চ ২ MB। জায়গার প্রস্তাবিত মাপ অনুযায়ী ছবি বানালে সবচেয়ে ভালো দেখায়।</p>
    </div>

    <div class="ad-fgroup">
        <label class="ad-label">ক্লিক করলে যে লিংকে যাবে</label>
        <input type="text" name="link_url" value="{{ old('link_url', $ad->link_url) }}" class="ad-field"
               placeholder="https://example.com/offer">
        <p class="ad-hint">ফাঁকা রাখলে ছবিতে ক্লিক করা যাবে না। ক্লিক গোনা হবে।</p>
    </div>
</div>

@if($isAdmin)
    <div id="ad-code-box">
        <div class="ad-fgroup">
            <label class="ad-label">বিজ্ঞাপনের কোড</label>
            <textarea name="code" rows="8" class="ad-field ads-code-input"
                      placeholder="Google AdSense বা অন্য বিজ্ঞাপন নেটওয়ার্কের কোড এখানে পেস্ট করুন">{{ old('code', $ad->code) }}</textarea>
            <p class="ad-hint">এই কোড পাবলিক সাইটে হুবহু বসবে, তাই শুধু বিশ্বস্ত উৎসের কোড দিন। ক্লিক গোনা এখানে প্রযোজ্য নয়।</p>
        </div>
    </div>
@endif

<div class="ad-two ad-fgroup">
    <div>
        <label class="ad-label">শুরুর সময় (ঐচ্ছিক)</label>
        <input type="datetime-local" name="starts_at" value="{{ $starts }}" class="ad-field">
    </div>
    <div>
        <label class="ad-label">শেষের সময় (ঐচ্ছিক)</label>
        <input type="datetime-local" name="ends_at" value="{{ $ends }}" class="ad-field">
    </div>
</div>
<p class="ad-hint" style="margin:-8px 0 16px;">সময় ({{ config('app.timezone') }}) ফাঁকা রাখলে বিজ্ঞাপনটি সঙ্গে সঙ্গে শুরু হবে এবং বন্ধ না করা পর্যন্ত চলবে।</p>

<div class="ad-fgroup">
    <label class="ad-label">অবস্থা</label>
    <select name="status" class="ad-field">
        <option value="1" @selected($status === 1)>চালু</option>
        <option value="0" @selected($status === 0)>বন্ধ</option>
    </select>
</div>

<div class="ad-form-actions">
    <button type="submit" class="ad-btn-solid">{{ $isEdit ? 'আপডেট করুন' : 'সংরক্ষণ করুন' }}</button>
    <a href="{{ route('ads.index') }}" class="ad-cancel">বাতিল</a>
</div>

<script>
    (function () {
        const type = document.getElementById('ad-type');
        const imageBox = document.getElementById('ad-image-box');
        const codeBox = document.getElementById('ad-code-box');
        const file = document.getElementById('ad-file');
        const preview = document.getElementById('ad-preview');

        function sync() {
            const isCode = type.value === 'code';
            imageBox.hidden = isCode;
            if (codeBox) { codeBox.hidden = ! isCode; }
        }

        type.addEventListener('change', sync);
        sync();

        file.addEventListener('change', function () {
            const f = file.files && file.files[0];
            if (! f) { return; }
            preview.src = URL.createObjectURL(f);
            preview.hidden = false;
        });
    })();
</script>