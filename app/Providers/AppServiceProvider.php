<?php

namespace App\Providers;

use App\Support\CitizenAuth;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
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
        // The signed-in citizen is cached per request; never let it carry over.
        Event::listen(RequestHandled::class, fn () => CitizenAuth::forget());
    }
}
