<?php

use App\Models\Category;
use App\Models\News;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use App\Support\PollMath;

function pollWithOptions(array $attributes = [], array $labels = ['হ্যাঁ ভোট', 'না ভোট', 'মন্তব্য নেই']): Poll
{
    $poll = Poll::create($attributes + [
        'question' => 'আপনি কি এই মন্তব্যকে যথাযথ মনে করেন?',
        'is_active' => true,
        'published_at' => now()->subMinute(),
    ]);

    foreach ($labels as $i => $label) {
        $poll->options()->create(['label' => $label, 'serial' => $i + 1]);
    }

    return $poll->load('options');
}

/** Votes the way a browser does: sends back the cookie the server set last time (kept in $cookie). */
function pollVote(Poll $poll, int $optionIndex, ?string &$cookie = null)
{
    $response = test()
        ->withCredentials()                                   // JSON requests only carry cookies when this is switched on
        ->withUnencryptedCookie('sa_voter', $cookie ?? '')   // already encrypted by the server; '' = a browser with no cookie
        ->postJson(route('frontend.polls.vote', $poll), ['option' => $poll->options[$optionIndex]->id]);

    $set = $response->getCookie('sa_voter', false);

    if ($set) {
        $cookie = $set->getValue();
    }

    return $response;
}

function pollHomeNeedsNews(): void
{
    $category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);

    News::create([
        'category_id' => $category->id,
        'title' => 'ভোটের পাশের খবর',
        'slug' => 'votter-pashey-khobor',
        'description' => '<p>x</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
    ]);
}

// ------------------------------------------------------------------ percentages

it('splits percentages so they add up to 100', function () {
    expect(PollMath::percentages([1 => 1, 2 => 1, 3 => 2]))->toBe([1 => 25, 2 => 25, 3 => 50])
        ->and(PollMath::percentages([1 => 1, 2 => 1, 3 => 1]))->toBe([1 => 34, 2 => 33, 3 => 33])
        ->and(PollMath::percentages([1 => 0, 2 => 0]))->toBe([1 => 0, 2 => 0])
        ->and(array_sum(PollMath::percentages([1 => 7, 2 => 11, 3 => 13, 4 => 1])))->toBe(100);
});

// ------------------------------------------------------------------ voting

it('records a vote and returns the new results', function () {
    $poll = pollWithOptions();

    $json = pollVote($poll, 0)->assertOk()->json();

    expect($json['already'])->toBeFalse()
        ->and($json['selected'])->toBe($poll->options[0]->id)
        ->and($json['total'])->toBe(1)
        ->and($json['total_text'])->toBe('১')
        ->and($json['options'][0]['pct'])->toBe(100)
        ->and($json['options'][0]['pct_text'])->toBe('১০০ %')
        ->and($poll->options[0]->fresh()->votes_count)->toBe(1)
        ->and(PollVote::count())->toBe(1);
});

it('counts one vote per browser and shows the earlier choice on a repeat', function () {
    $poll = pollWithOptions();

    $browser = null;

    pollVote($poll, 0, $browser)->assertOk();
    $json = pollVote($poll, 2, $browser)->assertOk()->json();

    expect($json['already'])->toBeTrue()
        ->and($json['selected'])->toBe($poll->options[0]->id)
        ->and($json['total'])->toBe(1)
        ->and(PollVote::count())->toBe(1)
        ->and($poll->options[2]->fresh()->votes_count)->toBe(0);
});

it('counts different browsers separately', function () {
    $poll = pollWithOptions();

    $first = null;
    $second = null;

    pollVote($poll, 0, $first)->assertOk();
    $json = pollVote($poll, 1, $second)->assertOk()->json();

    expect($json['total'])->toBe(2)
        ->and($json['options'][0]['pct'])->toBe(50)
        ->and($json['options'][1]['pct'])->toBe(50);
});

