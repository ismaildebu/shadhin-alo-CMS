@props(['news'])

<div class="audio-news-player bg-light p-3 rounded border my-3 d-flex align-items-center justify-content-between" id="audio-container-{{ $news->id }}">
    <div class="d-flex align-items-center gap-3">
        @if($news->audio_url)
            <audio id="player-{{ $news->id }}" src="{{ $news->audio_url }}" preload="metadata"></audio>
            <button type="button" class="btn btn-danger btn-sm rounded-circle p-2" onclick="toggleAudio({{ $news->id }})" id="btn-{{ $news->id }}" aria-label="{{ __('news.listen_news') }}">
                <i class="fas fa-play" id="icon-{{ $news->id }}"></i>
            </button>
            <div>
                <strong class="d-block mb-0">{{ __('news.listen_news') }}</strong>
                <small class="text-muted" id="timer-{{ $news->id }}">০:০০</small>
            </div>
        @else
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="generateAudio({{ $news->id }})" id="gen-btn-{{ $news->id }}">
                <i class="fas fa-volume-up"></i> {{ __('news.listen_news') }} (অডিও তৈরি করুন)
            </button>
        @endif
    </div>
</div>

<script>
function toggleAudio(id) {
    const audio = document.getElementById('player-' + id);

    if (! audio) {
        return;
    }

    const icon = document.getElementById('icon-' + id);
    const timer = document.getElementById('timer-' + id);

    if (audio.paused) {
        audio.play().catch(function () {
            alert('অডিও চালু করা যায়নি। আবার চেষ্টা করুন।');
        });
        if (icon) icon.className = 'fas fa-pause';
    } else {
        audio.pause();
        if (icon) icon.className = 'fas fa-play';
    }

    audio.ontimeupdate = function () {
        if (! timer) return;

        const mins = Math.floor(audio.currentTime / 60);
        let secs = Math.floor(audio.currentTime % 60);
        if (secs < 10) secs = '0' + secs;
        timer.innerText = mins + ':' + secs;
    };

    audio.onended = function () {
        if (icon) icon.className = 'fas fa-play';
    };

    audio.onerror = function () {
        if (timer) timer.innerText = 'অডিও ফাইলটি পাওয়া যাচ্ছে না';
    };
}

function generateAudio(id) {
    const btn = document.getElementById('gen-btn-' + id);

    if (! btn) return;

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ __("news.generating_audio") }}';

    fetch('/news/' + id + '/generate-audio', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(async function (response) {
        const data = await response.json().catch(function () { return {}; });

        if (! response.ok || ! data.success) {
            throw new Error(data.message || 'অডিও তৈরি করতে সমস্যা হয়েছে।');
        }

        return data;
    })
    .then(function () {
        location.reload();
    })
    .catch(function (error) {
        alert(error.message || 'সার্ভার কানেকশনে সমস্যা হয়েছে।');
        btn.disabled = false;
        btn.innerHTML = original;
    });
}
</script>
