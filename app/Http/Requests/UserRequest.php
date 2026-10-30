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
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $creating = ! $user instanceof User;

        // The super administrator can only change the password, so no
        // other field is accepted (or required) for that account.
        if ($user instanceof User && $user->isSuperAdmin()) {
            return [
                'password' => [
                    'nullable',
                    'string',
                    Password::defaults(),
                ],
            ];
        }

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(
                    $creating ? null : $user
                ),
            ],

            'password' => [
                $creating ? 'required' : 'nullable',
                'string',
                Password::defaults(),
            ],

            // The super administrator role can never be assigned from a form.
            'role' => [
                'required',
                Rule::in(
                    array_diff(
                        array_keys(User::ROLES),
                        [User::ROLE_SUPER_ADMIN]
                    )
                ),
            ],

            'is_active' => [
                'boolean',
            ],

            'district_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')
                    ->where('type', Location::DISTRICT),
            ],

            'upazila_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')
                    ->where('type', Location::UPAZILA),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
            ],

            'bio' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'নাম দিতে হবে।',
            'name.max' => 'নাম সর্বোচ্চ ২৫৫ অক্ষরের হতে পারবে।',

            'email.email' => 'সঠিক ইমেইল ঠিকানা দিন।',
            'email.unique' => 'এই ইমেইল দিয়ে ইতিমধ্যে অ্যাকাউন্ট আছে।',

            'password.required' => 'পাসওয়ার্ড দিতে হবে।',
            'password.min' => 'পাসওয়ার্ডটি আরও শক্তিশালী হতে হবে।',

            'role.required' => 'রোল নির্বাচন করুন।',
            'role.in' => 'নির্বাচিত রোল সঠিক নয়।',

            'district_id.exists' => 'নির্বাচিত জেলা সঠিক নয়।',
            'upazila_id.exists' => 'নির্বাচিত উপজেলা সঠিক নয়।',

            'phone.regex' => 'সঠিক ফোন নম্বর দিন।',
            'phone.max' => 'ফোন নম্বর সর্বোচ্চ ২০ অক্ষরের হতে পারবে।',

            'bio.max' => 'পরিচিতি সর্বোচ্চ ১০০০ অক্ষরের হতে পারবে।',
        ];
    }
}