it('stores only hashes, never the raw token', function () {
    $poll = pollWithOptions();

    $browser = null;

    pollVote($poll, 0, $browser)->assertOk();

    $vote = PollVote::firstOrFail();

    expect($vote->voter_hash)->toMatch('/^[0-9a-f]{64}$/')->not->toBe($browser)
        ->and($vote->ip_hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('rejects an option of another poll and a missing option', function () {
    $poll = pollWithOptions();
    $other = pollWithOptions(['question' => 'অন্য প্রশ্ন']);

    $this->postJson(route('frontend.polls.vote', $poll), ['option' => $other->options[0]->id])->assertStatus(422);
    $this->postJson(route('frontend.polls.vote', $poll), [])->assertStatus(422);

    expect(PollVote::count())->toBe(0);
});

it('does not take votes on a closed or not yet published poll', function () {
    $closed = pollWithOptions(['is_active' => false]);
    $future = pollWithOptions(['published_at' => now()->addDay()]);

    $this->postJson(route('frontend.polls.vote', $closed), ['option' => $closed->options[0]->id])->assertNotFound();
    $this->postJson(route('frontend.polls.vote', $future), ['option' => $future->options[0]->id])->assertNotFound();

    expect(PollVote::count())->toBe(0);
});

it('falls back to a redirect with a notice when JavaScript is off', function () {
    $poll = pollWithOptions();

    $this->from('/')
        ->post(route('frontend.polls.vote', $poll), ['option' => $poll->options[0]->id])
        ->assertRedirect('/')
        ->assertSessionHas('poll_notice');

    expect(PollVote::count())->toBe(1);
});

// ------------------------------------------------------------------ pages

it('shows the newest open poll in the sidebar of the home page', function () {
    pollHomeNeedsNews();
    pollWithOptions(['question' => 'পুরনো প্রশ্ন', 'published_at' => now()->subDays(3)]);
    pollWithOptions(['question' => 'সবচেয়ে নতুন প্রশ্ন', 'published_at' => now()->subHour()]);

    $this->get('/')->assertOk()
        ->assertSee('অনলাইন ভোট')
        ->assertSee('সবচেয়ে নতুন প্রশ্ন')
        ->assertDontSee('পুরনো প্রশ্ন')
        ->assertSee('মোট ভোটদাতাঃ', false);
});

it('draws nothing in the sidebar when no poll is open', function () {
    pollHomeNeedsNews();
    pollWithOptions(['question' => 'বন্ধ প্রশ্ন', 'is_active' => false]);
    pollWithOptions(['question' => 'ভবিষ্যতের প্রশ্ন', 'published_at' => now()->addDay()]);

    $this->get('/')->assertOk()->assertDontSee('বন্ধ প্রশ্ন')->assertDontSee('ভবিষ্যতের প্রশ্ন')->assertDontSee('sa-poll-card', false);
});

it('keeps closed polls and their results in the archive', function () {
    $closed = pollWithOptions(['question' => 'বন্ধ প্রশ্ন', 'is_active' => false]);
    pollWithOptions(['question' => 'ভবিষ্যতের প্রশ্ন', 'published_at' => now()->addDay()]);

    $this->get(route('frontend.polls.index'))->assertOk()->assertSee('বন্ধ প্রশ্ন')->assertDontSee('ভবিষ্যতের প্রশ্ন');
    $this->get(route('frontend.polls.show', $closed))->assertOk()->assertSee('এই ভোটে আর অংশ নেওয়া যাবে না।');
});

it('hides a poll that is not published yet from its own page', function () {
    $future = pollWithOptions(['published_at' => now()->addDay()]);

    $this->get(route('frontend.polls.show', $future))->assertNotFound();
});

it('marks the visitor\'s own choice and locks the options after voting', function () {
    $poll = pollWithOptions();
    $browser = null;
    pollVote($poll, 1, $browser)->assertOk();

    $html = $this->withUnencryptedCookie('sa_voter', $browser)->get(route('frontend.polls.show', $poll))->assertOk()->getContent();

    expect($html)->toContain('is-mine')->toContain('sa-poll is-voted');
});

// ------------------------------------------------------------------ admin

function pollAdmin(): User
{
    return User::factory()->admin()->create();
}

it('keeps the admin pages away from guests and non-admins', function () {
    $this->get(route('admin.polls.index'))->assertRedirect();

    $this->actingAs(User::factory()->reporter()->create())->get(route('admin.polls.index'))->assertForbidden();
});

it('lets an admin create a poll with its options', function () {
    $this->actingAs(pollAdmin())->get(route('admin.polls.create'))->assertOk()->assertSee('হ্যাঁ ভোট');

    $this->actingAs(pollAdmin())->post(route('admin.polls.store'), [
        'question' => 'নতুন প্রশ্ন?',
        'is_active' => '1',
        'options' => [['label' => 'এক'], ['label' => 'দুই'], ['label' => '']],
    ])->assertRedirect(route('admin.polls.index'));

    $poll = Poll::firstOrFail();

    expect($poll->question)->toBe('নতুন প্রশ্ন?')
        ->and($poll->is_active)->toBeTrue()
        ->and($poll->options->pluck('label')->all())->toBe(['এক', 'দুই'])
        ->and($poll->published_at)->not->toBeNull();
});

it('needs a question and at least two options', function () {
    $this->actingAs(pollAdmin())->post(route('admin.polls.store'), [
        'question' => 'একটি অপশন',
        'options' => [['label' => 'শুধু এক']],
    ])->assertSessionHasErrors('options');

    $this->actingAs(pollAdmin())->post(route('admin.polls.store'), [
        'question' => '',
        'options' => [['label' => 'এক'], ['label' => 'দুই']],
    ])->assertSessionHasErrors('question');

    expect(Poll::count())->toBe(0);
});

it('changes the options of a poll nobody has voted on yet', function () {
    $poll = pollWithOptions();

    $this->actingAs(pollAdmin())->put(route('admin.polls.update', $poll), [
        'question' => 'বদলানো প্রশ্ন',
        'options' => [['label' => 'শুধু হ্যাঁ'], ['label' => 'শুধু না']],
    ])->assertRedirect(route('admin.polls.index'));

    $poll->refresh()->load('options');

    expect($poll->question)->toBe('বদলানো প্রশ্ন')
        ->and($poll->is_active)->toBeFalse()   // the checkbox was not sent
        ->and($poll->options->pluck('label')->all())->toBe(['শুধু হ্যাঁ', 'শুধু না']);
});

it('only lets the wording change once votes exist', function () {
    $poll = pollWithOptions();
    pollVote($poll, 0)->assertOk();
    $first = $poll->options[0];

    $this->actingAs(pollAdmin())->put(route('admin.polls.update', $poll), [
        'question' => $poll->question,
        'is_active' => '1',
        'options' => [
            ['id' => $first->id, 'label' => 'নতুন লেখা'],
            ['id' => $poll->options[1]->id, 'label' => 'না'],
            ['label' => 'নতুন অপশন'],
        ],
    ])->assertRedirect();

    $poll->refresh()->load('options');

    expect($poll->options)->toHaveCount(3)
        ->and($poll->options[0]->label)->toBe('নতুন লেখা')
        ->and($poll->options[0]->votes_count)->toBe(1)
        ->and($poll->options->pluck('label')->all())->not->toContain('নতুন অপশন');
});

it('lets an admin delete a poll with its options and votes', function () {
    $poll = pollWithOptions();
    pollVote($poll, 0)->assertOk();

    $this->actingAs(pollAdmin())->delete(route('admin.polls.destroy', $poll))->assertRedirect(route('admin.polls.index'));

    expect(Poll::count())->toBe(0)->and(PollVote::count())->toBe(0);
});

it('lists the polls for an admin', function () {
    pollWithOptions(['question' => 'তালিকার প্রশ্ন']);

    $this->actingAs(pollAdmin())->get(route('admin.polls.index'))->assertOk()->assertSee('তালিকার প্রশ্ন')->assertSee('চালু');
});
