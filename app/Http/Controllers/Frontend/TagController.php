<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Support\BengaliText;
use Illuminate\View\View;

class TagController extends Controller
{
    public function show(string $slug): View
    {
        // Browsers and links may carry either spelling of a Bengali letter.
        $tag = Tag::where('slug', BengaliText::normalize($slug))->firstOrFail();

        $news = $tag->news()
            ->published()
            ->with('category')
            ->latestPublished()
            ->paginate(12)
            ->withQueryString();

        // A tag with nothing public attached does not exist for readers (and never reveals draft-only tags).
        abort_if($news->total() === 0, 404);

        return view('frontend.tag', compact('tag', 'news'));
    }
}
