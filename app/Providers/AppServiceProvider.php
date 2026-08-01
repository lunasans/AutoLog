<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Receipts\ClaudeReceiptExtractor;
use App\Services\Receipts\NullReceiptExtractor;
use App\Services\Receipts\ReceiptExtractor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ReceiptExtractor::class, function ($app) {
            $key = $app['config']->get('services.anthropic.key');

            if (blank($key)) {
                return new NullReceiptExtractor;
            }

            return new ClaudeReceiptExtractor(
                new Client(apiKey: $key),
                $app['config']->get('services.anthropic.model'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
