<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use App\Models\StoreAsset;
use App\Models\StoreAssetAssignment;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));

        if ($this->app->environment('production') || config('app.force_https')) {
            URL::forceScheme('https');
        }

        Event::listen(Login::class, [LogAuthenticationActivity::class, 'login']);
        Event::listen(Logout::class, [LogAuthenticationActivity::class, 'logout']);
        Event::listen(Failed::class, [LogAuthenticationActivity::class, 'failed']);

        Route::bind('asset', fn (string $value) => StoreAsset::query()->findOrFail($value));
        Route::bind('assignment', fn (string $value) => StoreAssetAssignment::query()->findOrFail($value));

        view()->share('shopName', config('brand.name'));
        view()->share('shopTagline', config('brand.tagline'));
    }
}
