<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Poll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class PollController extends Controller
{
    public function index(): View
    {
        $polls = Poll::with('options')->orderByDesc('published_at')->orderByDesc('id')->paginate(20);

        return view('admin.polls.index', compact('polls'));
    }

    public function create(): View
    {
        return view('admin.polls.form', ['poll' => new Poll(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $image = $this->resolveImage($request, null);

        try {
            DB::transaction(function () use ($data, $image) {
                $poll = Poll::create([
                    'question' => $data['question'],
                    'image' => $image,
                    'is_active' => $data['is_active'],
                    'published_at' => $data['published_at'] ?? now(),
                ]);

                $this->replaceOptions($poll, $data['options']);
            });
        } catch (\Throwable $e) {
            $this->deleteStoredImage($image);

            throw $e;
        }

        return redirect()->route('admin.polls.index')->with('success', 'ভোট সংরক্ষণ হয়েছে।');
    }

    public function edit(Poll $poll): View
    {
        $poll->load('options');

        return view('admin.polls.form', compact('poll'));
    }

    public function update(Request $request, Poll $poll): RedirectResponse
    {
        $data = $this->validated($request);
        $oldImage = $poll->image;
        $image = $this->resolveImage($request, $oldImage);

        try {
            DB::transaction(function () use ($poll, $data, $image) {
                $poll->update([
                    'question' => $data['question'],
                    'image' => $image,
                    'is_active' => $data['is_active'],
                    'published_at' => $data['published_at'] ?? $poll->published_at ?? now(),
                ]);

                $poll->load('options');

                if ($poll->totalVotes() > 0) {
                    // votes exist: only the wording may change, never the set of options
                    foreach ($data['options'] as $row) {
                        if (! empty($row['id'])) {
                            $poll->options->firstWhere('id', (int) $row['id'])?->update(['label' => $row['label']]);
                        }
                    }

                    return;
                }

                $this->replaceOptions($poll, $data['options']);
            });
        } catch (\Throwable $e) {
            if ($image !== $oldImage) {
                $this->deleteStoredImage($image);
            }

            throw $e;
        }

        if ($image !== $oldImage) {
            $this->deleteStoredImage($oldImage);
        }

        return redirect()->route('admin.polls.index')->with('success', 'ভোট হালনাগাদ হয়েছে।');
    }

    public function destroy(Poll $poll): RedirectResponse
    {
        $image = $poll->image;

        $poll->delete();

        $this->deleteStoredImage($image);

        return redirect()->route('admin.polls.index')->with('success', 'ভোট মুছে ফেলা হয়েছে।');
    }

    /** @return array{question: string, options: array<int, array{id?: mixed, label: string}>, is_active: bool, published_at: ?string} */
    private function validated(Request $request): array
    {
        // empty option rows are simply ignored
        $request->merge([
            'options' => array_values(array_filter(
                (array) $request->input('options', []),
                fn ($row) => is_array($row) && filled($row['label'] ?? null)
            )),
        ]);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*.label' => ['required', 'string', 'max:120'],
            'options.*.id' => ['nullable', 'integer'],
            'published_at' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
            'image_url' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
        ], [
            'question.required' => 'প্রশ্ন লিখুন।',
            'options.required' => 'কমপক্ষে দুটি অপশন দিন।',
            'options.min' => 'কমপক্ষে দুটি অপশন দিন।',
            'options.max' => 'সর্বোচ্চ ছয়টি অপশন দেওয়া যাবে।',
            'options.*.label.max' => 'অপশনের লেখা ১২০ অক্ষরের বেশি হবে না।',
            'published_at.date' => 'প্রকাশের তারিখ সঠিক নয়।',
            'image.image' => 'ছবিটি সঠিক ছবি নয়।',
            'image.mimes' => 'JPG, PNG অথবা WebP ছবি দিন।',
            'image.max' => 'ছবির আকার ৪ মেগাবাইটের বেশি হবে না।',
            'image_url.url' => 'ছবির লিংকটি সঠিক নয়।',
            'image_url.regex' => 'ছবির লিংক http:// বা https:// দিয়ে শুরু হতে হবে।',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function resolveImage(Request $request, ?string $old): ?string
    {
        if ($request->hasFile('image')) {
            try {
                return Media::storeUpload($request->file('image'), $request->user())->filename;
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['image' => $e->getMessage()]);
            }
        }

        if ($request->boolean('remove_image')) {
            return null;
        }

        $url = trim((string) $request->input('image_url'));

        return $url !== '' ? $url : $old;
    }

    private function deleteStoredImage(?string $image): void
    {
        if (! $image || preg_match('#^https?://#i', $image)) {
            return;
        }

        $name = basename($image);

        Media::removeFiles($name);
        Media::where('filename', $name)->delete();
    }

    /** @param array<int, array{label: string}> $rows */
    private function replaceOptions(Poll $poll, array $rows): void
    {
        $poll->options()->delete();

        foreach (array_values($rows) as $i => $row) {
            $poll->options()->create(['label' => $row['label'], 'serial' => $i + 1]);
        }
    }
}
