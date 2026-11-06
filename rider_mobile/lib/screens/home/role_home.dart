import 'package:flutter/material.dart';

import '../../providers/auth_provider.dart';
import '../home/home_shell.dart';
import '../logistics/logistics_scan_screen.dart';

/// Destination after login/session restore. Logistics roles open the
/// scanning workspace; riders and Hub Transport Drivers keep their existing
/// dashboards.
Widget homeForAuth(AuthProvider auth) =>
    auth.isLogistics ? const LogisticsScanScreen() : const HomeShell();
