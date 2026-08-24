<?php

namespace Juanparati\LaraGeos;

use Illuminate\Support\ServiceProvider;

class LaraGeosServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/larageos.php' => config_path('larageos.php'),
            ], 'larageos');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/larageos.php', 'larageos');
    }
}
