import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/models/logistics_account.dart';
import '../lib/models/logistics_scan.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/screens/home/home_shell.dart';
import '../lib/screens/home/role_home.dart';
import '../lib/screens/logistics/logistics_scan_screen.dart';

const _secureChannel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

Map<String, dynamic> _staffPayload({bool canScan = true}) => {
  'account_type': 'logistics_staff',
  'role': 'logistics_staff',
  'user': {
    'id': 42,
    'name': 'Calamba Staff',
    'email': 'calamba.staff@test.local',
    'phone': '09171234567',
    'role': 'logistics_staff',
    'account_type': 'logistics_staff',
    'logistics': {
      'role': 'logistics_staff',
      'scope': 'hub',
      'can_scan': canScan,
      'company': {'id': 3, 'name': 'INVOIZ Logistics'},
      'facility': {
        'id': 9,
        'name': 'Calamba Hub',
        'address': '1 Calamba Road',
        'city': 'Calamba',
      },
    },
  },
  'logistics': {
    'role': 'logistics_staff',
    'scope': 'hub',
    'can_scan': canScan,
    'company': {'id': 3, 'name': 'INVOIZ Logistics'},
    'facility': {
      'id': 9,
      'name': 'Calamba Hub',
      'address': '1 Calamba Road',
      'city': 'Calamba',
    },
  },
};

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final secureStore = <String, String>{};

  setUp(() {
    secureStore.clear();
    SharedPreferences.setMockInitialValues({});
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(_secureChannel, (call) async {
          final args =
              (call.arguments as Map?)?.cast<String, dynamic>() ??
              const <String, dynamic>{};
          final key = args['key'] as String?;
          switch (call.method) {
            case 'read':
              return secureStore[key];
            case 'write':
              secureStore[key!] = args['value'] as String;
              return null;
            case 'delete':
              secureStore.remove(key);
              return null;
            case 'deleteAll':
              secureStore.clear();
              return null;
            case 'readAll':
              return Map<String, String>.from(secureStore);
            case 'containsKey':
              return secureStore.containsKey(key);
          }
          return null;
        });
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(_secureChannel, null);
  });

  group('LogisticsAccount', () {
    test('parses the backend login payload with its facility', () {
      final account = LogisticsAccount.fromJson(_staffPayload());

      expect(account.id, 42);
      expect(account.role, 'logistics_staff');
      expect(account.roleLabel, 'Logistics Staff');
      expect(account.scopeLabel, 'Assigned hub');
      expect(account.canScan, isTrue);
      expect(account.companyName, 'INVOIZ Logistics');
      expect(account.facilityId, 9);
      expect(account.facilityName, 'Calamba Hub');
      expect(account.facilityCity, 'Calamba');
      expect(account.hasFacility, isTrue);
    });

    test('round-trips through the cached representation', () {
      final original = LogisticsAccount.fromJson(_staffPayload());
      final restored = LogisticsAccount.fromJson(original.toJson());

      expect(restored.id, original.id);
      expect(restored.role, original.role);
      expect(restored.canScan, original.canScan);
      expect(restored.facilityId, original.facilityId);
      expect(restored.facilityName, original.facilityName);
    });

    test('keeps a read-only platform administrator without a facility', () {
      final account = LogisticsAccount.fromJson({
        'account_type': 'platform_admin',
        'user': {
          'id': 1,
          'name': 'Platform Admin',
          'email': 'admin@test.local',
          'role': 'admin',
          'account_type': 'platform_admin',
          'logistics': {
            'scope': 'global',
            'can_scan': false,
            'facility': null,
            'company': null,
          },
        },
        'logistics': {
          'scope': 'global',
          'can_scan': false,
          'facility': null,
          'company': null,
        },
      });

      expect(account.roleLabel, 'Platform Administrator');
      expect(account.scopeLabel, 'All hubs');
      expect(account.canScan, isFalse);
      expect(account.hasFacility, isFalse);
    });
  });

  group('LogisticsScanOutcome', () {
    test('parses an eligible lookup with parcel details', () {
      final outcome = LogisticsScanOutcome.fromJson({
        'ok': true,
        'eligible': true,
        'error': null,
        'message': 'Parcel is ready to scan.',
        'delivery': {
          'tracking_number': 'TRK-20260101-ABCD',
          'recipient_name': 'Juan Dela Cruz',
          'parcel_status': 'received',
          'current_hub': {'id': 9, 'name': 'Calamba Hub'},
          'destination_hub': {'id': 10, 'name': 'Batangas Hub'},
          'service_area': 'Calamba',
          'weight': '1.50',
        },
        'facility': {'id': 9, 'name': 'Calamba Hub'},
      });

      expect(outcome.eligible, isTrue);
      expect(outcome.confirmed, isFalse);
      expect(outcome.parcel?.trackingNumber, 'TRK-20260101-ABCD');
      expect(outcome.parcel?.currentHub, 'Calamba Hub');
      expect(outcome.parcel?.destinationHub, 'Batangas Hub');
      expect(outcome.parcel?.statusLabel, 'received');
      expect(outcome.facilityName, 'Calamba Hub');
    });

    test('keeps the parcel details on a structured error', () {
      final outcome = LogisticsScanOutcome.fromJson({
        'ok': false,
        'eligible': false,
        'error': 'forbidden',
        'message': 'This parcel belongs to another logistics hub.',
        'delivery': {
          'tracking_number': 'TRK-20260101-ABCD',
          'parcel_status': 'received',
          'current_hub': {'id': 10, 'name': 'Batangas Hub'},
        },
        'history': [
          {
            'status': 'received',
            'notes': 'Received at hub.',
            'location': 'Batangas Hub',
            'actor': 'Hub Staff',
          },
        ],
      }, statusCode: 403);

      expect(outcome.ok, isFalse);
      expect(outcome.error, 'forbidden');
      expect(outcome.parcel?.currentHub, 'Batangas Hub');
      expect(outcome.history, hasLength(1));
      expect(outcome.history.first.location, 'Batangas Hub');
      expect(outcome.confirmed, isFalse);
    });

    test('only a successful, non-duplicate confirm counts as confirmed', () {
      final confirmed = LogisticsScanOutcome.fromJson({
        'ok': true,
        'already': false,
        'message': 'Parcel scanned successfully.',
      }, statusCode: 200);
      final duplicate = LogisticsScanOutcome.fromJson({
        'ok': true,
        'already': true,
        'message': 'Parcel was already scanned.',
      }, statusCode: 200);

      expect(confirmed.confirmed, isTrue);
      expect(duplicate.confirmed, isFalse);
      expect(duplicate.already, isTrue);
    });
  });

  group('homeForAuth', () {
    AuthProvider provider() =>
        AuthProvider(api: ApiClient(), storage: TokenStorage());

    test('routes logistics accounts to the scanning workspace', () {
      final auth = provider();
      auth.updateLogistics(LogisticsAccount.fromJson(_staffPayload()));

      expect(auth.isLogistics, isTrue);
      expect(homeForAuth(auth), isA<LogisticsScanScreen>());
    });

    test('keeps riders and hub drivers on the existing shell', () {
      final auth = provider();

      expect(auth.isLogistics, isFalse);
      expect(homeForAuth(auth), isA<HomeShell>());
    });
  });
}
