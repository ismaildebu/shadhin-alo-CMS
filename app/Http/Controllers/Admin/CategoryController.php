<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Activity;
use App\Models\Category;
use App\Models\News;
use App\Support\FrontendCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $all = Category::query()
            ->withCount(['news as all_news_count' => fn ($q) => $q->withTrashed()])
            ->ordered()
            ->get();

        return view('admin.categories.index', [
            'roots' => $all->whereNull('parent_id'),
            'children' => $all->whereNotNull('parent_id')->groupBy('parent_id'),
            'all' => $all,
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'category' => new Category,
            'parents' => $this->parentChoices(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['slug'] = ($data['slug'] ?? null) ?: Category::uniqueSlug($data['name']);
        $data['serial'] = $data['serial'] ?? 0;
        $data['status'] = $data['status'] ?? true;
        $data['show_in_menu'] = $data['show_in_menu'] ?? true;

        $category = Category::create($data);

        Activity::record('category.created', $category, ['parent_id' => $category->parent_id]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'ক্যাটাগরি সফলভাবে যোগ হয়েছে।');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
            'parents' => $this->parentChoices($category),
            'hasChildren' => $category->children()->exists(),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        $data['slug'] = ($data['slug'] ?? null) ?: $category->slug;
        $data['serial'] = $data['serial'] ?? $category->serial;
        $data['status'] = $data['status'] ?? $category->status;
        $data['show_in_menu'] = $data['show_in_menu'] ?? $category->show_in_menu;

        $before = $category->only(['parent_id', 'status', 'show_in_menu', 'slug']);

        $category->update($data);

        Activity::record('category.updated', $category, [
            'before' => $before,
            'after' => $category->only(['parent_id', 'status', 'show_in_menu', 'slug']),
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'ক্যাটাগরি সফলভাবে আপডেট হয়েছে।');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return redirect()
                ->route('categories.index')
                ->withErrors(['category' => 'এই ক্যাটাগরির উপ-ক্যাটাগরি আছে। আগে সেগুলো সরান বা মুছুন।']);
        }

        // news.category_id is ON DELETE CASCADE: deleting here would silently wipe every article
        // (trashed ones included). The articles must be moved somewhere first.
        $newsCount = $category->news()->withTrashed()->count();
        $movedTo = null;

        if ($newsCount > 0) {
            $request->validate([
                'move_to' => ['nullable', 'integer', Rule::exists('categories', 'id'), Rule::notIn([$category->id])],
            ], [
                'move_to.exists' => 'নির্বাচিত ক্যাটাগরিটি পাওয়া যায়নি।',
                'move_to.not_in' => 'সংবাদ একই ক্যাটাগরিতে সরানো যাবে না।',
            ]);

            $movedTo = $request->input('move_to');

            if (! $movedTo) {
                return redirect()
                    ->route('categories.index')
                    ->withErrors(['category' => "এই ক্যাটাগরিতে {$newsCount}টি সংবাদ আছে। \"সংবাদ সরিয়ে মুছুন\" দিয়ে অন্য ক্যাটাগরি বেছে নিন।"]);
            }

            DB::transaction(function () use ($category, $movedTo) {
                News::withTrashed()->where('category_id', $category->id)->update(['category_id' => $movedTo]);
                $category->delete();
            });

            // The bulk UPDATE above fires no model events.
            FrontendCache::flush();
        } else {
            $category->delete();
        }

        Activity::record('category.deleted', $category, [
            'name' => $category->name,
            'moved_articles' => $newsCount,
            'moved_to' => $movedTo,
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', $newsCount > 0
                ? "ক্যাটাগরি মুছে ফেলা হয়েছে; {$newsCount}টি সংবাদ সরানো হয়েছে।"
                : 'ক্যাটাগরি মুছে ফেলা হয়েছে।');
    }

    /** Possible parents: top-level categories, never the category itself. */
    private function parentChoices(?Category $category = null)
    {
        return Category::roots()
            ->when($category?->exists, fn ($q) => $q->where('id', '!=', $category->id))
            ->ordered()
            ->get(['id', 'name']);
    }
}
