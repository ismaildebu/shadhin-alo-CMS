<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChartController extends Controller
{
    private const DIGITS = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    public function index(): View
    {
        $charts = Chart::ordered()->paginate(20);

        return view('admin.charts.index', compact('charts'));
    }

    public function create(): View
    {
        return view('admin.charts.form', [
            'chart' => new Chart(['type' => 'grouped', 'is_published' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $chart = Chart::create($this->validated($request));

        return redirect()->route('charts.edit', $chart)
            ->with('success', 'গ্রাফ সংরক্ষণ হয়েছে। নিচের শর্টকোডটি সংবাদে বসাতে পারেন।');
    }

    public function edit(Chart $chart): View
    {
        return view('admin.charts.form', compact('chart'));
    }

    public function update(Request $request, Chart $chart): RedirectResponse
    {
        $chart->update($this->validated($request));

        return back()->with('success', 'গ্রাফ হালনাগাদ হয়েছে।');
    }

    public function destroy(Chart $chart): RedirectResponse
    {
        $chart->delete();

        return redirect()->route('charts.index')->with('success', 'গ্রাফ মুছে ফেলা হয়েছে।');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Chart::TYPES))],
            'style' => ['nullable', Rule::in(array_keys(Chart::STYLES))],
            'unit' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', 'string', 'max:200'],
            'serial' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'rows' => ['nullable', 'string', 'max:6000'],
            'labels' => ['nullable', 'string', 'max:2000'],
            'series_rows' => ['nullable', 'string', 'max:4000'],
        ], [
            'title.required' => 'গ্রাফের শিরোনাম লিখুন।',
            'title.max' => 'শিরোনাম ১৫০ অক্ষরের বেশি হবে না।',
            'type.required' => 'গ্রাফের ধরন বাছাই করুন।',
            'type.in' => 'গ্রাফের ধরনটি সঠিক নয়।',
            'style.in' => 'গ্রাফের স্টাইলটি সঠিক নয়।',
            'unit.max' => 'একক ২০ অক্ষরের বেশি হবে না।',
            'source.max' => 'সূত্র ২০০ অক্ষরের বেশি হবে না।',
            'serial.integer' => 'ক্রম একটি পূর্ণ সংখ্যা হতে হবে।',
        ]);

        $data = $validated['type'] === 'grouped'
            ? $this->groupedData((string) $request->input('labels', ''), (string) $request->input('series_rows', ''))
            : $this->singleData((string) $request->input('rows', ''));

        return [
            'title' => $validated['title'],
            'type' => $validated['type'],
            'style' => $validated['style'] ?? 'classic',
            'unit' => filled($validated['unit'] ?? null) ? trim((string) $validated['unit']) : null,
            'source' => filled($validated['source'] ?? null) ? trim((string) $validated['source']) : null,
            'data' => $data,
            'is_published' => $request->boolean('is_published'),
            'serial' => (int) ($validated['serial'] ?? 0),
        ];
    }

    /** @return array{labels: list<string>, values: list<float>} */
    private function singleData(string $raw): array
    {
        $labels = [];
        $values = [];

        foreach (preg_split('/\r\n|\r|\n/u', $raw) ?: [] as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            [$label, $value] = array_pad(explode('|', $line, 2), 2, '');
            $label = trim($label);
            $number = $this->number($value);

            if ($label === '' || $number === null) {
                throw ValidationException::withMessages([
                    'rows' => ($i + 1).' নম্বর লাইনে "লেবেল | সংখ্যা" দিন।',
                ]);
            }

            $labels[] = $label;
            $values[] = $number;
        }

        if (count($labels) < 2) {
            throw ValidationException::withMessages(['rows' => 'কমপক্ষে দুইটি তথ্য দিন।']);
        }

        if (count($labels) > 12) {
            throw ValidationException::withMessages(['rows' => 'সর্বোচ্চ ১২টি তথ্য দেওয়া যাবে।']);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels: list<string>, series: list<array{name: string, values: list<float>}>} */
    private function groupedData(string $labelsRaw, string $seriesRaw): array
    {
        $labels = array_values(array_filter(
            array_map('trim', explode('|', $labelsRaw)),
            fn (string $label) => $label !== ''
        ));

        if (count($labels) < 2) {
            throw ValidationException::withMessages(['labels' => 'কমপক্ষে দুইটি লেবেল দিন, মাঝে | চিহ্ন দিয়ে।']);
        }

        if (count($labels) > 12) {
            throw ValidationException::withMessages(['labels' => 'সর্বোচ্চ ১২টি লেবেল দেওয়া যাবে।']);
        }

        $series = [];

        foreach (preg_split('/\r\n|\r|\n/u', $seriesRaw) ?: [] as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            [$name, $valuesRaw] = array_pad(explode('|', $line, 2), 2, '');
            $name = trim(str_replace(['|', '='], ' ', $name));

            if ($name === '') {
                throw ValidationException::withMessages([
                    'series_rows' => ($i + 1).' নম্বর সিরিজে "সিরিজের নাম | মান, মান, ..." দিন।',
                ]);
            }

            $values = [];

            foreach (explode(',', $valuesRaw) as $value) {
                $number = $this->number($value);

                if ($number === null) {
                    throw ValidationException::withMessages([
                        'series_rows' => $name.' সিরিজের সব মান সংখ্যা হতে হবে, কমা দিয়ে আলাদা করুন।',
                    ]);
                }

                $values[] = $number;
            }

            if (count($values) !== count($labels)) {
                throw ValidationException::withMessages([
                    'series_rows' => $name.' সিরিজে '.count($labels).'টি মান দিন (লেবেলের সংখ্যার সমান)।',
                ]);
            }

            $series[] = ['name' => $name, 'values' => $values];

            if (count($series) > 4) {
                throw ValidationException::withMessages(['series_rows' => 'সর্বোচ্চ ৪টি সিরিজ দেওয়া যাবে।']);
            }
        }

        if ($series === []) {
            throw ValidationException::withMessages(['series_rows' => 'কমপক্ষে একটি সিরিজের তথ্য দিন।']);
        }

        return ['labels' => $labels, 'series' => $series];
    }

    private function number(string $raw): ?float
    {
        $raw = str_replace([',', ' ', "\u{00A0}"], '', strtr(trim($raw), self::DIGITS));

        return $raw !== '' && is_numeric($raw) ? (float) $raw : null;
    }
}
