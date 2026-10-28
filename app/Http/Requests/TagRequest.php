<?php

namespace App\Http\Requests;

use App\Models\Tag;
use App\Support\BengaliText;
use Illuminate\Foundation\Http\FormRequest;

class TagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-tags') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        $this->merge([
            'name' => Tag::cleanName($this->input('name')),
            // Same normalization the lookup uses, so what is stored is what URLs resolve to.
            'slug' => is_string($slug) && trim($slug) !== '' ? Tag::slugFor($slug) : null,
        ]);
    }

    public function rules(): array
    {
        $current = $this->route('tag');

        return [
            'name' => ['required', 'string', 'max:'.Tag::MAX_NAME],
            'slug' => [
                'nullable', 'string', 'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($current) {
                    $taken = Tag::where('slug', BengaliText::normalize($value))
                        ->when($current instanceof Tag, fn ($q) => $q->where('id', '!=', $current->id))
                        ->exists();

                    if ($taken) {
                        $fail('এই slug অন্য একটি ট্যাগ ব্যবহার করছে। একই বিষয়ের হলে ট্যাগ দুটি মার্জ করুন।');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'ট্যাগের নাম দিন।',
            'name.max' => 'ট্যাগ '.Tag::MAX_NAME.' অক্ষরের বেশি হতে পারবে না।',
        ];
    }
}
