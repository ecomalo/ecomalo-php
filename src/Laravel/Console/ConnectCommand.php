<?php

namespace EcomAlo\Laravel\Console;

use EcomAlo\Client;
use EcomAlo\Exceptions\EcomAloException;
use Illuminate\Console\Command;

// trades the panel's one-time connection code for the site token and webhook secret, and can write them to .env
class ConnectCommand extends Command
{
    protected $signature = 'ecomalo:connect
        {code : The connection code from EcomAlo > Integrations > Connect site (type Laravel)}
        {--site-url= : Your shop address (default APP_URL)}
        {--webhook-url= : Where EcomAlo posts updates (default this app\'s ecomalo.webhook route)}
        {--write : Write ECOMALO_API_KEY and ECOMALO_API_SECRET into .env}';

    protected $description = 'Connect this shop to EcomAlo with a one-time connection code';

    public function handle(): int
    {
        $siteUrl = $this->option('site-url') ?: config('app.url');
        $webhookUrl = $this->option('webhook-url') ?: (config('ecomalo.webhook.enabled', true) ? url(config('ecomalo.webhook.path', 'ecomalo/webhook')) : null);

        try {
            $connection = Client::connect((string) $this->argument('code'), $siteUrl, $webhookUrl, (array) config('ecomalo'));
        } catch (EcomAloException $e) {
            $this->error('EcomAlo refused the code: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Connected to EcomAlo store "'.($connection->storeName ?? '').'".');
        $this->line('Webhook URL: '.($webhookUrl ?: '(none)'));

        $values = ['ECOMALO_API_KEY' => $connection->apiKey, 'ECOMALO_API_SECRET' => (string) $connection->webhookSecret];

        if ($this->option('write')) {
            $this->writeEnv($values);
            $this->info('Saved to .env. Run `php artisan config:cache` if you cache config.');

            return self::SUCCESS;
        }

        $this->warn('Add these to .env now; they are shown only once:');

        foreach ($values as $key => $value) {
            $this->line($key.'='.$value);
        }

        return self::SUCCESS;
    }

    private function writeEnv(array $values): void
    {
        $path = $this->laravel->environmentFilePath();
        $env = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $env = preg_match('/^'.$key.'=.*$/m', $env)
                ? preg_replace_callback('/^'.$key.'=.*$/m', fn () => $line, $env)
                : rtrim($env, "\r\n").PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($path, $env);
    }
}
