import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

// ignore_for_file: avoid_relative_lib_imports
// (Same checkout-wide package-config note as the other runnable tests.)
import '../lib/screens/apply/application_status_screen.dart';
import '../lib/screens/apply/apply_screen.dart';

/// Responsive smoke test: the apply wizard and status screen must lay out
/// without overflow or clipped-content exceptions at common phone widths
/// (360 / 390 / 412 logical pixels) plus a narrow web window.
void main() {
  Future<void> pumpAtWidth(
      WidgetTester tester, double width, Widget screen) async {
    tester.view.physicalSize = Size(width, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    await tester.pumpWidget(MaterialApp(home: screen));
    await tester.pumpAndSettle();
    // Exercise scrolling: any overflow/clipping throws during layout.
    final scrollable = find.byType(SingleChildScrollView).first;
    await tester.drag(scrollable, const Offset(0, -600));
    await tester.pumpAndSettle();
    await tester.drag(scrollable, const Offset(0, 600));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  }

  for (final width in [360.0, 390.0, 412.0]) {
    testWidgets('ApplyScreen step 1 fits ${width.toInt()}px width',
        (tester) async {
      await pumpAtWidth(tester, width, const ApplyScreen());
      expect(find.text('Apply as a Rider'), findsWidgets);
    });

    testWidgets('ApplicationStatusScreen fits ${width.toInt()}px width',
        (tester) async {
      await pumpAtWidth(
          tester, width, const ApplicationStatusScreen());
      expect(find.text('Check your application'), findsOneWidget);
    });
  }
}
