<?php

namespace EcomAlo\Laravel;

use EcomAlo\Client;
use EcomAlo\Laravel\Console\ConnectCommand;
use EcomAlo\Webhook\WebhookVerifier;
use Illuminate\Support\ServiceProvider;

class EcomAloServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ecomalo.php', 'ecomalo');

        $this->app->singleton(Client::class, fn ($app) => new Client((array) $app['config']['ecomalo']));
        $this->app->alias(Client::class, 'ecomalo');

        $this->app->singleton(WebhookVerifier::class, fn ($app) => new WebhookVerifier(
            (string) $app['config']['ecomalo.api_secret'],
            (int) ($app['config']['ecomalo.webhook.max_age'] ?? WebhookVerifier::MAX_AGE_SECONDS),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../../config/ecomalo.php' => config_path('ecomalo.php')], 'ecomalo-config');
            $this->commands([ConnectCommand::class]);
        }

        if (config('ecomalo.webhook.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/webhook.php');
        }
    }
}
