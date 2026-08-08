<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Receipts\ExtractedParkingSession;
use App\Services\Receipts\ParkingExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ParkingScanTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<ExtractedParkingSession>  $sessions */
    private function fakeExtractor(array $sessions, bool $available = true): void
    {
        $this->app->instance(ParkingExtractor::class, new class($sessions, $available) implements ParkingExtractor
        {
            public function __construct(private array $sessions, private bool $available) {}

            public function extract(UploadedFile $file): array
            {
                return $this->sessions;
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }
        });
    }

    private function scan()
    {
        return $this->actingAs(User::factory()->create())->post(
            route('receipts.scan.parking'),
            ['receipt' => UploadedFile::fake()->create('parkschein.jpg', 100, 'image/jpeg')],
        );
    }

    public function test_it_returns_the_values_a_single_ticket_carries(): void
    {
        $this->fakeExtractor([
            new ExtractedParkingSession('2026-08-05', 'Parkhaus Zentrum', 4.5, '11:30', '12:29'),
        ]);

        $this->scan()->assertOk()->assertJson([
            'date' => '2026-08-05',
            'location' => 'Parkhaus Zentrum',
            'cost' => 4.5,
            'start_time' => '11:30',
            'end_time' => '12:29',
            'sessions' => 1,
        ]);
    }

    /** The provider's charge is part of what the form should propose. */
    public function test_the_proposed_cost_is_what_was_charged(): void
    {
        $this->fakeExtractor([
            new ExtractedParkingSession('2026-08-05', 'Musterstadt', 0.98, fee: 0.55),
        ]);

        $this->scan()->assertOk()->assertJson(['cost' => 1.53]);
    }

    /**
     * Prefilling the form with the first of many would quietly drop the rest,
     * so the answer says how many there are and the page points at the import.
     */
    public function test_an_invoice_with_several_sessions_is_not_squeezed_into_the_form(): void
    {
        $this->fakeExtractor([
            new ExtractedParkingSession('2026-08-05', 'Musterstadt', 0.98),
            new ExtractedParkingSession('2026-08-12', 'Beispielstadt', 2.4),
        ]);

        $this->scan()->assertOk()
            ->assertJson(['sessions' => 2])
            ->assertJsonMissingPath('location');
    }

    public function test_an_unreadable_document_yields_empty_fields_rather_than_an_error(): void
    {
        $this->fakeExtractor([]);

        $this->scan()->assertOk()->assertJson([
            'date' => null,
            'location' => null,
            'cost' => null,
            'sessions' => 0,
        ]);
    }

    public function test_a_half_read_session_does_not_prefill_a_guess(): void
    {
        // Location came through, the amount did not - not enough to propose.
        $this->fakeExtractor([new ExtractedParkingSession('2026-08-05', 'Musterstadt')]);

        $this->scan()->assertOk()->assertJson(['location' => null, 'sessions' => 0]);
    }

    public function test_without_a_working_extractor_it_reports_unavailable(): void
    {
        $this->fakeExtractor([], available: false);

        $this->scan()->assertStatus(503);
    }

    public function test_a_guest_cannot_scan(): void
    {
        $this->fakeExtractor([]);

        $this->post(route('receipts.scan.parking'), [
            'receipt' => UploadedFile::fake()->create('parkschein.jpg', 100, 'image/jpeg'),
        ])->assertRedirect(route('login'));
    }
}
