<?php

namespace App\Support;

use App\Models\News;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class Avatars
{
    public const DIR = 'uploads/avatars';

    public static function url(?User $user): ?string
    {
        return ($user && $user->avatar) ? asset(self::DIR.'/'.$user->avatar) : null;
    }

    /** নতুন ছবি রেখে আগেরটি মোছে। */
    public static function save(User $user, UploadedFile $file): void
    {
        $name = $user->id.'-'.Str::uuid().'.'.$file->extension();
        $file->move(public_path(self::DIR), $name);

        $old = $user->avatar;

        // forceFill: ইউজার মডেলের fillable তালিকা না ছুঁয়ে কলামটি সংরক্ষণ
        $user->forceFill(['avatar' => $name])->save();

        self::delete($old);
    }

    public static function remove(User $user): void
    {
        $old = $user->avatar;

        $user->forceFill(['avatar' => null])->save();

        self::delete($old);
    }

    /**
     * খবরের পেজের জন্য লেখকের নাম, ছবি ও পরিচিতি।
     * বাইলাইনের নাম লেখক-ইউজারের নামের সাথে না মিললে (অতিথি লেখক) ছবি ও পরিচিতি দেওয়া হয় না।
     *
     * @return array{name: string, photo: ?string, bio: string}
     */
    public static function byline(News $news): array
    {
        $author = $news->author;
        $name = trim((string) $news->reporter) ?: trim((string) ($author->name ?? ''));
        $same = $author && mb_strtolower($name) === mb_strtolower(trim((string) $author->name));

        return [
            'name' => $name,
            'photo' => $same ? self::url($author) : null,
            'bio' => $same ? trim((string) ($author->bio ?? '')) : '',
        ];
    }

    private static function delete(?string $name): void
    {
        if ($name) {
            File::delete(public_path(self::DIR.'/'.basename($name)));
        }
    }
}