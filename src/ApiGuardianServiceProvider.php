<?php

namespace WorkDoneRight\ApiGuardian;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use WorkDoneRight\ApiGuardian\Commands\ErrorsAnalyzeCommand;
use WorkDoneRight\ApiGuardian\Commands\ErrorsGenerateDocsCommand;
use WorkDoneRight\ApiGuardian\Commands\ErrorsListCommand;
use WorkDoneRight\ApiGuardian\Commands\ErrorsTestCommand;
use WorkDoneRight\ApiGuardian\Exceptions\Handler;
use WorkDoneRight\ApiGuardian\Http\Middleware\FormatNegotiation;

class ApiGuardianServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-api-guardian')
            ->hasConfigFile('api-guardian')
            ->hasViews()
            ->hasCommands([
                ErrorsListCommand::class,
                ErrorsGenerateDocsCommand::class,
                ErrorsTestCommand::class,
                ErrorsAnalyzeCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('api-guardian', function ($app) {
            return new ApiGuardian;
        });

        // Register the exception handler
        $this->app->singleton(
            ExceptionHandler::class,
            Handler::class
        );
    }

    public function packageBooted(): void
    {
        // Register middleware
        Route::aliasMiddleware('api-guardian', FormatNegotiation::class);

        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/api-guardian.php' => config_path('api-guardian.php'),
            ], 'api-guardian-config');
        }
    }
}
