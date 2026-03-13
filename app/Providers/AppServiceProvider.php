<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Observers\DashboardObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);

        Order::observe(DashboardObserver::class);
        Product::observe(\App\Observers\DashboardObserver::class);
        Category::observe(\App\Observers\DashboardObserver::class);
    }
}
