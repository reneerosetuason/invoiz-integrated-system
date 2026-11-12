<?php

namespace Tests\Feature;

use App\Http\Middleware\Logistics\EnsureParcelOperator;
use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Management accounts (Platform Administrator, designated global Logistics
 * Admin, company manager) keep read-only parcel monitoring and management
 * pages, but must never perform physical counter operations. Operational
 * logistics staff assigned to a hub retain every parcel operation, scoped to
 * their own facility.
 *
 * Runs on a self-contained in-memory SQLite schema, so no MySQL instance is
 * touched and no migrator runs.
 */
class LogisticsRoleSeparationTest extends TestCase
{
    private LogisticsCompany $company;
    private LogisticsCenter $origin;
    private LogisticsCenter $destination;
    private ServiceArea $originArea;
    private ServiceArea $destinationArea;

    private User $logisticsAdmin;
    private User $platformAdmin;
    private User $companyManager;
    private User $originStaff;
    private User $destinationStaff;
    private User $riderUser;

    private static int $trackingSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::getDefaultConnection());

        $this->createSchema();
        $this->seedFacilities();
    }

    // ------------------------------------------------------------------
    // 1-5, 7. Management cannot perform any physical parcel operation
    // ------------------------------------------------------------------

    public function test_global_logistics_admin_cannot_process_parcels(): void
    {
        $delivery = $this->standaloneDelivery($this->origin, 'received');
        $pickup = $this->pendingPickup($this->origin);
        [$transfer, $entry] = $this->hubTransfer();

        $this->actingAs($this->logisticsAdmin);

        $this->assertForbidden(fn () => $this->get(route('deliveries.scan-page')));
        $this->assertForbidden(fn () => $this->post(route('deliveries.scan-lookup'), [
            'tracking_number' => $delivery->tracking_number,
        ]));
        $this->assertForbidden(fn () => $this->post(route('deliveries.scan-verify'), [
            'tracking_number' => $delivery->tracking_number,
        ]));
        $this->assertForbidden(fn () => $this->patch(route('deliveries.scan', $delivery)));
        $this->assertForbidden(fn () => $this->post(route('deliveries.receive', $delivery)));
        $this->assertForbidden(fn () => $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $this->destination->id,
            'service_area_id' => $this->destinationArea->id,
        ]));
        $this->assertForbidden(fn () => $this->post(route('deliveries.dispatch', $delivery)));
        $this->assertForbidden(fn () => $this->post(route('deliveries.exception', $delivery), [
            'reason' => 'Damaged in transit',
        ]));
        $this->assertForbidden(fn () => $this->post(route('deliveries.return-to-seller', $delivery)));

        // Seller pickup processing.
        $this->assertForbidden(fn () => $this->post(route('pickup-requests.approve', $pickup)));
        $this->assertForbidden(fn () => $this->post(route('pickup-requests.reject', $pickup), [
            'rejection_reason' => 'Cannot collect',
        ]));

        // Hub transfer manifest operations.
        $this->assertForbidden(fn () => $this->get(route('hub-transfers.create')));
        $this->assertForbidden(fn () => $this->post(route('hub-transfers.store'), []));
        $this->assertForbidden(fn () => $this->post(route('hub-transfers.load', $transfer), [
            'tracking_number' => $delivery->tracking_number,
        ]));
        $this->assertForbidden(fn () => $this->delete(route('hub-transfers.parcels.remove', [
            'hubTransfer' => $transfer,
            'parcel' => $entry,
        ]), ['reason' => 'Wrong truck']));
        $this->assertForbidden(fn () => $this->post(route('hub-transfers.dispatch', $transfer)));
        $this->assertForbidden(fn () => $this->post(route('hub-transfers.arrive', $transfer)));
        $this->assertForbidden(fn () => $this->post(route('hub-transfers.receive', $transfer), [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'good',
        ]));
        $this->assertForbidden(fn () => $this->post(route('hub-transfers.complete', $transfer)));
    }

    public function test_platform_administrator_and_company_manager_are_read_only_for_parcels(): void
    {
        $delivery = $this->standaloneDelivery($this->origin, 'received');
        $pickup = $this->pendingPickup($this->origin);
        [$transfer] = $this->hubTransfer();

        foreach ([$this->platformAdmin, $this->companyManager] as $manager) {
            $this->actingAs($manager);

            $this->assertForbidden(fn () => $this->get(route('deliveries.scan-page')));
            $this->assertForbidden(fn () => $this->patch(route('deliveries.scan', $delivery)));
            $this->assertForbidden(fn () => $this->post(route('deliveries.receive', $delivery)));
            $this->assertForbidden(fn () => $this->post(route('pickup-requests.approve', $pickup)));
            $this->assertForbidden(fn () => $this->post(route('hub-transfers.dispatch', $transfer)));
            $this->assertForbidden(fn () => $this->get(route('hub-transfers.create')));
        }
    }

    public function test_mobile_scan_api_denies_management_accounts(): void
    {
        $delivery = $this->standaloneDelivery($this->origin, 'received');
        $payload = ['tracking_number' => $delivery->tracking_number];

        foreach ([$this->logisticsAdmin, $this->platformAdmin, $this->companyManager] as $manager) {
            Sanctum::actingAs($manager, ['logistics']);

            $this->postJson('/api/logistics/scan/lookup', $payload)
                ->assertForbidden()
                ->assertJsonPath('error', 'read_only');

            $this->postJson('/api/logistics/scan/confirm', $payload)
                ->assertForbidden()
                ->assertJsonPath('error', 'read_only');
        }

        // The API cannot be reached with a rider token either.
        Sanctum::actingAs($this->riderUser, ['rider']);
        $this->postJson('/api/logistics/scan/lookup', $payload)->assertForbidden();
    }

    // ------------------------------------------------------------------
    // 6, 9. Staff retain every counter operation inside their facility
    // ------------------------------------------------------------------

    public function test_staff_retain_receiving_scanning_sorting_and_pickup_review(): void
    {
        $delivery = $this->orderDelivery(withHandoff: true);

        $this->actingAs($this->originStaff);

        $this->get(route('deliveries.scan-page'))->assertOk();

        $this->post(route('deliveries.receive', $delivery))
            ->assertSessionHas('success', 'Parcel received and logged.');
        $this->assertSame('received', $delivery->fresh()->parcel_status);

        $this->patch(route('deliveries.scan', $delivery))
            ->assertSessionHas('success', 'Parcel scanned successfully.');
        $this->assertSame('scanned', $delivery->fresh()->parcel_status);

        $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $this->destination->id,
            'service_area_id' => $this->destinationArea->id,
        ])->assertSessionHas('success', 'Parcel sorted successfully.');
        $this->assertSame('sorted', $delivery->fresh()->parcel_status);

        // Seller pickup review stays a staff operation.
        $pickup = $this->pendingPickup($this->origin);
        $this->post(route('pickup-requests.approve', $pickup))
            ->assertSessionHas('success');
        $this->assertSame('approved', $pickup->fresh()->status);

        // The staff counter dashboard is still available.
        $this->get(route('sorting-center.index'))->assertOk();
    }

    public function test_staff_can_still_create_and_receive_hub_transfers(): void
    {
        $this->actingAs($this->originStaff);
        $this->get(route('hub-transfers.create'))->assertOk();

        // Creating passes the operator gate (validation errors are acceptable
        // here; a management account would have received 403 before this).
        $this->post(route('hub-transfers.store'), [])->assertStatus(302);

        // Destination staff receive an incoming manifest parcel with its
        // original condition choices.
        $delivery = $this->standaloneDelivery($this->origin, 'dispatched');
        $delivery->update(['destination_center_id' => $this->destination->id]);

        $transfer = HubTransfer::create([
            'reference' => 'HT-ROLE-RECEIVE',
            'logistics_company_id' => $this->company->id,
            'origin_center_id' => $this->origin->id,
            'destination_center_id' => $this->destination->id,
            'status' => 'arrived',
        ]);
        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'pending',
        ]);

        $this->actingAs($this->destinationStaff);
        $this->post(route('hub-transfers.receive', $transfer), [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'damaged',
            'exception_reason' => 'Carton crushed on arrival',
        ])->assertSessionHas('success', 'Receiving result recorded.');

        $delivery->refresh();
        $this->assertSame($this->destination->id, (int) $delivery->center_id);
        $this->assertSame('received', $delivery->parcel_status);
    }

    public function test_staff_are_restricted_to_their_assigned_facility(): void
    {
        $otherDelivery = $this->standaloneDelivery($this->destination, 'received');
        $otherPickup = $this->pendingPickup($this->destination);

        $this->actingAs($this->originStaff);

        $this->post(route('deliveries.receive', $otherDelivery), [
            'center_id' => $this->destination->id,
        ])->assertForbidden();

        $this->patch(route('deliveries.scan', $otherDelivery))->assertForbidden();

        $this->post(route('pickup-requests.approve', $otherPickup))->assertForbidden();
    }

    public function test_staff_scan_api_still_works_within_their_facility(): void
    {
        $delivery = $this->orderDelivery(withHandoff: true);
        $delivery->update(['parcel_status' => 'received', 'received_at' => now()]);

        Sanctum::actingAs($this->originStaff, ['logistics']);

        $this->postJson('/api/logistics/scan/lookup', [
            'tracking_number' => $delivery->tracking_number,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('scanned', $delivery->fresh()->parcel_status);
    }

    // ------------------------------------------------------------------
    // 5. Management keeps read-only monitoring and management pages
    // ------------------------------------------------------------------

    public function test_management_keeps_read_only_monitoring_and_management_pages(): void
    {
        $this->actingAs($this->logisticsAdmin);

        // Read-only operational aggregation (no counter forms for management).
        $sorting = $this->get(route('sorting-center.index'));
        $sorting->assertOk();
        $sorting->assertDontSee('Accept handover');

        // Personnel and facility management pages stay available.
        $this->get(route('staff.index'))->assertOk();
        $this->get(route('staff.create'))->assertOk();
    }

    // ------------------------------------------------------------------
    // Navigation: Operations is clickable and expandable with its submenu
    // ------------------------------------------------------------------

    public function test_staff_sidebar_places_scan_parcel_under_sorting_center(): void
    {
        $this->actingAs($this->originStaff);

        $response = $this->get(route('sorting-center.index'));
        $response->assertOk();
        $html = $response->getContent();

        // Operations is a clickable link to /logistics/operations paired with a separate toggle button.
        $this->assertMatchesRegularExpression(
            '/<div class="sb-parent[^"]*"[^>]*>\s*<a href="[^"]*\/logistics\/operations"[^>]*aria-label="Operations"[^>]*>.*?<\/a>\s*<button type="button"[^>]*@click\.stop\.prevent="[^"]*operationsOpen = !operationsOpen[^"]*"[^>]*aria-controls="subnav-operations"[^>]*>/s',
            $html,
            'Operations must render as a clickable link paired with a separate sibling dropdown toggle button.'
        );

        // Sorting Center is a direct link inside the Operations submenu, with no dropdown of its own.
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/logistics\/sorting-center"[^>]*aria-label="Sorting Center"/',
            $html,
            'Sorting Center must render as a direct link.'
        );
        $this->assertStringNotContainsString('sortingOpen', $html, 'Sorting Center must not have dropdown state.');
        $this->assertStringNotContainsString('aria-controls="subnav-sorting-center"', $html);

        $response->assertSee('id="subnav-operations"', false);
        $response->assertSee(route('deliveries.scan-page'), false);
        $response->assertSee(route('deliveries.index'), false);
        $response->assertSee(route('sorting-center.index'), false);
        $response->assertSee(route('hub-transfers.index'), false);
        $response->assertSee(route('pickup-requests.index'), false);

        // All five submenu entries appear in exact order inside #subnav-operations for hub staff.
        $this->assertSame(1, substr_count($html, 'aria-label="Scan Parcel"'));
        preg_match('/<div[^>]*id="subnav-operations"[^>]*>(.*?)<\/div>/s', $html, $submenu);
        $this->assertNotEmpty($submenu, 'The Operations submenu container must exist.');
        $this->assertSame(5, substr_count($submenu[1], '<a '), 'Staff must see all 5 submenu items under Operations.');
        $this->assertMatchesRegularExpression(
            '/aria-label="Scan Parcel".*aria-label="Deliveries".*aria-label="Sorting Center".*aria-label="Hub Transfers".*aria-label="Pickup Requests/s',
            $submenu[1],
            'Operations submenu items must appear in the exact required order.'
        );

        // Deliveries is a direct link inside Operations, without its old dropdown/submenu.
        $response->assertDontSee('id="subnav-deliveries"', false);
        $response->assertDontSee('deliveriesOpen', false);
        $response->assertDontSee('aria-label="All Deliveries"', false);
    }

    public function test_operations_sidebar_auto_expands_on_child_pages_and_highlights_active_page(): void
    {
        $this->actingAs($this->originStaff);

        // 1. Visiting /logistics/operations highlights Operations and keeps submenu collapsed by default.
        $opsHtml = $this->get(route('operations.index'))->assertOk()->getContent();
        $this->assertStringContainsString('operationsOpen: false', $opsHtml);
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/logistics\/operations"[^>]*aria-current="page"/',
            $opsHtml,
            'Operations link must be highlighted when visiting /logistics/operations.'
        );
        preg_match('/<div[^>]*id="subnav-operations"[^>]*>(.*?)<\/div>/s', $opsHtml, $opsSubmenu);
        $this->assertNotEmpty($opsSubmenu);
        $this->assertStringNotContainsString('aria-current="page"', $opsSubmenu[1]);

        // 2. Visiting each child page automatically expands the submenu and highlights only that child page.
        $childPages = [
            ['url' => route('deliveries.scan-page'), 'label' => 'Scan Parcel'],
            ['url' => route('deliveries.index'), 'label' => 'Deliveries'],
            ['url' => route('sorting-center.index'), 'label' => 'Sorting Center'],
            ['url' => route('hub-transfers.index'), 'label' => 'Hub Transfers'],
            ['url' => route('pickup-requests.index'), 'label' => 'Pickup Requests'],
        ];

        foreach ($childPages as $child) {
            $childHtml = $this->get($child['url'])->assertOk()->getContent();

            $this->assertStringContainsString(
                'operationsOpen: true',
                $childHtml,
                "Submenu must auto-expand when visiting {$child['label']}."
            );

            $this->assertDoesNotMatchRegularExpression(
                '/<a href="[^"]*\/logistics\/operations"[^>]*aria-current="page"/',
                $childHtml,
                "Operations parent link must not have aria-current=page when visiting {$child['label']}."
            );

            preg_match('/<a[^>]*aria-label="'.preg_quote($child['label'], '/').'[^"]*"[^>]*>/', $childHtml, $activeLink);
            $this->assertNotEmpty($activeLink, "Submenu link for {$child['label']} must exist.");
            $this->assertStringContainsString(
                'aria-current="page"',
                $activeLink[0],
                "{$child['label']} must be highlighted as active on its own page."
            );
        }
    }

    public function test_sorting_center_scan_action_opens_the_scanner_instead_of_verifying(): void
    {
        $delivery = $this->standaloneDelivery($this->origin, 'received');

        $this->actingAs($this->originStaff);

        $response = $this->get(route('sorting-center.index'))->assertOk();

        // The row action deep-links into the shared scanner with the tracking
        // number prefilled, and no longer posts to the direct scan endpoint.
        $response->assertSee(
            route('deliveries.scan-page', ['expect' => $delivery->tracking_number]),
            false
        );
        $response->assertDontSee(route('deliveries.scan', $delivery), false);

        $fresh = $delivery->fresh();
        $this->assertSame('received', $fresh->parcel_status);
        $this->assertNull($fresh->scanned_at);
        $this->assertSame(0, DeliveryStatusLog::where('delivery_id', $delivery->id)->count());
    }

    public function test_scan_lookup_shows_parcel_details_without_scanning_it(): void
    {
        $delivery = $this->standaloneDelivery($this->origin, 'received');

        $this->actingAs($this->originStaff)
            ->postJson(route('deliveries.scan-lookup'), [
                'tracking_number' => $delivery->tracking_number,
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'can_confirm' => true,
                'delivery' => [
                    'tracking_number' => $delivery->tracking_number,
                    'parcel_status' => 'received',
                ],
            ]);

        $fresh = $delivery->fresh();
        $this->assertSame('received', $fresh->parcel_status);
        $this->assertNull($fresh->scanned_at);
        $this->assertSame(0, DeliveryStatusLog::where('delivery_id', $delivery->id)->count());

        // Confirming afterwards runs the existing verification endpoint.
        $this->actingAs($this->originStaff)
            ->postJson(route('deliveries.scan-verify'), [
                'tracking_number' => $delivery->tracking_number,
            ])
            ->assertOk()
            ->assertJsonPath('delivery.parcel_status', 'scanned');

        $this->assertSame('scanned', $delivery->fresh()->parcel_status);
        $this->assertNotNull($delivery->fresh()->scanned_at);
    }

    public function test_scan_lookup_preserves_error_handling_for_unknown_and_foreign_parcels(): void
    {
        $foreign = $this->standaloneDelivery($this->destination, 'received');

        $this->actingAs($this->originStaff)
            ->postJson(route('deliveries.scan-lookup'), [
                'tracking_number' => 'TRK-20261107-ZZZZ',
            ])
            ->assertNotFound()
            ->assertJsonPath('error', 'not_found');

        $this->actingAs($this->originStaff)
            ->postJson(route('deliveries.scan-lookup'), [
                'tracking_number' => $foreign->tracking_number,
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden');

        $this->assertSame('received', $foreign->fresh()->parcel_status);
        $this->assertSame(0, DeliveryStatusLog::where('delivery_id', $foreign->id)->count());
    }

    public function test_scan_lookup_reports_already_scanned_without_writing_another_log(): void
    {
        $delivery = $this->standaloneDelivery($this->origin, 'scanned');
        $delivery->update(['scanned_at' => now()]);

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status' => 'scanned',
            'notes' => 'Parcel scanned and verified.',
        ]);

        $this->actingAs($this->originStaff)
            ->postJson(route('deliveries.scan-lookup'), [
                'tracking_number' => $delivery->tracking_number,
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'already' => true]);

        $this->assertSame(
            1,
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'scanned')->count(),
        );
    }

    public function test_sorting_center_page_highlights_only_sorting_center(): void
    {
        $this->actingAs($this->originStaff);

        $html = $this->get(route('sorting-center.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/logistics\/sorting-center"[^>]*aria-current="page"/',
            $html,
            'Sorting Center must be active on its own page.'
        );

        preg_match('/<a[^>]*aria-label="Scan Parcel"[^>]*>/', $html, $scanTag);
        $this->assertNotEmpty($scanTag);
        $this->assertStringNotContainsString(
            'aria-current="page"',
            $scanTag[0],
            'Scan Parcel must not be active on the Sorting Center page.'
        );
    }

    public function test_scan_page_highlights_only_scan_parcel(): void
    {
        $this->actingAs($this->originStaff);

        $html = $this->get(route('deliveries.scan-page'))->assertOk()->getContent();

        preg_match('/<a[^>]*aria-label="Scan Parcel"[^>]*>/', $html, $scanTag);
        $this->assertNotEmpty($scanTag);
        $this->assertStringContainsString(
            'aria-current="page"',
            $scanTag[0],
            'Scan Parcel must be highlighted on its own page.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<a href="[^"]*\/logistics\/sorting-center"[^>]*aria-current="page"/',
            $html,
            'Sorting Center must not be active on the scan page.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a href="[^"]*\/logistics\/deliveries"[^>]*aria-current="page"/',
            $html,
            'The Deliveries link must not be active on the scan page.'
        );
        $this->assertStringNotContainsString('sortingOpen', $html);
    }

    // ------------------------------------------------------------------
    // 10-11. Enforce the boundary for every counter route, not just the UI
    // ------------------------------------------------------------------

    public function test_every_parcel_counter_route_is_gated_by_the_operator_middleware(): void
    {
        $counterRoutes = [
            'hub-transfers.create',
            'hub-transfers.store',
            'hub-transfers.load',
            'hub-transfers.parcels.remove',
            'hub-transfers.dispatch',
            'hub-transfers.arrive',
            'hub-transfers.receive',
            'hub-transfers.complete',
            'deliveries.scan-page',
            'deliveries.scan-lookup',
            'deliveries.scan-verify',
            'deliveries.exception',
            'deliveries.return-to-seller',
            'deliveries.receive',
            'deliveries.scan',
            'deliveries.sort',
            'deliveries.dispatch',
            'pickup-requests.approve',
            'pickup-requests.reject',
        ];

        foreach ($counterRoutes as $name) {
            $this->assertTrue(
                $this->hasParcelOperatorMiddleware($name),
                "Route {$name} must require the parcel.operator gate."
            );
        }

        // Management/monitoring and routing routes must stay reachable.
        $managementRoutes = [
            'dashboard',
            'hub-transfers.index',
            'hub-transfers.show',
            'hub-transfers.cancel',
            'hub-transfers.hold',
            'hub-transfers.resume',
            'hub-transfers.reassign',
            'hub-transfers.reroute',
            'sorting-center.index',
            'deliveries.index',
            'deliveries.show',
            'deliveries.export',
            'deliveries.update-status',
            'pickup-requests.index',
            'pickup-requests.show',
            'pickup-requests.route',
            'riders.index',
            'hub-transport-drivers.index',
            'fleet.index',
            'staff.index',
            'centers.index',
            'service-areas.index',
            'rider-applications.index',
            'center-applications.index',
            'transactions.index',
            'remittances.index',
            'reports.index',
            'deliveries.archived',
        ];

        foreach ($managementRoutes as $name) {
            $this->assertFalse(
                $this->hasParcelOperatorMiddleware($name),
                "Route {$name} must remain available for read-only monitoring or management."
            );
        }
    }

    public function test_rider_and_hub_transport_driver_routes_are_untouched(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->all();

        foreach ([
            'api/logistics/rider/deliveries',
            'api/logistics/rider/deliveries/{delivery}/pickup',
            'api/logistics/rider/deliveries/{delivery}/sorting-center-handoff',
            'api/logistics/rider/deliveries/{delivery}/complete',
            'api/logistics/courier/login',
            'api/logistics/courier/transfers',
            'api/logistics/courier/transfers/{transfer}/origin-arrival',
        ] as $uri) {
            $this->assertContains($uri, $registered, "Route {$uri} must still be registered.");
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function assertForbidden(callable $request): void
    {
        $request()->assertForbidden();
    }

    private function hasParcelOperatorMiddleware(string $routeName): bool
    {
        $route = Route::getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "Route {$routeName} must exist.");

        foreach (array_merge($route->middleware(), $route->gatherMiddleware()) as $middleware) {
            $name = is_string($middleware) ? $middleware : get_class($middleware);

            if ($name === 'parcel.operator' || $name === EnsureParcelOperator::class) {
                return true;
            }
        }

        return false;
    }

    private function seedFacilities(): void
    {
        $this->company = LogisticsCompany::create(['name' => 'Role Separation Logistics', 'status' => 'active']);

        $this->origin = LogisticsCenter::create([
            'name' => 'Origin Hub',
            'address' => '1 Origin Street',
            'is_active' => true,
            'logistics_company_id' => $this->company->id,
        ]);
        $this->destination = LogisticsCenter::create([
            'name' => 'Destination Hub',
            'address' => '2 Destination Street',
            'is_active' => true,
            'logistics_company_id' => $this->company->id,
        ]);

        $this->originArea = ServiceArea::create([
            'name' => 'Origin Area',
            'logistics_center_id' => $this->origin->id,
            'is_active' => true,
        ]);
        $this->destinationArea = ServiceArea::create([
            'name' => 'Destination Area',
            'logistics_center_id' => $this->destination->id,
            'is_active' => true,
        ]);

        $this->logisticsAdmin = new User([
            'name' => 'Global Logistics Admin',
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'password' => 'TestManager123!',
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->logisticsAdmin->id = User::GLOBAL_LOGISTICS_ADMIN_ID;
        $this->logisticsAdmin->save();

        $this->platformAdmin = User::create([
            'name' => 'Platform Administrator',
            'email' => 'platform.admin@example.test',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->companyManager = User::create([
            'name' => 'Company Manager',
            'email' => 'company.manager@example.test',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $this->company->id,
        ]);

        $this->originStaff = User::create([
            'name' => 'Origin Staff',
            'email' => 'origin.staff@example.test',
            'password' => Hash::make('secret'),
            'role' => 'staff',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'center_id' => $this->origin->id,
            'logistics_company_id' => $this->company->id,
        ]);

        $this->destinationStaff = User::create([
            'name' => 'Destination Staff',
            'email' => 'destination.staff@example.test',
            'password' => Hash::make('secret'),
            'role' => 'logistics_staff',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'center_id' => $this->destination->id,
            'logistics_company_id' => $this->company->id,
        ]);

        $this->riderUser = User::create([
            'name' => 'Pickup Rider',
            'email' => 'pickup.rider@example.test',
            'password' => Hash::make('secret'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        Rider::create([
            'user_id' => $this->riderUser->id,
            'center_id' => $this->origin->id,
            'service_area_id' => $this->originArea->id,
            'name' => $this->riderUser->name,
            'email' => $this->riderUser->email,
            'vehicle_type' => 'motorcycle',
            'vehicle_verification' => 'verified',
            'status' => 'available',
            'is_verified' => true,
            'approved_at' => now(),
        ]);
    }

    private function orderDelivery(bool $withHandoff = false): Delivery
    {
        $order = Order::create([
            'buyer_id' => 900,
            'seller_id' => 901,
            'status' => 'ready_for_delivery',
            'payment_status' => 'unpaid',
            'total_amount' => 300,
            'total' => 300,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => 'pending',
            'amount' => 300,
        ]);

        return Delivery::create([
            'tracking_number' => $this->nextTracking(),
            'order_id' => $order->id,
            'seller_id' => 901,
            'center_id' => $this->origin->id,
            'service_area_id' => $this->originArea->id,
            'status' => $withHandoff ? 'picked_up' : 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
            'sender_name' => 'INVOIZ Seller',
            'sender_address' => '10 Seller Street',
            'recipient_name' => 'Buyer',
            'recipient_address' => '20 Buyer Street',
            'payment_method' => 'cod',
            'amount_to_collect' => 300,
            'delivery_fee' => 50,
            'picked_up_at' => $withHandoff ? now() : null,
            'sorting_center_handoff_at' => $withHandoff ? now() : null,
            'sorting_center_handoff_rider_id' => $withHandoff ? Rider::query()->value('id') : null,
        ]);
    }

    private function standaloneDelivery(LogisticsCenter $center, string $parcelStatus): Delivery
    {
        return Delivery::create([
            'tracking_number' => $this->nextTracking(),
            'center_id' => $center->id,
            'service_area_id' => $center->is($this->destination) ? $this->destinationArea->id : $this->originArea->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => $parcelStatus,
            'sender_name' => 'Walk-in Sender',
            'sender_address' => '30 Sender Street',
            'recipient_name' => 'Recipient',
            'recipient_address' => '40 Recipient Street',
            'delivery_fee' => 50,
        ]);
    }

    private function pendingPickup(LogisticsCenter $center): PickupRequest
    {
        $delivery = Delivery::create([
            'tracking_number' => $this->nextTracking(),
            'center_id' => $center->id,
            'service_area_id' => $center->is($this->destination) ? $this->destinationArea->id : $this->originArea->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
            'sender_name' => 'Pickup Seller',
            'sender_address' => '50 Seller Street',
            'recipient_name' => 'Pickup Buyer',
            'recipient_address' => '60 Buyer Street',
            'delivery_fee' => 50,
        ]);

        return PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $center->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    }

    /** @return array{0: HubTransfer, 1: HubTransferParcel} */
    private function hubTransfer(): array
    {
        $delivery = $this->standaloneDelivery($this->origin, 'received');

        $transfer = HubTransfer::create([
            'reference' => 'HT-ROLE-'.(++self::$trackingSequence),
            'logistics_company_id' => $this->company->id,
            'origin_center_id' => $this->origin->id,
            'destination_center_id' => $this->destination->id,
            'status' => 'loading',
        ]);

        $entry = HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'pending',
            'receiving_status' => 'pending',
        ]);

        return [$transfer, $entry];
    }

    private function nextTracking(): string
    {
        self::$trackingSequence++;

        return 'TRK-20261107-'.str_pad((string) self::$trackingSequence, 4, 'A', STR_PAD_LEFT);
    }

    private function createSchema(): void
    {
        $schema = Schema::connection('sqlite');

        foreach ([
            'hub_transfer_events', 'hub_transfer_parcels', 'hub_transfers',
            'order_status_histories', 'courier_pickups', 'seller_notifications',
            'delivery_failures', 'delivery_proofs', 'delivery_status_logs',
            'delivery_items', 'pickup_requests', 'payments', 'deliveries',
            'orders', 'sellers', 'logistics_settings', 'riders', 'vehicle_types',
            'service_areas', 'logistics_centers', 'logistics_companies', 'users',
            'logistics_trucks', 'hub_transport_drivers',
        ] as $table) {
            $schema->dropIfExists($table);
        }

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('rider');
            $table->boolean('is_admin')->default(false);
            $table->string('status')->default('active');
            $table->string('account_status')->default('active');
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        $schema->create('logistics_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('logistics_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->timestamps();
        });

        $schema->create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('logistics_center_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('riders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('vehicle_verification')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_online')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        $schema->create('sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('business_name')->nullable();
            $table->string('status')->nullable();
            $table->string('approval_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('method')->nullable();
            $table->string('status')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('proof_path')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        $schema->create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('origin_center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->string('status')->default('waiting_for_rider');
            $table->string('parcel_status')->nullable();
            $table->string('priority')->nullable();
            $table->string('package_type')->nullable();
            $table->string('package_description')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_phone')->nullable();
            $table->string('sender_address')->nullable();
            $table->decimal('sender_lat', 10, 7)->nullable();
            $table->decimal('sender_lng', 10, 7)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_address')->nullable();
            $table->decimal('recipient_lat', 10, 7)->nullable();
            $table->decimal('recipient_lng', 10, 7)->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('amount_to_collect', 10, 2)->nullable();
            $table->decimal('cod_amount_received', 10, 2)->nullable();
            $table->timestamp('cod_received_at')->nullable();
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('pickup_pin')->nullable();
            $table->text('notes')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('reattempt_requested_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('return_reason')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->string('archive_note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('sorting_center_handoff_at')->nullable();
            $table->unsignedBigInteger('sorting_center_handoff_rider_id')->nullable();
            $table->timestamp('sorting_center_pickup_at')->nullable();
            $table->unsignedBigInteger('sorting_center_pickup_rider_id')->nullable();
            $table->unsignedBigInteger('inter_hub_rider_id')->nullable();
            $table->timestamp('inter_hub_dispatched_at')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('name')->nullable();
            $table->string('variant_label')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->timestamps();
        });

        $schema->create('delivery_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('status');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('type')->nullable();
            $table->string('signature_name')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_failures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        $schema->create('pickup_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('center_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        $schema->create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('notes')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });

        $schema->create('courier_pickups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('courier')->nullable();
            $table->timestamp('pickup_at')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $schema->create('seller_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        $schema->create('logistics_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('photo_path')->nullable();
            $table->text('preferences')->nullable();
            $table->timestamps();
        });

        $schema->create('hub_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('origin_center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('truck_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('dispatched_by')->nullable();
            $table->unsignedBigInteger('arrived_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('planned_dispatch_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('origin_arrived_at')->nullable();
            $table->timestamp('destination_arrived_at')->nullable();
            $table->timestamps();
        });

        $schema->create('hub_transfer_parcels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_transfer_id');
            $table->unsignedBigInteger('delivery_id');
            $table->string('loading_status')->default('pending');
            $table->unsignedBigInteger('loaded_by')->nullable();
            $table->unsignedBigInteger('loaded_by_hub_driver_id')->nullable();
            $table->timestamp('loaded_at')->nullable();
            $table->string('receiving_status')->default('pending');
            $table->string('condition')->nullable();
            $table->text('exception_reason')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        $schema->create('hub_transfer_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_transfer_id');
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->string('event_type');
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('hub_transport_driver_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->timestamps();
        });

        // Minimal fleet tables so the staff manifest page can render its
        // (empty) eligible truck/driver pickers.
        $schema->create('logistics_trucks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('hub_transport_driver_id')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('name')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('status')->default('available');
            $table->string('verification_status')->default('approved');
            $table->integer('parcel_capacity')->default(0);
            $table->decimal('capacity_kg', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('hub_transport_drivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('logistics_truck_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('available');
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
