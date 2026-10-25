<?php

declare(strict_types=1);

namespace Atannex\Share;

use Atannex\Share\Console\InspectCommand;
use Atannex\Share\Console\LinksCommand;
use Atannex\Share\Console\PlatformsCommand;
use Atannex\Share\Contracts\Recorder;
use Atannex\Share\Http\Controllers\BeaconController;
use Atannex\Share\Http\Controllers\RedirectController;
use Atannex\Share\Links\UrlComposer;
use Atannex\Share\Platforms\PlatformRegistry;
use Atannex\Share\Recorders\DatabaseRecorder;
use Atannex\Share\Recorders\NullRecorder;
use Atannex\Share\Resolution\Normalizer;
use Atannex\Share\Resolution\Resolver;
use Atannex\Share\Tracking\Tracker;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class ShareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/share.php', 'share');

        $this->app->singleton(PlatformRegistry::class, fn (Application $app) => new PlatformRegistry(
            PlatformRegistry::catalog(),
            (array) $app['config']->get('share.custom', []),
            array_map('intval', (array) $app['config']->get('share.popup', [620, 680])),
        ));

        $this->app->singleton(Normalizer::class, fn (Application $app) => new Normalizer(
            $app['url'],
            (int) $app['config']->get('share.text_limit', 200),
            (int) $app['config']->get('share.hashtags', 2),
        ));

        $this->app->singleton(Resolver::class, fn (Application $app) => new Resolver($app->make(Normalizer::class), $app['url'], $app['router']));

        $this->app->singleton(UrlComposer::class, fn (Application $app) => new UrlComposer(
            $app['config']->get('share.utm.enabled', true) ? (array) $app['config']->get('share.utm.parameters', []) : [],
        ));

        $this->app->singleton(Tracker::class, fn (Application $app) => new Tracker(
            $app['url'],
            $app['config']->get('share.tracking.enabled', true) && $app['config']->get('share.routes.enabled', true),
            $app['cache']->store($app['config']->get('share.tracking.cache')),
            (int) $app['config']->get('share.tracking.dedupe', 30),
        ));

        $this->app->singleton(ShareManager::class, fn (Application $app) => new ShareManager(
            $app->make(PlatformRegistry::class),
            $app->make(Resolver::class),
            $app->make(Normalizer::class),
            $app->make(UrlComposer::class),
            $app->make(Tracker::class),
            $app['events'],
            fn () => $this->recorder($app),
            (array) $app['config']->get('share', []),
        ));

        $this->app->alias(ShareManager::class, 'share');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'share');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'share');
        Blade::componentNamespace('Atannex\\Share\\View\\Components', 'share');

        if ($this->app['config']->get('share.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([PlatformsCommand::class, LinksCommand::class, InspectCommand::class]);

            $this->publishes([__DIR__.'/../config/share.php' => config_path('share.php')], 'share-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/share')], 'share-views');
            $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/share')], 'share-lang');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'share-migrations');
        }
    }

    private function registerRoutes(): void
    {
        $config = (array) $this->app['config']->get('share.routes', []);

        if (! ($config['enabled'] ?? true) || $this->app->routesAreCached()) {
            return;
        }

        $middleware = (array) ($config['middleware'] ?? ['web']);
        if (! empty($config['throttle'])) {
            $middleware[] = 'throttle:'.$config['throttle'];
        }

        Route::group(array_filter([
            'prefix' => $config['prefix'] ?? 'share',
            'domain' => $config['domain'] ?? null,
            'middleware' => $middleware,
        ]), function () {
            // Pages are often cached with a stale CSRF token; the beacon is signed instead.
            Route::post('beacon', BeaconController::class)
                ->name('share.beacon')
                ->withoutMiddleware([ValidateCsrfToken::class, VerifyCsrfToken::class]);

            Route::get('{platform}', RedirectController::class)
                ->name('share.redirect')
                ->where('platform', '[a-z0-9_-]+');
        });
    }

    private function recorder(Application $app): Recorder
    {
        $recorder = $app['config']->get('share.tracking.recorder', 'database');

        return match ($recorder) {
            'database', null => new DatabaseRecorder,
            'null', 'none', false => new NullRecorder,
            default => is_string($recorder) && is_subclass_of($recorder, Recorder::class)
                ? $app->make($recorder)
                : throw new InvalidArgumentException("Share recorder [{$recorder}] must be \"database\", \"null\" or a class implementing ".Recorder::class.'.'),
        };
    }
}
