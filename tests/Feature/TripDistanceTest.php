<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripDistanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->car = $this->user->cars()->create([
            'brand' => 'VW',
            'model' => 'Polo',
            'year' => 2010,
            'license_plate' => 'GL-MS 141',
            'initial_odometer' => 0,
        ]);
    }

    private function log(array $attributes): void
    {
        $this->actingAs($this->user)->post(route('fuelings.store', $this->car), $attributes + [
            'liters' => 20.0,
            'price_total' => 40.0,
        ]);
    }

    public function test_the_history_shows_what_was_entered_not_the_running_total(): void
    {
        // Logged in July, then the June receipt was filed afterwards. July's
        // reading becomes 550 - but 200 is what was driven that month.
        $this->log(['date' => '2026-07-30', 'trip_km' => 200]);
        $this->log(['date' => '2026-06-28', 'trip_km' => 350]);

        $july = $this->car->fuelings()->where('date', '2026-07-30')->sole();
        $june = $this->car->fuelings()->where('date', '2026-06-28')->sole();

        $this->assertSame(550, $july->fresh()->odometer_reading);

        $trips = $this->car->fresh()->tripDistances();

        $this->assertSame(350.0, $trips[$june->id]);
        $this->assertSame(200.0, $trips[$july->id]);
    }

    public function test_an_entry_without_a_distance_has_none_to_show(): void
    {
        $this->log(['date' => '2026-07-30']);

        $fueling = $this->car->fuelings()->sole();

        $this->assertNull($this->car->fresh()->tripDistances()[$fueling->id]);
    }

    public function test_the_distances_reach_the_view(): void
    {
        $this->log(['date' => '2026-07-30', 'trip_km' => 200]);
        $this->log(['date' => '2026-06-28', 'trip_km' => 350]);

        $response = $this->actingAs($this->user)->get(route('cars.show', $this->car))->assertOk();

        $this->assertSame([350.0, 200.0], array_values($response->viewData('tripDistances')));
        $response->assertSee('Strecke');
    }
}
