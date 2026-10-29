import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/screens/apply/apply_screen.dart';

/// Picker-boundary tests for the Documents step.
///
/// The native file picker cannot run in widget tests, so these drive the
/// real wizard UI with a fake [DocFilePicker]: pick success (all four
/// formats), cancel silence, picker failure message, replace and remove.
class FakeDocPicker extends DocFilePicker {
  PickedDoc? next;
  Object? error;
  List<String>? lastAllowed;

  @override
  Future<PickedDoc?> pickSingle(List<String> allowedExtensions) async {
    lastAllowed = List.of(allowedExtensions);
    if (error != null) throw error!;
    return next;
  }
}

Uint8List _bytesWithHeader(String name, int size) {
  final ext = name.split('.').last.toLowerCase();
  final header = switch (ext) {
    'pdf' => <int>[0x25, 0x50, 0x44, 0x46, 0x2D, 0x31, 0x2E, 0x37],
    'png' => <int>[0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A],
    _ => <int>[0xFF, 0xD8, 0xFF, 0xE0],
  };
  final bytes = Uint8List(size < header.length ? header.length : size);
  bytes.setRange(0, header.length, header);
  for (var i = header.length; i < bytes.length; i++) {
    bytes[i] = 7;
  }
  return bytes;
}

PickedDoc docOf(String name, [int size = 1500]) =>
    PickedDoc(name: name, readBytes: () async => _bytesWithHeader(name, size));

/// Backend for the RIDER apply wizard: email OTP + PSGC address data.
/// Vehicle types fall back to the built-in list when the endpoint 404s.
MockClient _applyBackend() {
  return MockClient((http.BaseRequest request) async {
    http.Response json(Object data, int status) => http.Response(
      jsonEncode(data),
      status,
      headers: {'content-type': 'application/json'},
    );
    final path = request.url.path;
    if (path.endsWith('/rider/email/request-code') ||
        path.endsWith('/rider/email/resend')) {
      return json({'message': 'Verification code sent.'}, 200);
    }
    if (path.endsWith('/rider/email/verify')) {
      return json({
        'message': 'Email verified successfully.',
        'email': 'juan.test@example.com',
      }, 200);
    }
    if (path.endsWith('/provinces')) {
      return json({
        'provinces': [
          {'id': 120000000, 'name': 'SOCCSKSARGEN'},
        ],
      }, 200);
    }
    if (!path.endsWith('/provinces') && path.endsWith('/municipalities')) {
      return json({
        'municipalities': [
          {'id': 1200100000, 'name': 'General Santos City'},
        ],
      }, 200);
    }
    if (path.endsWith('/barangays')) {
      return json({
        'barangays': [
          {'id': 1200100001, 'name': 'Barangay Poblacion'},
        ],
      }, 200);
    }
    return json({'message': 'not found'}, 404);
  });
}

/// Selects Province → City/Municipality → Barangay by keyboard (overlay
/// menu taps are unreliable in the cascade). Index 0, then 1, then 2.
Future<void> _selectAddress(WidgetTester tester) async {
  Future<void> pick(int index) async {
    await tester.ensureVisible(find.byType(DropdownButton<String>).first);
    await tester.pumpAndSettle();
    await tester.tap(
      find.byType(DropdownButton<String>).at(index),
      warnIfMissed: false,
    );
    await tester.pumpAndSettle();
    await tester.sendKeyEvent(LogicalKeyboardKey.arrowDown);
    await tester.sendKeyEvent(LogicalKeyboardKey.arrowDown);
    await tester.pumpAndSettle();
    await tester.sendKeyEvent(LogicalKeyboardKey.enter);
    await tester.pumpAndSettle();
  }

  await pick(0);
  await pick(1);
  await pick(2);
}

