<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Fueling;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditFuelingTest extends TestCase
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
            'initial_odometer' => 1000,
        ]);
    }

    private function log(array $attributes): Fueling
    {
        $this->actingAs($this->user)
            ->post(route('fuelings.store', $this->car), $attributes + [
                'date' => '2026-07-30',
                'liters' => 20.0,
                'price_total' => 40.0,
            ]);

        return $this->car->fuelings()->latest('id')->first();
    }

    private function revise(Fueling $fueling, array $attributes)
    {
        return $this->actingAs($this->user)->patch(route('fuelings.update', $fueling), $attributes + [
            'date' => \Carbon\Carbon::parse($fueling->date)->format('Y-m-d'),
            'liters' => $fueling->liters,
            'price_total' => $fueling->price_total,
        ]);
    }

    public function test_the_edit_form_shows_the_distance_rather_than_the_reading(): void
    {
        // 1000 + 550 = 1550, but the user entered 550.
        $fueling = $this->log(['trip_km' => 550]);

        $this->actingAs($this->user)
            ->get(route('fuelings.edit', $fueling))
            ->assertOk()
            ->assertViewHas('tripKm', 550.0);
    }

    public function test_correcting_the_distance_updates_the_reading(): void
    {
        $fueling = $this->log(['trip_km' => 550]);

        $this->revise($fueling, ['trip_km' => 250])->assertRedirect();

        $this->assertSame(1250, $fueling->fresh()->odometer_reading);
    }

    public function test_later_entries_keep_their_own_distances(): void
    {
        $first = $this->log(['date' => '2026-07-30', 'trip_km' => 550]);
        $second = $this->log(['date' => '2026-08-10', 'trip_km' => 300]);
        $third = $this->log(['date' => '2026-08-20', 'trip_km' => 200]);

        $this->assertSame([1550, 1850, 2050], [
            $first->fresh()->odometer_reading,
            $second->fresh()->odometer_reading,
            $third->fresh()->odometer_reading,
        ]);

        // The mistake: it was 250 km, not 550.
        $this->revise($first, ['trip_km' => 250]);

        // Everything shifts back by 300, and the later trips stay 300 and 200.
        $this->assertSame([1250, 1550, 1750], [
            $first->fresh()->odometer_reading,
            $second->fresh()->odometer_reading,
            $third->fresh()->odometer_reading,
        ]);
    }

    public function test_the_distance_can_be_removed_altogether(): void
    {
        $first = $this->log(['date' => '2026-07-30', 'trip_km' => 550]);
        $second = $this->log(['date' => '2026-08-10', 'trip_km' => 300]);

        $this->revise($first, ['trip_km' => null]);

        $this->assertNull($first->fresh()->odometer_reading);
        // The later entry falls back onto the initial odometer plus its own trip.
        $this->assertSame(1300, $second->fresh()->odometer_reading);
    }

    public function test_a_distance_can_be_supplied_after_the_fact(): void
    {
        $fueling = $this->log([]);
        $this->assertNull($fueling->odometer_reading);

        $this->revise($fueling, ['trip_km' => 420]);

        $this->assertSame(1420, $fueling->fresh()->odometer_reading);
    }

    public function test_the_other_figures_can_be_corrected_too(): void
    {
        $fueling = $this->log(['trip_km' => 550]);

        $this->revise($fueling, ['liters' => 20.35, 'price_total' => 42.51, 'trip_km' => 550]);

        $fueling->refresh();
        $this->assertEqualsWithDelta(20.35, $fueling->liters, 0.001);
        $this->assertEqualsWithDelta(42.51, $fueling->price_total, 0.001);
        $this->assertSame(2.089, $fueling->price_per_liter);
    }

    public function test_it_validates_the_same_rules_as_creating(): void
    {
        $fueling = $this->log(['trip_km' => 550]);

        $this->revise($fueling, ['liters' => 0])->assertSessionHasErrors('liters');

        $this->assertSame(1550, $fueling->fresh()->odometer_reading);
    }

    public function test_a_stranger_cannot_edit_someone_elses_fueling(): void
    {
        $fueling = $this->log(['trip_km' => 550]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('fuelings.edit', $fueling))->assertForbidden();
        $this->actingAs($stranger)->patch(route('fuelings.update', $fueling), [
            'date' => '2026-07-30', 'liters' => 1, 'price_total' => 1, 'trip_km' => 1,
        ])->assertForbidden();

        $this->assertSame(1550, $fueling->fresh()->odometer_reading);
    }

    public function test_guests_cannot_edit(): void
    {
        // Created directly: logging it over HTTP would authenticate this test.
        $fueling = $this->car->fuelings()->create([
            'date' => '2026-07-30', 'liters' => 20.0, 'price_total' => 40.0, 'odometer_reading' => 1550,
        ]);

        $this->get(route('fuelings.edit', $fueling))->assertRedirect(route('login'));
        $this->patch(route('fuelings.update', $fueling), [
            'date' => '2026-07-30', 'liters' => 1, 'price_total' => 1, 'trip_km' => 1,
        ])->assertRedirect(route('login'));

        $this->assertSame(1550, $fueling->fresh()->odometer_reading);
    }
}
