<?php

namespace Allyson\SafeMode;

use Allyson\SafeMode\Console\InstallCommand;
use Allyson\SafeMode\Services\SafeModeService;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SafeModeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/safe-mode.php',
            'safe-mode'
        );

        $this->app->singleton(SafeModeService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publicar config
            $this->publishes([
                __DIR__ . '/Config/safe-mode.php' => config_path('safe-mode.php'),
            ], 'safe-mode-config');

            // Publicar migrations
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'safe-mode-migrations');

            // Registrar commands
            $this->commands([
                InstallCommand::class,
            ]);
        }

        // Registrar evento que intercepta comandos Artisan
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            /** @var SafeModeService $service */
            $service = $this->app->make(SafeModeService::class);
            $service->handle($event);
        });
    }
}

