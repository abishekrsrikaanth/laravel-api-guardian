<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use WorkDoneRight\ApiGuardian\Commands\CircuitBreakerListCommand;
use WorkDoneRight\ApiGuardian\Commands\CircuitBreakerResetCommand;
use WorkDoneRight\ApiGuardian\Commands\ErrorsAnalyzeCommand;
use WorkDoneRight\ApiGuardian\Commands\ErrorsClearCommand;
use WorkDoneRight\ApiGuardian\Commands\ErrorsExportCommand;
use WorkDoneRight\ApiGuardian\Commands\ErrorsGenerateDocsCommand;
use WorkDoneRight\ApiGuardian\Commands\ErrorsListCommand;
use WorkDoneRight\ApiGuardian\Commands\ErrorsStatsCommand;
use WorkDoneRight\ApiGuardian\Commands\ErrorsTestCommand;
use WorkDoneRight\ApiGuardian\Commands\VersionCommand;
use WorkDoneRight\ApiGuardian\Contracts\ErrorCollectorContract;
use WorkDoneRight\ApiGuardian\Contracts\RecoveryStrategyContract;
use WorkDoneRight\ApiGuardian\Exceptions\Handler;
use WorkDoneRight\ApiGuardian\Http\Middleware\FormatNegotiation;
use WorkDoneRight\ApiGuardian\Services\CircuitBreakerService;
use WorkDoneRight\ApiGuardian\Services\ErrorCollector;
use WorkDoneRight\ApiGuardian\Services\ErrorService;
use WorkDoneRight\ApiGuardian\Services\SmartErrorRecovery;

final class ApiGuardianServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-api-guardian')
            ->hasConfigFile('api-guardian')
            ->hasViews('api-guardian')
            ->hasCommands([
                ErrorsListCommand::class,
                ErrorsAnalyzeCommand::class,
                ErrorsTestCommand::class,
                ErrorsGenerateDocsCommand::class,
                ErrorsClearCommand::class,
                ErrorsExportCommand::class,
                ErrorsStatsCommand::class,
                CircuitBreakerListCommand::class,
                CircuitBreakerResetCommand::class,
                VersionCommand::class,
            ])
            ->hasMigration('2024_01_01_000001_create_api_guardian_tables');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('api-guardian', fn ($app): ApiGuardian => new ApiGuardian);

        // Register services
        $this->app->singleton(ErrorCollectorContract::class, fn ($app): ErrorCollector => new ErrorCollector);

        $this->app->singleton(RecoveryStrategyContract::class, fn ($app): SmartErrorRecovery => new SmartErrorRecovery);

        $this->app->singleton(ErrorService::class, fn ($app): ErrorService => new ErrorService);

        $this->app->singleton(CircuitBreakerService::class, fn ($app): CircuitBreakerService => new CircuitBreakerService);

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

        // Register routes
        $this->registerRoutes();

        // Publish assets
        $this->registerPublishing();
    }

    /**
     * Register package routes.
     */
    private function registerRoutes(): void
    {
        // API routes are loaded if API is enabled
        if (config('api-guardian.api.enabled', true)) {
            Route::group($this->apiRouteConfiguration(), function (): void {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }

        // Livewire routes
        if (config('api-guardian.ui.frameworks.livewire.enabled', false)) {
            Route::group($this->webRouteConfiguration('livewire'), function (): void {
                $this->loadRoutesFrom(__DIR__.'/../routes/livewire.php');
            });
        }

        // Inertia Vue routes
        if (config('api-guardian.ui.frameworks.inertia-vue.enabled', false)) {
            Route::group($this->webRouteConfiguration('inertia-vue'), function (): void {
                $this->loadRoutesFrom(__DIR__.'/../routes/inertia-vue.php');
            });
        }

        // Inertia React routes
        if (config('api-guardian.ui.frameworks.inertia-react.enabled', false)) {
            Route::group($this->webRouteConfiguration('inertia-react'), function (): void {
                $this->loadRoutesFrom(__DIR__.'/../routes/inertia-react.php');
            });
        }
    }

    /**
     * Get the API route group configuration.
     */
    private function apiRouteConfiguration(): array
    {
        return [
            'prefix' => config('api-guardian.api.prefix', 'api/api-guardian'),
            'middleware' => array_merge(['api'], config('api-guardian.api.middleware', [])),
            'namespace' => 'WorkDoneRight\ApiGuardian\Http\Controllers\Api',
        ];
    }

    /**
     * Get the web route group configuration for a specific framework.
     */
    private function webRouteConfiguration(string $framework): array
    {
        $config = config("api-guardian.ui.frameworks.{$framework}", []);

        $routeConfig = [
            'prefix' => Arr::get($config, 'route_prefix', 'api-guardian'),
            'middleware' => Arr::get($config, 'middleware', ['web', 'auth']),
        ];

        // Only add namespace for Inertia routes (which use controllers)
        // Livewire routes don't need namespace as they reference component classes directly
        if ($framework !== 'livewire') {
            $routeConfig['namespace'] = 'WorkDoneRight\ApiGuardian\Http\Controllers';
        }

        return $routeConfig;
    }

    /**
     * Register package publishable assets.
     */
    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/api-guardian.php' => config_path('api-guardian.php'),
            ], 'api-guardian-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'api-guardian-migrations');

            // Publish views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/api-guardian'),
            ], 'api-guardian-views');

            // Publish JavaScript assets (for Inertia)
            $this->publishes([
                __DIR__.'/../resources/js' => resource_path('js/vendor/api-guardian'),
            ], 'api-guardian-assets');

            // Publish all at once
            $this->publishes([
                __DIR__.'/../config/api-guardian.php' => config_path('api-guardian.php'),
                __DIR__.'/../database/migrations/' => database_path('migrations'),
                __DIR__.'/../resources/views' => resource_path('views/vendor/api-guardian'),
                __DIR__.'/../resources/js' => resource_path('js/vendor/api-guardian'),
            ], 'api-guardian');
        }
    }
}
