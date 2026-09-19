<?php

namespace App\Providers;

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
        // Detras del proxy del hosting, los enlaces deben salir en https.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
