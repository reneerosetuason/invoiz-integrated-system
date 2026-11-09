// ignore_for_file: avoid_relative_lib_imports
import 'dart:convert';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/core/theme/app_theme.dart';
import '../lib/models/rider.dart';
import '../lib/models/hub_courier.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/providers/rider_provider.dart';
import '../lib/providers/delivery_provider.dart';
import '../lib/providers/earnings_provider.dart';
import '../lib/providers/notification_provider.dart';
import '../lib/services/rider_service.dart';
import '../lib/services/delivery_service.dart';
import '../lib/services/earnings_service.dart';
import '../lib/services/notification_service.dart';
import '../lib/providers/remittance_provider.dart';
import '../lib/services/remittance_service.dart';
import '../lib/screens/remittances/remittances_screen.dart';
import '../lib/screens/deliveries/return_collections_screen.dart';
import '../lib/screens/messages/conversations_screen.dart';
import '../lib/screens/auth/login_screen.dart';
import '../lib/screens/apply/application_type_select_screen.dart';
import '../lib/screens/apply/apply_screen.dart';
import '../lib/screens/apply/hub_transport_driver_apply_screen.dart';
import 'support/psgc_fixture.dart';
import '../lib/screens/onboarding/rider_tutorial_screen.dart';

import '../lib/screens/dashboard/dashboard_screen.dart';
import '../lib/screens/home/hub_courier_home_screen.dart';
import '../lib/screens/home/hub_transfers_screen.dart';
import '../lib/screens/home/home_shell.dart';
import '../lib/screens/profile/profile_screen.dart';
import '../lib/screens/settings/settings_screen.dart';
import '../lib/screens/earnings/earnings_screen.dart';
import '../lib/screens/earnings/history_screen.dart';
import '../lib/screens/deliveries/deliveries_screen.dart';
import '../lib/screens/deliveries/delivery_detail_screen.dart';
import '../lib/screens/remittances/hub_courier_remittances_screen.dart';
import '../lib/widgets/receipt_confirmation.dart';
import '../lib/screens/scan/parcel_scan_controller.dart';
import '../lib/screens/scan/scan_parcel_screen.dart';
import '../lib/models/logistics_account.dart';
import '../lib/screens/logistics/logistics_scan_screen.dart';

class _MemoryStorage extends TokenStorage {
  @override
  Future<void> saveToken(String token) async {}
  @override
  Future<void> saveUser(String user) async {}
  @override
  Future<String?> readToken() async => null;
  @override
  Future<String?> readUser() async => null;
  @override
  Future<void> clear() async {}
}

