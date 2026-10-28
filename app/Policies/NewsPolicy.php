<?php

namespace App\Policies;

use App\Models\News;
use App\Models\User;

/**
 * Admin / editor: everything.
 * Reporter: only their own articles, and only while they are a draft or waiting for review.
 */
class NewsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageCms();
    }

    public function view(User $user, News $news): bool
    {
        return $user->isEditorOrAdmin() || $this->ownsEditable($user, $news, includePublished: true);
    }

    public function create(User $user): bool
    {
        return $user->canManageCms();
    }

    public function update(User $user, News $news): bool
    {
        return $user->isEditorOrAdmin() || $this->ownsEditable($user, $news);
    }

    public function delete(User $user, News $news): bool
    {
        return $user->isEditorOrAdmin()
            || ($user->isReporter() && $news->author_id === $user->id && $news->status === News::STATUS_DRAFT);
    }

    public function restore(User $user, News $news): bool
    {
        return $user->isEditorOrAdmin();
    }

    private function ownsEditable(User $user, News $news, bool $includePublished = false): bool
    {
        if (! $user->isReporter() || $news->author_id !== $user->id) {
            return false;
        }

        return $includePublished
            || in_array($news->status, [News::STATUS_DRAFT, News::STATUS_PENDING], true);
    }
}
