<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-categories') ?? false;
    }

    public function rules(): array
    {
        $current = $this->route('category');
        $current = $current instanceof Category ? $current : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            // Optional: generated from the name when left blank.
            'slug' => [
                'nullable', 'string', 'max:255',
                'regex:/^[\p{L}\p{M}\p{N}_-]+$/u',
                Rule::unique('categories', 'slug')->ignore($current),
            ],
            'parent_id' => array_values(array_filter([
                'nullable',
                'integer',
                // Only top-level categories can be parents: one level of sub-categories, no cycles possible.
                Rule::exists('categories', 'id')->whereNull('parent_id'),
                $current ? Rule::notIn([$current->id]) : null,
                // A category that already has sub-categories would make a 3rd level if it became a child.
                function (string $attribute, mixed $value, \Closure $fail) use ($current) {
                    if ($value !== null && $current && $current->children()->exists()) {
                        $fail('এই ক্যাটাগরির উপ-ক্যাটাগরি আছে, তাই এটিকে অন্য ক্যাটাগরির উপ-ক্যাটাগরি করা যাবে না।');
                    }
                },
            ])),
            'serial' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['nullable', 'boolean'],
            'show_in_menu' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'ক্যাটাগরির নাম দিন।',
            'slug.unique' => 'এই slug আগে থেকেই ব্যবহৃত হয়েছে।',
            'slug.regex' => 'Slug-এ শুধু অক্ষর, সংখ্যা, হাইফেন (-) ও আন্ডারস্কোর (_) ব্যবহার করা যাবে।',
            'parent_id.exists' => 'প্যারেন্ট অবশ্যই একটি মূল (শীর্ষ-স্তরের) ক্যাটাগরি হতে হবে।',
            'parent_id.not_in' => 'একটি ক্যাটাগরি নিজের উপ-ক্যাটাগরি হতে পারে না।',
            'meta_description.max' => 'মেটা বিবরণ ৫০০ অক্ষরের বেশি হতে পারবে না।',
        ];
    }
}
