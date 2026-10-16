<?php

namespace App\Providers;

use App\Auth\LegacyPasswordUserProvider;
use App\Support\Admin\AdminPermission;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::provider("legacy_eloquent", function ($app, array $config) {
            return new LegacyPasswordUserProvider(
                $app->make(HasherContract::class),
                $config["model"]
            );
        });

        Gate::define("admin.access-panel", function ($user) {
            return AdminPermission::canAccessPanel($user);
        });

        Gate::define("admin.can", function ($user, string $permission) {
            return AdminPermission::can($user, $permission);
        });
    }
}
