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
            ->when($term !== '', fn (Builder $query) => $query->matching($term))
            ->latestPublished()
            ->paginate(12)
            ->withQueryString();

        return view('frontend.search', compact('term', 'news'));
    }
}
