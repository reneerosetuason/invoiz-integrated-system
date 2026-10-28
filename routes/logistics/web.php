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
use App\Http\Controllers\Logistics\CenterApplicationAdminController;
use App\Http\Controllers\Logistics\CenterApplicationPublicController;
use App\Http\Controllers\Logistics\CenterEmailVerificationController;
use App\Http\Controllers\Logistics\PickupRequestController;
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

    // Sorting Center operational hub (read-only aggregation; mutations
    // post to the existing deliveries.* routes).
    Route::get('sorting-center', [SortingCenterController::class, 'index'])->name('sorting-center.index');
    Route::get('sorting-center/export', [SortingCenterController::class, 'export'])->name('sorting-center.export');

    // Deliveries
    // Parcel QR scanner (page + verify endpoint). The GET route must be
    // registered before the resource below so "deliveries/scan" is not
    // captured by the "deliveries/{delivery}" show route.
    Route::get('deliveries/scan', [DeliveryController::class, 'scanPage'])->name('deliveries.scan-page');
    Route::post('deliveries/scan-verify', [DeliveryController::class, 'scanByTracking'])->name('deliveries.scan-verify');
    Route::post('deliveries/bulk-assign-rider', [DeliveryController::class, 'bulkAssignRider'])->name('deliveries.bulk-assign');
    Route::get('deliveries/export', [DeliveryController::class, 'export'])->name('deliveries.export');
    Route::resource('deliveries', DeliveryController::class)->except(['destroy']);
    Route::post('deliveries/{delivery}/assign-rider', [DeliveryController::class, 'assignRider'])->name('deliveries.assign-rider');
    Route::post('deliveries/{delivery}/auto-assign', [DeliveryController::class, 'autoAssign'])->name('deliveries.auto-assign');
    Route::patch('deliveries/{delivery}/update-status', [DeliveryController::class, 'updateStatus'])->name('deliveries.update-status');
    Route::post('deliveries/{delivery}/exception', [DeliveryController::class, 'reportException'])->name('deliveries.exception');
    Route::post('deliveries/{delivery}/return-to-seller', [DeliveryController::class, 'dispatchReturnToSeller'])->name('deliveries.return-to-seller');
    Route::post('deliveries/{delivery}/receive', [DeliveryController::class, 'receive'])->name('deliveries.receive');
    Route::patch('deliveries/{delivery}/scan', [DeliveryController::class, 'scan'])->name('deliveries.scan');
    Route::post('deliveries/{delivery}/sort', [DeliveryController::class, 'sort'])->name('deliveries.sort');
    Route::post('deliveries/{delivery}/dispatch', [DeliveryController::class, 'dispatch'])->name('deliveries.dispatch');

    // Archived Deliveries (Admin Only)
    Route::get('deliveries-archived', [DeliveryController::class, 'archived'])->name('deliveries.archived');
    Route::post('deliveries/{delivery}/archive', [DeliveryController::class, 'archive'])->name('deliveries.archive');
    Route::post('deliveries/{delivery}/restore', [DeliveryController::class, 'restore'])->name('deliveries.restore');
    Route::delete('deliveries/{delivery}', [DeliveryController::class, 'destroy'])->name('deliveries.destroy');

    // Riders (Directory + Management)
    Route::get('riders', [RiderController::class, 'index'])->name('riders.index');
    Route::get('riders/{rider}', [RiderController::class, 'show'])->name('riders.show');
    Route::post('riders/{rider}/reset-credentials', [RiderController::class, 'resetCredentials'])->name('riders.reset-credentials');
    Route::post('riders/{rider}/activate', [RiderController::class, 'activate'])->name('riders.activate');
    Route::post('riders/{rider}/deactivate', [RiderController::class, 'deactivate'])->name('riders.deactivate');
    Route::post('riders/{rider}/reverify', [RiderController::class, 'reverify'])->name('riders.reverify');

    // Pickup Requests (staff: approve/reject pickups at their center)
    Route::get('pickup-requests', [PickupRequestController::class, 'index'])->name('pickup-requests.index');
    Route::get('pickup-requests/{pickupRequest}', [PickupRequestController::class, 'show'])->name('pickup-requests.show');
    Route::post('pickup-requests/{pickupRequest}/route', [PickupRequestController::class, 'route'])
        ->middleware('admin')
        ->name('pickup-requests.route');
    Route::post('pickup-requests/{pickupRequest}/approve', [PickupRequestController::class, 'approve'])->name('pickup-requests.approve');
    Route::post('pickup-requests/{pickupRequest}/reject', [PickupRequestController::class, 'reject'])->name('pickup-requests.reject');

    // Rider Applications (Admin Only: review & provision)
    Route::middleware('admin')->group(function () {
        Route::get('rider-applications', [RiderApplicationAdminController::class, 'index'])->name('rider-applications.index');
        Route::get('rider-applications/documents/{document}/view', [RiderApplicationAdminController::class, 'viewDocument'])->name('rider-applications.documents.view');
        Route::get('rider-applications/documents/{document}/download', [RiderApplicationAdminController::class, 'downloadDocument'])->name('rider-applications.documents.download');
        Route::get('rider-applications/{application}', [RiderApplicationAdminController::class, 'show'])->name('rider-applications.show');
        Route::post('rider-applications/{application}/approve', [RiderApplicationAdminController::class, 'approve'])->name('rider-applications.approve');
        Route::post('rider-applications/{application}/resend-credentials', [RiderApplicationAdminController::class, 'resendCredentials'])->name('rider-applications.resend-credentials');
        Route::post('rider-applications/{application}/reject', [RiderApplicationAdminController::class, 'reject'])->name('rider-applications.reject');
    });

    // Logistics Center Applications (Admin Only: review & provision)
    Route::middleware('admin')->group(function () {
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

    // Transactions
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');

    // Remittances (read-only: COD cash handoffs rider -> seller, center-scoped)
    Route::get('remittances', [RemittanceController::class, 'index'])->name('remittances.index');
    Route::get('remittances/export', [RemittanceController::class, 'export'])->name('remittances.export');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports/export/csv', [ReportController::class, 'exportCsv'])->name('reports.export.csv');

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
