@props(['news'])

<div class="audio-news-player bg-light p-3 rounded border my-3 d-flex align-items-center justify-content-between" id="audio-container-{{ $news->id }}">
    <div class="d-flex align-items-center gap-3">
        @if($news->audio_url)
            <audio id="player-{{ $news->id }}" src="{{ $news->audio_url }}" preload="metadata"></audio>
            <button class="btn btn-danger btn-sm rounded-circle p-2" onclick="toggleAudio({{ $news->id }})" id="btn-{{ $news->id }}">
                <i class="fas fa-play" id="icon-{{ $news->id }}"></i>
            </button>
            <div>
                <strong class="d-block mb-0">{{ __('news.listen_news') }}</strong>
                <small class="text-muted" id="timer-{{ $news->id }}">অডিও প্রস্তুত</small>
            </div>
        @else
            <button class="btn btn-outline-danger btn-sm" onclick="generateAudio({{ $news->id }})" id="gen-btn-{{ $news->id }}">
                <i class="fas fa-volume-up"></i> {{ __('news.listen_news') }} (অডিও তৈরি করুন)
            </button>
        @endif
    </div>
</div>

<script>
function toggleAudio(id) {
    const audio = document.getElementById('player-' + id);
    const icon = document.getElementById('icon-' + id);

    if (audio.paused) {
        audio.play();
        icon.className = 'fas fa-pause';
    } else {
        audio.pause();
        icon.className = 'fas fa-play';
    }

    audio.ontimeupdate = function() {
        let mins = Math.floor(audio.currentTime / 60);
        let secs = Math.floor(audio.currentTime % 60);
        if (secs < 10) secs = '0' + secs;
        document.getElementById('timer-' + id).innerText = mins + ':' + secs;
    };
    
    audio.onended = function() {
        icon.className = 'fas fa-play';
    };
}

function generateAudio(id) {
    const btn = document.getElementById('gen-btn-' + id);
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ __("news.generating_audio") }}';

    fetch(`/news/${id}/generate-audio`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'অডিও তৈরি করতে সমস্যা হয়েছে।');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-volume-up"></i> {{ __("news.listen_news") }}';
        }
    })
    .catch(() => {
        alert('সার্ভার কানেকশনে সমস্যা হয়েছে।');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-volume-up"></i> {{ __("news.listen_news") }}';
    });
}
</script>





@props(['news'])

<div class="audio-news-player bg-light p-3 rounded border my-3 d-flex align-items-center justify-content-between" id="audio-container-{{ $news->id }}">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-danger btn-sm rounded-circle p-2" onclick="playSimpleAudio('{{ addslashes(strip_tags($news->localized_title)) }}', {{ $news->id }})" id="btn-{{ $news->id }}">
            <i class="fas fa-volume-up" id="icon-{{ $news->id }}"></i>
        </button>
        <div>
            <strong class="d-block mb-0">{{ __('news.listen_news') ?? 'সংবাদ শুনুন' }}</strong>
            <small class="text-muted" id="status-{{ $news->id }}">শুনতে এখানে ক্লিক করুন</small>
        </div>
    </div>
</div>

<script>
function playSimpleAudio(titleText, id) {
    const statusText = document.getElementById('status-' + id);
    
    if (!('speechSynthesis' in window)) {
        alert('আপনার ব্রাউজার এই ফিচারটি সমর্থন করছে না।');
        return;
    }

    // যদি আগে থেকেই কিছু বলতে থাকে তবে থামিয়ে দেবো
    window.speechSynthesis.cancel();

    let utterance = new SpeechSynthesisUtterance(titleText);
    utterance.lang = 'bn-BD'; // বাংলা ভাষা
    utterance.rate = 0.9;   // পড়ার গতি একটু স্বাভাবিক রাখা

    utterance.onstart = function() {
        statusText.innerText = 'সংবাদটি পড়া হচ্ছে...';
    };

    utterance.onend = function() {
        statusText.innerText = 'পড়া শেষ হয়েছে';
    };

    utterance.onerror = function(e) {
        // যদি ব্রাউজারে বাংলা ভয়েস প্যাক না থাকে, তবে নোটিফিকেশন দেখাবে
        statusText.innerText = 'ভয়েস প্যাক পাওয়া যায়নি';
        console.warn('Speech Synthesis Error:', e);
        alert('আপনার কম্পিউটারে বা ব্রাউজারে বাংলা ভয়েস (Bengali Speech Voice) ইনস্টল করা নেই। দয়া করে উইন্ডোজের Settings > Time & Language > Speech থেকে Bengali ভয়েস প্যাক ইনস্টল করে নিন।');
    };

    window.speechSynthesis.speak(utterance);
}
</script>