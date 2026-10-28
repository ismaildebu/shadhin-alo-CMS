<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\News;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(string $slug): View
    {
        $category = Category::published()
            ->with('parent')
            ->where('slug', $slug)
            ->firstOrFail();

        // A sub-category of an inactive parent is hidden together with its parent.
        abort_unless($category->isPubliclyVisible(), 404);

        $children = $category->children()->published()->get();

        $news = News::published()
            ->whereIn('category_id', $category->categoryIds())
            ->with('category')
            ->latestPublished()
            ->paginate(12)
            ->withQueryString();

        return view('frontend.category', compact('category', 'children', 'news'));
    }
}
