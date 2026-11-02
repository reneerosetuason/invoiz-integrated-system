import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/models/hub_courier.dart';
import '../lib/models/rider.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/providers/rider_provider.dart';
import '../lib/screens/profile/profile_screen.dart';
import '../lib/screens/settings/settings_screen.dart';
import '../lib/services/rider_service.dart';

class _FakeMemoryTokenStorage extends TokenStorage {
  String? token;
  String? user;

  @override
  Future<void> saveToken(String value) async => token = value;
  @override
  Future<String?> readToken() async => token;
  @override
  Future<void> deleteToken() async => token = null;
  @override
  Future<void> saveUser(String value) async => user = value;
  @override
  Future<String?> readUser() async => user;
  @override
  Future<void> deleteUser() async => user = null;
  @override
  Future<void> clear() async {
    token = null;
    user = null;
  }
}

class _TestPhotoPicker extends ProfilePhotoPicker {
  const _TestPhotoPicker({this.mockResult});
  final ({Uint8List bytes, String filename})? mockResult;

  @override
  Future<({Uint8List bytes, String filename})?> pickPhoto() async {
    return mockResult;
  }
}

void main() {
  group('Settings Screen', () {
    testWidgets('displays account, notifications, privacy, support and logout without appearance or region', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final api = ApiClient(client: MockClient((_) async => http.Response('{}', 200)));
      final auth = AuthProvider(api: api, storage: _FakeMemoryTokenStorage());

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AuthProvider>.value(value: auth),
          ],
          child: const MaterialApp(home: SettingsScreen()),
        ),
      );
      await tester.pumpAndSettle();

      // Check expected section headers (rendered in uppercase by SectionEyebrow)
      expect(find.text('ACCOUNT & SECURITY'), findsOneWidget);
      expect(find.text('NOTIFICATION PREFERENCES'), findsOneWidget);
      expect(find.text('PRIVACY & SECURITY'), findsOneWidget);
      expect(find.text('HELP & SUPPORT'), findsOneWidget);

      // Check expected working options
      expect(find.text('Profile Information'), findsOneWidget);
      expect(find.text('Change Password'), findsOneWidget);
      expect(find.text('Notification Center'), findsOneWidget);
      expect(find.text('Alert Preferences'), findsOneWidget);
      expect(find.text('Privacy Policy & Data Usage'), findsOneWidget);
      expect(find.text('Device Permissions'), findsOneWidget);
      expect(find.text('Workflow Tutorial'), findsOneWidget);
      expect(find.text('Logistics Support Desk'), findsOneWidget);
      expect(find.text('Log Out'), findsOneWidget);

      // Verify Appearance, Language, and Region are absent
      expect(find.text('Appearance'), findsNothing);
      expect(find.text('Theme'), findsNothing);
      expect(find.text('Language'), findsNothing);
      expect(find.text('Region'), findsNothing);
      expect(find.text('Timezone'), findsNothing);

      // Tapping Privacy Policy opens working dialog
      await tester.tap(find.text('Privacy Policy & Data Usage'));
      await tester.pumpAndSettle();
      expect(find.text('Location Tracking Policy'), findsOneWidget);
      expect(find.text('Document & Photo Security'), findsOneWidget);
      await tester.tap(find.text('Understood'));
      await tester.pumpAndSettle();

      // Tapping Log Out opens confirmation dialog
      await tester.tap(find.text('Log Out'));
      await tester.pumpAndSettle();
      expect(find.text('Log out?'), findsOneWidget);
      expect(find.text('Cancel'), findsOneWidget);
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
    });
  });

  group('Profile Screen - Hub Transport Driver', () {
    testWidgets('renders hub transport driver profile with truck info and protected fields', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final courier = HubCourier.fromJson({
        'id': 12,
        'name': 'Pedro Driver',
        'email': 'pedro.courier@test.local',
        'phone': '09181234567',
        'company': 'Inter-Hub Cargo Logistics',
        'license_number': 'N02-88-765432',
        'status': 'active',
        'is_online': true,
        'vehicle_arrangement': 'company',
        'verification_status': 'approved',
        'has_assigned_truck': true,
        'vehicle': {
          'id': 4,
          'name': 'Isuzu 6-Wheeler Forward',
          'type': 'Box Truck',
          'model': 'FRR90',
          'plate_number': 'TRK-9876',
          'parcel_capacity': 500,
          'capacity_kg': 4000.0,
          'status': 'active',
          'ownership_type': 'company',
          'verification_status': 'approved',
          'is_assignable': true,
        },
      });

      final storage = _FakeMemoryTokenStorage();
      final api = ApiClient(client: MockClient((_) async => http.Response('{}', 200)));
      final auth = AuthProvider(api: api, storage: storage);
      auth.updateCourier(courier);

      final riderProvider = RiderProvider(RiderService(api));

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AuthProvider>.value(value: auth),
            ChangeNotifierProvider<RiderProvider>.value(value: riderProvider),
          ],
          child: const MaterialApp(home: ProfileScreen()),
        ),
      );
      await tester.pumpAndSettle();

      // Verify Hub Transport Driver branding and details
      expect(find.text('Pedro Driver'), findsOneWidget);
      expect(find.text('pedro.courier@test.local'), findsAtLeastNWidgets(1));
      expect(find.text('09181234567'), findsOneWidget);
      expect(find.text('Hub Transport Driver'), findsOneWidget);
      expect(find.text('Inter-Hub Cargo Logistics'), findsOneWidget);
      expect(find.text('Company Fleet'), findsOneWidget);
      expect(find.text('Isuzu 6-Wheeler Forward'), findsOneWidget);
      expect(find.text('TRK-9876'), findsOneWidget);
      expect(find.text('N02-88-765432'), findsOneWidget);
      expect(find.text('500 parcels (4000.0 kg)'), findsOneWidget);
      expect(find.text('Approved'), findsOneWidget);

      // Verify protected notice is displayed
      expect(
        find.textContaining('Company, center assignments, and vehicle approval status are managed by Logistics Administration'),
        findsOneWidget,
      );
    });

    testWidgets('courier can edit phone and changes persist to storage', (tester) async {
      var patchCalls = 0;
      final api = ApiClient(
        client: MockClient((request) async {
          if (request.method == 'PATCH' && request.url.path.endsWith('/courier/profile')) {
            patchCalls++;
            final body = jsonDecode(request.body) as Map<String, dynamic>;
            return http.Response(
              jsonEncode({
                'courier': {
                  'id': 12,
                  'name': 'Pedro Driver',
                  'email': 'pedro.courier@test.local',
                  'phone': body['phone'],
                  'company': 'Inter-Hub Cargo Logistics',
                  'status': 'active',
                  'is_online': true,
                },
              }),
              200,
              headers: {'content-type': 'application/json'},
            );
          }
          return http.Response('{}', 200);
        }),
      );

      final storage = _FakeMemoryTokenStorage();
      final auth = AuthProvider(api: api, storage: storage);
      auth.updateCourier(HubCourier.fromJson({
        'id': 12,
        'name': 'Pedro Driver',
        'email': 'pedro.courier@test.local',
        'phone': '09181234567',
        'company': 'Inter-Hub Cargo Logistics',
        'status': 'active',
      }));

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AuthProvider>.value(value: auth),
            ChangeNotifierProvider<RiderProvider>(create: (_) => RiderProvider(RiderService(api))),
          ],
          child: const MaterialApp(
            home: EditProfileScreen(
              initialPhone: '09181234567',
              isCourier: true,
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextFormField).first, '09189990000');
      await tester.tap(find.text('Save Changes'));
      await tester.pumpAndSettle();

      expect(patchCalls, 1);
      expect(auth.courier?.phone, '09189990000');
      expect(storage.user, contains('09189990000'));
    });
  });

  group('Profile Screen - Photo Uploading', () {
    testWidgets('delivery rider can upload photo and it updates avatar', (tester) async {
      var uploadCalls = 0;
      final api = ApiClient(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/rider/profile/photo')) {
            uploadCalls++;
            return http.Response(
              jsonEncode({
                'message': 'Profile photo updated successfully.',
                'rider': {
                  'id': 5,
                  'name': 'Juan Rider',
                  'email': 'juan@test.local',
                  'phone': '09171112222',
                  'avatar': 'http://example.test/storage/avatars/new_rider.jpg',
                  'status': 'available',
                },
              }),
              200,
              headers: {'content-type': 'application/json'},
            );
          }
          return http.Response('{}', 200);
        }),
      );

      final storage = _FakeMemoryTokenStorage();
      final auth = AuthProvider(api: api, storage: storage);
      final rider = Rider.fromJson({
        'id': 5,
        'name': 'Juan Rider',
        'email': 'juan@test.local',
        'phone': '09171112222',
        'status': 'available',
      });
      auth.updateRider(rider);

      final riderProvider = RiderProvider(RiderService(api));

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AuthProvider>.value(value: auth),
            ChangeNotifierProvider<RiderProvider>.value(value: riderProvider),
          ],
          child: MaterialApp(
            home: ProfileScreen(
              photoPicker: _TestPhotoPicker(
                mockResult: (bytes: Uint8List.fromList([1, 2, 3]), filename: 'avatar.jpg'),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Tap the camera button on the avatar
      await tester.tap(find.byIcon(Icons.camera_alt));
      await tester.pumpAndSettle();

      expect(uploadCalls, 1);
      expect(find.text('Profile photo updated successfully.'), findsOneWidget);
      expect(auth.rider?.avatar, 'http://example.test/storage/avatars/new_rider.jpg');
      expect(storage.user, contains('new_rider.jpg'));
    });

    testWidgets('courier can upload photo and it updates avatar', (tester) async {
      var uploadCalls = 0;
      final api = ApiClient(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/courier/profile/photo')) {
            uploadCalls++;
            return http.Response(
              jsonEncode({
                'message': 'Profile photo updated successfully.',
                'courier': {
                  'id': 12,
                  'name': 'Pedro Driver',
                  'email': 'pedro.courier@test.local',
                  'phone': '09181234567',
                  'company': 'Inter-Hub Cargo Logistics',
                  'avatar': 'http://example.test/storage/avatars/new_courier.jpg',
                  'status': 'active',
                },
              }),
              200,
              headers: {'content-type': 'application/json'},
            );
          }
          return http.Response('{}', 200);
        }),
      );

      final storage = _FakeMemoryTokenStorage();
      final auth = AuthProvider(api: api, storage: storage);
      auth.updateCourier(HubCourier.fromJson({
        'id': 12,
        'name': 'Pedro Driver',
        'email': 'pedro.courier@test.local',
        'company': 'Inter-Hub Cargo Logistics',
        'status': 'active',
      }));

      final riderProvider = RiderProvider(RiderService(api));

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AuthProvider>.value(value: auth),
            ChangeNotifierProvider<RiderProvider>.value(value: riderProvider),
          ],
          child: MaterialApp(
            home: ProfileScreen(
              photoPicker: _TestPhotoPicker(
                mockResult: (bytes: Uint8List.fromList([4, 5, 6]), filename: 'courier_photo.png'),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Tap the camera button on the avatar
      await tester.tap(find.byIcon(Icons.camera_alt));
      await tester.pumpAndSettle();

      expect(uploadCalls, 1);
      expect(find.text('Profile photo updated successfully.'), findsOneWidget);
      expect(auth.courier?.avatar, 'http://example.test/storage/avatars/new_courier.jpg');
      expect(storage.user, contains('new_courier.jpg'));
    });
  });
}
