<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Receipts\ChainedParkingExtractor;
use App\Services\Receipts\ChainedReceiptExtractor;
use App\Services\Receipts\ClaudeDocumentReader;
use App\Services\Receipts\ClaudeParkingExtractor;
use App\Services\Receipts\ClaudeReceiptExtractor;
use App\Services\Receipts\ClaudeRepairExtractor;
use App\Services\Receipts\NullRepairExtractor;
use App\Services\Receipts\ParkingExtractor;
use App\Services\Receipts\PdfTextParkingExtractor;
use App\Services\Receipts\PdfTextReceiptExtractor;
use App\Services\Receipts\ReceiptExtractor;
use App\Services\Receipts\RepairExtractor;
use Illuminate\Support\ServiceProvider;
use Smalot\PdfParser\Parser;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Null when no API key is configured, which every consumer checks via
        // isAvailable() rather than reaching for the config itself.
        $this->app->singleton(ClaudeDocumentReader::class, function ($app) {
            $key = $app['config']->get('services.anthropic.key');

            if (blank($key)) {
                return null;
            }

            $effort = $app['config']->get('services.anthropic.effort');

            return new ClaudeDocumentReader(
                new Client(apiKey: $key),
                $app['config']->get('services.anthropic.model'),
                blank($effort) ? null : $effort,
            );
        });

        $this->app->singleton(ReceiptExtractor::class, function ($app) {
            // Cheapest first: provider PDFs are read locally for free, and only
            // scans or unknown layouts reach the paid vision model.
            $extractors = [new PdfTextReceiptExtractor(new Parser)];

            if ($reader = $app->make(ClaudeDocumentReader::class)) {
                $extractors[] = new ClaudeReceiptExtractor($reader);
            }

            return new ChainedReceiptExtractor($extractors);
        });

        // Workshop invoices are scans - no free path, so without a key the
        // feature is off rather than degraded.
        $this->app->singleton(RepairExtractor::class, function ($app) {
            $reader = $app->make(ClaudeDocumentReader::class);

            return $reader ? new ClaudeRepairExtractor($reader) : new NullRepairExtractor;
        });

        // Provider invoices are generated PDFs and are read for free; tickets
        // and app screenshots have no shared layout and need the model.
        $this->app->singleton(ParkingExtractor::class, function ($app) {
            $extractors = [new PdfTextParkingExtractor(new Parser)];

            if ($reader = $app->make(ClaudeDocumentReader::class)) {
                $extractors[] = new ClaudeParkingExtractor($reader);
            }

            return new ChainedParkingExtractor($extractors);
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
