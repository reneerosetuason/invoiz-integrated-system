import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/models/rider.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/providers/rider_provider.dart';
import '../lib/screens/profile/profile_screen.dart';
import '../lib/services/application_service.dart';
import '../lib/services/rider_service.dart';

void main() {
  group('Rider profile parsing', () {
    test('parses license details, company, status and documents', () {
      final rider = Rider.fromJson({
        'id': 7,
        'name': 'Juan Dela Cruz',
        'email': 'juan@example.com',
        'phone': '09171234567',
        'vehicle_type': 'motorcycle',
        'license_plate': 'ABC 1234',
        'license_number': 'N01-23-456789',
        'license_expiry': '2030-05-31',
        'vehicle_registration': 'REG-7788',
        'company': 'Invoiz Logistics Laguna',
        'service_area': 'Santa Cruz',
        'status': 'available',
        'is_online': true,
        'documents': [
          {
            'id': 11,
            'type': 'drivers_license',
            'label': "Driver's License",
            'name': 'license.jpg',
            'url': 'http://example.test/api/rider/documents/11?signature=abc',
            'mime_type': 'image/jpeg',
            'is_image': true,
            'size': '12.3 KB',
          },
        ],
      });

      expect(rider.licenseNumber, 'N01-23-456789');
      expect(rider.licenseExpiry, '2030-05-31');
      expect(rider.vehicleRegistration, 'REG-7788');
      expect(rider.company, 'Invoiz Logistics Laguna');
      expect(rider.serviceArea, 'Santa Cruz');
      expect(rider.status, 'available');
      expect(rider.documents, hasLength(1));
      expect(rider.documents.first.label, "Driver's License");
      expect(rider.documents.first.isImage, isTrue);
      expect(rider.documents.first.size, '12.3 KB');
      expect(rider.documents.first.url, contains('signature='));
    });

    test('parses licence compliance flags and online block reason', () {
      final rider = Rider.fromJson({
        'id': 8,
        'name': 'Expired Rider',
        'email': 'expired@example.com',
        'phone': '09170000000',
        'license_number': 'LIC-9',
        'license_expiry': '2020-01-01',
        'license_status': 'expired',
        'license_expired': true,
        'license_expiring_soon': false,
        'can_go_online': false,
        'online_block_reason': "Driver's license expired on Jan 01, 2020",
      });

      expect(rider.licenseExpired, isTrue);
      expect(rider.licenseStatus, 'expired');
      expect(rider.canGoOnline, isFalse);
      expect(rider.onlineBlockReason, contains('expired'));

      final copy = rider.copyWith(isOnline: false);
      expect(copy.licenseExpired, isTrue);
      expect(copy.canGoOnline, isFalse);
    });

    test('defaults documents to an empty list when absent', () {
      final rider = Rider.fromJson({
        'id': 1,
        'name': 'No Docs',
        'email': 'nodocs@example.com',
        'phone': '09000000000',
      });

      expect(rider.documents, isEmpty);
      expect(rider.licenseExpiry, isNull);
    });

    test('copyWith preserves the profile detail fields', () {
      final rider = Rider.fromJson({
        'id': 1,
        'name': 'Keep Details',
        'email': 'keep@example.com',
        'phone': '09000000000',
        'license_number': 'LIC-1',
        'license_expiry': '2031-01-01',
        'vehicle_registration': 'REG-1',
        'company': 'Company X',
        'documents': [
          {'id': 2, 'type': 'valid_id', 'label': 'Valid ID', 'name': 'id.png'},
        ],
      });

      final updated = rider.copyWith(isOnline: true);

      expect(updated.licenseNumber, 'LIC-1');
      expect(updated.licenseExpiry, '2031-01-01');
      expect(updated.vehicleRegistration, 'REG-1');
      expect(updated.company, 'Company X');
      expect(updated.documents, hasLength(1));
      expect(updated.isOnline, isTrue);
    });
  });

  test('application submission sends the license expiry field', () async {
    String? body;
    final service = ApplicationService(
      ApiClient(
        client: MockClient((http.BaseRequest request) async {
          if (request is http.Request) {
            body = utf8.decode(request.bodyBytes);
          }
          return http.Response(
            jsonEncode({
              'message': 'Application submitted successfully.',
              'application': {
                'id': 42,
                'status': 'pending',
                'submitted_via': 'mobile',
              },
            }),
            201,
            headers: {'content-type': 'application/json'},
          );
        }),
      ),
    );

    final result = await service.submit(
      name: '  Juan   Dela   Cruz ',
      email: 'juan@example.com',
      phone: '09171234567',
      address: '123 Test St',
      vehicleType: 'motorcycle',
      licensePlate: 'ABC 1234',
      licenseNumber: 'N01-23-456789',
      licenseExpiry: '2030-05-31',
      vehicleRegistration: 'REG-7788',
      riderType: 'full_time',
      vehicleOwnership: 'own',
      acceptedTerms: true,
      documents: {
        'valid_id': (bytes: Uint8List.fromList([1, 2, 3]), filename: 'id.png'),
      },
    );

    expect(result.id, 42);
    expect(body, isNotNull);
    expect(body, contains('Juan Dela Cruz'));
    expect(body, contains('name="license_expiry"'));
    expect(body, contains('2030-05-31'));
    expect(body, contains('N01-23-456789'));
  });

  testWidgets('profile screen shows license, company, status and documents', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = ApiClient(
      client: MockClient((http.BaseRequest request) async {
        expect(request.url.path, endsWith('/rider/profile'));
        return http.Response(
          jsonEncode({
            'rider': {
              'id': 7,
              'name': 'Juan Dela Cruz',
              'email': 'juan@example.com',
              'phone': '09171234567',
              'vehicle_type': 'motorcycle',
              'license_plate': 'ABC 1234',
              'license_number': 'N01-23-456789',
              'license_expiry': '2030-05-31',
              'vehicle_registration': 'REG-7788',
              'company': 'Invoiz Logistics Laguna',
              'service_area': 'Santa Cruz',
              'status': 'available',
              'is_online': true,
              'documents': [
                {
                  'id': 11,
                  'type': 'drivers_license',
                  'label': "Driver's License",
                  'name': 'license.jpg',
                  'url': 'http://example.test/api/rider/documents/11?sig=abc',
                  'mime_type': 'image/jpeg',
                  'is_image': true,
                  'size': '12.3 KB',
                },
              ],
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final riderProvider = RiderProvider(RiderService(api));
    await riderProvider.loadProfile();

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider.value(value: riderProvider),
          ChangeNotifierProvider(
            create: (_) => AuthProvider(api: api, storage: TokenStorage()),
          ),
        ],
        child: const MaterialApp(home: ProfileScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Invoiz Logistics Laguna'), findsOneWidget);
    expect(find.text('Service Area'), findsOneWidget);
    expect(find.text('N01-23-456789'), findsOneWidget);
    expect(find.text('2030-05-31'), findsOneWidget);
    expect(find.text('REG-7788'), findsOneWidget);
    expect(find.text('Documents'), findsOneWidget);
    expect(find.text("Driver's License"), findsOneWidget);
    expect(find.text('license.jpg'), findsOneWidget);
    expect(find.text('View'), findsOneWidget);
  });
}
