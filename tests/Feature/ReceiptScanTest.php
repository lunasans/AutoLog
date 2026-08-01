<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Receipts\ExtractedReceipt;
use App\Services\Receipts\NullReceiptExtractor;
use App\Services\Receipts\ReceiptExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReceiptScanTest extends TestCase
{
    use RefreshDatabase;

    private function fakeExtractor(ExtractedReceipt $result): void
    {
        $this->app->instance(ReceiptExtractor::class, new class($result) implements ReceiptExtractor
        {
            public function __construct(private ExtractedReceipt $result) {}

            public function extract(UploadedFile $file): ExtractedReceipt
            {
                return $this->result;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        });
    }

    public function test_it_returns_the_extracted_values(): void
    {
        $this->fakeExtractor(new ExtractedReceipt(
            date: '2026-07-14',
            liters: 42.5,
            priceTotal: 78.31,
        ));

        $this->actingAs(User::factory()->create())
            ->post(route('receipts.scan'), ['receipt' => UploadedFile::fake()->create('beleg.jpg', 100, 'image/jpeg')])
            ->assertOk()
            ->assertJson([
                'date' => '2026-07-14',
                'liters' => 42.5,
                'price_total' => 78.31,
                'odometer_reading' => null,
            ]);
    }

    public function test_it_reports_unavailable_when_no_api_key_is_configured(): void
    {
        $this->app->instance(ReceiptExtractor::class, new NullReceiptExtractor);

        $this->actingAs(User::factory()->create())
            ->post(route('receipts.scan'), ['receipt' => UploadedFile::fake()->create('beleg.jpg', 100, 'image/jpeg')])
            ->assertStatus(503);
    }

    public function test_it_rejects_unsupported_file_types(): void
    {
        $this->fakeExtractor(ExtractedReceipt::empty());

        $this->actingAs(User::factory()->create())
            ->post(route('receipts.scan'), ['receipt' => UploadedFile::fake()->create('beleg.exe', 10)])
            ->assertSessionHasErrors('receipt');
    }

    public function test_guests_cannot_scan(): void
    {
        $this->post(route('receipts.scan'), ['receipt' => UploadedFile::fake()->create('beleg.jpg', 100, 'image/jpeg')])
            ->assertRedirect(route('login'));
    }
}
