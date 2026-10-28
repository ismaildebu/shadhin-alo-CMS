<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'cms:create-admin {email : Admin e-mail address} {--name=Admin : Display name}';

    protected $description = 'Create (or promote) a CMS administrator. The password is asked interactively.';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));

        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->exists ? $user->name : $this->option('name');
        $user->password = $password; // hashed by the model cast
        $user->email_verified_at ??= now();
        $user->role = User::ROLE_ADMIN;
        $user->is_active = true;
        $user->save();

        $this->info("Admin ready: {$email}");

        return self::SUCCESS;
    }
}
