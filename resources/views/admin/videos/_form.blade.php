@php
    $isEdit = $video->exists;
    $title = old('title', $isEdit ? $video->title : '');
    $url = old('url', $isEdit ? $video->watch_url : '');
    $description = old('description', $isEdit ? $video->description : '');
    $status = (int) old('status', $isEdit ? (int) $video->status : 1);
    $previewId = \App\Models\Video::extractYoutubeId($url);
@endphp

@if($errors->any())
    <div class="ad-alert ad-alert-err">
        <ul class="ad-errlist">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="ad-fgroup">
    <label class="ad-label">শিরোনাম <span class="ad-req">*</span></label>
    <input type="text" name="title" value="{{ $title }}" class="ad-field" required>
</div>

<div class="ad-fgroup">
    <label class="ad-label">ইউটিউব লিংক <span class="ad-req">*</span></label>
    <input type="text" id="video-url" name="url" value="{{ $url }}" class="ad-field"
           placeholder="https://www.youtube.com/watch?v=..." required>
    <p class="ad-hint">watch, youtu.be, shorts ও embed লিংক চলবে। লিংক বসালে নিচে থাম্বনেইল দেখা যাবে।</p>
    <img id="video-preview" class="ad-vprev" alt=""
         @if($previewId) src="https://i.ytimg.com/vi/{{ $previewId }}/hqdefault.jpg" @else hidden @endif>
</div>

<div class="ad-fgroup">
    <label class="ad-label">বিবরণ (ঐচ্ছিক)</label>
    <textarea name="description" rows="3" class="ad-field">{{ $description }}</textarea>
</div>

<div class="ad-fgroup">
    <label class="ad-label">অবস্থা</label>
    <select name="status" class="ad-field">
        <option value="1" @selected($status === 1)>প্রকাশিত</option>
        <option value="0" @selected($status === 0)>লুকানো</option>
    </select>
</div>

<div class="ad-form-actions">
    <button type="submit" class="ad-btn-solid">{{ $isEdit ? 'আপডেট করুন' : 'সংরক্ষণ করুন' }}</button>
    <a href="{{ route('videos.index') }}" class="ad-cancel">বাতিল</a>
</div>

<script>
    (function () {
        const input = document.getElementById('video-url');
        const img = document.getElementById('video-preview');
        const re = /(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:[^#]*&)?v=|embed\/|shorts\/|live\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/;

        function update() {
            const value = input.value.trim();
            const m = /^[A-Za-z0-9_-]{11}$/.test(value) ? [null, value] : value.match(re);

            if (m) {
                img.src = 'https://i.ytimg.com/vi/' + m[1] + '/hqdefault.jpg';
                img.hidden = false;
            } else {
                img.removeAttribute('src');
                img.hidden = true;
            }
        }

        input.addEventListener('input', update);
    })();
</script>