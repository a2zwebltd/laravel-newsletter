<?php

namespace A2ZWeb\Newsletter\Tests;

use A2ZWeb\Newsletter\NewsletterServiceProvider;
use Cviebrock\EloquentSluggable\ServiceProvider as SluggableServiceProvider;
use Dyrynda\Database\Support\LaravelModelUuidServiceProvider;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelModelUuidServiceProvider::class,
            SluggableServiceProvider::class,
            LaravelSettingsServiceProvider::class,
            NewsletterServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('newsletter.user_model', User::class);

        // Host wiring used by a couple of tests.
        $app['config']->set('newsletter.callbacks.subscribe_existing_user', function (User $user): void {
            $user->update(['marketing' => true]);
        });
        $app['config']->set('newsletter.callbacks.unsubscribe_user', function (User $user): void {
            $user->update(['marketing' => false]);
        });
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('/', fn () => 'home')->name('home');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
