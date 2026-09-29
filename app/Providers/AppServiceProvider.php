<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Gate::define('operate', fn (User $u) => $u->isOperator());
        Gate::define('admin', fn (User $u) => $u->isAdmin());
        Gate::define('reports', fn (User $u) => in_array($u->role, User::ROLES, true));
        Gate::define('view-asset', fn (User $u, Asset $asset) => Asset::visibleTo($u)->whereKey($asset->id)->exists());
    }
}
