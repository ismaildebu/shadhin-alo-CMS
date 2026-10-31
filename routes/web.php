<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HomeLayoutController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\NewsRevisionController;
use App\Http\Controllers\Admin\NewsWorkflowController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Admin\VideoChannelController;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\SidebarController;
use App\Http\Controllers\Admin\NewsCardController;
use App\Http\Controllers\Admin\ArticleDesignController;
use App\Http\Controllers\Admin\VisualBuilderController;
use App\Http\Controllers\Frontend\AdClickController;
use App\Http\Controllers\Frontend\CategoryController as FrontendCategoryController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\LocationController;
use App\Http\Controllers\Frontend\TagController as FrontendTagController;
use App\Http\Controllers\Frontend\VideoController as FrontendVideoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AvatarController;
use Illuminate\Support\Facades\Route;

// Frontend
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/article/{slug}/next', [HomeController::class, 'next'])
    ->middleware('throttle:60,1')
    ->name('frontend.news.next');

Route::get('/article/{slug}', [HomeController::class, 'show'])
    ->name('frontend.news.show');

Route::get('/category/{slug}', [FrontendCategoryController::class, 'show'])
    ->name('frontend.category.show');

Route::get('/tag/{slug}', [FrontendTagController::class, 'show'])
    ->name('frontend.tag.show');

Route::get('/videos', [FrontendVideoController::class, 'index'])->name('frontend.videos');

Route::get('/video/{id}', [FrontendVideoController::class, 'show'])
    ->whereNumber('id')
    ->name('frontend.video.show');

Route::get('/ads/{id}/go', AdClickController::class)
    ->whereNumber('id')
    ->middleware('throttle:120,1')
    ->name('frontend.ad.click');


// /location/{division}[/{district}[/{upazila}]]
Route::get('/location/{division}/{district?}/{upazila?}', [LocationController::class, 'show'])
    ->name('frontend.location.show');

