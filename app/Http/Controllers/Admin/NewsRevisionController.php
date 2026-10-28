<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ArticleRevision;
use App\Models\Category;
use App\Models\Location;
use App\Models\News;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NewsRevisionController extends Controller
{
    public function index(News $news): View
    {
        Gate::authorize('update', $news);

        return view('admin.news.revisions', [
            'news' => $news,
            'revisions' => $news->revisions()->with('user')->paginate(20),
        ]);
    }

    public function restore(Request $request, News $news, ArticleRevision $revision): RedirectResponse
    {
        Gate::authorize('update', $news);

        $fields = Arr::only($revision->snapshot, ArticleRevision::CONTENT_FIELDS);

        // A deleted category must not break the restore.
        if (! Category::whereKey($fields['category_id'] ?? null)->exists()) {
            unset($fields['category_id']);
        }

        // A location that no longer exists simply leaves the article without one.
        if (array_key_exists('location_id', $fields) && ! Location::whereKey($fields['location_id'])->exists()) {
            $fields['location_id'] = null;
        }

        $fields['description'] = HtmlSanitizer::clean($fields['description'] ?? '');

        $news->update($fields);

        // Older revisions (before tags existed) have no "tags" key: leave the current tags alone then.
        if (isset($revision->snapshot['tags']) && is_array($revision->snapshot['tags'])) {
            $news->syncTagNames($revision->snapshot['tags']);
        }

        ArticleRevision::capture($news, $request->user(), 'সংস্করণ #'.$revision->id.' পুনরুদ্ধার');
        Activity::record('article.revision_restored', $news, ['revision' => $revision->id]);

        return redirect()
            ->route('news.edit', $news)
            ->with('success', 'সংস্করণ পুনরুদ্ধার হয়েছে।');
    }
}
