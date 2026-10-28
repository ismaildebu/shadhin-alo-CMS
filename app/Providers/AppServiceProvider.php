<?php

namespace App\Providers;

use App\Models\User;
use App\Support\FrontendCache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Admin panel access, by capability:
        //  manage-cms        admin, editor, reporter (reporters only see their own articles)
        //  manage-categories admin, editor
        //  manage-tags       admin, editor
        //  manage-users      admin
        Gate::define('manage-cms', fn (User $user) => $user->canManageCms());
        Gate::define('manage-categories', fn (User $user) => $user->isEditorOrAdmin());
        Gate::define('manage-tags', fn (User $user) => $user->isEditorOrAdmin());
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());

        // Only the public views need categories/breaking news. Partials and
        // sections inherit these from the view that includes them, so we avoid
        // running the query for every single Blade file (the old '*' composer).
        // The data itself is cached (see FrontendCache).
        View::composer(
            ['frontend.layouts.app', 'frontend.home', 'frontend.news', 'frontend.category', 'frontend.tag', 'frontend.location'],
            fn ($view) => $view->with(FrontendCache::shared())
        );
    }
}
