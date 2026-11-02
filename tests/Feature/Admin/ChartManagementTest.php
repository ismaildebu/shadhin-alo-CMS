<?php

use App\Models\Chart;
use App\Models\User;
use App\Support\ArticleVisuals;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('keeps chart management away from guests and non-admins', function () {
    $this->get(route('charts.index'))->assertRedirect();

    $this->actingAs(User::factory()->reporter()->create())
        ->get(route('charts.index'))
        ->assertForbidden();

    $this->actingAs($this->admin)->get(route('charts.index'))->assertOk();
});

it('creates a grouped chart with one data set per series', function () {
    $this->actingAs($this->admin)->post(route('charts.store'), [
        'title' => 'বিভাগভিত্তিক তুলনা',
        'type' => 'grouped',
        'unit' => '%',
        'source' => 'বিবিএস ২০২৪',
        'labels' => '২০২১|২০২২|২০২৩',
        'series_rows' => "Fig 1 | ১০, 12, 14\nFig 2 | 15, 18, 20",
        'serial' => 2,
        'is_published' => '1',
    ])->assertRedirect();

    $chart = Chart::firstOrFail();

    expect($chart->title)->toBe('বিভাগভিত্তিক তুলনা')
        ->and($chart->type)->toBe('grouped')
        ->and($chart->serial)->toBe(2)
        ->and($chart->is_published)->toBeTrue()
        ->and($chart->data['labels'])->toBe(['২০২১', '২০২২', '২০২৩'])
        ->and($chart->data['series'][0])->toEqual(['name' => 'Fig 1', 'values' => [10, 12, 14]])
        ->and($chart->data['series'][1])->toEqual(['name' => 'Fig 2', 'values' => [15, 18, 20]]);
});

it('creates a single-series chart and accepts Bengali digits', function () {
    $this->actingAs($this->admin)->post(route('charts.store'), [
        'title' => 'জেলাভিত্তিক হার',
        'type' => 'column',
        'rows' => "ঢাকা | ৭৮\nচট্টগ্রাম | 74",
        'is_published' => '1',
    ])->assertRedirect();

    $chart = Chart::firstOrFail();

    expect($chart->type)->toBe('column')
        ->and($chart->data)->toEqual(['labels' => ['ঢাকা', 'চট্টগ্রাম'], 'values' => [78, 74]]);
});

it('rejects a grouped series whose value count does not match the labels', function () {
    $this->actingAs($this->admin)->post(route('charts.store'), [
        'title' => 'ভুল তথ্য',
        'type' => 'grouped',
        'labels' => '২০২১|২০২২',
        'series_rows' => 'Fig 1 | 10, 12, 14',
        'is_published' => '1',
    ])->assertSessionHasErrors('series_rows');

    expect(Chart::count())->toBe(0);
});

it('updates and deletes a chart', function () {
    $chart = Chart::create([
        'title' => 'পুরোনো নাম',
        'type' => 'pie',
        'data' => ['labels' => ['ক', 'খ'], 'values' => [1, 2]],
        'is_published' => true,
    ]);

    $this->actingAs($this->admin)->put(route('charts.update', $chart), [
        'title' => 'নতুন নাম',
        'type' => 'donut',
        'rows' => "ক | 5\nখ | 7",
        'serial' => 9,
        'is_published' => '1',
    ])->assertRedirect();

    $chart->refresh();

    expect($chart->title)->toBe('নতুন নাম')
        ->and($chart->type)->toBe('donut')
        ->and($chart->data['values'])->toEqual([5, 7]);

    $this->actingAs($this->admin)
        ->delete(route('charts.destroy', $chart))
        ->assertRedirect(route('charts.index'));

    expect(Chart::count())->toBe(0);
});

it('lists every chart with a copyable shortcode', function () {
    $chart = Chart::create([
        'title' => 'তালিকার গ্রাফ',
        'type' => 'column',
        'data' => ['labels' => ['ক', 'খ'], 'values' => [1, 2]],
        'is_published' => false,
    ]);

    $this->actingAs($this->admin)->get(route('charts.index'))
        ->assertOk()
        ->assertSee('তালিকার গ্রাফ')
        ->assertSee('লুকানো')
        ->assertSee('[chart id="'.$chart->id.'"]', false);
});

