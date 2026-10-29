import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

// Relative import is intentional: the generated package config in this
// checkout does not reliably contain the root package entry, which breaks
// package: self-imports for the analyzer and test runner.
// ignore: avoid_relative_lib_imports
import '../lib/widgets/liquid_nav_bar.dart';

Future<void> _pumpNav(
  WidgetTester tester, {
  required int selected,
  required ValueChanged<int> onTap,
}) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: const SizedBox.expand(),
        bottomNavigationBar: LiquidNavBar(
          selectedIndex: selected,
          onTap: onTap,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('shows all five navigation destinations', (tester) async {
    await _pumpNav(tester, selected: 0, onTap: (_) {});

    for (final label in ['Home', 'Deliveries', 'Scan', 'Settings', 'Profile']) {
      expect(find.bySemanticsLabel(label), findsOneWidget);
    }
  });

  testWidgets('tapping a destination reports its index', (tester) async {
    int? tapped;
    await _pumpNav(tester, selected: 0, onTap: (i) => tapped = i);

    await tester.tap(find.bySemanticsLabel('Deliveries'));
    await tester.pumpAndSettle();
    expect(tapped, 1);

    await tester.tap(find.bySemanticsLabel('Scan'));
    await tester.pumpAndSettle();
    expect(tapped, 2);
  });

  testWidgets('active state matches the selected index', (tester) async {
    await _pumpNav(tester, selected: 3, onTap: (_) {});

    expect(
      tester.getSemantics(find.bySemanticsLabel('Settings')),
      matchesSemantics(
        label: 'Settings',
        isButton: true,
        isSelected: true,
        hasSelectedState: true,
        isFocusable: true,
        hasTapAction: true,
        hasFocusAction: true,
      ),
    );
    expect(
      tester.getSemantics(find.bySemanticsLabel('Home')),
      matchesSemantics(
        label: 'Home',
        isButton: true,
        isSelected: false,
        hasSelectedState: true,
        isFocusable: true,
        hasTapAction: true,
        hasFocusAction: true,
      ),
    );
    // Selected tab renders its filled icon variant.
    expect(find.byIcon(Icons.settings), findsOneWidget);
    expect(find.byIcon(Icons.home_outlined), findsOneWidget);
  });

  testWidgets('every destination exposes an accessible label', (tester) async {
    await _pumpNav(tester, selected: 0, onTap: (_) {});

    for (final label in ['Home', 'Deliveries', 'Scan', 'Settings', 'Profile']) {
      expect(
        tester.getSemantics(find.bySemanticsLabel(label)),
        matchesSemantics(
          label: label,
          isButton: true,
          // Selected tab is Home (0) in this pump.
          isSelected: label == 'Home',
          hasSelectedState: true,
          isFocusable: true,
          hasTapAction: true,
          hasFocusAction: true,
        ),
      );
    }
  });
}
