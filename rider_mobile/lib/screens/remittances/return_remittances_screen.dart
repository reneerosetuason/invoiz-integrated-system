import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import 'hub_courier_remittances_screen.dart';

/// Compatibility for old shortcuts: the same combined remittance screen.
class ReturnRemittancesScreen extends StatelessWidget {
  const ReturnRemittancesScreen({super.key, this.api});
  final ApiClient? api;
  @override
  Widget build(BuildContext context) =>
      HubCourierRemittancesScreen(title: 'Remittances', api: api);
}