it('shows only published charts on the public page, in serial order', function () {
    Chart::create([
        'title' => 'দ্বিতীয় গ্রাফ',
        'type' => 'column',
        'data' => ['labels' => ['ক', 'খ'], 'values' => [1, 2]],
        'is_published' => true,
        'serial' => 2,
    ]);

    Chart::create([
        'title' => 'প্রথম গ্রাফ',
        'type' => 'pie',
        'data' => ['labels' => ['ক', 'খ'], 'values' => [1, 2]],
        'is_published' => true,
        'serial' => 1,
    ]);

    Chart::create([
        'title' => 'লুকানো গ্রাফ',
        'type' => 'line',
        'data' => ['labels' => ['ক', 'খ'], 'values' => [1, 2]],
        'is_published' => false,
        'serial' => 0,
    ]);

    $this->get(route('frontend.charts.index'))
        ->assertOk()
        ->assertSeeInOrder(['প্রথম গ্রাফ', 'দ্বিতীয় গ্রাফ'])
        ->assertDontSee('লুকানো গ্রাফ');
});

it('renders a saved chart shortcode with grouped columns and a data table', function () {
    $chart = Chart::create([
        'title' => 'সেভ করা গ্রাফ',
        'type' => 'grouped',
        'unit' => '%',
        'data' => [
            'labels' => ['ক', 'খ'],
            'series' => [
                ['name' => 'Fig 1', 'values' => [10, 20]],
                ['name' => 'Fig 2', 'values' => [15, 25]],
            ],
        ],
        'is_published' => true,
    ]);

    $html = ArticleVisuals::expand('[chart id="'.$chart->id.'"]');

    expect($html)->toContain('sa-group-chart')
        ->toContain('Fig 1')
        ->toContain('Fig 2')
        ->toContain('sa-group-table')
        ->and($chart->render())->toContain('সেভ করা গ্রাফ');
});

it('renders grouped columns with axis ticks, grid lines and series colours', function () {
    $chart = Chart::create([
        'title' => 'সিরিজ তুলনা',
        'type' => 'grouped',
        'unit' => '%',
        'data' => [
            'labels' => ['২০২১', '২০২২'],
            'series' => [
                ['name' => 'Fig 1', 'values' => [10, 20]],
                ['name' => 'Fig 2', 'values' => [15, 25]],
            ],
        ],
        'is_published' => true,
    ]);

    $html = ArticleVisuals::expand($chart->shortcode());

    expect($html)->toContain('sa-group-chart')
        ->toContain('sa-group-grid')
        ->toContain('sa-group-base')
        ->toContain('sa-group-tick')
        ->toContain('sa-group-xlabel')
        ->toContain('#1f9bd7')
        ->toContain('#e04b23')
        ->toContain('Fig 1')
        ->toContain('Fig 2');
});

it('writes each pie value and percentage inside its slice', function () {
    $chart = Chart::create([
        'title' => 'ভাগের হিসাব',
        'type' => 'pie',
        'data' => ['labels' => ['ক', 'খ'], 'values' => [75, 25]],
        'is_published' => true,
    ]);

    $html = ArticleVisuals::expand($chart->shortcode());

    expect($html)->toContain('sa-pie-label')
        ->toContain('75%')
        ->toContain('25%')
        ->not->toContain('sa-legend-value');
});

it('creates and renders a three-dimensional arrow chart', function () {
    $this->actingAs($this->admin)->post(route('charts.store'), [
        'title' => 'ধারাবাহিক উত্থান',
        'type' => '3d',
        'unit' => 'হাজার',
        'rows' => "২০২১ | 10\n২০২২ | 18\n২০২৩ | 26",
        'is_published' => '1',
    ])->assertRedirect();

    $chart = Chart::firstOrFail();
    $html = ArticleVisuals::expand($chart->shortcode());

    expect($chart->type)->toBe('3d')
        ->and($html)->toContain('sa-visual-3d')
        ->toContain('sa-3d-chart')
        ->toContain('sa-3d-label')
        ->toContain('hsl(118, 88%, 49%)')
        ->toContain('linearGradient');
});

it('adds an image download button to every visual', function () {
    expect(ArticleVisuals::expand('[chart type="grouped" title="তুলনা" labels="ক|খ" series="Fig 1=1,2"]'))->toContain('sa-download')
        ->and(ArticleVisuals::expand('[chart type="pie" title="ভাগ" labels="ক|খ" values="1|2"]'))->toContain('sa-download')
        ->and(ArticleVisuals::expand('[stats title="তথ্য" items="ক=১|খ=২"]'))->toContain('sa-download');
});

it('hides a saved chart shortcode when the chart is unpublished', function () {
    $chart = Chart::create([
        'title' => 'লুকানো',
        'type' => 'column',
        'data' => ['labels' => ['ক', 'খ'], 'values' => [1, 2]],
        'is_published' => false,
    ]);

    expect(ArticleVisuals::expand('[chart id="'.$chart->id.'"]'))
        ->toContain('সংরক্ষিত গ্রাফটি পাওয়া যায়নি')
        ->and(ArticleVisuals::thumb('[chart id="'.$chart->id.'"]'))->toBeNull();
});

