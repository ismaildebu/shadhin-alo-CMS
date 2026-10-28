<?php

use App\Models\Category;
use App\Models\News;
use App\Models\Poll;
use App\Models\SidebarWidget;
use App\Models\User;

function sidebarPollSetup(): Poll
{
    $category = Category::create(['name' => 'জাতীয়', 'slug' => 'jatiyo']);

    News::create([
        'category_id' => $category->id,
        'title' => 'সাইডবারের পাশের খবর',
        'slug' => 'saidbar-khobor',
        'description' => '<p>x</p>',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now()->subMinute(),
    ]);

    $poll = Poll::create(['question' => 'সাইডবারের প্রশ্ন?', 'is_active' => true, 'published_at' => now()->subMinute()]);
    $poll->options()->create(['label' => 'এক', 'serial' => 1]);
    $poll->options()->create(['label' => 'দুই', 'serial' => 2]);

    return $poll;
}

it('has the poll as a managed sidebar widget', function () {
    $widget = SidebarWidget::where('name', 'poll')->first();

    expect($widget)->not->toBeNull()
        ->and($widget->visible)->toBeTrue()
        ->and(SidebarWidget::META)->toHaveKey('poll');
});

it('hides the poll from the sidebar when the widget is switched off', function () {
    sidebarPollSetup();

    $this->get('/')->assertOk()->assertSee('সাইডবারের প্রশ্ন?');

    SidebarWidget::where('name', 'poll')->firstOrFail()->update(['visible' => false]);

    $this->get('/')->assertOk()->assertDontSee('সাইডবারের প্রশ্ন?');
});

it('puts the poll where the widget order says', function () {
    sidebarPollSetup();

    SidebarWidget::where('name', 'poll')->firstOrFail()->update(['serial' => 5]);   // before the tabs (20)
    $html = $this->get('/')->assertOk()->getContent();

    expect(strpos($html, 'sa-poll-card'))->toBeLessThan(strpos($html, 'id="latestNews"'));

    SidebarWidget::where('name', 'poll')->firstOrFail()->update(['serial' => 25]);  // after the tabs
    $html = $this->get('/')->assertOk()->getContent();

    expect(strpos($html, 'id="latestNews"'))->toBeLessThan(strpos($html, 'sa-poll-card'));
});

it('is listed on the admin sidebar page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('sidebar.edit'))
        ->assertOk()
        ->assertSee('অনলাইন ভোট');
});
