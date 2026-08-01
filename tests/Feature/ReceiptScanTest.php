<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Receipts\ExtractedReceipt;
use App\Services\Receipts\ExtractedRepair;
use App\Services\Receipts\NullReceiptExtractor;
use App\Services\Receipts\NullRepairExtractor;
use App\Services\Receipts\ReceiptExtractor;
use App\Services\Receipts\RepairExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReceiptScanTest extends TestCase
{
    use RefreshDatabase;

    private function fakeReceiptExtractor(ExtractedReceipt $result): void
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

    private function fakeRepairExtractor(ExtractedRepair $result): void
    {
        $this->app->instance(RepairExtractor::class, new class($result) implements RepairExtractor
        {
            public function __construct(private ExtractedRepair $result) {}

            public function extract(UploadedFile $file): ExtractedRepair
            {
                return $this->result;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        });
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->create('beleg.jpg', 100, 'image/jpeg');
    }

    public function test_it_returns_the_extracted_fueling_values(): void
    {
        $this->fakeReceiptExtractor(new ExtractedReceipt(
            date: '2026-07-14',
            liters: 42.5,
            priceTotal: 78.31,
        ));

        $this->actingAs(User::factory()->create())
            ->post(route('receipts.scan.fueling'), ['receipt' => $this->file()])
            ->assertOk()
            ->assertJson([
                'date' => '2026-07-14',
                'liters' => 42.5,
                'price_total' => 78.31,
                'odometer_reading' => null,
            ]);
    }

    public function test_it_returns_the_extracted_repair_values(): void
    {
        $this->fakeRepairExtractor(new ExtractedRepair(
            date: '2026-07-14',
            description: 'Ölwechsel, Bremsbeläge vorne',
            cost: 429.9,
            odometerReading: 84120,
        ));

        $this->actingAs(User::factory()->create())
            ->post(route('receipts.scan.repair'), ['receipt' => $this->file()])
            ->assertOk()
            ->assertJson([
                'date' => '2026-07-14',
                'description' => 'Ölwechsel, Bremsbeläge vorne',
                'cost' => 429.9,
                'odometer_reading' => 84120,
            ]);
    }

    public function test_repair_scanning_reports_unavailable_without_an_api_key(): void
    {
        $this->app->instance(RepairExtractor::class, new NullRepairExtractor);

        $this->actingAs(User::factory()->create())
            ->post(route('receipts.scan.repair'), ['receipt' => $this->file()])
            ->assertStatus(503);
    }

    public function test_fueling_scanning_reports_unavailable_when_disabled(): void
    {
        $this->app->instance(ReceiptExtractor::class, new NullReceiptExtractor);

        $this->actingAs(User::factory()->create())
            ->post(route('receipts.scan.fueling'), ['receipt' => $this->file()])
            ->assertStatus(503);
    }

    public function test_it_rejects_unsupported_file_types(): void
    {
        $this->fakeReceiptExtractor(ExtractedReceipt::empty());

        $this->actingAs(User::factory()->create())
            ->post(route('receipts.scan.fueling'), ['receipt' => UploadedFile::fake()->create('beleg.exe', 10)])
            ->assertSessionHasErrors('receipt');
    }

    public function test_guests_cannot_scan(): void
    {
        $this->post(route('receipts.scan.fueling'), ['receipt' => $this->file()])
            ->assertRedirect(route('login'));

        $this->post(route('receipts.scan.repair'), ['receipt' => $this->file()])
            ->assertRedirect(route('login'));
    }
}
