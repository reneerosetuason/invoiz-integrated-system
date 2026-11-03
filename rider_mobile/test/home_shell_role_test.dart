import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/storage/token_storage.dart';
import 'package:invoize_rider/models/hub_courier.dart';
import 'package:invoize_rider/models/rider.dart';
import 'package:invoize_rider/providers/auth_provider.dart';
import 'package:invoize_rider/providers/delivery_provider.dart';
import 'package:invoize_rider/providers/earnings_provider.dart';
import 'package:invoize_rider/providers/notification_provider.dart';
import 'package:invoize_rider/providers/rider_provider.dart';
import 'package:invoize_rider/screens/home/home_shell.dart';
import 'package:invoize_rider/screens/home/hub_courier_home_screen.dart';
import 'package:invoize_rider/screens/home/hub_transfers_screen.dart';
import 'package:invoize_rider/services/delivery_service.dart';
import 'package:invoize_rider/services/earnings_service.dart';
import 'package:invoize_rider/services/notification_service.dart';
import 'package:invoize_rider/services/rider_service.dart';
import 'package:invoize_rider/widgets/liquid_nav_bar.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  Widget buildShell({
    required AuthProvider auth,
    required ApiClient api,
  }) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider<AuthProvider>.value(value: auth),
        ChangeNotifierProvider(create: (_) => RiderProvider(RiderService(api))),
        ChangeNotifierProvider(create: (_) => DeliveryProvider(DeliveryService(api))),
        ChangeNotifierProvider(create: (_) => NotificationProvider(NotificationService(api))),
        ChangeNotifierProvider(create: (_) => EarningsProvider(EarningsService(api))),
      ],
      child: const MaterialApp(
        home: HomeShell(),
      ),
    );
  }

  testWidgets('HomeShell adapts to Delivery Rider workflow', (tester) async {
    final api = ApiClient(client: MockClient((_) async => http.Response('{}', 200)));
    final auth = AuthProvider(api: api, storage: TokenStorage());

    // Set Delivery Rider profile
    auth.updateRider(Rider.fromJson({
      'id': 1,
      'name': 'Test Rider',
      'email': 'rider@test.com',
      'phone': '09123456789',
      'vehicle_type': 'motorcycle',
      'plate_number': 'MC-1234',
      'status': 'active',
      'is_online': false,
    }));

    await tester.pumpWidget(buildShell(auth: auth, api: api));
    await tester.pump();

    // Check AppBar title
    expect(find.text('Invoiz Rider'), findsOneWidget);
    // Check rider tabs in LiquidNavBar
    expect(find.byTooltip('Deliveries'), findsOneWidget);
    expect(find.byType(LiquidNavBar), findsOneWidget);
    // Hub Courier specific screens must NOT be in tree
    expect(find.byType(HubCourierHomeScreen), findsNothing);
    expect(find.byType(HubTransfersScreen), findsNothing);

    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(milliseconds: 500));
  });

  testWidgets('HomeShell adapts to Hub Transport Driver workflow', (tester) async {
    final api = ApiClient(client: MockClient((req) async {
      if (req.url.path.contains('/courier/dashboard')) {
        return http.Response('{"courier":{"id":5,"name":"Justin Barber"},"active_transfer":null,"recent_transfers":[]}', 200, headers: {'content-type': 'application/json'});
      }
      if (req.url.path.contains('/courier/transfers')) {
        return http.Response('{"transfers":[]}', 200, headers: {'content-type': 'application/json'});
      }
      if (req.url.path.contains('/courier/notifications')) {
        return http.Response('{"notifications":[]}', 200, headers: {'content-type': 'application/json'});
      }
      return http.Response('{}', 200, headers: {'content-type': 'application/json'});
    }));
    final auth = AuthProvider(api: api, storage: TokenStorage());

    // Set Hub Courier profile
    auth.updateCourier(HubCourier.fromJson({
      'id': 5,
      'name': 'Justin Barber',
      'company': 'INVOIZ Logistics',
      'email': 'lanime05172020@gmail.com',
      'phone': '09681249813',
      'is_online': true,
      'status': 'available',
      'vehicle_arrangement': 'company',
      'has_assigned_truck': true,
      'vehicle': {
        'id': 5,
        'plate_number': 'DEV-TEST-TRK-01',
        'type': 'light truck',
        'status': 'assigned',
        'parcel_capacity': 100,
        'capacity_kg': 2500,
      },
    }));

    await tester.pumpWidget(buildShell(auth: auth, api: api));
    await tester.pump();

    // Check AppBar title
    expect(find.text('Invoiz Hub Transport'), findsOneWidget);
    // Check Courier tabs in LiquidNavBar
    expect(find.byTooltip('Transfers'), findsOneWidget);
    expect(find.byType(LiquidNavBar), findsOneWidget);
    // Embedded Hub Courier screens are in stack
    expect(find.byType(HubCourierHomeScreen), findsOneWidget);
    expect(find.byType(HubTransfersScreen, skipOffstage: false), findsOneWidget);

    // Switching to Transfers tab renders HubTransfersScreen onstage
    await tester.tap(find.byTooltip('Transfers'));
    await tester.pump();
    expect(find.byType(HubTransfersScreen), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(milliseconds: 500));
  });
}