const _name = 'Alexandria Maria Dela Cruz';
const _email = 'alexandria.delacruz@logistics.example.test';
Map<String, dynamic> _logisticsPayload({bool readOnly = false}) => {
  'account_type': readOnly ? 'platform_admin' : 'logistics_staff',
  'user': {'id': 3, 'name': _name, 'email': _email, 'phone': '09171234567'},
  'logistics': {
    'scope': readOnly ? 'global' : 'hub',
    'can_scan': !readOnly,
    'company': {'id': 1, 'name': 'INVOIZ Provincial Logistics Company'},
    'facility': {
      'id': 1,
      'name': 'San Antonio Provincial Logistics Hub',
      'city': 'San Antonio',
      'address': '123 Provincial Highway',
    },
  },
};
const _rider = {
  'id': 1,
  'name': _name,
  'email': _email,
  'phone': '09171234567',
  'vehicle_type': 'motorcycle',
  'license_plate': 'LONG-PLATE-1234',
  'status': 'available',
  'is_online': false,
  'can_go_online': true,
};
const _courier = {
  'id': 2,
  'name': _name,
  'email': _email,
  'company': 'INVOIZ Provincial Logistics Company',
  'phone': '09171234567',
  'vehicle_arrangement': 'company',
  'is_online': false,
  'status': 'available',
  'has_assigned_truck': true,
  'vehicle': {
    'plate_number': 'DEV-TEST-TRK-01',
    'type': 'light truck',
    'status': 'assigned',
    'parcel_capacity': 150,
    'capacity_kg': 3000,
  },
};
const _delivery = {
  'id': 1,
  'tracking_number': 'TRK-20261106-LONG-TRACKING-001',
  'status': 'out_for_delivery',
  'status_label': 'Out for Delivery',
  'shop': {
    'name': 'Alexandria Provincial Store',
    'address': '123 Provincial Highway, Barangay San Antonio',
  },
  'customer': {
    'name': _name,
    'address': '123 Provincial Highway, Barangay San Antonio',
    'phone': '09171234567',
  },
  'items': [
    {
      'name': 'Large parcel with a long product description',
      'quantity': 2,
      'price': 1299.99,
    },
  ],
  'subtotal': 2599.98,
  'total': 2649.98,
  'delivery_fee': 50,
  'payment_method': 'gcash',
  'amount_to_collect': 0,
};
const _transfer = {
  'id': 1,
  'reference': 'HT-20261106-LONG-REFERENCE',
  'status': 'ready_for_dispatch',
  'origin': {'name': 'San Antonio Provincial Logistics Hub'},
  'destination': {'name': 'Lumban Provincial Logistics Hub'},
  'vehicle': {'plate_number': 'DEV-TEST-TRK-01', 'type': 'light truck'},
  'parcel_count': 123,
  'loaded_count': 123,
  'total_weight_kg': 1234.56,
  'parcels': [],
  'events': [],
};
Object _response(String path) {
  if (path.endsWith('/remittances/incoming')) {
    return {
      'remittances': [
        {
          'id': 1,
          'amount': 123456.78,
          'status': 'remitted',
          'order_number': 'INV-LONG-ORDER-REFERENCE',
          'bank_reference': 'LONG-BANK-REFERENCE-123456789',
          'recipient_role': 'last_mile_rider',
        },
      ],
    };
  }
  if (path.endsWith('/rider/remittances')) {
    return {
      'remittances': [],
      'summary': {'total_due': 123456.78, 'total_confirmed': 123456.78},
    };
  }
  if (path.endsWith('/rider/returns')) {
    return {
      'returns': [
        {
          'id': 1,
          'order_number': 'INV-LONG-ORDER-REFERENCE',
          'tracking_number': 'TRK-LONG-REFERENCE-123456789',
          'stage': 'assigned',
          'buyer_name': _name,
          'buyer_address': '123 Provincial Highway, Barangay San Antonio',
          'product_name': 'Large parcel with a long product description',
        },
      ],
    };
  }
  if (path.endsWith('/rider/conversations')) {
    return {'conversations': [], 'has_more': false};
  }
  if (path.endsWith('/courier/dashboard')) {
    return {
      'courier': _courier,
      'active_transfer': _transfer,
      'available_transfers': [_transfer],
      'completed_transfers': [_transfer],
      'earnings_summary': {
        'total_earnings': 123456.78,
        'base_earnings': 123456.78,
        'completed_trips_count': 1234,
      },
      'recent_incidents': [],
    };
  }
  if (path.endsWith('/courier/transfers')) {
    return {
      'transfers': [_transfer],
    };
  }
  if (path.endsWith('/courier/remittances')) {
    return {
      'remittances': [
        {
          'id': 1,
          'amount': 123456.78,
          'status': 'remitted',
          'order_number': 'INV-20261106-LONG-REFERENCE',
          'bank_reference': 'BANK-LONG-TRANSFER-REFERENCE-123',
          'transfer_reference': 'HT-LONG-REFERENCE',
        },
      ],
      'summary': {
        'accepted': 123456.78,
        'remitted': 123456.78,
        'awaiting_release': 123456.78,
      },
    };
  }
  if (path.endsWith('/rider/profile')) return {'rider': _rider};
  if (path.endsWith('/rider/dashboard')) {
    return {
      'rider': _rider,
      'today_earnings': 123456.78,
      'stats': {'assigned': 1234, 'pending': 1234, 'completed': 1234},
      'current_delivery': _delivery,
      'upcoming_pickup': _delivery,
      'recent_completed': [_delivery],
    };
  }
  if (path.endsWith('/rider/deliveries/1')) return {'delivery': _delivery};
  if (path.endsWith('/rider/deliveries')) {
    return {
      'deliveries': [_delivery],
    };
  }
  if (path.endsWith('/rider/earnings')) {
    return {
      'today': 123456.78,
      'this_week': 123456.78,
      'this_month': 123456.78,
      'history': [
        {'date': '2026-11-06', 'amount': 123456.78, 'deliveries': 1234},
      ],
    };
  }
  if (path.endsWith('/rider/history')) return {'history': []};
  return {'notifications': []};
}

