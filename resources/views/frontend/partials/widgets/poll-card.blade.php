{{-- Poll card: date, question, options with live percentages, total, share buttons.
     Needs: $poll (with options). Optional: $withHead (default true) shows the "online vote" heading. --}}
@php
    $voterHash = \App\Support\PollVoter::hash(\App\Support\PollVoter::token(request()));
    $mine      = $voterHash ? \App\Models\PollVote::where('poll_id', $poll->id)->where('voter_hash', $voterHash)->first() : null;
    $mineId    = $mine ? (int) $mine->poll_option_id : null;
    $isOpen    = $poll->isOpen();
    $canVote   = $isOpen && ! $mine;
    $percent   = $poll->percentages();
    $total     = $poll->totalVotes();
    $shareUrl  = urlencode(route('frontend.polls.show', $poll));
    $shareText = urlencode($poll->question);
    $notice    = (int) session('poll_notice_id') === (int) $poll->id ? session('poll_notice') : null;
@endphp
<div class="sa-poll{{ $canVote ? '' : ' is-voted' }}" id="poll-{{ $poll->id }}" data-poll>

    @if($withHead ?? true)
        <div class="sa-poll-head">
            <h3 class="sa-poll-title">অনলাইন ভোট</h3>
            <a href="{{ route('frontend.polls.index') }}" class="sa-poll-archive" title="ভোটের আর্কাইভ" aria-label="ভোটের আর্কাইভ">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v10.2l3.6-3.6 1.4 1.4-6 6-6-6 1.4-1.4 3.6 3.6V3h2zM4 19h16v2H4v-2z"/></svg>
            </a>
        </div>
    @endif

    <div class="sa-poll-card">

        <div class="sa-poll-date">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 5v5.4l3.6 2.1-.8 1.3L11 13V7h2z"/></svg>
            <span>{{ \App\Support\BanglaTime::date($poll->shownDate()) }}</span>
        </div>

        <p class="sa-poll-q">{{ $poll->question }}</p>

        <form method="POST" action="{{ route('frontend.polls.vote', $poll) }}" class="sa-poll-form">
            @csrf
            @foreach($poll->options as $option)
                <label class="sa-poll-opt{{ $mineId === (int) $option->id ? ' is-mine' : '' }}" data-option="{{ $option->id }}" style="--pct: {{ $percent[$option->id] ?? 0 }}%">
                    <input type="radio" name="option" value="{{ $option->id }}" @checked($mineId === (int) $option->id) @disabled(! $canVote)>
                    <span class="sa-poll-label">{{ $option->label }}</span>
                    <span class="sa-poll-pct" data-pct>{{ \App\Support\BanglaTime::digits($percent[$option->id] ?? 0) }} %</span>
                </label>
            @endforeach
            @if($canVote)
                <noscript><button type="submit" class="sa-poll-btn">ভোট দিন</button></noscript>
            @endif
        </form>

        <p class="sa-poll-msg" data-msg data-text-ok="আপনার ভোট গ্রহণ করা হয়েছে।" data-text-already="আপনি আগেই ভোট দিয়েছেন।" data-text-error="দুঃখিত, ভোট দেওয়া যায়নি। আবার চেষ্টা করুন।" @if(! $notice) hidden @endif>{{ $notice }}</p>

        @if(! $isOpen)
            <p class="sa-poll-closed">এই ভোটে আর অংশ নেওয়া যাবে না।</p>
        @endif

        <div class="sa-poll-total">মোট ভোটদাতাঃ <span data-total>{{ \App\Support\BanglaTime::digits($total) }}</span> জন</div>

        <div class="sa-poll-share">
            <a class="sa-poll-sh" href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" rel="noopener" aria-label="Facebook" title="Facebook">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.2h-3.1V7.8c0-.9.3-1.5 1.6-1.5h1.7V3.4c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.5-4 4.1v2.4H7.7V13h2.7v8h3.1z"/></svg>
            </a>
            <a class="sa-poll-sh" href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&amp;text={{ $shareText }}" target="_blank" rel="noopener" aria-label="X" title="X">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.8 3h3l-6.6 7.5L22 21h-6.1l-4.8-6.3L5.6 21h-3l7-8L2 3h6.2l4.3 5.7L17.8 3zm-1 16.2h1.7L7.2 4.7H5.4l11.4 14.5z"/></svg>
            </a>
            <a class="sa-poll-sh" href="https://wa.me/?text={{ $shareText }}%20{{ $shareUrl }}" target="_blank" rel="noopener" aria-label="WhatsApp" title="WhatsApp">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 0 0-7.7 13.6L3 21l4.5-1.2A9 9 0 1 0 12 3zm0 1.8a7.2 7.2 0 1 1-3.7 13.4l-.3-.2-2.4.6.6-2.3-.2-.3A7.2 7.2 0 0 1 12 4.8zm-3 3.5c-.2 0-.5.1-.7.4-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 2.2.9 2.6.7 3.1.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.2-.2-.5-.3l-1.6-.8c-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1-.2-.1-1-.4-1.9-1.2-.7-.6-1.2-1.4-1.3-1.6-.1-.2 0-.4.1-.5l.4-.5c.1-.1.1-.3.2-.4 0-.1 0-.3 0-.4l-.7-1.7c-.2-.4-.4-.4-.5-.4z"/></svg>
            </a>
            <a class="sa-poll-sh" href="fb-messenger://share/?link={{ $shareUrl }}" aria-label="Messenger" title="Messenger">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.5 2 2 6.2 2 11.5c0 2.9 1.3 5.4 3.4 7.2V22l3.1-1.7c1 .3 2.1.4 3.2.4 5.5 0 10-4.2 10-9.5S17.5 2 12 2zm1 12.7-2.5-2.7-4.9 2.7 5.4-5.7 2.6 2.7 4.8-2.7-5.4 5.7z"/></svg>
            </a>
            <button type="button" class="sa-poll-sh" data-print aria-label="Print" title="Print">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v4H7V3zm-3 5h16a2 2 0 0 1 2 2v7h-4v4H6v-4H2v-7a2 2 0 0 1 2-2zm4 7v4h8v-4H8zm10-4a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>
            </button>
        </div>

    </div>

    <script src="{{ asset('js/poll.js') }}" defer></script>
</div>
