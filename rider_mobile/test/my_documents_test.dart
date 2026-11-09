import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/screens/profile/my_documents_screen.dart';

/// Picker boundary fake: widget tests cannot run the native file picker.
class FakeDocumentChangePicker extends DocumentChangePicker {
  FakeDocumentChangePicker({this.next});

  PickedChangeDocument? next;
  int calls = 0;

  @override
  Future<PickedChangeDocument?> pickSingle() async {
    calls++;
    return next;
  }
}

Uint8List _pngBytes([int size = 512]) {
  final bytes = Uint8List(size);
  bytes.setRange(
    0,
    8,
    const [0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A],
  );
  return bytes;
}

Map<String, dynamic> _overviewJson() => {
      'documents': [
        {
          'id': 1,
          'type': 'drivers_license',
          'label': "Driver's License",
          'name': 'license.png',
          'mime_type': 'image/png',
          'is_image': true,
          'size': '1.2 MB',
          'status': 'approved',
          'expires_on': '2025-03-01',
          'url': 'https://example.test/documents/1',
        },
        {
          'id': 2,
          'type': 'nbi_clearance',
          'label': 'NBI Clearance',
          'name': 'nbi.png',
          'mime_type': 'image/png',
          'is_image': true,
          'size': '0.4 MB',
          'status': 'approved',
          'expires_on': null,
          'url': 'https://example.test/documents/2',
        },
      ],
      'requests': [
        {
          'id': 9,
          'type': 'drivers_license',
          'type_label': "Driver's License",
          'status': 'rejected',
          'status_label': 'Rejected',
          'reason': 'License renewed at the LTO branch.',
          'rejection_reason':
              'The photo is blurry. Please upload a clearer scan.',
          'proposed_expiry': '2028-01-31',
          'previous_expiry': '2025-03-01',
          'submitted_at': '2026-10-01T10:00:00Z',
          'reviewed_at': '2026-10-02T09:00:00Z',
          'reviewer_name': 'Logistics Admin',
          'can_resubmit': true,
          'document': {
            'name': 'license-front.png',
            'mime_type': 'image/png',
            'is_image': true,
            'size': '1.0 MB',
            'exists': true,
            'url': 'https://example.test/change/9/proposed',
          },
          'previous_document': null,
        },
      ],
      'document_types': [
        {
          'value': 'drivers_license',
          'label': "Driver's License",
          'has_current': true,
          'pending': false,
        },
        {
          'value': 'nbi_clearance',
          'label': 'NBI Clearance',
          'has_current': true,
          'pending': false,
        },
      ],
      'license': {
        'expires_on': '2025-03-01',
        'status': 'expired',
        'expired': true,
        'expiring_soon': false,
        'pending_change': false,
        'can_go_online': false,
        'block_reason':
            "Driver's license expired on Mar 01, 2025",
      },
    };

({ApiClient api, List<String> calls}) _backend() {
  final calls = <String>[];

  final client = MockClient((request) async {
    calls.add('${request.method} ${request.url.path}');

    Map<String, String> headers() => {'content-type': 'application/json'};

    if (request.method == 'GET') {
      return http.Response(jsonEncode(_overviewJson()), 200, headers: headers());
    }

    return http.Response(
      jsonEncode({'message': 'submitted', 'request': {'id': 10}}),
      201,
      headers: headers(),
    );
  });

  return (api: ApiClient(client: client), calls: calls);
}

Future<void> _pump(WidgetTester tester, FakeDocumentChangePicker picker) async {
  final backend = _backend();
  await tester.pumpWidget(
    MaterialApp(
      home: MyDocumentsScreen(apiClient: backend.api, picker: picker),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('shows approved documents, request history and license state',
      (tester) async {
    await _pump(tester, FakeDocumentChangePicker());

    expect(find.text('My Documents'), findsOneWidget);
    expect(find.text('Approved Documents'), findsOneWidget);
    expect(find.text('NBI Clearance'), findsOneWidget);
    expect(find.text('Driver’s license expired'), findsOneWidget);
    expect(find.text('Rejected'), findsOneWidget);
    expect(
      find.text('The photo is blurry. Please upload a clearer scan.'),
      findsOneWidget,
    );
    expect(find.text('Submit a corrected document'), findsOneWidget);
    expect(find.text('Approved'), findsWidgets);
  });

  testWidgets('submission requires a document type and file', (tester) async {
    await _pump(tester, FakeDocumentChangePicker());

    await tester.tap(find.text('New Request'));
    await tester.pumpAndSettle();
    expect(find.text('Request Document Change'), findsOneWidget);

    await tester.ensureVisible(find.text('Submit for Approval'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Submit for Approval'));
    await tester.pumpAndSettle();

    expect(
      find.text('Select the document you want to replace.'),
      findsOneWidget,
    );
  });

  testWidgets('submits a replacement through the API and refreshes the list',
      (tester) async {
    final picker = FakeDocumentChangePicker(
      next: PickedChangeDocument(
        name: 'nbi-clearance.png',
        readBytes: () async => _pngBytes(),
      ),
    );
    final backend = _backend();

    await tester.pumpWidget(
      MaterialApp(
        home: MyDocumentsScreen(apiClient: backend.api, picker: picker),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('New Request'));
    await tester.pumpAndSettle();

    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('NBI Clearance').last);
    await tester.pumpAndSettle();

    await tester.tap(find.text('Choose a PDF, JPG, or PNG (max 5 MB)'));
    await tester.pumpAndSettle();
    expect(find.text('nbi-clearance.png'), findsOneWidget);

    await tester.ensureVisible(find.text('Submit for Approval'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Submit for Approval'));
    await tester.pumpAndSettle();

    expect(
      backend.calls.where((c) => c.startsWith('POST')).length,
      1,
      reason: 'the replacement must be uploaded exactly once',
    );
    expect(
      backend.calls.where((c) => c.startsWith('GET')).length,
      greaterThanOrEqualTo(2),
      reason: 'the overview reloads after a successful submission',
    );
    expect(
      find.textContaining('Request submitted for review'),
      findsOneWidget,
    );
  });
}
