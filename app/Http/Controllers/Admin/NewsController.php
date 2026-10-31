<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\NewsRequest;
use App\Models\Activity;
use App\Models\ArticleRevision;
use App\Models\Category;
use App\Models\Location;
use App\Models\Media;
use App\Models\News;
use App\Services\NewsWorkflow;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class NewsController extends Controller
{
    private const IMAGE_DIR = 'uploads/news';

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', News::class);

        $user = $request->user();
        $status = $request->query('status');
        $trashed = $status === 'trashed' && $user->isEditorOrAdmin();
        $term = trim((string) $request->query('q', ''));

        $query = News::with(['category', 'author'])
            ->visibleTo($user);

        if ($trashed) {
            $query->onlyTrashed();
        } elseif (in_array($status, News::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($term !== '') {
            $query->where(
                'title',
                'like',
                '%'.addcslashes($term, '%_\\').'%'
            );
        }

        $news = $query
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $counts = News::visibleTo($user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $trashedCount = $user->isEditorOrAdmin()
            ? News::onlyTrashed()->count()
            : 0;

        return view('admin.news.index', compact(
            'news',
            'counts',
            'status',
            'term',
            'trashed',
            'trashedCount'
        ));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', News::class);

        return view('admin.news.create', [
            'locationTree' => $this->locationTree(),
            'categories' => Category::orderBy('name')->get(),
            'statuses' => NewsWorkflow::allowedTargets($request->user()),
        ]);
    }

    public function store(NewsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $status = $request->validated('status');

        $data = $this->payload($request);
        $categoryIds = $request->validated('category_ids');

        // Keep the first selected category in the legacy column.
        $data['category_id'] = $categoryIds[0];

        $data['slug'] = News::uniqueSlug($data['title']);
        $data['author_id'] = $user->id;
        $data['reporter'] = ($data['reporter'] ?? null) ?: $user->name;
        $data['status'] = $status;
        $data['published_at'] = NewsWorkflow::resolvePublishedAt(
            $status,
            null,
            $request->publishAt()
        );
        $data['location_id'] = $request->location()?->id;

        if (! $user->isEditorOrAdmin()) {
            // Reporters cannot flag articles.
            $data['breaking'] = false;
            $data['featured'] = false;
            $data['is_lead'] = false;
        }

        $newImage = $request->hasFile('image')
            ? $this->storeImage($request->file('image'))
            : null;

        $data['image'] = $newImage ?: $this->galleryChoice($request);

        try {
            $news = DB::transaction(function () use (
                $data,
                $categoryIds
            ) {
                $news = News::create($data);

                $news->categories()->sync($categoryIds);

                return $news;
            });
        } catch (Throwable $e) {
            $this->deleteImage($newImage);

            throw $e;
        }

        if ($request->tagsSubmitted()) {
            $news->syncTagNames($request->validated('tags', []));
        }

        ArticleRevision::capture($news, $user, 'প্রথম সংস্করণ');

        Activity::record(
            'article.created',
            $news,
            ['status' => $status],
            $user
        );

        return redirect()
            ->route('news.index')
            ->with(
                'success',
                'সংবাদ সংরক্ষণ হয়েছে ('.News::STATUS_LABELS[$status].')।'
            );
    }

    public function edit(Request $request, News $news): View
    {
        Gate::authorize('update', $news);

        $news->load(['tags', 'location', 'categories']);

        return view('admin.news.edit', [
            'locationTree' => $this->locationTree(),
            'news' => $news,
            'categories' => Category::orderBy('name')->get(),
            'statuses' => NewsWorkflow::allowedTargets(
                $request->user(),
                $news
            ),
        ]);
    }

    public function update(
        NewsRequest $request,
        News $news
    ): RedirectResponse {
        $user = $request->user();

        // Keep the original image until the update succeeds.
        $oldImage = $news->image;

        // Do not regenerate the slug: existing links must keep working.
        $data = $this->payload($request);
        $categoryIds = $request->validated('category_ids');

        // Maintain the legacy category_id column.
        $data['category_id'] = $categoryIds[0];

        if (! $user->isEditorOrAdmin()) {
            // Preserve editorial flags set by an editor.
            unset(
                $data['breaking'],
                $data['featured'],
                $data['is_lead']
            );
        }

        $newImage = $request->hasFile('image')
            ? $this->storeImage($request->file('image'))
            : null;

        $picked = $newImage
            ? null
            : $this->galleryChoice($request);

        if ($newImage || $picked) {
            $data['image'] = $newImage ?: $picked;
        }

        // Keep the existing location unless the form submitted this field.
        if ($request->locationSubmitted()) {
            $data['location_id'] = $request->location()?->id;
        }

        $target = $request->validated('status');
        $changesStatus = $target && $target !== $news->status;

        try {
            DB::transaction(function () use (
                $news,
                $data,
                $categoryIds,
                $request,
                $user,
                $target,
                $changesStatus
            ) {
                $news->update($data);

                $news->categories()->sync($categoryIds);

                if ($request->tagsSubmitted()) {
                    $news->syncTagNames(
                        $request->validated('tags', [])
                    );
                }

                if ($changesStatus) {
                    NewsWorkflow::transition(
                        $news,
                        $user,
                        $target,
                        null,
                        $request->publishAt()
                    );
                } else {
                    ArticleRevision::capture($news, $user);
                }
            });
        } catch (Throwable $e) {
            // Only the newly uploaded image is cleaned up on failure.
            $this->deleteImage($newImage);

            throw $e;
        }

        // Remove the old image only after a successful replacement.
        // Never delete it if another article still uses it.
        if ($newImage && $oldImage && $oldImage !== $newImage) {
            $this->deleteImageIfUnused($oldImage, $news);
        }

        Activity::record('article.updated', $news, [], $user);

        return redirect()
            ->route('news.index')
            ->with('success', 'সংবাদ আপডেট হয়েছে।');
    }

    public function destroy(News $news): RedirectResponse
    {
        Gate::authorize('delete', $news);

        // Soft delete: keep the article and its image for restoration.
        $news->delete();

        Activity::record('article.deleted', $news);

        return redirect()
            ->route('news.index')
            ->with('success', 'সংবাদ ট্র্যাশে সরানো হয়েছে।');
    }

    /**
     * Move several articles to the trash at once (soft delete, restorable).
     *
     * The NewsPolicy "delete" rule is checked for every article, so a reporter
     * can only trash their own drafts; anything else is skipped and reported.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', News::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => 'মুছে ফেলার জন্য অন্তত একটি সংবাদ বাছুন।',
            'ids.min' => 'মুছে ফেলার জন্য অন্তত একটি সংবাদ বাছুন।',
            'ids.max' => 'একবারে সর্বোচ্চ ১০০টি সংবাদ মোছা যাবে।',
            'ids.*.integer' => 'সংবাদের তথ্য সঠিক নয়।',
        ]);

        $ids = array_unique($data['ids']);

        // Only articles this user may see; the rest count as skipped.
        $items = News::visibleTo($request->user())
            ->whereIn('id', $ids)
            ->get();

        $deleted = 0;

        foreach ($items as $news) {
            if (! Gate::allows('delete', $news)) {
                continue;
            }

            $news->delete();

            Activity::record('article.deleted', $news);

            $deleted++;
        }

        $skipped = count($ids) - $deleted;

        if ($deleted === 0) {
            return back()->withErrors([
                'ids' => 'কোনো সংবাদ মোছা যায়নি। এই সংবাদগুলো মোছার অনুমতি আপনার নেই।',
            ]);
        }

        $message = "{$deleted}টি সংবাদ ট্র্যাশে সরানো হয়েছে।";

        if ($skipped > 0) {
            $message .= " {$skipped}টি সংবাদ অনুমতির অভাবে বাদ গেছে।";
        }

        return back()->with('success', $message);
    }

    public function restore(News $news): RedirectResponse
    {
        Gate::authorize('restore', $news);

        $news->restore();

        Activity::record('article.restored', $news);

        return redirect()
            ->route('news.index')
            ->with('success', 'সংবাদ পুনরুদ্ধার হয়েছে।');
    }

    public function preview(News $news): View
    {
        Gate::authorize('view', $news);

        $news->loadMissing([
            'category',
            'categories',
            'tags',
            'location.parent.parent',
        ]);

        return view('frontend.news', [
            'news' => $news,
            'relatedNews' => collect(),
        ]);
    }

    /**
     * Return divisions, districts and upazilas for dependent selects.
     *
     * @return array{
     *     divisions: \Illuminate\Support\Collection,
     *     districts: \Illuminate\Support\Collection,
     *     upazilas: \Illuminate\Support\Collection
     * }
     */
    private function locationTree(): array
    {
        $all = Location::query()
            ->orderBy('name_en')
            ->get(['id', 'parent_id', 'type', 'name_bn']);

        return [
            'divisions' => $all
                ->where('type', Location::DIVISION)
                ->values(),

            'districts' => $all
                ->where('type', Location::DISTRICT)
                ->values(),

            'upazilas' => $all
                ->where('type', Location::UPAZILA)
                ->values(),
        ];
    }

    /**
     * Validated and sanitized content attributes.
     */
    private function payload(NewsRequest $request): array
    {
        $data = Arr::except(
            $request->validated(),
            [
                'image',
                'status',
                'published_at',
                'tags',
                'category_ids',
                'division_id',
                'district_id',
                'upazila_id',
            ]
        );

        $data['description'] = HtmlSanitizer::clean(
            $data['description']
        );

        if (
            trim(strip_tags($data['description'])) === ''
            && ! str_contains($data['description'], '<img')
        ) {
            throw ValidationException::withMessages([
                'description' => 'সংবাদের বিস্তারিত লিখুন।',
            ]);
        }

        $data['seo_title'] = ($data['seo_title'] ?? null)
            ?: $data['title'];

        return $data;
    }

    /**
     * Store an uploaded image and register it in the media gallery.
     *
     * The image is optimized (fixed size, under 1 MB) by Media::storeUpload().
     * Image processing failures become a validation error on the "image" field.
     */
    private function storeImage(UploadedFile $file): string
    {
        try {
            return Media::storeUpload(
                $file,
                auth()->user()
            )->filename;
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'image' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove an image uploaded during an operation that failed.
     *
     * This helper is used for newly uploaded files that have not
     * been successfully attached to an article.
     */
    private function deleteImage(?string $name): void
    {
        if (! $name) {
            return;
        }

        $name = basename($name);

        Media::removeFiles($name);

        Media::where('filename', $name)->delete();
    }

    /**
     * Delete a replaced image only when no other article references it.
     */
    private function deleteImageIfUnused(
        string $name,
        News $updatedNews
    ): void {
        $name = basename($name);

        $stillUsed = News::withTrashed()
            ->where('id', '!=', $updatedNews->id)
            ->where(function ($query) use ($name) {
                $query->where('image', $name)
                    ->orWhere('card_image', $name);
            })
            ->exists();

        if ($stillUsed) {
            return;
        }

        Media::removeFiles($name);

        Media::where('filename', $name)->delete();
    }

    /**
     * Accept only image names that exist in the media gallery.
     */
    private function galleryChoice(Request $request): ?string
    {
        $name = trim(
            (string) $request->input('gallery_image', '')
        );

        return $name !== ''
            ? Media::where('filename', $name)->value('filename')
            : null;
    }
}