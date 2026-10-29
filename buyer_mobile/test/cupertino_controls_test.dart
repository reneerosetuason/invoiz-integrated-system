import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/main.dart';
import 'package:invoiz/screens/home_screen.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:invoiz/widgets/buyer_controls.dart';
import 'package:invoiz/widgets/buyer_dialog.dart';
import 'package:invoiz/widgets/buyer_field.dart';
import 'package:invoiz/widgets/buyer_picker.dart';
import 'package:invoiz/widgets/main_layout.dart';

Widget _app(Widget child) => MaterialApp(
  theme: AppTheme.light,
  home: Scaffold(body: child),
);

void main() {
  WidgetController.hitTestWarningShouldBeFatal = true;
  setUp(() => SharedPreferences.setMockInitialValues({}));

  testWidgets('Cupertino app shell retains navigation and feedback support', (
    tester,
  ) async {
    final auth = AuthService();
    await tester.pumpWidget(InvoizApp(authService: auth));
    expect(find.byType(CupertinoApp), findsOneWidget);
    await tester.pump(const Duration(milliseconds: 1400));
    await tester.pumpAndSettle();
    expect(find.byType(HomeScreen), findsOneWidget);
    ScaffoldMessenger.of(
      tester.element(find.byType(HomeScreen)),
    ).showSnackBar(const SnackBar(content: Text('Fixture feedback')));
    await tester.pump();
    expect(find.text('Fixture feedback'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
    auth.dispose();
  });

  testWidgets(
    'native field retains validation, saves, and external controller ownership',
    (tester) async {
      final form = GlobalKey<FormState>();
      final controller = TextEditingController();
      String? saved;
      var changes = 0;
      await tester.pumpWidget(
        _app(
          Form(
            key: form,
            child: BuyerField.form(
              controller: controller,
              decoration: const InputDecoration(labelText: 'Recipient'),
              validator: (value) =>
                  value == null || value.isEmpty ? 'Required' : null,
              onSaved: (value) => saved = value,
              onChanged: (_) => changes++,
            ),
          ),
        ),
      );
      expect(form.currentState!.validate(), false);
      await tester.pump();
      expect(find.text('Required'), findsOneWidget);
      await tester.enterText(
        find.byType(CupertinoTextField),
        'Fixture recipient',
      );
      expect(form.currentState!.validate(), true);
      form.currentState!.save();
      expect(saved, 'Fixture recipient');
      expect(changes, 1);
      controller.clear();
      expect(form.currentState!.validate(), false);
      expect(changes, 1);
      await tester.pumpWidget(const SizedBox());
      controller.text = 'Still owned by the screen';
      controller.dispose();
    },
  );

  testWidgets('replacing a native field controller refreshes its form value', (
    tester,
  ) async {
    final form = GlobalKey<FormState>();
    final first = TextEditingController(text: 'Previous recipient');
    final second = TextEditingController();
    Widget build(TextEditingController controller) => _app(
      Form(
        key: form,
        child: BuyerField.form(
          controller: controller,
          validator: (value) =>
              value == null || value.isEmpty ? 'Required' : null,
        ),
      ),
    );
    await tester.pumpWidget(build(first));
    expect(form.currentState!.validate(), true);
    await tester.pumpWidget(build(second));
    expect(form.currentState!.validate(), false);
    await tester.pumpWidget(const SizedBox());
    first.dispose();
    second.dispose();
  });

  testWidgets(
    'native picker selects once, validates, and clears dependent values',
    (tester) async {
      final form = GlobalKey<FormState>();
      String? value = 'one';
      var changes = 0;
      late StateSetter update;
      await tester.pumpWidget(
        _app(
          StatefulBuilder(
            builder: (_, setLocal) {
              update = setLocal;
              return Form(
                key: form,
                child: BuyerPicker<String>(
                  value: value,
                  hint: const Text('Choose a location'),
                  items: const [
                    DropdownMenuItem(
                      value: 'one',
                      child: Text('First location'),
                    ),
                    DropdownMenuItem(
                      value: 'two',
                      child: Text('Second location'),
                    ),
                  ],
                  validator: (value) => value == null ? 'Required' : null,
                  onChanged: (selected) => update(() {
                    value = selected;
                    changes++;
                  }),
                ),
              );
            },
          ),
        ),
      );
      await tester.tap(find.text('First location'));
      await tester.pumpAndSettle();
      expect(find.byType(CupertinoActionSheet), findsOneWidget);
      await tester.tap(find.text('Second location'));
      await tester.pumpAndSettle();
      expect(value, 'two');
      expect(changes, 1);
      expect(form.currentState!.validate(), true);
      update(() => value = null);
      await tester.pump();
      expect(find.text('Choose a location'), findsOneWidget);
      expect(form.currentState!.validate(), false);
    },
  );

  testWidgets('native date picker retains cancel and confirmation behavior', (
    tester,
  ) async {
    DateTime? result;
    await tester.pumpWidget(
      _app(
        Builder(
          builder: (context) => BuyerButton.primary(
            onPressed: () async {
              result = await showBuyerDatePicker(
                context: context,
                initialDate: DateTime(2000, 1, 1),
                firstDate: DateTime(1940),
                lastDate: DateTime(2026, 10, 29),
              );
            },
            child: const Text('Choose date'),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Choose date'));
    await tester.pumpAndSettle();
    expect(find.byType(CupertinoDatePicker), findsOneWidget);
    await tester.tap(find.text('Cancel'));
    await tester.pumpAndSettle();
    expect(result, isNull);
    await tester.tap(find.text('Choose date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Done'));
    await tester.pumpAndSettle();
    expect(result, DateTime(2000, 1, 1));
  });

  testWidgets('native tabs respect notch and home indicator at 320px', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    tester.view.padding = const FakeViewPadding(top: 44, bottom: 34);
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetPadding);
    final auth = AuthService();
    await tester.pumpWidget(
      AuthServiceProvider(
        authService: auth,
        child: MaterialApp(
          theme: AppTheme.light,
          home: const MainLayout(
            title: 'Shop',
            currentIndex: 1,
            showTopActions: false,
            showNotificationIcon: false,
            child: SizedBox.expand(key: ValueKey('safe-content')),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    final content = tester.getRect(find.byKey(const ValueKey('safe-content')));
    final tabs = tester.getRect(find.byType(CupertinoTabBar));
    expect(content.top, greaterThanOrEqualTo(104));
    // The pill floats over the page instead of being boxed into its own strip.
    expect(content.bottom, greaterThan(tabs.top));
    expect(tabs.left, greaterThanOrEqualTo(16));
    expect(tabs.right, lessThanOrEqualTo(304));
    expect(tabs.height, 62);
    expect(tabs.bottom, lessThanOrEqualTo(606));
    expect(find.byKey(const ValueKey('floating-tab-pill')), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
    auth.dispose();
  });
}
