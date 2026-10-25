<?php

declare(strict_types=1);

namespace Atannex\Share\Tests;

use Atannex\Share\Facades\Share;
use Atannex\Share\ShareServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @var array<string, mixed> config overrides for the next app */
    protected array $share = [];

    protected function getPackageProviders($app): array
    {
        return [ShareServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Share' => Share::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('share.via', 'atannex');
        $app['config']->set('share.site_name', 'Atannex');

        foreach ($this->share as $key => $value) {
            $app['config']->set("share.{$key}", $value);
        }
    }

    protected function defineRoutes($router): void
    {
        Route::get('/stories/{story}', fn () => 'story')->name('stories.show');
        Route::get('/people/{person}', fn () => 'person')->name('people.show');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('cover_url')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id');
            $table->string('name');
        });

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * A desktop browser, so the request is counted.
     *
     * @return array<string, string>
     */
    protected function browser(): array
    {
        return ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'];
    }
}