it('renders compact card charts for every chart family', function () {
    $column = ArticleVisuals::thumb('[chart type="column" title="কলাম" labels="ক|খ" values="3|7"]');
    $line = ArticleVisuals::thumb('[chart type="line" labels="ক|খ|গ" values="1|4|2"]');
    $pie = ArticleVisuals::thumb('[chart type="donut" labels="ক|খ" values="2|3"]');
    $grouped = ArticleVisuals::thumb('[chart type="grouped" labels="ক|খ" series="Fig1=1,2|Fig2=2,1"]');

    expect($column)->not->toBeNull()
        ->and($column['svg'])->toContain('<rect')
        ->and($column['svg'])->toContain('class="sa-thumb-axis"')
        ->and($column['svg'])->toContain('class="sa-thumb-value"')
        ->and($column['svg'])->toContain('>3</text>')
        ->and($column['label'])->toBe('কলাম')
        ->and($line['svg'])->toContain('<polyline')
        ->and($line['svg'])->toContain('class="sa-thumb-axis"')
        ->and($line['svg'])->toContain('class="sa-thumb-value"')
        ->and($pie['svg'])->toContain('<circle')
        ->and($pie['svg'])->toContain('class="sa-thumb-slice"')
        ->and($pie['svg'])->toContain('40%')
        ->and($pie['svg'])->toContain('class="sa-thumb-legend"')
        ->and(substr_count($grouped['svg'], '<rect'))->toBeGreaterThanOrEqual(4)
        ->and($grouped['svg'])->toContain('class="sa-thumb-legend"')
        ->and($grouped['svg'])->toContain('Fig1')
        ->and(ArticleVisuals::thumb('গ্রাফ দেখুন'))->toBeNull()
        ->and(ArticleVisuals::thumb('[chart type="column" labels="ক|খ" values="abc|def"]'))->toBeNull();
});

it('uses each style palette and background in card charts', function () {
    $dark = ArticleVisuals::thumb('[chart type="column" style="dark" labels="ক|খ" values="1|2"]');
    $outline = ArticleVisuals::thumb('[chart type="line" style="outline" labels="ক|খ" values="1|2"]');
    $mono = ArticleVisuals::thumb('[chart type="pie" style="mono" labels="ক|খ" values="1|2"]');

    expect($dark['background'])->toBe('#0f172a')
        ->and($dark['svg'])->toContain('#38bdf8')
        ->and($outline['svg'])->toContain('stroke-dasharray="4 4"')
        ->and($mono['svg'])->toContain('#111827');
});

it('stores a chosen style and renders it on the chart', function () {
    $this->actingAs($this->admin)->post(route('charts.store'), [
        'title' => 'ডার্ক গ্রাফ',
        'type' => 'line',
        'style' => 'dark',
        'rows' => "২০২৩ | 10\n২০২৪ | 18",
        'is_published' => '1',
    ])->assertRedirect();

    $chart = Chart::firstOrFail();

    expect($chart->style)->toBe('dark')
        ->and($chart->styleLabel())->toBe('ডার্ক')
        ->and($chart->shortcode())->toContain('style="dark"')
        ->and($chart->render())->toContain('sa-style-dark')
        ->toContain('data-style="dark"')
        ->toContain('--sa-viz-line:#22d3ee');
});

it('renders six visually different chart styles', function () {
    $shortcode = fn (string $style) => '[chart type="grouped" style="'.$style.'" title="তুলনা" labels="ক|খ" series="Fig 1=10,20|Fig 2=15,25"]';

    expect(ArticleVisuals::expand($shortcode('classic')))->toContain('sa-style-classic')
        ->and(ArticleVisuals::expand($shortcode('gradient')))->toContain('sa-style-gradient')->toContain('linearGradient')
        ->and(ArticleVisuals::expand($shortcode('outline')))->toContain('sa-style-outline')->toContain('stroke-dasharray')
        ->and(ArticleVisuals::expand($shortcode('rounded')))->toContain('sa-style-rounded')
        ->and(ArticleVisuals::expand($shortcode('dark')))->toContain('sa-style-dark')->toContain('#24344d')
        ->and(ArticleVisuals::expand($shortcode('mono')))->toContain('sa-style-mono')->toContain('#111827');
});

it('falls back to the classic style for an unknown style name', function () {
    expect(ArticleVisuals::expand('[chart type="pie" style="neon" labels="ক|খ" values="1|2"]'))
        ->toContain('sa-style-classic')
        ->not->toContain('sa-style-neon');
});

it('rejects an unknown style in the admin form', function () {
    $this->actingAs($this->admin)->post(route('charts.store'), [
        'title' => 'ভুল স্টাইল',
        'type' => 'column',
        'style' => 'neon',
        'rows' => "ক | ১\nখ | ২",
        'is_published' => '1',
    ])->assertSessionHasErrors('style');

    expect(Chart::count())->toBe(0);
});
