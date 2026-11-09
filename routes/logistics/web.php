<?php

use App\Http\Controllers\Logistics\CentralEntryController;
use App\Http\Controllers\Logistics\DashboardController;
use App\Http\Controllers\Logistics\DeliveryController;
use App\Http\Controllers\Logistics\RiderController;
use App\Http\Controllers\Logistics\TrackingController;
use App\Http\Controllers\Logistics\SettingController;
use App\Http\Controllers\Logistics\SortingCenterController;
use App\Http\Controllers\Logistics\ProfileController;
use App\Http\Controllers\Logistics\NotificationController;
use App\Http\Controllers\Logistics\MessageController;
use App\Http\Controllers\Logistics\RiderMessageController;
use App\Http\Controllers\Logistics\StaffController;
use App\Http\Controllers\Logistics\LogisticsCenterController;
use App\Http\Controllers\Logistics\ServiceAreaController;
use App\Http\Controllers\Logistics\TransactionController;
use App\Http\Controllers\Logistics\ReportController;
use App\Http\Controllers\Logistics\RemittanceController;
use App\Http\Controllers\Logistics\RiderApplicationAdminController;
use App\Http\Controllers\Logistics\RiderDocumentChangeController;
use App\Http\Controllers\Logistics\CenterApplicationAdminController;
use App\Http\Controllers\Logistics\CenterApplicationPublicController;
use App\Http\Controllers\Logistics\CenterEmailVerificationController;
use App\Http\Controllers\Logistics\PickupRequestController;
use App\Http\Controllers\Logistics\HubTransferController;
use App\Http\Controllers\Logistics\OperationsController;
use App\Http\Controllers\Logistics\HubTransportDriverController;
use App\Http\Controllers\Logistics\FleetController;
use App\Http\Controllers\Logistics\CompanyStructureController;
use Illuminate\Support\Facades\Route;

