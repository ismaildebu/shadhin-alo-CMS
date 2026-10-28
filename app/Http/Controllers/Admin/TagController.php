<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagRequest;
use App\Models\Activity;
use App\Models\News;
use App\Models\Tag;
use App\Support\BengaliText;
use App\Support\FrontendCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $sort = $request->query('sort') === 'name' ? 'name' : 'usage';

        $tags = Tag::query()
            ->withCount([
                'news as all_news_count' => fn ($q) => $q->withTrashed(),
                'news as published_count' => fn ($q) => $q->published(),
            ])
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes(BengaliText::normalize($term), '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $like));
            })
            ->when($sort === 'name',
                fn ($q) => $q->orderBy('name'),
                fn ($q) => $q->orderByDesc('all_news_count')->orderBy('name'))
            ->paginate(25)
            ->withQueryString();

        return view('admin.tags.index', [
            'tags' => $tags,
            'term' => $term,
            'sort' => $sort,
            'total' => Tag::count(),
        ]);
    }

    public function edit(Tag $tag): View
    {
        $tag->loadCount(['news as all_news_count' => fn ($q) => $q->withTrashed()]);

        return view('admin.tags.edit', compact('tag'));
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $before = $tag->only(['name', 'slug']);

        $tag->update([
            'name' => $request->validated('name'),
            // The slug is part of public URLs: it only changes when explicitly edited.
            'slug' => $request->validated('slug') ?: $tag->slug,
        ]);

        Activity::record('tag.updated', $tag, ['before' => $before, 'after' => $tag->only(['name', 'slug'])]);

        return redirect()->route('tags.index')->with('success', 'ট্যাগ আপডেট হয়েছে।');
    }

    /** Fold $tag into another tag: its articles move over, then it is removed. */
    public function merge(Request $request, Tag $tag): RedirectResponse
    {
        $request->validate([
            'into' => ['required', 'integer', Rule::exists('tags', 'id'), Rule::notIn([$tag->id])],
        ], [
            'into.required' => 'কোন ট্যাগে মার্জ করবেন তা বেছে নিন।',
            'into.exists' => 'নির্বাচিত ট্যাগটি পাওয়া যায়নি।',
            'into.not_in' => 'একটি ট্যাগকে নিজের সাথে মার্জ করা যায় না।',
        ]);

        $target = Tag::findOrFail($request->integer('into'));
        $mergedName = $tag->name;
        $mergedSlug = $tag->slug;

        $moved = DB::transaction(function () use ($tag, $target) {
            $articleIds = DB::table('article_tag')->where('tag_id', $tag->id)->pluck('news_id');
            $alreadyThere = DB::table('article_tag')->where('tag_id', $target->id)
                ->whereIn('news_id', $articleIds)->pluck('news_id');

            $rows = $articleIds->diff($alreadyThere)
                ->map(fn ($newsId) => ['news_id' => $newsId, 'tag_id' => $target->id])
                ->values()->all();

            if ($rows) {
                DB::table('article_tag')->insert($rows);
            }

            DB::table('article_tag')->where('tag_id', $tag->id)->delete();
            $tag->delete();

            return count($rows);
        });

        FrontendCache::flush();

        Activity::record('tag.merged', $target, [
            'merged_name' => $mergedName,
            'merged_slug' => $mergedSlug,
            'into' => $target->name,
            'articles_moved' => $moved,
        ]);

        return redirect()->route('tags.index')
            ->with('success', "\"{$mergedName}\" ট্যাগটি \"{$target->name}\"-এ মার্জ হয়েছে।");
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $count = $tag->news()->withTrashed()->count();

        DB::transaction(function () use ($tag) {
            $tag->news()->detach();
            $tag->delete();
        });

        FrontendCache::flush();

        Activity::record('tag.deleted', null, ['name' => $tag->name, 'slug' => $tag->slug, 'articles' => $count]);

        return redirect()->route('tags.index')->with('success', 'ট্যাগ মুছে ফেলা হয়েছে।');
    }

    /** Autocomplete for the article form (every staff member who can write articles). */
    public function suggest(Request $request): JsonResponse
    {
        $term = Tag::cleanName((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json(['tags' => []]);
        }

        $like = addcslashes($term, '%_\\').'%';
        $slugLike = addcslashes(Tag::slugFor($term), '%_\\').'%';

        $names = Tag::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('slug', 'like', $slugLike))
            ->withCount('news')
            ->orderByDesc('news_count')
            ->orderBy('name')
            ->limit(10)
            ->pluck('name');

        return response()->json(['tags' => $names]);
    }
}
