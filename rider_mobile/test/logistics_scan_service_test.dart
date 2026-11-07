import 'dart:convert';

import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/services/logistics_scan_service.dart';

const _tracking = 'TRK-20261107-ABCD';

Map<String, dynamic> _parcel(String status) => {
  'tracking_number': _tracking,
  'parcel_status': status,
  'current_hub': {'id': 12, 'name': 'Test Hub'},
};

void main() {
  setUp(() {
    dotenv.testLoad(
      fileInput: 'API_BASE_URL=https://invoiz.store/api/logistics',
    );
  });

  test(
    'context resolves the hub using the registered logistics API URL',
    () async {
      final api = ApiClient(
        client: MockClient((request) async {
          expect(request.method, 'GET');
          expect(
            request.url.toString(),
            'https://invoiz.store/api/logistics/scan/context',
          );
          expect(request.headers['Authorization'], 'Bearer scan-test');
          return http.Response(
            jsonEncode({
              'user': {'id': 42, 'name': 'Hub Staff'},
              'logistics': {
                'role': 'logistics_staff',
                'scope': 'hub',
                'can_scan': true,
                'facility': {'id': 12, 'name': 'Test Hub'},
              },
            }),
            200,
          );
        }),
      )..setToken('scan-test');
      final account = await LogisticsScanService(api).context();
      expect(account.canScan, isTrue);
      expect(account.facilityId, 12);
    },
  );

  test(
    'received parcel can be looked up and confirmed without a duplicated prefix',
    () async {
      var confirmed = false;
      final paths = <String>[];
      final api = ApiClient(
        client: MockClient((request) async {
          paths.add(request.url.path);
          expect(request.method, 'POST');
          expect(request.headers['Authorization'], 'Bearer scan-test');
          expect(jsonDecode(request.body), {'tracking_number': _tracking});
          switch (request.url.path) {
            case '/api/logistics/scan/lookup':
              return http.Response(
                jsonEncode({
                  'ok': true,
                  'eligible': true,
                  'already': false,
                  'delivery': _parcel('received'),
                }),
                200,
              );
            case '/api/logistics/scan/confirm':
              final duplicate = confirmed;
              confirmed = true;
              return http.Response(
                jsonEncode({
                  'ok': true,
                  'eligible': false,
                  'already': duplicate,
                  'delivery': _parcel('scanned'),
                }),
                200,
              );
            default:
              return http.Response(
                jsonEncode({'message': 'Route not found'}),
                404,
              );
          }
        }),
      )..setToken('scan-test');
      final service = LogisticsScanService(api);
      final lookup = await service.lookup(_tracking);
      expect(lookup.eligible, isTrue);
      expect(lookup.parcel?.parcelStatus, 'received');
      expect((await service.confirm(_tracking)).confirmed, isTrue);
      final repeat = await service.confirm(_tracking);
      expect(repeat.already, isTrue);
      expect(repeat.confirmed, isFalse);
      expect(paths, [
        '/api/logistics/scan/lookup',
        '/api/logistics/scan/confirm',
        '/api/logistics/scan/confirm',
      ]);
    },
  );

  for (final endpoint in ['lookup', 'confirm']) {
    test(
      '$endpoint retains backend custody restrictions and parcel details',
      () async {
        final api = ApiClient(
          client: MockClient((request) async {
            expect(request.url.path, '/api/logistics/scan/$endpoint');
            return http.Response(
              jsonEncode({
                'ok': false,
                'eligible': false,
                'error': 'transfer_in_progress',
                'message': 'Receiving must be finalized.',
                'delivery': _parcel('received'),
              }),
              409,
            );
          }),
        );
        final service = LogisticsScanService(api);
        final result = endpoint == 'lookup'
            ? await service.lookup(_tracking)
            : await service.confirm(_tracking);
        expect(result.eligible, isFalse);
        expect(result.confirmed, isFalse);
        expect(result.error, 'transfer_in_progress');
        expect(result.parcel?.trackingNumber, _tracking);
      },
    );
  }
}
