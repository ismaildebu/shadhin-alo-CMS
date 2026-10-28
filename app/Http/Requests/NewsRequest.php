<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Models\News;
use App\Models\Tag;
use App\Services\LocationResolver;
use App\Services\NewsWorkflow;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsRequest extends FormRequest
{
    private ?Location $resolvedLocation = null;

    public function authorize(): bool
    {
        $user = $this->user();
        $news = $this->route('news');

        if (! $user) {
            return false;
        }

        return $news instanceof News
            ? $user->can('update', $news)
            : $user->can('create', News::class);
    }

    protected function prepareForValidation(): void
    {
        // Unchecked checkboxes are simply absent from the request.
        $this->merge([
            'breaking' => $this->boolean('breaking'),
            'featured' => $this->boolean('featured'),
            'is_lead' => $this->boolean('is_lead'),
            'type' => $this->filled('type') ? $this->input('type') : News::TYPE_NEWS,
        ]);


        // Support both the new multi-category form and legacy category_id.
        if ($this->exists('category_ids')) {
            $categoryIds = $this->input('category_ids');

            if (is_array($categoryIds) && count($categoryIds) > 0) {
                $this->merge([
                    'category_id' => $this->input('category_id', $categoryIds[0]),
                ]);
            }
        } elseif ($this->filled('category_id')) {
            $this->merge([
                'category_ids' => [$this->input('category_id')],
            ]);
        }




        // Only when the form actually sent tags: an update that never mentions them must keep them.
        if ($this->tagsSubmitted()) {
            $this->merge(['tags' => $this->collectTagNames()]);
        }
    }

    public function rules(): array
    {
        $news = $this->route('news');
        $news = $news instanceof News ? $news : null;

        // A status is valid if it is where the article already is, or somewhere this user may move it.
        $allowed = NewsWorkflow::allowedTargets($this->user(), $news);
        if ($news) {
            $allowed[] = $news->status;
        }

         return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            'type' => ['required', Rule::in(array_keys(News::TYPES))],

            'title' => ['required', 'string', 'max:255'],
            'shoulder' => ['nullable', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'reporter' => ['nullable', 'string', 'max:255'],
            'breaking' => ['boolean'],
            'featured' => ['boolean'],
            'is_lead' => ['boolean'],
            'status' => [$news ? 'nullable' : 'required', Rule::in($allowed)],
            'published_at' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image_caption' => ['nullable', 'string', 'max:255'],
            'image_credit' => ['nullable', 'string', 'max:255'],
            'tags' => ['array', 'max:'.Tag::MAX_PER_ARTICLE],
            'tags.*' => ['string', 'max:'.Tag::MAX_NAME],
            'division_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'upazila_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'ক্যাটাগরি নির্বাচন করুন।',
            'category_id.exists' => 'নির্বাচিত ক্যাটাগরিটি পাওয়া যায়নি।',
            'type.in' => 'সংবাদের ধরন সঠিক নয়।',
            'title.required' => 'শিরোনাম দিন।',
            'title.max' => 'শিরোনাম ২৫৫ অক্ষরের বেশি হতে পারবে না।',
            'description.required' => 'সংবাদের বিস্তারিত লিখুন।',
            'status.required' => 'অবস্থা নির্বাচন করুন।',
            'status.in' => 'এই অবস্থা বেছে নেওয়ার অনুমতি আপনার নেই।',
            'published_at.date' => 'প্রকাশের তারিখ সঠিক নয়।',
            'image.image' => 'ফাইলটি অবশ্যই একটি ছবি হতে হবে।',
            'image.mimes' => 'শুধু jpg, jpeg, png বা webp ছবি দেওয়া যাবে।',
            'image.max' => 'ছবির আকার ২ মেগাবাইটের বেশি হতে পারবে না।',
            'tags.max' => 'একটি সংবাদে সর্বোচ্চ '.Tag::MAX_PER_ARTICLE.'টি ট্যাগ দেওয়া যাবে।',
            'tags.*.max' => 'একটি ট্যাগ '.Tag::MAX_NAME.' অক্ষরের বেশি হতে পারবে না।',
            'division_id.integer' => 'লোকেশন সঠিক নয়।',
            'district_id.integer' => 'লোকেশন সঠিক নয়।',
            'upazila_id.integer' => 'লোকেশন সঠিক নয়।',
        ];
    }

    /**
     * The article's place, worked out from the three selects.
     * Returns null when none was chosen (and the user is allowed to leave it empty).
     */
    public function location(): ?Location
    {
        return $this->resolvedLocation;
    }

    /** Did the form send any location field at all? An update that never mentions it must keep the place. */
    public function locationSubmitted(): bool
    {
        return $this->has('division_id') || $this->has('district_id') || $this->has('upazila_id')
            || $this->boolean('location_present');
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $article = $this->route('news');
            $article = $article instanceof News ? $article : null;

            $submitted = $this->locationSubmitted();

            $resolved = $submitted
                ? LocationResolver::resolve(
                    $this->integer('division_id') ?: null,
                    $this->integer('district_id') ?: null,
                    $this->integer('upazila_id') ?: null,
                )
                : ['location' => $article?->location, 'error' => null];

            if ($resolved['error']) {
                $validator->errors()->add('upazila_id', $resolved['error']);

                return;
            }

            $location = $resolved['location'];

            // A district correspondent with no choice gets their own district; anything outside it is refused.
            // An article that already sits elsewhere (the correspondent was moved to another district since)
            // may still be edited as long as its place is left unchanged.
            if ($user->isDistrictCorrespondent()) {
                if ($location === null && $submitted) {
                    $location = $user->district;
                }

                $unchanged = $article && $location && $article->location_id === $location->id;

                if (! $unchanged && ! LocationResolver::allows($user, $location)) {
                    $validator->errors()->add('district_id', 'আপনি শুধু নিজের জেলা ('.$user->district?->name_bn.') ও তার উপজেলার সংবাদ লিখতে পারবেন।');

                    return;
                }
            }

            $this->resolvedLocation = $location;
        });
    }

    /**
     * Did the form send tags at all? An update that never mentions tags must not wipe them.
     * (The form always sends the hidden "tags_present" marker, even when every tag was removed.)
     */
    public function tagsSubmitted(): bool
    {
        return $this->has('tags') || $this->has('tag_input') || $this->boolean('tags_present');
    }

    /**
     * Tags arrive as tags[] (chips) and/or a comma-separated "tag_input" text (no-JS fallback).
     * Clean them, and drop duplicates that would end up as the same tag.
     *
     * @return list<string>
     */
    private function collectTagNames(): array
    {
        $raw = $this->input('tags', []);
        $names = is_array($raw) ? $raw : (is_string($raw) ? Tag::splitNames($raw) : []);

        if (is_string($this->input('tag_input'))) {
            $names = array_merge($names, Tag::splitNames($this->input('tag_input')));
        }

        $unique = [];

        foreach ($names as $name) {
            if (! is_string($name)) {
                continue;
            }

            $clean = Tag::cleanName($name);

            if ($clean !== '') {
                $unique[Tag::slugFor($clean)] ??= $clean;
            }
        }

        return array_values($unique);
    }

    /** The requested publication time, interpreted in the application timezone. */
    public function publishAt(): ?Carbon
    {
        $value = $this->validated('published_at');

        return $value ? Carbon::parse($value, config('app.timezone')) : null;
    }
}
