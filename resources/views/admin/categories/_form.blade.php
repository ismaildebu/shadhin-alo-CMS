@php
    $isEdit = $category->exists;
    $val = fn (string $field, $default = '') => old($field, $isEdit ? ($category->{$field} ?? $default) : $default);
    $hasChildren = $hasChildren ?? false;
@endphp

@if($errors->any())
    <div class="ad-alert ad-alert-err">
        <ul class="ad-errlist">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="ad-fgroup">
    <label class="ad-label">ক্যাটাগরির নাম <span class="ad-req">*</span></label>
    <input type="text" name="name" value="{{ $val('name') }}" class="ad-field" required>
</div>

<div class="ad-fgroup">
    <label class="ad-label">Slug</label>
    <input type="text" name="slug" value="{{ $val('slug') }}" class="ad-field" placeholder="যেমন: jatiyo">
    <p class="ad-hint">ফাঁকা রাখলে নাম থেকে স্বয়ংক্রিয়ভাবে তৈরি হবে।</p>
</div>

<div class="ad-fgroup">
    <label class="ad-label">প্যারেন্ট ক্যাটাগরি</label>
    <select name="parent_id" class="ad-field" @if($hasChildren) disabled @endif>
        <option value="">— নেই (মূল ক্যাটাগরি) —</option>
        @foreach($parents as $parent)
            <option value="{{ $parent->id }}" @selected((string) $val('parent_id') === (string) $parent->id)>{{ $parent->name }}</option>
        @endforeach
    </select>
    @if($hasChildren)
        <p class="ad-hint">এই ক্যাটাগরির উপ-ক্যাটাগরি আছে, তাই এটি অন্যের উপ-ক্যাটাগরি হতে পারবে না।</p>
    @else
        <p class="ad-hint">উপ-ক্যাটাগরি শুধু এক স্তর পর্যন্ত।</p>
    @endif
</div>

<div class="ad-three ad-fgroup">
    <div>
        <label class="ad-label">ক্রম (Serial)</label>
        <input type="number" name="serial" min="0" value="{{ $val('serial', 0) }}" class="ad-field">
        <p class="ad-hint">ছোট সংখ্যা আগে।</p>
    </div>
    <div>
        <label class="ad-label">স্ট্যাটাস</label>
        <select name="status" class="ad-field">
            <option value="1" @selected((string) (int) $val('status', true) === '1')>সক্রিয়</option>
            <option value="0" @selected((string) (int) $val('status', true) === '0')>নিষ্ক্রিয়</option>
        </select>
    </div>
    <div>
        <label class="ad-label">মেনুতে দেখাবে?</label>
        <select name="show_in_menu" class="ad-field">
            <option value="1" @selected((string) (int) $val('show_in_menu', true) === '1')>হ্যাঁ</option>
            <option value="0" @selected((string) (int) $val('show_in_menu', true) === '0')>না</option>
        </select>
    </div>
</div>

<div class="ad-fgroup">
    <label class="ad-label">মেটা শিরোনাম (SEO, ঐচ্ছিক)</label>
    <input type="text" name="meta_title" value="{{ $val('meta_title') }}" class="ad-field">
</div>

<div class="ad-fgroup">
    <label class="ad-label">মেটা বিবরণ (SEO, ঐচ্ছিক, সর্বোচ্চ ৫০০ অক্ষর)</label>
    <textarea name="meta_description" rows="2" class="ad-field">{{ $val('meta_description') }}</textarea>
</div>

<div class="ad-form-actions">
    <button type="submit" class="ad-btn-solid">{{ $isEdit ? 'আপডেট করুন' : 'সংরক্ষণ করুন' }}</button>
    <a href="{{ route('categories.index') }}" class="ad-cancel">বাতিল</a>
</div>