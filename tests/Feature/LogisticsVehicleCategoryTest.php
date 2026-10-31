<?php

namespace Tests\Feature;

use App\Support\LogisticsVehicleCategory;
use PHPUnit\Framework\TestCase;

class LogisticsVehicleCategoryTest extends TestCase
{
    public function test_vehicle_categories_are_explicit_and_unknown_types_are_ineligible(): void
    {
        $this->assertSame('two_wheel', LogisticsVehicleCategory::for('Motorcycle'));
        $this->assertSame('two_wheel', LogisticsVehicleCategory::for('scooter'));
        $this->assertSame('four_wheel', LogisticsVehicleCategory::for('delivery van'));
        $this->assertSame('four_wheel', LogisticsVehicleCategory::for('light delivery truck'));
        $this->assertNull(LogisticsVehicleCategory::for('unregistered vehicle'));
    }
}
