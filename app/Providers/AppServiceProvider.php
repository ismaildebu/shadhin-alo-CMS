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
        // The super administrator passes every gate and policy.
        // (Typed as User on purpose: guests are skipped, never null-called.)
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Admin panel access, by capability. Which roles hold each one is
        // edited in the panel (Roles & permissions) and stored in the
        // role_permissions table; defaults are in App\Support\Permissions.
        Gate::define('manage-cms', fn (User $user) => $user->canManageCms());
        Gate::define('manage-categories', fn (User $user) => $user->hasPermission('categories_manage'));
        Gate::define('manage-tags', fn (User $user) => $user->hasPermission('tags_manage'));
        Gate::define('manage-videos', fn (User $user) => $user->hasPermission('videos_manage'));
        Gate::define('manage-photocards', fn (User $user) => $user->hasPermission('photocards_manage'));
        Gate::define('manage-polls', fn (User $user) => $user->hasPermission('polls_manage'));
        Gate::define('manage-ads', fn (User $user) => $user->hasPermission('ads_manage'));
        Gate::define('manage-layout', fn (User $user) => $user->hasPermission('layout_manage'));
        Gate::define('manage-users', fn (User $user) => $user->hasPermission('users_manage'));
        // The roles & permissions page: super administrator only.
        Gate::define('manage-roles', fn (User $user) => $user->isSuperAdmin());

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