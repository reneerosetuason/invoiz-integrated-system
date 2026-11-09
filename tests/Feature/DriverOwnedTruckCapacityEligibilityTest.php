<?php

namespace Tests\Feature;

use App\Models\LogisticsTruck;
use Tests\TestCase;

/**
 * Driver-owned truck capacity eligibility.
 *
 * No parcel-capacity default exists in the business rules, so a legacy truck
 * whose parcel capacity is missing or non-positive stays unassignable. These
 * assertions are attribute-only (no database access).
 */
class DriverOwnedTruckCapacityEligibilityTest extends TestCase
{
    private function truck(array $overrides = []): LogisticsTruck
    {
        return new LogisticsTruck(array_merge([
            'ownership_type' => LogisticsTruck::OWNERSHIP_DRIVER,
            'vehicle_type' => 'light_truck',
            'status' => LogisticsTruck::STATUS_AVAILABLE,
            'verification_status' => LogisticsTruck::VERIFICATION_APPROVED,
            'parcel_capacity' => 20,
            'capacity_kg' => 1000.00,
        ], $overrides));
    }

    public function test_valid_driver_owned_capacity_is_unchanged_and_assignable(): void
    {
        $truck = $this->truck();

        $this->assertTrue($truck->isAssignable());
        $this->assertSame(20, $truck->parcel_capacity);
        $this->assertSame('1000.00', (string) $truck->capacity_kg);
    }

    public function test_missing_legacy_parcel_capacity_is_not_assignable(): void
    {
        $this->assertFalse($this->truck(['parcel_capacity' => null])->isAssignable());
    }

    public function test_weight_only_default_does_not_make_a_missing_parcel_capacity_eligible(): void
    {
        // The provisioner falls back to 1000 kg of *weight*; that value can
        // never stand in for a parcel count.
        $truck = $this->truck(['parcel_capacity' => null, 'capacity_kg' => 1000.00]);

        $this->assertFalse($truck->isAssignable());
    }

    public function test_zero_or_negative_parcel_capacity_is_not_assignable(): void
    {
        $this->assertFalse($this->truck(['parcel_capacity' => 0])->isAssignable());
        $this->assertFalse($this->truck(['parcel_capacity' => -5])->isAssignable());
    }

    public function test_missing_or_non_positive_weight_limit_is_not_assignable(): void
    {
        $this->assertFalse($this->truck(['capacity_kg' => null])->isAssignable());
        $this->assertFalse($this->truck(['capacity_kg' => 0])->isAssignable());
        $this->assertFalse($this->truck(['capacity_kg' => -10])->isAssignable());
    }

    public function test_unapproved_or_unsupported_vehicles_are_not_assignable(): void
    {
        $this->assertFalse($this->truck(['verification_status' => LogisticsTruck::VERIFICATION_PENDING])->isAssignable());
        $this->assertFalse($this->truck(['verification_status' => LogisticsTruck::VERIFICATION_REJECTED])->isAssignable());
        $this->assertFalse($this->truck(['vehicle_type' => 'motorcycle'])->isAssignable());
        $this->assertFalse($this->truck(['status' => LogisticsTruck::STATUS_MAINTENANCE])->isAssignable());
    }
}