void main() {
  setUpAll(() async {
    for (final family in ['Inter', 'Fraunces', 'MaterialIcons']) {
      final asset = family == 'MaterialIcons'
          ? 'fonts/MaterialIcons-Regular.otf'
          : 'assets/fonts/$family.ttf';
      await (FontLoader(family)..addFont(rootBundle.load(asset))).load();
    }
  });
  for (final width in [320.0, 390.0]) {
    testWidgets(
      'iOS receipt alert fits ${width.toInt()}px at 200% text and requires an explicit choice',
      (tester) async {
        tester.view.physicalSize = Size(width, 568);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        bool? received;
        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.light(),
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: const TextScaler.linear(2)),
              child: child!,
            ),
            home: Builder(
              builder: (context) => Scaffold(
                body: Center(
                  child: TextButton(
                    onPressed: () async {
                      received = await showReceiptConfirmation(
                        context,
                        title: 'Confirm payout received',
                        message:
                            'Have you received the full payment? Confirm only after checking the actual transfer in your account.',
                        cancelLabel: 'Not yet',
                        confirmLabel: 'Yes, received',
                      );
                    },
                    child: const Text('Review'),
                  ),
                ),
              ),
            ),
          ),
        );
        await tester.tap(find.text('Review'));
        await tester.pumpAndSettle();
        expect(find.byType(CupertinoAlertDialog), findsOneWidget);
        expect(received, isNull);
        await tester.tap(find.text('Not yet'));
        await tester.pumpAndSettle();
        expect(received, isFalse);
        await tester.tap(find.text('Review'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Yes, received'));
        await tester.pumpAndSettle();
        expect(received, isTrue);
        expect(tester.takeException(), isNull);
      },
    );
  }
  final pages = <String, Widget>{
    'dashboard': const DashboardScreen(),
    'deliveries': const DeliveriesScreen(),
    'detail': const DeliveryDetailScreen(deliveryId: 1),
    'earnings': const EarningsScreen(),
    'history': const HistoryScreen(),
    'profile': const ProfileScreen(),
    'settings': const SettingsScreen(),
    'hub-dashboard': const HubCourierHomeScreen(),
    'hub-transfers': const HubTransfersScreen(),
    'hub-profile': const ProfileScreen(),
    'hub-remittances': const HubCourierRemittancesScreen(),
    'rider-shell': const HomeShell(),
    'hub-shell': const HomeShell(),
    'remittances': const RemittancesScreen(),
    'returns': const ReturnCollectionsScreen(),
    'messages': const ConversationsScreen(),
    'login': const LoginScreen(),
    'application-types': const ApplicationTypeSelectScreen(),
    'apply': const ApplyScreen(),
    'hub-apply': const HubTransportDriverApplyScreen(),
    'tutorial': const RiderTutorialScreen(workflowType: 'delivery_rider'),
    'hub-tutorial': const RiderTutorialScreen(workflowType: 'hub_courier'),
    'scan': const SizedBox(),
    'logistics-scan': const SizedBox(),
    'logistics-readonly': const SizedBox(),
  };
  for (final config in [
    (320.0, 1.4),
    (390.0, 1.0),
    (390.0, 2.0),
    (844.0, 1.4),
  ]) {
    for (final entry in pages.entries) {
      testWidgets(
        '${entry.key} ${config.$1.toInt()}px text ${config.$2} has no overflow',
        (tester) async {
          tester.view.physicalSize = Size(
            config.$1,
            config.$1 > 800 ? 390 : 844,
          );
          tester.view.devicePixelRatio = 1;
          tester.view.padding = const FakeViewPadding(top: 47, bottom: 34);
          tester.view.viewPadding = const FakeViewPadding(top: 47, bottom: 34);
          addTearDown(tester.view.resetPhysicalSize);
          addTearDown(tester.view.resetDevicePixelRatio);
          addTearDown(tester.view.resetPadding);
          addTearDown(tester.view.resetViewPadding);
          SharedPreferences.setMockInitialValues({
            'invoiz_delivery_rider_tutorial_completed': true,
            'invoiz_hub_courier_tutorial_completed': true,
          });
          final api = ApiClient(
            client: MockClient(
              (r) async =>
                  psgcFixture(r) ??
                  http.Response(
                    jsonEncode(
                      r.url.path.endsWith('/scan/context')
                          ? _logisticsPayload(
                              readOnly: entry.key == 'logistics-readonly',
                            )
                          : _response(r.url.path),
                    ),
                    200,
                    headers: {'content-type': 'application/json'},
                  ),
            ),
          );
          final auth = AuthProvider(api: api, storage: _MemoryStorage());
          if (entry.key.startsWith('logistics-')) {
            auth.updateLogistics(
              LogisticsAccount.fromJson(
                _logisticsPayload(readOnly: entry.key == 'logistics-readonly'),
              ),
            );
          } else if (entry.key.startsWith('hub-')) {
            auth.updateCourier(
              HubCourier.fromJson(Map<String, dynamic>.from(_courier)),
            );
          } else {
            auth.updateRider(Rider.fromJson(Map<String, dynamic>.from(_rider)));
          }
          final rider = RiderProvider(RiderService(api));
          final delivery = DeliveryProvider(DeliveryService(api));
          final earnings = EarningsProvider(EarningsService(api));
          await rider.loadProfile();
          await rider.loadDashboard();
          await delivery.load();
          await earnings.loadSummary();
          final remittance = RemittanceProvider(RemittanceService(api));
          await remittance.load();
          final scan = entry.key == 'scan'
              ? ParcelScanController(
                  DeliveryService(api),
                  requestCameraPermission: () async => PermissionStatus.granted,
                  isCameraPermanentlyDenied: () async => false,
                  openSystemSettings: () async {},
                )
              : null;
          final screen = switch (entry.key) {
            'apply' => ApplyScreen(apiClient: api),
            'hub-apply' => HubTransportDriverApplyScreen(apiClient: api),
            'scan' => ScanParcelScreen(cameraEnabled: false, controller: scan),
            'logistics-scan' || 'logistics-readonly' =>
              const LogisticsScanScreen(cameraEnabled: false),
            _ => entry.value,
          };
          final errors = <String>[];
          final handler = FlutterError.onError;
          FlutterError.onError = (details) => errors.add(details.toString());
          try {
            await tester.pumpWidget(
              MultiProvider(
                providers: [
                  ChangeNotifierProvider.value(value: auth),
                  ChangeNotifierProvider.value(value: rider),
                  ChangeNotifierProvider.value(value: delivery),
                  ChangeNotifierProvider.value(value: earnings),
                  ChangeNotifierProvider.value(value: remittance),
                  ChangeNotifierProvider(
                    create: (_) =>
                        NotificationProvider(NotificationService(api)),
                  ),
                ],
                child: MaterialApp(
                  debugShowCheckedModeBanner: false,
                  theme: AppTheme.light(),
                  builder: (context, child) => MediaQuery(
                    data: MediaQuery.of(
                      context,
                    ).copyWith(textScaler: TextScaler.linear(config.$2)),
                    child: child!,
                  ),
                  home: screen,
                ),
              ),
            );
            for (var i = 0; i < 8; i++) {
              await tester.pump(const Duration(milliseconds: 200));
            }
            if (const bool.fromEnvironment('RIDER_IOS_PREVIEW')) {
              if (config.$1 == 390 &&
                  config.$2 == 1 &&
                  [
                    'rider-shell',
                    'hub-shell',
                    'profile',
                    'settings',
                    'login',
                  ].contains(entry.key)) {
                await expectLater(
                  find.byType(MaterialApp),
                  matchesGoldenFile('../../.tools/rider-ios-${entry.key}.png'),
                );
              }
            }
            final scrollables = find.byType(Scrollable);
            if (scrollables.evaluate().isNotEmpty) {
              for (var i = 0; i < 12; i++) {
                await tester.drag(scrollables.first, const Offset(0, -450));
                await tester.pump(const Duration(milliseconds: 250));
              }
            }
            await tester.pumpWidget(const SizedBox());
            await tester.pump(const Duration(milliseconds: 500));
          } finally {
            FlutterError.onError = handler;
            auth.dispose();
            rider.dispose();
            delivery.dispose();
            earnings.dispose();
            remittance.dispose();
            scan?.dispose();
          }
          expect(
            errors,
            isEmpty,
            reason: '${entry.key} ${config.$1}px text ${config.$2}',
          );
        },
      );
    }
  }
}
