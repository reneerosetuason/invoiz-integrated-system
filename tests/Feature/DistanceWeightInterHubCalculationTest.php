<?php

namespace Tests\Feature;

use App\Services\ParcelInterHubPricing;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DistanceWeightInterHubCalculationTest extends TestCase
{
    private function quote(float $distance = 91.972, float $weight = 1, int $count = 1, array $size = []): array
    {
        $truck = ['capacity_kg' => 1500, 'parcel_capacity' => 120, 'cargo_length_cm' => 300, 'cargo_width_cm' => 160, 'cargo_height_cm' => 160];
        $rates = ['trip_base_payment' => 100, 'trip_rate_per_km' => 5, 'trip_bonus_50' => 50, 'trip_bonus_80' => 100,
            'trip_volumetric_divisor' => 5000, 'inter_hub_handling_fee' => 20, 'inter_hub_rate_per_km' => 0.20, 'inter_hub_rate_per_kg_km' => 0.04];
        $parcels = [];
        for ($i = 1; $i <= $count; $i++) {
            $parcels[] = array_replace(['id' => $i, 'weight' => $weight, 'length_cm' => 30, 'width_cm' => 25, 'height_cm' => 2], $size);
        }

        return app(ParcelInterHubPricing::class)->calculate($truck, $parcels, $distance, $rates);
    }

    public function test_agreed_example_and_twenty_parcels_pay_the_exact_reviewed_amount(): void
    {
        $one = $this->quote();
        $this->assertSame(4207, $one['total']);
        $this->assertSame([1 => 4207], $one['allocations']);
        $this->assertSame($one['allocations'], $one['seller_budgets']);
        $twenty = $this->quote(count: 20);
        $this->assertSame(84140, $twenty['total']);
        $this->assertSame(84140, array_sum($twenty['allocations']));
        $this->assertSame(array_fill(1, 20, 4207), $twenty['allocations']);
        $this->assertSame(0, $twenty['platform_adjustment_cents']);
    }

    public function test_distance_and_actual_weight_independently_change_the_actual_payout(): void
    {
        $this->assertSame(2240, $this->quote(10, 1)['total']);
        $this->assertSame(2480, $this->quote(20, 1)['total']);
        $this->assertSame(2400, $this->quote(10, 5)['total']);
    }

    public function test_dimensions_are_ignored_by_the_distance_and_weight_fee(): void
    {
        $this->assertSame(4207, $this->quote(size: ['length_cm' => 300, 'width_cm' => 160, 'height_cm' => 160])['total']);
        $this->assertSame(4207, $this->quote(size: ['length_cm' => 1000, 'width_cm' => null, 'height_cm' => null])['total']);
    }

    public function test_components_reconcile_when_fractional_centavos_round_up(): void
    {
        $quote = $this->quote(0.01, 10);
        $this->assertSame(2001, $quote['total']);
        $this->assertSame($quote['total'], $quote['base'] + $quote['distancePayment'] + $quote['weightPayment']);
    }

    public function test_weight_capacity_remains_enforced_without_dimensions(): void
    {
        $this->expectException(ValidationException::class);
        $this->quote(weight: 1501);
    }
}
