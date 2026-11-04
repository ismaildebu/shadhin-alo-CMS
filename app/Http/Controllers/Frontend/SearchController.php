<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $news = News::published()
            ->with('category')
            ->when($term !== '', fn (Builder $query) => $this->match($query, $term))
            ->latestPublished()
            ->paginate(12)
            ->withQueryString();

        return view('frontend.search', compact('term', 'news'));
    }

    /**
     * Title or summary holds the typed word. "!" is named as the escape character so a
     * % or _ inside the word stays an ordinary character on every database driver.
     */
    private function match(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        $query->where(fn (Builder $part) => $part
            ->whereRaw("title LIKE ? ESCAPE '!'", [$like])
            ->orWhereRaw("summary LIKE ? ESCAPE '!'", [$like]));
    }
}
