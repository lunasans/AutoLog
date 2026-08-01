<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Receipts\ChainedReceiptExtractor;
use App\Services\Receipts\ClaudeReceiptExtractor;
use App\Services\Receipts\PdfTextReceiptExtractor;
use App\Services\Receipts\ReceiptExtractor;
use Illuminate\Support\ServiceProvider;
use Smalot\PdfParser\Parser;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ReceiptExtractor::class, function ($app) {
            $key = $app['config']->get('services.anthropic.key');

            // Cheapest first: provider PDFs are read locally for free, and only
            // scans or unknown layouts reach the paid vision model.
            $extractors = [new PdfTextReceiptExtractor(new Parser)];

            if (filled($key)) {
                $extractors[] = new ClaudeReceiptExtractor(
                    new Client(apiKey: $key),
                    $app['config']->get('services.anthropic.model'),
                );
            }

            return new ChainedReceiptExtractor($extractors);
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
