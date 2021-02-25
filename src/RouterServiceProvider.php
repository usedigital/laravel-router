<?php

namespace UseDigital\LaravelRouter;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouterServiceProvider extends ServiceProvider
{

    private $config_path = __DIR__ . "/config/";
    private $resources_path = __DIR__ . "/resources/";
    private $views_path = __DIR__ . "/resources/views/";

    private $config_files = [
        "router"
    ];

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //Configs
        foreach ($this->config_files as $config_file) {
            $this->mergeConfigFrom(
                $this->config_path.$config_file.".php", $config_file
            );
        }

        //Commands
        $this->commands([
            Commands\RouterCommand::class
        ]);

        //Rotas
        if(file_exists(base_path('routes/api_generated.php'))) {

                //$this->loadRoutesFrom(base_path('routes/api_generated.php'));

            Route::prefix(config('router.api.prefix', 'api'))
                ->as(config('router.api.as', null))
                ->domain(config('router.api.domain', null))
                ->middleware(config('router.api.middleware', 'api'))
                ->namespace(config('router.controllers_namespace').'\\'.config('router.api.namespace', 'API'))
                ->group(base_path('routes/api_generated.php'));

        }

        if(file_exists(base_path('routes/web_generated.php'))) {

                //$this->loadRoutesFrom(base_path('routes/web_generated.php'));

            Route::middleware(config('router.web.middleware', 'web'))
                ->namespace(config('router.controllers_namespace'))
                ->domain(config('router.web.domain', null))
                ->group(base_path('routes/web_generated.php'));

        }
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //Configs
        foreach ($this->config_files as $config_file) {
            $this->publishes([
                $this->config_path.$config_file.".php" => config_path($config_file.".php"),
            ], 'config');
        }

        //Views
        if($this->app->has('view')){
            $this->loadViewsFrom($this->views_path, 'router');

            $this->publishes([
                $this->views_path => resource_path('views/vendor/router'),
            ], 'views');
        }

        /*if(file_exists(base_path('routes/api_generated.php'))){
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api_generated.php'));
        }*/
    }
}
