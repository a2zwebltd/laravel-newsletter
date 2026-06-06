<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter;

use A2ZWeb\Newsletter\Console\Commands\SendScheduledMailings;
use A2ZWeb\Newsletter\Events\MailingSaved;
use A2ZWeb\Newsletter\Jobs\SendMailingJob;
use A2ZWeb\Newsletter\Listeners\UpdateMailingShortUrl;
use A2ZWeb\Newsletter\Nova\Mailing;
use A2ZWeb\Newsletter\Nova\MailingRecipient;
use A2ZWeb\Newsletter\Nova\MailingSubscriber;
use A2ZWeb\Newsletter\Nova\MailingType;
use A2ZWeb\Newsletter\Support\AudienceResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;

class NewsletterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/newsletter.php', 'newsletter');

        $this->app->singleton(AudienceResolver::class);

        // Ensure the Spatie settings migration ships with the package.
        $paths = config('settings.migrations_paths', []);
        $paths[] = __DIR__.'/../database/settings';
        config()->set('settings.migrations_paths', array_values(array_unique($paths)));

        // Auto-discover the package's Settings class so app(MailingSettings::class) resolves.
        $discover = config('settings.auto_discover_settings', []);
        $discover[] = __DIR__.'/Settings';
        config()->set('settings.auto_discover_settings', array_values(array_unique($discover)));
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerMigrations();
        $this->registerViews();
        $this->registerRateLimiter();
        $this->registerRoutes();
        $this->registerListeners();
        $this->registerCommands();
        $this->registerSchedule();
        $this->registerNova();
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/newsletter.php' => config_path('newsletter.php'),
        ], 'newsletter-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/newsletter'),
        ], 'newsletter-views');
    }

    private function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'newsletter');
    }

    private function registerRateLimiter(): void
    {
        RateLimiter::for('mailings', function (SendMailingJob $job) {
            // Keyed per recipient: each recipient is sent once, so this throttles
            // duplicate/retry storms without ever dropping a distinct email. The
            // real pacing comes from the staggered ->delay() at dispatch time.
            return Limit::perMinute((int) config('newsletter.per_minute', 12))
                ->by((string) $job->recipient->getId());
        });
    }

    private function registerRoutes(): void
    {
        if (! config('newsletter.routes.enabled', true)) {
            return;
        }

        Route::group([
            'middleware' => config('newsletter.routes.middleware', ['web']),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    private function registerListeners(): void
    {
        if (config('newsletter.short_url.enabled', true)) {
            Event::listen(MailingSaved::class, UpdateMailingShortUrl::class);
        }
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            SendScheduledMailings::class,
        ]);
    }

    private function registerSchedule(): void
    {
        if (! config('newsletter.schedule.send_scheduled', true)) {
            return;
        }

        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('mailings:send-scheduled')->everyMinute();
        });
    }

    private function registerNova(): void
    {
        if (! config('newsletter.nova.register_resources', true) || ! class_exists(Nova::class)) {
            return;
        }

        Nova::resources([
            Mailing::class,
            MailingType::class,
            MailingSubscriber::class,
            MailingRecipient::class,
        ]);
    }
}
