<?php

namespace XLaravel\Followable;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use XLaravel\Followable\Support\FollowableConfig;

class FollowableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/followable.php', 'followable');
    }

    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, $this->schedulePruning(...));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/followable.php' => $this->app->configPath('followable.php'),
        ], 'followable-config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'followable-migrations');
    }

    private function schedulePruning(Schedule $schedule): void
    {
        if (! Config::boolean('followable.prune.enabled', true)) {
            return;
        }

        $frequency = Config::string('followable.prune.schedule', 'daily');

        $event = $schedule->command('model:prune', [
            '--model' => [FollowableConfig::followModel()],
            '--chunk' => Config::integer('followable.prune.chunk', 1000),
        ]);

        if (str_contains($frequency, ' ')) {
            $event->cron($frequency);

            return;
        }

        [$method, $arguments] = array_pad(explode(':', $frequency, 2), 2, '');

        if ($method === '' || ! method_exists($event, $method)) {
            throw new InvalidArgumentException("[{$frequency}] is not a schedule frequency.");
        }

        $event->{$method}(...($arguments === '' ? [] : explode(',', $arguments)));
    }
}
