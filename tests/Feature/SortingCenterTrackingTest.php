<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SortingCenterTrackingTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::create([
            'name' => 'Hub Admin',
            'email' => 'hub-admin-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function staff(int $centerId): User
    {
        return User::create([
            'name' => 'Hub Staff',
            'email' => 'hub-staff-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'staff',
            'center_id' => $centerId,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function center(string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name,
            'address' => 'Hub Address',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'is_active' => true,
        ]);
    }

    private function delivery(array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'status' => 'assigned',
            'parcel_status' => 'received',
            'tracking_number' => 'TRK-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2))),
            'sender_name' => 'Hub Sender',
            'recipient_name' => 'Hub Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
        ], $extra));
    }

    public function test_tracking_page_exposes_existing_parcel_milestones_without_changing_delivery_status(): void
    {
        $delivery = $this->delivery([
            'status' => 'out_for_delivery',
            'parcel_status' => 'scanned',
            'scanned_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('tracking.search', ['tracking_number' => $delivery->tracking_number]))
            ->assertOk()
            ->assertSee('Parcel Processing')
            ->assertSee('At Hub')
            ->assertSee('Scanned')
            ->assertSee('Out for Delivery')
            ->assertDontSee('Final Status');
    }

    public function test_duplicate_scan_is_idempotent_and_does_not_write_another_log(): void
    {
        $delivery = $this->delivery([
            'parcel_status' => 'scanned',
            'scanned_at' => now(),
        ]);

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status' => 'scanned',
            'notes' => 'Parcel scanned and verified.',
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('deliveries.scan-verify'), [
                'tracking_number' => $delivery->tracking_number,
            ])
            ->assertOk()
            ->assertJsonPath('already', true);

        $this->assertSame(
            1,
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'scanned')->count(),
        );
    }

    public function test_unknown_tracking_number_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('deliveries.scan-verify'), [
                'tracking_number' => 'TRK-'.now()->format('Ymd').'-ZZZZ',
            ])
            ->assertNotFound()
            ->assertJsonPath('error', 'not_found');
    }

    public function test_cross_center_scan_is_forbidden(): void
    {
        $centerA = $this->center('Center A');
        $centerB = $this->center('Center B');
        $delivery = $this->delivery(['center_id' => $centerB->id]);

        $this->actingAs($this->staff($centerA->id))
            ->postJson(route('deliveries.scan-verify'), [
                'tracking_number' => $delivery->tracking_number,
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_sorting_center_service_area_filter_is_applied_server_side(): void
    {
        $center = $this->center('Filter Center');
        $areaA = ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Area A',
            'is_active' => true,
        ]);
        $areaB = ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Area B',
            'is_active' => true,
        ]);

        $inAreaA = $this->delivery([
            'center_id' => $center->id,
            'service_area_id' => $areaA->id,
        ]);
        $inAreaB = $this->delivery([
            'center_id' => $center->id,
            'service_area_id' => $areaB->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('sorting-center.index', ['service_area_id' => $areaA->id]))
            ->assertOk()
            ->assertSee($inAreaA->tracking_number)
            ->assertDontSee($inAreaB->tracking_number);
    }

    public function test_active_transfer_parcels_are_linked_and_not_processed_from_the_origin_queue(): void
    {
        $company = LogisticsCompany::create(['name' => 'Sorting Transfer '.bin2hex(random_bytes(4)), 'status' => 'active']);
        $origin = $this->center('Transfer Origin');
        $destination = $this->center('Transfer Destination');
        $origin->update(['logistics_company_id' => $company->id]);
        $destination->update(['logistics_company_id' => $company->id]);
        $transfer = HubTransfer::create([
            'reference' => 'HT-SORT-'.strtoupper(bin2hex(random_bytes(3))),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'status' => 'in_transit',
        ]);
        $delivery = $this->delivery([
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'parcel_status' => 'received',
            'received_at' => now(),
        ]);
        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->get(route('sorting-center.index', ['tab' => 'received', 'search' => $delivery->tracking_number]))
            ->assertOk()
            ->assertSee($transfer->reference)
            ->assertDontSee('Logistics Hub Staff verify the received parcel against its records.');
    }

    public function test_destination_filter_with_three_different_destinations_displays_only_matching_parcels(): void
    {
        $originHub = $this->center('Origin Hub');
        $destinationManila = $this->center('Destination Manila');
        $destinationCebu = $this->center('Destination Cebu');
        $destinationDavao = $this->center('Destination Davao');
        $destinationEmpty = $this->center('Destination Empty');

        $parcelManila = $this->delivery([
            'center_id' => $originHub->id,
            'destination_center_id' => $destinationManila->id,
            'parcel_status' => 'sorted',
        ]);
        $parcelCebu = $this->delivery([
            'center_id' => $originHub->id,
            'destination_center_id' => $destinationCebu->id,
            'parcel_status' => 'sorted',
        ]);
        $parcelDavao = $this->delivery([
            'center_id' => $originHub->id,
            'destination_center_id' => $destinationDavao->id,
            'parcel_status' => 'sorted',
        ]);
        $parcelUnassigned = $this->delivery([
            'center_id' => $originHub->id,
            'destination_center_id' => null,
            'parcel_status' => 'scanned',
        ]);

        $admin = $this->admin();

        // 1. When filter is cleared or set to All Destinations, display all applicable parcels
        $this->actingAs($admin)
            ->get(route('sorting-center.index', ['tab' => 'all']))
            ->assertOk()
            ->assertSee('All Destinations')
            ->assertSee($parcelManila->tracking_number)
            ->assertSee($parcelCebu->tracking_number)
            ->assertSee($parcelDavao->tracking_number)
            ->assertSee($parcelUnassigned->tracking_number);

        // 2. Select Destination Manila: display only Manila parcel, hide others
        $this->actingAs($admin)
            ->get(route('sorting-center.index', ['tab' => 'all', 'destination_center_id' => $destinationManila->id]))
            ->assertOk()
            ->assertSee($parcelManila->tracking_number)
            ->assertDontSee($parcelCebu->tracking_number)
            ->assertDontSee($parcelDavao->tracking_number)
            ->assertDontSee($parcelUnassigned->tracking_number);

        // 3. Select Destination Cebu: display only Cebu parcel, hide others
        $this->actingAs($admin)
            ->get(route('sorting-center.index', ['tab' => 'all', 'destination_center_id' => $destinationCebu->id]))
            ->assertOk()
            ->assertSee($parcelCebu->tracking_number)
            ->assertDontSee($parcelManila->tracking_number)
            ->assertDontSee($parcelDavao->tracking_number)
            ->assertDontSee($parcelUnassigned->tracking_number);

        // 4. Select Destination Davao: display only Davao parcel, hide others
        $this->actingAs($admin)
            ->get(route('sorting-center.index', ['tab' => 'all', 'destination_center_id' => $destinationDavao->id]))
            ->assertOk()
            ->assertSee($parcelDavao->tracking_number)
            ->assertDontSee($parcelManila->tracking_number)
            ->assertDontSee($parcelCebu->tracking_number)
            ->assertDontSee($parcelUnassigned->tracking_number);

        // 5. Select Destination with no parcels: display empty-state message
        $this->actingAs($admin)
            ->get(route('sorting-center.index', ['tab' => 'all', 'destination_center_id' => $destinationEmpty->id]))
            ->assertOk()
            ->assertSee('No parcels found for this destination')
            ->assertDontSee($parcelManila->tracking_number)
            ->assertDontSee($parcelCebu->tracking_number)
            ->assertDontSee($parcelDavao->tracking_number);

        // 6. Test with center staff at Origin Hub
        $staff = $this->staff($originHub->id);

        $this->actingAs($staff)
            ->get(route('sorting-center.index', ['tab' => 'all', 'destination_center_id' => $destinationCebu->id]))
            ->assertOk()
            ->assertSee($parcelCebu->tracking_number)
            ->assertDontSee($parcelManila->tracking_number)
            ->assertDontSee($parcelDavao->tracking_number);
    }

    public function test_incoming_tab_destination_filter_isolates_incoming_manifest_destinations(): void
    {
        $company = LogisticsCompany::create(['name' => 'Incoming Filter Company '.bin2hex(random_bytes(4)), 'status' => 'active']);
        $originHub = $this->center('Origin Hub Inbound');
        $destA = $this->center('Destination Hub A');
        $destB = $this->center('Destination Hub B');
        $destC = $this->center('Destination Hub C');
        $originHub->update(['logistics_company_id' => $company->id]);
        $destA->update(['logistics_company_id' => $company->id]);
        $destB->update(['logistics_company_id' => $company->id]);
        $destC->update(['logistics_company_id' => $company->id]);

        $transferA = HubTransfer::create([
            'reference' => 'HT-IN-A-'.strtoupper(bin2hex(random_bytes(3))),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $originHub->id,
            'destination_center_id' => $destA->id,
            'status' => 'in_transit',
        ]);
        $transferB = HubTransfer::create([
            'reference' => 'HT-IN-B-'.strtoupper(bin2hex(random_bytes(3))),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $originHub->id,
            'destination_center_id' => $destB->id,
            'status' => 'in_transit',
        ]);
        $transferC = HubTransfer::create([
            'reference' => 'HT-IN-C-'.strtoupper(bin2hex(random_bytes(3))),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $originHub->id,
            'destination_center_id' => $destC->id,
            'status' => 'in_transit',
        ]);

        $deliveryA = $this->delivery(['center_id' => $originHub->id, 'destination_center_id' => $destA->id]);
        $deliveryB = $this->delivery(['center_id' => $originHub->id, 'destination_center_id' => $destB->id]);
        $deliveryC = $this->delivery(['center_id' => $originHub->id, 'destination_center_id' => $destC->id]);

        HubTransferParcel::create(['hub_transfer_id' => $transferA->id, 'delivery_id' => $deliveryA->id, 'loading_status' => 'loaded', 'receiving_status' => 'pending']);
        HubTransferParcel::create(['hub_transfer_id' => $transferB->id, 'delivery_id' => $deliveryB->id, 'loading_status' => 'loaded', 'receiving_status' => 'pending']);
        HubTransferParcel::create(['hub_transfer_id' => $transferC->id, 'delivery_id' => $deliveryC->id, 'loading_status' => 'loaded', 'receiving_status' => 'pending']);

        $admin = $this->admin();

        // 1. All destinations shows all 3 incoming parcels
        $this->actingAs($admin)
            ->get(route('sorting-center.index', ['tab' => 'incoming']))
            ->assertOk()
            ->assertSee($deliveryA->tracking_number)
            ->assertSee($deliveryB->tracking_number)
            ->assertSee($deliveryC->tracking_number);

        // 2. Select Destination A: only A appears
        $this->actingAs($admin)
            ->get(route('sorting-center.index', ['tab' => 'incoming', 'destination_center_id' => $destA->id]))
            ->assertOk()
            ->assertSee($deliveryA->tracking_number)
            ->assertDontSee($deliveryB->tracking_number)
            ->assertDontSee($deliveryC->tracking_number);

        // 3. Select Destination B: only B appears
        $this->actingAs($admin)
            ->get(route('sorting-center.index', ['tab' => 'incoming', 'destination_center_id' => $destB->id]))
            ->assertOk()
            ->assertSee($deliveryB->tracking_number)
            ->assertDontSee($deliveryA->tracking_number)
            ->assertDontSee($deliveryC->tracking_number);

        // 4. Select Destination C: only C appears
        $this->actingAs($admin)
            ->get(route('sorting-center.index', ['tab' => 'incoming', 'destination_center_id' => $destC->id]))
            ->assertOk()
            ->assertSee($deliveryC->tracking_number)
            ->assertDontSee($deliveryA->tracking_number)
            ->assertDontSee($deliveryB->tracking_number);
    }
}