Route::prefix('logistics')->group(function () {

// Central entry: guests see the public landing page, authenticated
// users are routed to their role destination (never a platform chooser).
Route::get('/', [CentralEntryController::class, 'index'])->name('logistics.landing');

// Public: Open a Logistics Center / check application status
// Shared logic with the mobile API via CenterApplicationService.
Route::get('logistics-center/apply', [CenterApplicationPublicController::class, 'create'])
    ->name('center-application.apply');
Route::post('logistics-center/apply', [CenterApplicationPublicController::class, 'store'])
    ->name('center-application.store');
Route::get('logistics-center/application-status', [CenterApplicationPublicController::class, 'status'])
    ->name('center-application.status');
Route::post('logistics-center/application-status', [CenterApplicationPublicController::class, 'check'])
    ->name('center-application.status.check')->middleware('throttle:10,1');
// Email OTP gate for the public center-application form (mirrors the rider
// apply flow). JSON endpoints consumed by the wizard via fetch.
Route::post('logistics-center/verification/send', [CenterEmailVerificationController::class, 'send'])
    ->middleware('throttle:5,1')
    ->name('center-application.verification.send');
Route::post('logistics-center/verification/verify', [CenterEmailVerificationController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('center-application.verification.verify');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'staff'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'staff'])->group(function () {

    // Unified hub operations queue (read-only aggregation; every action links
    // to the canonical delivery/transfer page that performs the mutation).
    Route::get('operations', [OperationsController::class, 'index'])->name('operations.index');

    Route::get('hub-transfers', [HubTransferController::class, 'index'])->name('hub-transfers.index');
    Route::get('hub-transfers/create', [HubTransferController::class, 'create'])->middleware('parcel.operator')->name('hub-transfers.create');
    Route::post('hub-transfers', [HubTransferController::class, 'store'])->middleware('parcel.operator')->name('hub-transfers.store');
    Route::get('hub-transfers/{hubTransfer}', [HubTransferController::class, 'show'])->name('hub-transfers.show');
    Route::post('hub-transfers/{hubTransfer}/load', [HubTransferController::class, 'loadParcel'])->middleware('parcel.operator')->name('hub-transfers.load');
    Route::delete('hub-transfers/{hubTransfer}/parcels/{parcel}', [HubTransferController::class, 'removeParcel'])->middleware('parcel.operator')->name('hub-transfers.parcels.remove');
    Route::post('hub-transfers/{hubTransfer}/dispatch', [HubTransferController::class, 'dispatch'])->middleware('parcel.operator')->name('hub-transfers.dispatch');
    Route::post('hub-transfers/{hubTransfer}/arrive', [HubTransferController::class, 'arrive'])->middleware('parcel.operator')->name('hub-transfers.arrive');
    Route::post('hub-transfers/{hubTransfer}/receive', [HubTransferController::class, 'receiveParcel'])->middleware('parcel.operator')->name('hub-transfers.receive');
    Route::post('hub-transfers/{hubTransfer}/complete', [HubTransferController::class, 'complete'])->middleware('parcel.operator')->name('hub-transfers.complete');
    Route::post('hub-transfers/{hubTransfer}/cancel', [HubTransferController::class, 'cancel'])->name('hub-transfers.cancel');
    Route::post('hub-transfers/{hubTransfer}/hold', [HubTransferController::class, 'hold'])->name('hub-transfers.hold');
    Route::post('hub-transfers/{hubTransfer}/resume', [HubTransferController::class, 'resume'])->name('hub-transfers.resume');
    Route::post('hub-transfers/{hubTransfer}/reassign', [HubTransferController::class, 'reassign'])->name('hub-transfers.reassign');
    Route::post('hub-transfers/{hubTransfer}/reroute', [HubTransferController::class, 'reroute'])->name('hub-transfers.reroute');
    // Sorting Center operational hub (read-only aggregation; mutations
    // post to the existing deliveries.* routes).
    Route::get('sorting-center', [SortingCenterController::class, 'index'])->name('sorting-center.index');
    Route::get('sorting-center/export', [SortingCenterController::class, 'export'])->name('sorting-center.export');

    // Deliveries
    // Parcel QR scanner (page + verify endpoint). The GET route must be
    // registered before the resource below so "deliveries/scan" is not
    // captured by the "deliveries/{delivery}" show route.
    Route::get('deliveries/scan', [DeliveryController::class, 'scanPage'])->middleware('parcel.operator')->name('deliveries.scan-page');
    // Read-only lookup used by the scanner to show parcel details before the
    // operator confirms. It never changes parcel status or writes a scan log.
    Route::post('deliveries/scan-lookup', [DeliveryController::class, 'lookupScanByTracking'])->middleware('parcel.operator')->name('deliveries.scan-lookup');
    Route::post('deliveries/scan-verify', [DeliveryController::class, 'scanByTracking'])->middleware('parcel.operator')->name('deliveries.scan-verify');
    Route::post('deliveries/bulk-assign-rider', [DeliveryController::class, 'bulkAssignRider'])->middleware('rider.manager')->name('deliveries.bulk-assign');
    Route::get('deliveries/export', [DeliveryController::class, 'export'])->name('deliveries.export');
    Route::resource('deliveries', DeliveryController::class)->except(['destroy']);
    Route::post('deliveries/{delivery}/assign-rider', [DeliveryController::class, 'assignRider'])->middleware('rider.manager')->name('deliveries.assign-rider');
    Route::post('deliveries/{delivery}/auto-assign', [DeliveryController::class, 'autoAssign'])->middleware('rider.manager')->name('deliveries.auto-assign');
    Route::patch('deliveries/{delivery}/update-status', [DeliveryController::class, 'updateStatus'])->name('deliveries.update-status');
    Route::post('deliveries/{delivery}/exception', [DeliveryController::class, 'reportException'])->middleware('parcel.operator')->name('deliveries.exception');
    Route::post('deliveries/{delivery}/return-to-seller', [DeliveryController::class, 'dispatchReturnToSeller'])->middleware('parcel.operator')->name('deliveries.return-to-seller');
    Route::post('deliveries/{delivery}/receive', [DeliveryController::class, 'receive'])->middleware('parcel.operator')->name('deliveries.receive');
    Route::patch('deliveries/{delivery}/scan', [DeliveryController::class, 'scan'])->middleware('parcel.operator')->name('deliveries.scan');
    Route::post('deliveries/{delivery}/sort', [DeliveryController::class, 'sort'])->middleware('parcel.operator')->name('deliveries.sort');
    Route::post('deliveries/{delivery}/dispatch', [DeliveryController::class, 'dispatch'])->middleware('parcel.operator')->name('deliveries.dispatch');

    // Riders (Directory + Management)
    Route::get('riders', [RiderController::class, 'index'])->name('riders.index');
    Route::get('riders/{rider}', [RiderController::class, 'show'])->name('riders.show');
    Route::post('riders/{rider}/reset-credentials', [RiderController::class, 'resetCredentials'])->middleware('rider.manager')->name('riders.reset-credentials');
    Route::post('riders/{rider}/activate', [RiderController::class, 'activate'])->middleware('rider.manager')->name('riders.activate');
    Route::post('riders/{rider}/deactivate', [RiderController::class, 'deactivate'])->middleware('rider.manager')->name('riders.deactivate');
    Route::post('riders/{rider}/reverify', [RiderController::class, 'reverify'])->middleware('rider.manager')->name('riders.reverify');
    Route::post('riders/payouts/{id}/accept', [RiderController::class, 'acceptPayout'])->name('riders.payouts.accept');

    // Hub Transport Drivers (Admin Only: kept separate from delivery riders)
    Route::middleware('admin')->group(function () {
        Route::get('hub-transport-drivers', [HubTransportDriverController::class, 'index'])->name('hub-transport-drivers.index');
        Route::get('hub-transport-drivers/{driver}', [HubTransportDriverController::class, 'show'])->name('hub-transport-drivers.show');
    });

    // Pickup Requests (staff: approve/reject pickups at their center)
    Route::get('pickup-requests', [PickupRequestController::class, 'index'])->name('pickup-requests.index');
    Route::get('pickup-requests/{pickupRequest}', [PickupRequestController::class, 'show'])->name('pickup-requests.show');
    Route::post('pickup-requests/{pickupRequest}/route', [PickupRequestController::class, 'route'])
        ->middleware('admin')
        ->name('pickup-requests.route');
    Route::post('pickup-requests/{pickupRequest}/approve', [PickupRequestController::class, 'approve'])->middleware('parcel.operator')->name('pickup-requests.approve');
    Route::post('pickup-requests/{pickupRequest}/reject', [PickupRequestController::class, 'reject'])->middleware('parcel.operator')->name('pickup-requests.reject');

    // Fleet Management (Admin Only)
    Route::middleware('admin')->group(function () {
        Route::get('fleet', [FleetController::class, 'index'])->name('fleet.index');
        Route::post('fleet/trucks', [FleetController::class, 'storeTruck'])->name('fleet.trucks.store');
        Route::put('fleet/trucks/{truck}', [FleetController::class, 'updateTruck'])->name('fleet.trucks.update');
        Route::post('fleet/trucks/{truck}/approve', [FleetController::class, 'approveVehicle'])->name('fleet.trucks.approve');
        Route::post('fleet/trucks/{truck}/reject', [FleetController::class, 'rejectVehicle'])->name('fleet.trucks.reject');
        Route::post('fleet/drivers', [FleetController::class, 'storeDriver'])->name('fleet.drivers.store');
        Route::put('fleet/drivers/{driver}', [FleetController::class, 'updateDriver'])->name('fleet.drivers.update');
        Route::post('fleet/incidents', [FleetController::class, 'storeIncident'])->name('fleet.incidents.store');
        Route::put('fleet/incidents/{incident}', [FleetController::class, 'updateIncident'])->name('fleet.incidents.update');
        Route::post('fleet/maintenance', [FleetController::class, 'storeMaintenanceRecord'])->name('fleet.maintenance.store');
        Route::put('fleet/maintenance/{maintenanceRecord}', [FleetController::class, 'updateMaintenanceRecord'])->name('fleet.maintenance.update');
    });

    // Archived Deliveries (Admin Only)
    Route::middleware('admin')->group(function () {
        Route::get('deliveries-archived', [DeliveryController::class, 'archived'])->name('deliveries.archived');
        Route::post('deliveries/{delivery}/archive', [DeliveryController::class, 'archive'])->name('deliveries.archive');
        Route::post('deliveries/{delivery}/restore', [DeliveryController::class, 'restore'])->name('deliveries.restore');
        Route::delete('deliveries/{delivery}', [DeliveryController::class, 'destroy'])->name('deliveries.destroy');
    });

    // Rider Applications (Logistics Admin Only: review & provision)
    Route::middleware(['admin', 'rider.manager'])->group(function () {
        Route::get('rider-applications', [RiderApplicationAdminController::class, 'index'])->name('rider-applications.index');
        Route::get('rider-applications/documents/{document}/view', [RiderApplicationAdminController::class, 'viewDocument'])->name('rider-applications.documents.view');
        Route::get('rider-applications/documents/{document}/download', [RiderApplicationAdminController::class, 'downloadDocument'])->name('rider-applications.documents.download');
        Route::get('rider-applications/{application}', [RiderApplicationAdminController::class, 'show'])->name('rider-applications.show');
        Route::post('rider-applications/{application}/approve', [RiderApplicationAdminController::class, 'approve'])->name('rider-applications.approve');
        Route::post('rider-applications/{application}/resend-credentials', [RiderApplicationAdminController::class, 'resendCredentials'])->name('rider-applications.resend-credentials');
        Route::post('rider-applications/{application}/resend-rejection', [RiderApplicationAdminController::class, 'resendRejection'])->name('rider-applications.resend-rejection');
        Route::post('rider-applications/{application}/reject', [RiderApplicationAdminController::class, 'reject'])->name('rider-applications.reject');
    });

    // Rider Document Change Requests (Logistics Admin Only)
    Route::middleware(['admin', 'rider.manager'])->group(function () {
        Route::get('document-change-requests', [RiderDocumentChangeController::class, 'index'])->name('document-change-requests.index');
        Route::get('document-change-requests/{changeRequest}/documents/{kind}/view', [RiderDocumentChangeController::class, 'viewDocument'])->name('document-change-requests.documents.view');
        Route::get('document-change-requests/{changeRequest}/documents/{kind}/download', [RiderDocumentChangeController::class, 'downloadDocument'])->name('document-change-requests.documents.download');
        Route::get('document-change-requests/{changeRequest}', [RiderDocumentChangeController::class, 'show'])->name('document-change-requests.show');
        Route::post('document-change-requests/{changeRequest}/approve', [RiderDocumentChangeController::class, 'approve'])->name('document-change-requests.approve');
        Route::post('document-change-requests/{changeRequest}/reject', [RiderDocumentChangeController::class, 'reject'])->name('document-change-requests.reject');
    });

    // Logistics Center Applications (Admin Only: review & provision)
    Route::middleware('admin')->group(function () {
        Route::get('companies', [CompanyStructureController::class, 'index'])->name('companies.index');
        Route::post('companies', [CompanyStructureController::class, 'store'])->name('companies.store');
        Route::put('centers/{center}/ownership', [CompanyStructureController::class, 'assignCenter'])->name('companies.centers.assign');
        Route::get('center-applications', [CenterApplicationAdminController::class, 'index'])->name('center-applications.index');
        Route::get('center-applications/documents/{document}/view', [CenterApplicationAdminController::class, 'viewDocument'])->name('center-applications.documents.view');
        Route::get('center-applications/documents/{document}/download', [CenterApplicationAdminController::class, 'downloadDocument'])->name('center-applications.documents.download');
        Route::get('center-applications/{application}', [CenterApplicationAdminController::class, 'show'])->name('center-applications.show');
        Route::post('center-applications/{application}/approve', [CenterApplicationAdminController::class, 'approve'])->name('center-applications.approve');
        Route::post('center-applications/{application}/resend-credentials', [CenterApplicationAdminController::class, 'resendCredentials'])->name('center-applications.resend-credentials');
        Route::post('center-applications/{application}/reject', [CenterApplicationAdminController::class, 'reject'])->name('center-applications.reject');
    });

    // Staff Management (Admin Only)
    Route::middleware('admin')->group(function () {
        Route::resource('staff', StaffController::class)->except(['destroy']);
        Route::post('staff/{staff}/activate', [StaffController::class, 'activate'])->name('staff.activate');
        Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });

    // Logistics Centers (Admin Only)
    Route::middleware('admin')->group(function () {
        Route::resource('centers', LogisticsCenterController::class);
        Route::post('centers/{center}/toggle', [LogisticsCenterController::class, 'toggle'])->name('centers.toggle');
    });

    // Service Areas (Admin Only)
    Route::middleware('admin')->group(function () {
        Route::resource('service-areas', ServiceAreaController::class);
        Route::post('service-areas/{serviceArea}/toggle', [ServiceAreaController::class, 'toggle'])->name('service-areas.toggle');
    });

    // Tracking
    Route::get('tracking', [TrackingController::class, 'index'])->name('tracking.index');
    Route::get('tracking/search', [TrackingController::class, 'search'])->name('tracking.search');

    // Finance and Network Reports (Admin Only)
    Route::middleware('admin')->group(function () {
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('remittances', [RemittanceController::class, 'index'])->name('remittances.index');
        Route::get('remittances/export', [RemittanceController::class, 'export'])->name('remittances.export');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('reports/export/csv', [ReportController::class, 'exportCsv'])->name('reports.export.csv');
    });

    // Remittance Operational Actions (Staff & Admin at assigned center)
    Route::get('hub-payouts', [RemittanceController::class, 'hubPayouts'])->name('remittances.hub-payouts.index');
    Route::post('remittances/{remittance}/confirm-received', [RemittanceController::class, 'confirmReceived'])->name('remittances.confirm-received');
    Route::post('remittances/hub-payouts/{id}/accept', [RemittanceController::class, 'acceptHubPayout'])->name('remittances.hub-payouts.accept');

    // Settings
    // (Profile editing lives only on the Profile page; Settings manages
    // preferences and account configuration.)
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings/password', [SettingController::class, 'updatePassword'])->name('settings.update-password');
    Route::post('settings/photo', [SettingController::class, 'updatePhoto'])->name('settings.update-photo');
    Route::put('settings/notifications', [SettingController::class, 'updateNotifications'])->name('settings.update-notifications');
    Route::put('settings/delivery', [SettingController::class, 'updateDelivery'])->name('settings.update-delivery');
    Route::put('settings/appearance', [SettingController::class, 'updateAppearance'])->name('settings.update-appearance');
    Route::put('settings/region', [SettingController::class, 'updateRegion'])->name('settings.update-region');
    Route::patch('settings/navigation', [SettingController::class, 'updateNavigation'])->name('settings.update-navigation');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

    // Messages
    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/poll', [MessageController::class, 'poll'])->name('messages.poll');
    Route::get('messages/recipients', [MessageController::class, 'recipients'])->name('messages.recipients');
    Route::post('messages/start', [MessageController::class, 'start'])->name('messages.start');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
    Route::patch('messages/{message}', [MessageController::class, 'update'])->name('messages.update');
    Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
    Route::patch('messages-read/{conversation}', [MessageController::class, 'markRead'])->name('messages.mark-read');
    Route::get('attachments/{attachment}/view', [MessageController::class, 'viewAttachment'])->name('messages.attachments.view');
    Route::get('attachments/{attachment}/download', [MessageController::class, 'downloadAttachment'])->name('messages.attachments.download');

    // Profile (personal info + photo; Settings stays separate)
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.update-photo');
    Route::patch('profile', [ProfileController::class, 'update'])->name('logistics.profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Rider messaging only (riders access via Logistics login, messaging only)
Route::middleware(['auth', 'rider.web'])->group(function () {
    Route::get('rider/entry', [CentralEntryController::class, 'riderEntry'])->name('rider.entry');
    Route::get('rider/messages', [RiderMessageController::class, 'index'])->name('rider.messages');
    Route::get('rider/messages/poll', [RiderMessageController::class, 'poll'])->name('rider.messages.poll');
    Route::post('rider/messages', [RiderMessageController::class, 'store'])->name('rider.messages.send');
    Route::patch('rider/messages/{message}', [RiderMessageController::class, 'update'])->name('rider.messages.update');
    Route::delete('rider/messages/{message}', [RiderMessageController::class, 'destroy'])->name('rider.messages.destroy');
    Route::get('rider/attachments/{attachment}/view', [RiderMessageController::class, 'viewAttachment'])->name('rider.messages.attachments.view');
    Route::get('rider/attachments/{attachment}/download', [RiderMessageController::class, 'downloadAttachment'])->name('rider.messages.attachments.download');
});

require __DIR__.'/auth.php';

});
