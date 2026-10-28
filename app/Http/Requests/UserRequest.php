<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $creating = ! $user instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($creating ? null : $user),
            ],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::defaults()],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => ['boolean'],
            // Only a reporter can be tied to a district, and it must really be a district.
            'district_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('type', Location::DISTRICT)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'নাম দিন।',
            'email.required' => 'ইমেইল দিন।',
            'email.unique' => 'এই ইমেইল আগে থেকেই ব্যবহৃত হয়েছে।',
            'password.required' => 'পাসওয়ার্ড দিন।',
            'role.in' => 'রোল সঠিক নয়।',
            'district_id.exists' => 'নির্বাচিত জেলাটি পাওয়া যায়নি।',
            'phone.regex' => 'ফোন নম্বরে শুধু সংখ্যা, + - ( ) ও স্পেস থাকতে পারবে।',
        ];
    }
}