/// Sends a code and verifies it, clearing the success SnackBar overlay so
/// the next tap on Continue is not blocked.
Future<void> _verifyEmail(WidgetTester tester) async {
  await tester.ensureVisible(find.text('Send Verification Code'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Send Verification Code'));
  for (var i = 0; i < 10; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
  await tester.enterText(
    find.widgetWithText(TextField, 'Verification Code'),
    '123456',
  );
  await tester.pump();
  await tester.ensureVisible(find.text('Verify Email'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Verify Email'));
  await tester.pumpAndSettle();
  final sm = tester.state<ScaffoldMessengerState>(
    find.byType(ScaffoldMessenger),
  );
  sm.clearSnackBars();
  await tester.pumpAndSettle();
}

/// Drives Personal (valid) → Rider Type (default full-time) → Vehicle
/// (default own) → Requirements → Documents. Ownership `own` keeps the
/// required set to the trio + clearance card.
Future<void> driveToDocuments(WidgetTester tester, FakeDocPicker picker) async {
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(() {
    tester.view.resetPhysicalSize();
    tester.view.resetDevicePixelRatio();
  });
  await tester.pumpWidget(
    MaterialApp(
      home: ApplyScreen(
        apiClient: ApiClient(client: _applyBackend()),
        picker: picker,
      ),
    ),
  );
  await tester.pumpAndSettle();

  await tester.enterText(
    find.widgetWithText(TextField, 'First Name *'),
    'Juan',
  );
  await tester.enterText(
    find.widgetWithText(TextField, 'Last Name *'),
    'Dela Cruz',
  );
  await tester.enterText(
    find.widgetWithText(TextField, 'Email Address *'),
    'juan.test@example.com',
  );
  await tester.enterText(
    find.widgetWithText(TextField, 'Phone Number *  (+639 / 09)'),
    '09178881111',
  );
  await tester.tap(find.text('Male'));
  await tester.pump();
  await tester.enterText(find.widgetWithText(TextField, 'House No.'), '123');
  await tester.enterText(find.widgetWithText(TextField, 'Street'), 'Test St');
  await _selectAddress(tester);
  await tester.ensureVisible(find.text('Select date'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Select date'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('OK'));
  await tester.pumpAndSettle();
  await _verifyEmail(tester);

  await tester.tap(find.text('Continue')); // → rider type
  await tester.pumpAndSettle();
  await tester.ensureVisible(find.text('Continue'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Continue')); // → vehicle (full-time default)
  await tester.pumpAndSettle();
  await tester.enterText(
    find.widgetWithText(TextField, 'License Plate *'),
    'ABC1234',
  );
  await tester.enterText(
    find.widgetWithText(TextField, 'License Number *'),
    'L123456',
  );
  // Pick a license expiry date (future date via the date picker).
  await tester.ensureVisible(find.text('Select date').last);
  await tester.pumpAndSettle();
  await tester.tap(find.text('Select date').last);
  await tester.pumpAndSettle();
  await tester.tap(find.text('OK'));
  await tester.pumpAndSettle();
  await tester.enterText(
    find.widgetWithText(TextField, 'Vehicle Registration *'),
    'REG-1',
  );
  await tester.tap(find.text('Continue')); // → requirements
  await tester.pumpAndSettle();
  await tester.tap(find.text('Continue')); // → documents
  await tester.pumpAndSettle();
  expect(find.text('Upload Documents'), findsOneWidget);
}

void main() {
  testWidgets('tapping upload stages file: name, size, replace, remove', (
    tester,
  ) async {
    final picker = FakeDocPicker()..next = docOf('license.pdf');
    await driveToDocuments(tester, picker);

    await tester.tap(find.text('Upload file').first);
    await tester.pumpAndSettle();

    // Picker received the supported filter and the real filename is shown.
    expect(picker.lastAllowed, containsAll(['pdf', 'jpg', 'jpeg', 'png']));
    expect(find.textContaining('license.pdf'), findsOneWidget);
    expect(find.textContaining('1.5 KB'), findsOneWidget);
    expect(find.text('Replace'), findsOneWidget);
    expect(find.byIcon(Icons.delete_outline), findsWidgets);
    expect(
      find.textContaining('will be uploaded when you submit'),
      findsOneWidget,
    );
  });

  testWidgets('pdf, jpg, jpeg and png are all accepted with names kept', (
    tester,
  ) async {
    final picker = FakeDocPicker();
    await driveToDocuments(tester, picker);

    const names = ['clearance.pdf', 'photo.jpg', 'scan.jpeg', 'id.png'];
    for (final name in names) {
      picker.next = docOf(name);
      await tester.ensureVisible(find.text('Upload file').first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Upload file').first);
      await tester.pumpAndSettle();
    }
    for (final name in names) {
      expect(find.textContaining(name), findsOneWidget);
    }
  });

  testWidgets('oversized files are rejected with a clear message', (
    tester,
  ) async {
    final picker = FakeDocPicker()..next = docOf('huge.pdf', 6 * 1024 * 1024);
    await driveToDocuments(tester, picker);

    await tester.tap(find.text('Upload file').first);
    await tester.pumpAndSettle();

    expect(find.textContaining('huge.pdf'), findsNothing);
    expect(find.textContaining('over 5 MB'), findsOneWidget);
    // Card stays in pick state so the user can try another file.
    expect(find.text('Upload file'), findsWidgets);
  });

  testWidgets('canceling the picker shows no error', (tester) async {
    final picker = FakeDocPicker()..next = null; // user pressed back
    await driveToDocuments(tester, picker);

    await tester.tap(find.text('Upload file').first);
    await tester.pumpAndSettle();

    expect(find.byType(SnackBar), findsNothing);
    // Nothing staged: the card is still in pick state.
    expect(find.text('Upload file'), findsWidgets);
  });

  testWidgets('picker failure shows a meaningful message', (tester) async {
    final picker = FakeDocPicker()
      ..error = MissingPluginException(
        'No implementation found for method pickFile',
      );
    await driveToDocuments(tester, picker);

    await tester.tap(find.text('Upload file').first);
    await tester.pumpAndSettle();

    expect(
      find.textContaining('not available in this app build'),
      findsOneWidget,
    );
  });

  testWidgets('replace swaps the staged file', (tester) async {
    final picker = FakeDocPicker()..next = docOf('old.pdf');
    await driveToDocuments(tester, picker);

    await tester.tap(find.text('Upload file').first);
    await tester.pumpAndSettle();
    expect(find.textContaining('old.pdf'), findsOneWidget);

    picker.next = docOf('new.pdf');
    await tester.tap(find.text('Replace').first);
    await tester.pumpAndSettle();
    expect(find.textContaining('new.pdf'), findsOneWidget);
    expect(find.textContaining('old.pdf'), findsNothing);
  });

  testWidgets('remove clears the staged file', (tester) async {
    final picker = FakeDocPicker()..next = docOf('license.pdf');
    await driveToDocuments(tester, picker);

    final uploadCount = find.text('Upload file').evaluate().length;
    await tester.tap(find.text('Upload file').first);
    await tester.pumpAndSettle();
    expect(find.textContaining('license.pdf'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.delete_outline).first);
    await tester.pumpAndSettle();
    expect(find.textContaining('license.pdf'), findsNothing);
    expect(find.text('Upload file').evaluate().length, uploadCount);
  });
}
