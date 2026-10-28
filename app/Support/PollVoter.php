<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Anonymous voter identity: a random cookie token, stored only as an HMAC hash. */
final class PollVoter
{
    public const COOKIE = 'sa_voter';

    public static function token(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && $token !== '' && strlen($token) <= 100 ? $token : null;
    }

    public static function hash(?string $token): ?string
    {
        return $token ? hash_hmac('sha256', $token, (string) config('app.key')) : null;
    }

    public static function ipHash(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
    }
}
