<?php

namespace App\Http\Requests;

use App\Models\News;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real permission check (per status pair) lives in NewsWorkflow::transition().
        return $this->user()?->canManageCms() ?? false;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', Rule::in(News::STATUSES)],
            'note' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function publishAt(): ?Carbon
    {
        $value = $this->validated('published_at');

        return $value ? Carbon::parse($value, config('app.timezone')) : null;
    }
}
