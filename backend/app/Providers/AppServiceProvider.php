<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SearchService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SearchService::class, function ($app) {
            return new SearchService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
