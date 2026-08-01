<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use App\Services\Receipts\ExtractedReceipt;
use App\Services\Receipts\ReceiptExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->car = $this->user->cars()->create([
            'brand' => 'VW',
            'model' => 'Polo',
            'year' => 2010,
            'license_plate' => 'GL-MS 141',
            'initial_odometer' => 0,
        ]);
    }

    /** Returns a result per uploaded file, in order; null means unreadable. */
    private function fakeExtractor(array $results): void
    {
        $this->app->instance(ReceiptExtractor::class, new class($results) implements ReceiptExtractor
        {
            private int $calls = 0;

            public function __construct(private array $results) {}

            public function extract(UploadedFile $file): ExtractedReceipt
            {
                return $this->results[$this->calls++] ?? ExtractedReceipt::empty();
            }

            public function isAvailable(): bool
            {
                return true;
            }
        });
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'application/pdf');
    }

    private function import(array $files)
    {
        return $this->actingAs($this->user)
            ->post(route('fuelings.import', $this->car), ['receipts' => $files]);
    }

    public function test_it_creates_one_entry_per_receipt(): void
    {
        $this->fakeExtractor([
            new ExtractedReceipt(date: '2026-06-28', liters: 21.0, priceTotal: 43.0),
            new ExtractedReceipt(date: '2026-07-30', liters: 20.35, priceTotal: 42.51),
        ]);

        $this->import([$this->pdf('juni.pdf'), $this->pdf('juli.pdf')])
            ->assertRedirect(route('cars.show', $this->car))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 Einträge angelegt'));

        $this->assertSame(2, $this->car->fuelings()->count());
    }

    public function test_imported_entries_carry_no_mileage(): void
    {
        $this->fakeExtractor([new ExtractedReceipt(date: '2026-07-30', liters: 20.35, priceTotal: 42.51)]);

        $this->import([$this->pdf('juli.pdf')]);

        $this->assertNull($this->car->fuelings()->sole()->odometer_reading);
    }

    public function test_it_keeps_the_receipt_with_each_entry(): void
    {
        $this->fakeExtractor([new ExtractedReceipt(date: '2026-07-30', liters: 20.35, priceTotal: 42.51)]);

        $this->import([$this->pdf('juli.pdf')]);

        $fueling = $this->car->fuelings()->sole();

        $this->assertTrue($fueling->hasReceipt());
        $this->assertSame('juli.pdf', $fueling->receipt_name);
        Storage::disk('local')->assertExists($fueling->receipt_path);
    }

    public function test_an_unreadable_receipt_is_reported_rather_than_guessed_at(): void
    {
        $this->fakeExtractor([
            new ExtractedReceipt(date: '2026-07-30', liters: 20.35, priceTotal: 42.51),
            // Only the total came through - not enough to build an entry.
            new ExtractedReceipt(priceTotal: 43.0),
        ]);

        $this->import([$this->pdf('juli.pdf'), $this->pdf('unleserlich.pdf')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'nicht lesbar: unleserlich.pdf'));

        $this->assertSame(1, $this->car->fuelings()->count());
    }

    public function test_importing_the_same_receipts_twice_does_not_duplicate_them(): void
    {
        $receipt = new ExtractedReceipt(date: '2026-07-30', liters: 20.35, priceTotal: 42.51);

        $this->fakeExtractor([$receipt]);
        $this->import([$this->pdf('juli.pdf')]);

        $this->fakeExtractor([$receipt]);
        $this->import([$this->pdf('juli.pdf')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 Beleg war schon erfasst'));

        $this->assertSame(1, $this->car->fuelings()->count());
    }

    public function test_it_refuses_more_than_a_sensible_batch(): void
    {
        $this->fakeExtractor([]);

        $this->import(array_map(fn ($i) => $this->pdf("beleg{$i}.pdf"), range(1, 25)))
            ->assertSessionHasErrors('receipts');

        $this->assertSame(0, $this->car->fuelings()->count());
    }

    public function test_a_stranger_cannot_import_into_someone_elses_car(): void
    {
        $this->fakeExtractor([new ExtractedReceipt(date: '2026-07-30', liters: 20.0, priceTotal: 40.0)]);

        $this->actingAs(User::factory()->create())
            ->post(route('fuelings.import', $this->car), ['receipts' => [$this->pdf('juli.pdf')]])
            ->assertForbidden();

        $this->assertSame(0, $this->car->fuelings()->count());
    }
}
