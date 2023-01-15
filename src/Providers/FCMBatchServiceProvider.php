<?php

namespace Quantomtech\LaravelFirebaseBatchMessaging\Providers;

use Illuminate\Support\ServiceProvider;
use Quantomtech\LaravelFirebaseBatchMessaging\FCMBatch;

class FCMBatchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        /*
         * Function not available and 'publish' not relevant in Lumen
         * 
         */ 
        if (function_exists('config_path')) {

            $this->mergeConfigFrom(__DIR__.'/../config/fcm-batch.php', 'fcm-batch');
        }

        /*
         * Register the service the package provides.
         * 
         */ 
        $this->app->singleton('qt.fcm.batch', function () {
            return new FCMBatch;
        });
    }


    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/fcm-batch.php' => config_path('fcm-batch.php'),
        ], 'config');
    }
}