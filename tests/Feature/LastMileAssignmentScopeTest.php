<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Rider;
use App\Models\ServiceArea;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class LastMileAssignmentScopeTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Schema::create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->integer('logistics_center_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function parcel(array $attributes = []): Delivery
    {
        return new Delivery(array_merge([
            'order_id' => 1, 'center_id' => 11, 'destination_center_id' => 11,
            'service_area_id' => 7, 'recipient_address' => 'Maytalang I, Lumban, Laguna',
            'received_at' => now(), 'parcel_status' => 'sorted',
        ], $attributes));
    }

    public function test_duplicate_area_names_cannot_override_reviewed_destination_after_sorting(): void
    {
        ServiceArea::create(['id' => 5, 'logistics_center_id' => 8, 'name' => 'Lumban', 'is_active' => true]);
        $destination = ServiceArea::create(['logistics_center_id' => 11, 'name' => 'Lumban', 'is_active' => true]);
        $delivery = $this->parcel(['service_area_id' => $destination->id]);
        $this->assertSame(8, $delivery->suggestedDestination()['center_id']);
        $this->assertSame(['stage' => 'last_mile', 'center_id' => 11, 'service_area_id' => $destination->id], $delivery->riderAssignmentScope());
        $local = new Rider(['center_id' => 11, 'service_area_id' => $destination->id]);
        $other = new Rider(['center_id' => 8, 'service_area_id' => 5]);
        $this->assertTrue($local->matchesDestinationCenter($delivery));
        $this->assertTrue($local->matchesServiceArea($delivery));
        $this->assertFalse($other->matchesDestinationCenter($delivery));
        $this->assertFalse($other->matchesServiceArea($delivery));
    }

    public function test_intermediate_hub_stays_blocked_even_after_receiving_and_sorting(): void
    {
        $this->assertSame('hub_processing', $this->parcel(['center_id' => 8])->riderAssignmentStage());
    }

    public function test_completed_sorting_at_intermediate_hub_explains_missing_transfer(): void
    {
        $delivery = $this->parcel(['center_id' => 8, 'sorted_at' => now()]);
        $this->assertSame('hub_processing', $delivery->riderAssignmentScope()['stage']);
        $this->assertSame(
            'Parcel is at an intermediate hub and still needs transfer to its final destination hub before last-mile assignment.',
            $delivery->riderAssignmentBlockReason(),
        );
    }

    public function test_destination_processing_message_identifies_the_remaining_step(): void
    {
        $this->assertSame(
            'Parcel must be scanned and sorted at its destination hub before last-mile assignment.',
            $this->parcel(['parcel_status' => 'received'])->riderAssignmentBlockReason(),
        );
        $this->assertSame(
            'Parcel must be sorted at its destination hub before last-mile assignment.',
            $this->parcel(['parcel_status' => 'scanned'])->riderAssignmentBlockReason(),
        );
        $this->assertNull($this->parcel()->riderAssignmentBlockReason());
        $this->assertNull($this->parcel(['parcel_status' => 'dispatched'])->riderAssignmentBlockReason());
        $this->assertNull($this->parcel(['received_at' => null, 'parcel_status' => 'pending_arrival'])->riderAssignmentBlockReason());
    }

    public function test_receiving_alone_does_not_skip_destination_scan_and_sort(): void
    {
        foreach (['received', 'scanned'] as $status) {
            $this->assertSame('hub_processing', $this->parcel(['parcel_status' => $status])->riderAssignmentStage());
        }
        $this->assertSame('last_mile', $this->parcel()->riderAssignmentStage());
    }

    public function test_area_from_other_hub_is_replaced_only_with_a_match_inside_destination(): void
    {
        $wrong = ServiceArea::create(['logistics_center_id' => 8, 'name' => 'Lumban', 'is_active' => true]);
        $right = ServiceArea::create(['logistics_center_id' => 11, 'name' => 'Lumban', 'is_active' => true]);
        $this->assertSame($right->id, $this->parcel(['service_area_id' => $wrong->id])->finalDestinationScope()['service_area_id']);
    }

    public function test_address_matching_remains_available_for_unrouted_legacy_parcels(): void
    {
        $area = ServiceArea::create(['logistics_center_id' => 8, 'name' => 'Lumban', 'is_active' => true]);
        $this->assertSame(['center_id' => 8, 'service_area_id' => $area->id], $this->parcel(['destination_center_id' => null])->finalDestinationScope());
    }
}