// Admin panel. Entry: authenticated, active, and holding the "cms_access"
// permission ("manage-cms"). Finer rules live in NewsPolicy, NewsWorkflow and
// the gates below; which roles hold each gate is edited under Roles & permissions.
Route::middleware(['auth', 'can:manage-cms'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::prefix('admin')->group(function () {

        // News: everyone with panel access (non-editors are limited to their own by the policy)
        Route::resource('news', NewsController::class)->except('show');
        Route::post('news/bulk-destroy', [NewsController::class, 'bulkDestroy'])->name('news.bulk-destroy');
        Route::post('news/{news}/restore', [NewsController::class, 'restore'])->name('news.restore')->withTrashed();
        Route::get('news/{news}/preview', [NewsController::class, 'preview'])->name('news.preview');

        Route::get('news/{news}/card', [NewsCardController::class, 'show'])->name('news.card');
        Route::post('news/{news}/card', [NewsCardController::class, 'store'])->name('news.card.store');
        Route::delete('news/{news}/card', [NewsCardController::class, 'destroy'])->name('news.card.destroy');
        Route::post('news/{news}/transition', [NewsWorkflowController::class, 'store'])->name('news.transition');
        Route::get('news/{news}/revisions', [NewsRevisionController::class, 'index'])->name('news.revisions.index');
        Route::post('news/{news}/revisions/{revision}/restore', [NewsRevisionController::class, 'restore'])
            ->name('news.revisions.restore')
            ->scopeBindings();

        // Tag autocomplete for the article form: everyone who can write articles
        Route::get('tags/suggest', [TagController::class, 'suggest'])
            ->middleware('throttle:60,1')
            ->name('tags.suggest');

        // Photo gallery (media library): everyone with panel access; deleting is limited inside the controller
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::get('media/list', [MediaController::class, 'list'])->middleware('throttle:60,1')->name('media.list');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
        Route::post('media/bulk-destroy', [MediaController::class, 'bulkDestroy'])->name('media.bulk-destroy');
        Route::post('media/editor-upload', [MediaController::class, 'editorUpload'])->middleware('throttle:30,1')->name('media.editor-upload');

        // Tag management (rename, merge, delete): permission "tags_manage"
        Route::middleware('can:manage-tags')->group(function () {
            Route::resource('tags', TagController::class)->only(['index', 'edit', 'update', 'destroy']);
            Route::post('tags/{tag}/merge', [TagController::class, 'merge'])->name('tags.merge');
        });

        // Categories: permission "categories_manage"
        Route::middleware('can:manage-categories')->group(function () {
            Route::resource('categories', CategoryController::class)->except('show');
        });

        // Video gallery (YouTube links and channels): permission "videos_manage"
        Route::middleware('can:manage-videos')->group(function () {
            Route::get('video-channels', [VideoChannelController::class, 'index'])->name('video-channels.index');
            Route::post('video-channels', [VideoChannelController::class, 'store'])->name('video-channels.store');
            Route::post('video-channels/sync', [VideoChannelController::class, 'syncAll'])->name('video-channels.sync-all');
            Route::post('video-channels/{channel}/sync', [VideoChannelController::class, 'sync'])->name('video-channels.sync');
            Route::delete('video-channels/{channel}', [VideoChannelController::class, 'destroy'])->name('video-channels.destroy');

            Route::resource('videos', VideoController::class)->except('show');
        });

        // Advertisements: permission "ads_manage"
        Route::middleware('can:manage-ads')->group(function () {
            Route::resource('ads', AdController::class)->except('show');
        });

        // Home page, menu, sidebar, article design, visual builder: permission "layout_manage"
        Route::middleware('can:manage-layout')->group(function () {
            Route::get('home-layout', [HomeLayoutController::class, 'edit'])->name('home-layout.edit');
            Route::put('home-layout', [HomeLayoutController::class, 'update'])->name('home-layout.update');

            Route::get('menu', [MenuController::class, 'edit'])->name('menu.edit');
            Route::put('menu', [MenuController::class, 'update'])->name('menu.update');
            Route::post('menu/links', [MenuController::class, 'storeLink'])->name('menu.links.store');
            Route::delete('menu/links/{item}', [MenuController::class, 'destroyLink'])->name('menu.links.destroy');

            Route::get('sidebar', [SidebarController::class, 'edit'])->name('sidebar.edit');
            Route::put('sidebar', [SidebarController::class, 'update'])->name('sidebar.update');

            Route::get('article-design', [ArticleDesignController::class, 'edit'])->name('article-design.edit');
            Route::put('article-design', [ArticleDesignController::class, 'update'])->name('article-design.update');

            Route::get('visual-builder', [VisualBuilderController::class, 'index'])->name('visual-builder.index');
            Route::post('visual-builder/preview', [VisualBuilderController::class, 'preview'])->name('visual-builder.preview');
        });

        // Photo card center: permission "photocards_manage"
        Route::middleware('can:manage-photocards')->group(function () {
            Route::get('photocards', [NewsCardController::class, 'center'])->name('photocards.index');
            Route::get('photocards/queue', [NewsCardController::class, 'queue'])->name('photocards.queue');
            Route::put('photocards/defaults', [NewsCardController::class, 'saveDefaults'])->name('photocards.defaults');
        });

        // Roles & permissions matrix: super administrator only
        Route::middleware('can:manage-roles')->group(function () {
            Route::get('roles', [RolePermissionController::class, 'edit'])->name('roles.edit');
            Route::put('roles', [RolePermissionController::class, 'update'])->name('roles.update');
            Route::post('roles/reset', [RolePermissionController::class, 'reset'])->name('roles.reset');
        });

        // Users, audit log and site settings: permission "users_manage"
        Route::middleware('can:manage-users')->group(function () {

            Route::resource('users', UserController::class)->except('show');
            Route::post('users/{user}/avatar', [AvatarController::class, 'storeFor'])->name('users.avatar.store');
            Route::delete('users/{user}/avatar', [AvatarController::class, 'destroyFor'])->name('users.avatar.destroy');
            Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
            Route::get('site-settings', [SiteSettingController::class, 'edit'])->name('site-settings.edit');
            Route::put('site-settings', [SiteSettingController::class, 'update'])->name('site-settings.update');
        });
    });
});

// Own profile: any authenticated user
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [AvatarController::class, 'storeOwn'])->name('profile.avatar.store');
    Route::delete('/profile/avatar', [AvatarController::class, 'destroyOwn'])->name('profile.avatar.destroy');
});

require __DIR__.'/auth.php';


// --- home-page "desh" block: cascading division / district / upazila filter ---
\Illuminate\Support\Facades\Route::get('/locations/{location}/children', \App\Http\Controllers\Frontend\LocationChildrenController::class)
    ->whereNumber('location')
    ->name('frontend.locations.children');


// --- online poll (sidebar widget) ---
\Illuminate\Support\Facades\Route::get('/polls', [\App\Http\Controllers\Frontend\PollController::class, 'index'])->name('frontend.polls.index');
\Illuminate\Support\Facades\Route::get('/polls/{poll}', [\App\Http\Controllers\Frontend\PollController::class, 'show'])->whereNumber('poll')->name('frontend.polls.show');
\Illuminate\Support\Facades\Route::post('/polls/{poll}/vote', [\App\Http\Controllers\Frontend\PollController::class, 'vote'])->whereNumber('poll')->middleware('throttle:30,1')->name('frontend.polls.vote');

// Poll management: permission "polls_manage"
\Illuminate\Support\Facades\Route::middleware(['auth', 'can:manage-cms', 'can:manage-polls'])->prefix('admin')->name('admin.')->group(function () {
    \Illuminate\Support\Facades\Route::resource('polls', \App\Http\Controllers\Admin\PollController::class)->except('show');
});