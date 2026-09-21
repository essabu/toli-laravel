<?php

declare(strict_types=1);

namespace Essabu\Toli\Laravel;

use Essabu\Toli\Exception\ToliConfigError;
use Essabu\Toli\Laravel\Console\KindsCommand;
use Essabu\Toli\Laravel\Readings\EloquentReadingStore;
use Essabu\Toli\Laravel\Readings\EventReadingBroadcast;
use Essabu\Toli\Readings\NullBroadcast;
use Essabu\Toli\Readings\Reader;
use Essabu\Toli\Readings\ReadingBroadcast;
use Essabu\Toli\Readings\ReadingStore;
use Essabu\Toli\Thresholds;
use Essabu\Toli\Toli;
use Essabu\Toli\Transport\Gateway;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the SDK into the container and ships the one table it needs.
 *
 * Installing this package gives an application four things without writing
 * them: a `Toli` client from `config/toli.php`, a `Reader` that keeps one
 * reading per subject and model, the migration for the table it keeps them
 * in, and a broadcast event when one is recorded. What an application still
 * writes is its own questions and its own analysis — the part that is its
 * business.
 */
final class ToliServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/toli.php', 'toli');

        $this->app->singleton(Thresholds::class, static fn (): Thresholds => Thresholds::defaults());

        $this->app->singleton(Toli::class, function (): Toli {
            $key = (string) config('toli.key', '');
            if (trim($key) === '') {
                throw new ToliConfigError('TOLI_API_KEY is not set: nothing can be asked without it.');
            }

            $factory = new HttpFactory;

            return new Toli(
                apiKey: $key,
                http: new Client(['timeout' => 60, 'connect_timeout' => 15]),
                requests: $factory,
                streams: $factory,
                transport: new Gateway((string) config('toli.base_url', Gateway::DEFAULT_BASE)),
                thresholds: $this->app->make(Thresholds::class),
                model: (string) config('toli.model', 'toli-1'),
            );
        });

        $this->app->singleton(ReadingStore::class, EloquentReadingStore::class);

        $this->app->singleton(ReadingBroadcast::class, static fn (): ReadingBroadcast => config('toli.broadcast', true)
            ? new EventReadingBroadcast
            : new NullBroadcast);

        $this->app->singleton(Reader::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/toli.php' => config_path('toli.php')], 'toli-config');

        // Loaded, not only published: `php artisan migrate` creates the table
        // the day the package is installed, which is the whole reason a
        // framework package exists beside the core SDK.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'toli-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([KindsCommand::class]);
        }
    }
}
