<?php

use App\Models\Poll;
use App\Models\PollVote;
use App\Models\SidebarWidget;
use App\Models\User;

/**
 * খোলা ভোট, দুটি অপশন: pdVote() দিয়ে ব্রাউজারের মতো ভোট দেওয়া যায়।
 */
function pdPoll(array $attributes = []): Poll
{
    $poll = Poll::create($attributes + [
        'question' => 'আপনি কি রাজি?',
        'is_active' => true,
        'published_at' => now()->subMinute(),
    ]);

    foreach (['হ্যাঁ', 'না'] as $i => $label) {
        $poll->options()->create(['label' => $label, 'serial' => $i + 1]);
    }

    return $poll->load('options');
}

/** ভোট দেয় এবং সেই ব্রাউজারের কুকি ধরে রাখে (ফলাফল দেখানোর অবস্থা বোঝার জন্য)। */
function pdVote(Poll $poll, int $optionIndex, ?string &$cookie = null): void
{
    $response = test()
        ->withCredentials()
        ->withUnencryptedCookie('sa_voter', $cookie ?? '')
        ->postJson(route('frontend.polls.vote', $poll), ['option' => $poll->options[$optionIndex]->id])
        ->assertOk();

    $set = $response->getCookie('sa_voter', false);

    if ($set) {
        $cookie = $set->getValue();
    }
}

function pdAdmin(): User
{
    return User::factory()->admin()->create();
}

beforeEach(function () {
    // ভোটের সাইডবার-অংশ সীড করা থাকে; শুধু ডিজাইনের কলাম ফেরত আনা হয়
    SidebarWidget::firstOrCreate(['name' => 'poll'], ['visible' => true, 'serial' => 1])
        ->update(['layout' => null, 'accent' => null]);
});

it('keeps the result numbers hidden until the reader votes', function () {
    $poll = pdPoll();
    pdVote($poll, 0);                       // অন্য ব্রাউজার ভোট দিয়েছে; ফলাফল আছে

    $html = $this->get(route('frontend.polls.show', $poll))->getContent();

    expect($html)->toContain('sa-poll--blind')
        ->and($html)->not->toContain('১০ %')
        ->and($html)->not->toContain('--pct: 100%')
        ->and($html)->toContain('ভোট দেওয়ার পর ফলাফল দেখা যাবে');
});

it('shows the percentages once the reader has voted', function () {
    $poll = pdPoll();
    $browser = null;
    pdVote($poll, 0, $browser);

    $html = $this->withUnencryptedCookie('sa_voter', $browser)->get(route('frontend.polls.show', $poll))->getContent();

    expect($html)->not->toContain('sa-poll--blind')
        ->and($html)->toContain('১০০ %')
        ->and($html)->toContain('--pct: 100%')
        ->and($html)->toContain('is-mine');
});

it('shows the results of a finished poll to everyone', function () {
    $poll = pdPoll(['is_active' => false]);

    // বন্ধ ভোটে ভোট দেওয়া যায় না — আগের ভোটটি সরাসরি বসানো হলো
    PollVote::create([
        'poll_id' => $poll->id,
        'poll_option_id' => $poll->options[1]->id,
        'voter_hash' => str_repeat('a', 64),
        'ip_hash' => str_repeat('b', 64),
    ]);
    $poll->options[1]->increment('votes_count');

    $html = $this->get(route('frontend.polls.show', $poll->fresh()->load('options')))->getContent();

    expect($html)->not->toContain('sa-poll--blind')
        ->and($html)->toContain('১০০ %')
        ->and($html)->toContain('এই ভোটে আর অংশ নেওয়া যাবে না।');
});

it('uses the dashboard design on the poll page, not only in the sidebar', function () {
    $poll = pdPoll();

    $this->actingAs(pdAdmin())->put(route('sidebar.update'), [
        'rows' => [
            'poll' => ['show' => '1', 'layout' => 'side', 'accent_on' => '1', 'accent' => '#0f766e'],
        ],
    ])->assertRedirect();

    foreach ([$this->get(route('frontend.polls.show', $poll))->getContent(),
        $this->get(route('frontend.polls.index'))->getContent()] as $html) {
        expect($html)->toContain('sa-poll--side')
            ->and($html)->toContain('--sa-poll-accent: #0f766e');
    }
});

it('falls back to the default design when the dashboard choice is unusable', function () {
    // মডেলের update: ইভেন্ট চলে, memo-র পুরোনো ডিজাইনও মুছে যায়
    SidebarWidget::firstWhere('name', 'poll')->update(['layout' => 'bogus', 'accent' => 'not-a-colour']);

    $html = $this->get(route('frontend.polls.show', pdPoll()))->getContent();

    expect($html)->toContain('sa-poll--cover')
        ->and($html)->toContain('--sa-poll-accent: #d60000');
});
