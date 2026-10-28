<?php

namespace App\Http\Requests\Admin;

use App\Support\ArticleDesign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The routes also sit behind the admin/editor middleware group.
        return $this->user()?->can('manage-cms') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'layout' => ['required', Rule::in(array_keys(ArticleDesign::LAYOUTS))],
            'side' => ['required', Rule::in(array_keys(ArticleDesign::SIDES))],
            'font' => ['required', Rule::in(array_keys(ArticleDesign::FONTS))],
            'title_size' => ['required', Rule::in(array_keys(ArticleDesign::TITLE_SIZES))],
            'body_size' => ['required', Rule::in(array_keys(ArticleDesign::BODY_SIZES))],
            'body_width' => ['required', Rule::in(array_keys(ArticleDesign::BODY_WIDTHS))],
            'leading' => ['required', Rule::in(array_keys(ArticleDesign::LEADINGS))],
            'image_ratio' => ['required', Rule::in(array_keys(ArticleDesign::IMAGE_RATIOS))],
            'image_fit' => ['required', Rule::in(array_keys(ArticleDesign::IMAGE_FITS))],
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'link' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'widgets' => ['required', 'array'],
            'widgets.*.serial' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'widgets.*.show' => ['nullable', 'in:1'],
        ];

        foreach (ArticleDesign::COUNTS as $key => $rule) {
            $rules[$key.'_count'] = ['required', 'integer', 'min:'.$rule['min'], 'max:'.$rule['max']];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $messages = [
            'layout.in' => 'লেআউটের মান সঠিক নয়।',
            'side.in' => 'সাইডবারের অবস্থান সঠিক নয়।',
            'font.in' => 'ফন্টের মান সঠিক নয়।',
            'title_size.in' => 'শিরোনামের সাইজ সঠিক নয়।',
            'body_size.in' => 'লেখার সাইজ সঠিক নয়।',
            'body_width.in' => 'লেখার প্রস্থ সঠিক নয়।',
            'leading.in' => 'লাইনের ফাঁক সঠিক নয়।',
            'image_ratio.in' => 'ছবির অনুপাত সঠিক নয়।',
            'image_fit.in' => 'ছবি বসানোর ধরন সঠিক নয়।',
            'accent.regex' => 'মূল রংটি সঠিক নয়।',
            'link.regex' => 'লিংকের রংটি সঠিক নয়।',
            'widgets.required' => 'সাইডবারের কোনো তথ্য পাওয়া যায়নি।',
            'widgets.*.serial.integer' => 'ক্রম একটি পূর্ণ সংখ্যা হতে হবে।',
            'widgets.*.show.in' => 'সাইডবারের দৃশ্যমানতার মান সঠিক নয়।',
        ];

        foreach (ArticleDesign::COUNTS as $key => $rule) {
            $label = $rule['label'].'-এর সংখ্যা';
            $range = $rule['min'].' থেকে '.$rule['max'];

            $messages[$key.'_count.required'] = $label.' দিন।';
            $messages[$key.'_count.integer'] = $label.' একটি পূর্ণ সংখ্যা হতে হবে।';
            $messages[$key.'_count.min'] = $label.' '.$range.'-এর মধ্যে হতে হবে।';
            $messages[$key.'_count.max'] = $label.' '.$range.'-এর মধ্যে হতে হবে।';
        }

        return $messages;
    }
}
