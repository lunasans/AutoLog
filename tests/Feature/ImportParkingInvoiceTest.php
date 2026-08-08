<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use App\Services\Receipts\ExtractedParkingSession;
use App\Services\Receipts\ParkingExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportParkingInvoiceTest extends TestCase
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

    /** Returns a list of sessions per uploaded file, in order. */
    private function fakeExtractor(array $results): void
    {
        $this->app->instance(ParkingExtractor::class, new class($results) implements ParkingExtractor
        {
            private int $calls = 0;

            public function __construct(private array $results) {}

            public function extract(UploadedFile $file): array
            {
                return $this->results[$this->calls++] ?? [];
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
            ->post(route('parking-tickets.import', $this->car), ['receipts' => $files]);
    }

    public function test_one_invoice_becomes_one_entry_per_parking_session(): void
    {
        $this->fakeExtractor([[
            new ExtractedParkingSession('2026-07-03', 'Wien Zone 1', 2.4, '08:15', '10:30'),
            new ExtractedParkingSession('2026-07-11', 'Graz Zentrum', 1.8),
            new ExtractedParkingSession('2026-07-19', 'Wien Zone 1', 3.6, '17:00', '19:00'),
        ]]);

        $this->import([$this->pdf('easypark-juli.pdf')])
            ->assertRedirect(route('cars.show', $this->car))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '3 Parkvorgänge angelegt'));

        $this->assertSame(3, $this->car->parkingTickets()->count());

        $first = $this->car->parkingTickets()->orderBy('date')->first();
        $this->assertSame('Wien Zone 1', $first->location);
        $this->assertSame('08:15 – 10:30', $first->parked_period);
    }

    public function test_the_invoice_is_filed_with_every_entry_it_produced(): void
    {
        $this->fakeExtractor([[
            new ExtractedParkingSession('2026-07-03', 'Wien Zone 1', 2.4),
            new ExtractedParkingSession('2026-07-11', 'Graz Zentrum', 1.8),
        ]]);

        $this->import([$this->pdf('easypark-juli.pdf')]);

        foreach ($this->car->parkingTickets as $ticket) {
            $this->assertSame('easypark-juli.pdf', $ticket->receipt_name);
            Storage::disk('local')->assertExists($ticket->receipt_path);
        }

        // Each entry keeps its own copy, so deleting one leaves the other's
        // proof intact.
        $tickets = $this->car->parkingTickets()->orderBy('id')->get();
        $this->assertNotSame($tickets[0]->receipt_path, $tickets[1]->receipt_path);
    }

    public function test_a_single_ticket_yields_a_single_entry(): void
    {
        $this->fakeExtractor([[new ExtractedParkingSession('2026-07-03', 'Parkhaus Zentrum', 4.5)]]);

        $this->import([$this->pdf('parkschein.pdf')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 Parkvorgang angelegt'));

        $this->assertSame(1, $this->car->parkingTickets()->count());
    }

    public function test_an_incomplete_session_is_left_out(): void
    {
        $this->fakeExtractor([[
            new ExtractedParkingSession('2026-07-03', 'Wien Zone 1', 2.4),
            // No amount - nothing to book.
            new ExtractedParkingSession('2026-07-11', 'Graz Zentrum'),
        ]]);

        $this->import([$this->pdf('easypark-juli.pdf')]);

        $this->assertSame(1, $this->car->parkingTickets()->count());
    }

    public function test_an_unreadable_document_is_reported_rather_than_guessed_at(): void
    {
        $this->fakeExtractor([
            [new ExtractedParkingSession('2026-07-03', 'Wien Zone 1', 2.4)],
            [],
        ]);

        $this->import([$this->pdf('easypark-juli.pdf'), $this->pdf('unleserlich.pdf')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'nicht lesbar: unleserlich.pdf'));

        $this->assertSame(1, $this->car->parkingTickets()->count());
    }

    public function test_importing_the_same_invoice_twice_does_not_duplicate_it(): void
    {
        $sessions = [[
            new ExtractedParkingSession('2026-07-03', 'Wien Zone 1', 2.4),
            new ExtractedParkingSession('2026-07-11', 'Graz Zentrum', 1.8),
        ]];

        $this->fakeExtractor($sessions);
        $this->import([$this->pdf('easypark-juli.pdf')]);

        $this->fakeExtractor($sessions);
        $this->import([$this->pdf('easypark-juli.pdf')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 Parkvorgänge waren schon erfasst'));

        $this->assertSame(2, $this->car->parkingTickets()->count());
    }

    public function test_two_stays_in_the_same_spot_on_one_day_are_not_mistaken_for_a_duplicate(): void
    {
        $this->fakeExtractor([[
            new ExtractedParkingSession('2026-07-03', 'Wien Zone 1', 2.4, '08:00', '10:00'),
            new ExtractedParkingSession('2026-07-03', 'Wien Zone 1', 3.6, '17:00', '19:00'),
        ]]);

        $this->import([$this->pdf('easypark-juli.pdf')]);

        $this->assertSame(2, $this->car->parkingTickets()->count());
    }

    public function test_it_refuses_more_than_a_sensible_batch(): void
    {
        $this->fakeExtractor([]);

        $this->import(array_map(fn ($i) => $this->pdf("rechnung{$i}.pdf"), range(1, 13)))
            ->assertSessionHasErrors('receipts');

        $this->assertSame(0, $this->car->parkingTickets()->count());
    }

    public function test_a_stranger_cannot_import_into_someone_elses_car(): void
    {
        $this->fakeExtractor([[new ExtractedParkingSession('2026-07-03', 'Wien Zone 1', 2.4)]]);

        $this->actingAs(User::factory()->create())
            ->post(route('parking-tickets.import', $this->car), ['receipts' => [$this->pdf('easypark-juli.pdf')]])
            ->assertForbidden();

        $this->assertSame(0, $this->car->parkingTickets()->count());
    }

    public function test_without_a_working_extractor_the_import_is_off(): void
    {
        $this->app->instance(ParkingExtractor::class, new class implements ParkingExtractor
        {
            public function extract(UploadedFile $file): array
            {
                return [];
            }

            public function isAvailable(): bool
            {
                return false;
            }
        });

        $this->import([$this->pdf('easypark-juli.pdf')])->assertNotFound();

        $this->assertSame(0, $this->car->parkingTickets()->count());
    }

    /**
     * The provider bills its handling charge apart from the parking fee. What
     * the entry has to show is what was actually debited.
     */
    public function test_the_provider_charge_is_part_of_what_the_entry_costs(): void
    {
        $this->fakeExtractor([[
            new ExtractedParkingSession('2026-08-05', 'Bergisch Gladbach, Tarif II', 0.98, '11:30', '12:29', fee: 0.55),
        ]]);

        $this->import([$this->pdf('easypark-august.pdf')]);

        $this->assertEqualsWithDelta(1.53, $this->car->parkingTickets()->sole()->cost, 0.001);
    }

    public function test_a_session_of_another_car_is_reported_instead_of_imported(): void
    {
        $this->fakeExtractor([[
            // The plate is printed without separators; the car carries them.
            new ExtractedParkingSession('2026-08-05', 'Bergisch Gladbach', 0.98, licensePlate: 'GLMS141'),
            new ExtractedParkingSession('2026-08-06', 'Köln', 2.4, licensePlate: 'K-XY 999'),
        ]]);

        $this->import([$this->pdf('easypark-august.pdf')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 Parkvorgang gehört zu einem anderen Kennzeichen'));

        $this->assertSame('Bergisch Gladbach', $this->car->parkingTickets()->sole()->location);
    }

    public function test_a_document_naming_no_plate_is_taken_at_face_value(): void
    {
        $this->fakeExtractor([[new ExtractedParkingSession('2026-08-05', 'Parkhaus Zentrum', 4.5)]]);

        $this->import([$this->pdf('parkschein.pdf')]);

        $this->assertSame(1, $this->car->parkingTickets()->count());
    }
}
