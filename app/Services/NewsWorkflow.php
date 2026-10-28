<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ArticleRevision;
use App\Models\News;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The editorial workflow: who may move an article to which status.
 *
 *   draft -> pending -> published | scheduled -> published -> archived
 *      ^________|                                    |
 *      (rejected / withdrawn / unpublished)  <_______|
 */
final class NewsWorkflow
{
    /**
     * Statuses $user may move $news to (the current status is not included).
     * Pass null for $news when creating a new article.
     *
     * @return list<string>
     */
    public static function allowedTargets(User $user, ?News $news = null): array
    {
        if (! $user->canManageCms()) {
            return [];
        }

        if ($news === null || ! $news->exists) {
            return $user->isEditorOrAdmin()
                ? [News::STATUS_DRAFT, News::STATUS_PENDING, News::STATUS_SCHEDULED, News::STATUS_PUBLISHED]
                : [News::STATUS_DRAFT, News::STATUS_PENDING];
        }

        if ($user->isEditorOrAdmin()) {
            return match ($news->status) {
                News::STATUS_DRAFT => [News::STATUS_PENDING, News::STATUS_SCHEDULED, News::STATUS_PUBLISHED],
                News::STATUS_PENDING => [News::STATUS_DRAFT, News::STATUS_SCHEDULED, News::STATUS_PUBLISHED],
                News::STATUS_SCHEDULED => [News::STATUS_DRAFT, News::STATUS_PUBLISHED],
                News::STATUS_PUBLISHED => [News::STATUS_DRAFT, News::STATUS_ARCHIVED],
                News::STATUS_ARCHIVED => [News::STATUS_DRAFT],
                default => [],
            };
        }

        // Reporter: own articles only, and only between draft and pending.
        if ($news->author_id !== $user->id) {
            return [];
        }

        return match ($news->status) {
            News::STATUS_DRAFT => [News::STATUS_PENDING],
            News::STATUS_PENDING => [News::STATUS_DRAFT],
            default => [],
        };
    }

    /**
     * The publication time that goes with a status change.
     */
    public static function resolvePublishedAt(string $to, ?News $news, ?CarbonInterface $requested): ?CarbonInterface
    {
        if ($to === News::STATUS_PUBLISHED) {
            if ($requested && $requested->isFuture()) {
                throw ValidationException::withMessages([
                    'published_at' => 'ভবিষ্যতের সময়ে প্রকাশ করতে "নির্ধারিত" অবস্থা বেছে নিন।',
                ]);
            }

            return $requested ?? now();
        }

        if ($to === News::STATUS_SCHEDULED) {
            if (! $requested || ! $requested->isFuture()) {
                throw ValidationException::withMessages([
                    'published_at' => 'নির্ধারিত প্রকাশের জন্য ভবিষ্যতের তারিখ ও সময় দিন।',
                ]);
            }

            return $requested;
        }

        return $requested ?? $news?->published_at;
    }

    /**
     * Move an existing article to a new status, with permission check, revision and audit log.
     */
    public static function transition(
        News $news,
        User $actor,
        string $to,
        ?string $note = null,
        ?CarbonInterface $requestedAt = null,
    ): News {
        if (! in_array($to, self::allowedTargets($actor, $news), true)) {
            throw new AuthorizationException('এই অবস্থা-পরিবর্তনের অনুমতি আপনার নেই।');
        }

        $note = $note !== null ? trim($note) : null;
        $from = $news->status;

        // An editor sending someone else's article back must say why.
        if ($to === News::STATUS_DRAFT && $from === News::STATUS_PENDING
            && $news->author_id !== $actor->id && ($note === null || $note === '')) {
            throw ValidationException::withMessages(['note' => 'ফেরত পাঠানোর কারণ লিখুন।']);
        }

        $publishedAt = self::resolvePublishedAt($to, $news, $requestedAt);

        $news->forceFill(['status' => $to, 'published_at' => $publishedAt])->save();

        $label = News::STATUS_LABELS[$from].' → '.News::STATUS_LABELS[$to];
        ArticleRevision::capture($news, $actor, $note ? "{$label}: {$note}" : $label);

        Activity::record('article.transition', $news, ['from' => $from, 'to' => $to, 'note' => $note], $actor);

        return $news;
    }
}
