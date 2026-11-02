import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/network/api_exception.dart';
import '../../models/hub_courier.dart';
import '../../providers/auth_provider.dart';
import '../../services/hub_courier_service.dart';
import '../onboarding/rider_tutorial_screen.dart';

String _formatTimestamp(String? value) {
  if (value == null || value.isEmpty) return '—';
  final parsed = DateTime.tryParse(value);
  if (parsed == null) return value;
  final local = parsed.toLocal();
  final month = local.month.toString().padLeft(2, '0');
  final day = local.day.toString().padLeft(2, '0');
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '${local.year}-$month-$day $hour:$minute';
}

/// Hub Transport Driver workspace. Hub Transport Drivers sign in with their own
/// `hub_transport_drivers` credentials and only ever see hub-to-hub
/// transfers assigned to their own four-wheel vehicle.
class HubCourierHomeScreen extends StatefulWidget {
  const HubCourierHomeScreen({super.key, this.embedded = false});

  final bool embedded;

  @override
  State<HubCourierHomeScreen> createState() => _HubCourierHomeScreenState();
}

class _HubCourierHomeScreenState extends State<HubCourierHomeScreen> {
  late Future<HubCourierDashboard> _dashboard;
  bool _statusSaving = false;
  HubCourierService get _service =>
      HubCourierService(context.read<AuthProvider>().api);

  @override
  void initState() {
    super.initState();
    _dashboard = _load();
  }

  Future<HubCourierDashboard> _load() async {
    final raw = await _service.dashboard();
    return HubCourierDashboard.fromJson(raw);
  }

  Future<void> _refresh() async {
    final future = _load();
    if (mounted) {
      // setState callbacks must be synchronous: return nothing from the closure.
      setState(() {
        _dashboard = future;
      });
    }
    try {
      await future;
    } catch (_) {
      // The FutureBuilder renders the error state; this keeps the pull-to-
      // refresh indicator from completing with an unhandled exception.
    }
  }

  Future<void> _setOnline(bool online) async {
    if (_statusSaving) return;
    setState(() {
      _statusSaving = true;
    });
    try {
      await _service.setStatus(online);
      if (!mounted) return;
      // Reload from the server so the switch always mirrors the status the
      // backend actually persisted.
      await _refresh();
    } on ApiException catch (e) {
      _message('Could not change status: ${e.message}');
    } catch (e) {
      _message('Could not change status: $e');
    } finally {
      if (mounted) {
        setState(() {
          _statusSaving = false;
        });
      }
    }
  }

  String _errorMessage(Object? error) {
    if (error is ApiException) return error.message;
    if (error is FormatException) return error.message;
    return error?.toString() ?? 'Unknown error.';
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<HubCourierDashboard>(
    future: _dashboard,
    builder: (context, snapshot) {
      final Widget body;
      if (snapshot.hasError) {
        body = _error(_errorMessage(snapshot.error));
      } else if (!snapshot.hasData) {
        body = _loading();
      } else {
        body = RefreshIndicator(
          onRefresh: _refresh,
          child: _content(snapshot.data!),
        );
      }

      if (widget.embedded) {
        return body;
      }

      return Scaffold(
        appBar: AppBar(
          title: const Text('Hub Transport Driver'),
          actions: [
            IconButton(
              tooltip: 'Help & tutorial',
              icon: const Icon(Icons.help_outline),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) =>
                      const RiderTutorialScreen(workflowType: 'hub_courier'),
                ),
              ),
            ),
            IconButton(
              tooltip: 'Log out',
              icon: const Icon(Icons.logout),
              onPressed: () => context.read<AuthProvider>().logout(),
            ),
          ],
        ),
        body: body,
      );
    },
  );

  Widget _loading() => const Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        CircularProgressIndicator(),
        SizedBox(height: 14),
        Text('Loading transfer jobs…'),
      ],
    ),
  );

  Widget _error(String error) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.wifi_off, size: 42),
          const SizedBox(height: 12),
          const Text('Could not load transfer jobs.'),
          Text(error, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          ElevatedButton(onPressed: _refresh, child: const Text('Retry')),
        ],
      ),
    ),
  );

  Widget _content(HubCourierDashboard dashboard) {
    final courier = dashboard.courier;
    final vehicle = courier.vehicle ?? const <String, dynamic>{};
    final jobs = dashboard.availableTransfers;
    final completed = dashboard.completedTransfers;
    final active = dashboard.activeTransfer;
    final earnings = dashboard.earnings;
    final incidents = dashboard.recentIncidents;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        // Driver & Status Card
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'HUB TRANSPORT DRIVER',
                      style: Theme.of(context).textTheme.labelLarge,
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: courier.isDriverOwned
                            ? Colors.purple.shade50
                            : Colors.blue.shade50,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: courier.isDriverOwned
                              ? Colors.purple.shade200
                              : Colors.blue.shade200,
                        ),
                      ),
                      child: Text(
                        courier.isDriverOwned ? 'Driver-Owned Truck' : 'Company Fleet',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: courier.isDriverOwned
                              ? Colors.purple.shade800
                              : Colors.blue.shade800,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  courier.name,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                Text(
                  '${courier.company} · ${courier.email}',
                  style: const TextStyle(fontSize: 12, color: Colors.black54),
                ),
                const Divider(height: 20),

                // Vehicle Details Section
                Row(
                  children: [
                    const Icon(Icons.local_shipping_outlined, size: 20, color: Color(0xFF16697A)),
                    const SizedBox(width: 8),
                    Text(
                      'Vehicle: ${vehicle['plate_number'] ?? 'Not Assigned'}',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    const Spacer(),
                    if (vehicle['status'] != null)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: vehicle['status'] == 'available'
                              ? Colors.green.shade50
                              : vehicle['status'] == 'damaged'
                                  ? Colors.orange.shade50
                                  : Colors.amber.shade50,
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: vehicle['status'] == 'available'
                                ? Colors.green.shade200
                                : vehicle['status'] == 'damaged'
                                    ? Colors.orange.shade200
                                    : Colors.amber.shade200,
                          ),
                        ),
                        child: Text(
                          vehicle['status'].toString().toUpperCase(),
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: vehicle['status'] == 'available'
                                ? Colors.green.shade800
                                : vehicle['status'] == 'damaged'
                                    ? Colors.orange.shade800
                                    : Colors.amber.shade800,
                          ),
                        ),
                      ),
                  ],
                ),
                if (courier.isDriverOwned) ...[
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Text(
                        'Ownership Verification: ',
                        style: TextStyle(fontSize: 11, color: Colors.black54),
                      ),
                      Text(
                        (courier.verificationStatus ?? 'pending_review').replaceAll('_', ' ').toUpperCase(),
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: courier.isVehicleApproved
                              ? Colors.green.shade700
                              : courier.isVehicleRejected
                                  ? Colors.red.shade700
                                  : Colors.orange.shade800,
                        ),
                      ),
                    ],
                  ),
                  if (courier.verificationNotes != null && courier.verificationNotes!.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        'Note: ${courier.verificationNotes}',
                        style: const TextStyle(fontSize: 11, fontStyle: FontStyle.italic, color: Colors.black54),
                      ),
                    ),
                ],
                const SizedBox(height: 4),
                Text(
                  'Capacity: ${vehicle['parcel_capacity'] ?? 0} parcels · ${vehicle['capacity_kg'] ?? 0} kg',
                  style: const TextStyle(fontSize: 11, color: Colors.black54),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    courier.online
                        ? 'Online for assigned transfers'
                        : 'Offline',
                  ),
                  subtitle: _statusSaving
                      ? const Text(
                          'Updating status…',
                          style: TextStyle(fontSize: 11),
                        )
                      : null,
                  value: courier.online,
                  onChanged: _statusSaving ? null : _setOnline,
                ),
              ],
            ),
          ),
        ),

        // Earnings Summary Card
        const SizedBox(height: 12),
        Card(
          color: const Color(0xFFF7FAF9),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'COMPLETED TRIP EARNINGS',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF16697A),
                        letterSpacing: 0.5,
                      ),
                    ),
                    Text(
                      '${earnings.completedTrips} trips',
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.black54),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Base Pay', style: TextStyle(fontSize: 11, color: Colors.black54)),
                          Text(
                            '₱${earnings.base.toStringAsFixed(2)}',
                            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.black87),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Vehicle Allowance', style: TextStyle(fontSize: 11, color: Colors.black54)),
                          Text(
                            '₱${earnings.allowance.toStringAsFixed(2)}',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                              color: courier.isDriverOwned ? Colors.purple.shade700 : Colors.black87,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          const Text('Total Earned', style: TextStyle(fontSize: 11, color: Colors.black54)),
                          Text(
                            '₱${earnings.total.toStringAsFixed(2)}',
                            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF16697A)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                if (courier.isDriverOwned)
                  const Padding(
                    padding: EdgeInsets.only(top: 8),
                    child: Text(
                      'Includes separate vehicle-use allowance for driver-owned truck.',
                      style: TextStyle(fontSize: 10.5, color: Colors.black54, fontStyle: FontStyle.italic),
                    ),
                  ),
              ],
            ),
          ),
        ),

        // Incident Reporting Action
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                const Icon(Icons.report_problem_outlined, color: Colors.amber, size: 24),
                const SizedBox(width: 10),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Accident or Damage?',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                      Text(
                        'Reports do not deduct driver earnings.',
                        style: TextStyle(fontSize: 11, color: Colors.black54),
                      ),
                    ],
                  ),
                ),
                OutlinedButton(
                  // The global theme's full-width minimum size is invalid
                  // inside a Row, which gives children unbounded width.
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(88, 44),
                    padding: const EdgeInsets.symmetric(horizontal: 18),
                  ),
                  onPressed: () => _showReportIncidentDialog(active?.id),
                  child: const Text('Report'),
                ),
              ],
            ),
          ),
        ),

        if (incidents.isNotEmpty) ...[
          const SizedBox(height: 12),
          Text(
            'Recent Reported Incidents',
            style: Theme.of(context).textTheme.titleSmall,
          ),
          const SizedBox(height: 6),
          for (final inc in incidents)
            Card(
              elevation: 0,
              color: Colors.grey.shade50,
              shape: RoundedRectangleBorder(
                side: BorderSide(color: Colors.grey.shade200),
                borderRadius: BorderRadius.circular(8),
              ),
              child: ListTile(
                dense: true,
                title: Text(
                  '${asJsonString(inc['reference'], 'Incident')} · '
                  '${asJsonString(inc['incident_type'], 'incident').replaceAll('_', ' ').toUpperCase()}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                ),
                subtitle: Text(
                  '${asJsonString(inc['description'])}\n'
                  'Severity: ${asJsonString(inc['severity'], '—')} · '
                  'Status: ${asJsonString(inc['status'], '—')}',
                  style: const TextStyle(fontSize: 11),
                ),
              ),
            ),
        ],
        if (active != null) ...[
          const SizedBox(height: 12),
          _jobCard(active, prominent: true),
        ],
        const SizedBox(height: 18),
        Text(
          'Assigned Transfers',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        if (jobs.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 28),
            child: Center(
              child: Text(
                'No assigned transfer jobs. Your dispatcher will assign work here.',
              ),
            ),
          ),
        for (final job in jobs)
          if (job.id != active?.id) _jobCard(job),
        const SizedBox(height: 18),
        Text(
          'Completed Transfers',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        if (completed.isEmpty)
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text('No completed transfers yet.'),
          ),
        for (final job in completed) _jobCard(job),
      ],
    );
  }

  Widget _jobCard(HubTransferJob job, {bool prominent = false}) => Card(
    color: prominent ? Theme.of(context).colorScheme.primaryContainer : null,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            prominent ? 'ACTIVE HUB TRANSFER' : 'HUB-TO-HUB TRANSPORT',
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          Text(
            '${job.origin['name'] ?? 'Origin'}  →  '
            '${job.destination['name'] ?? 'Destination'}',
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
          ),
          Text(
            '${job.parcelCount} parcels · ${job.loadedCount} loaded · '
            '${job.statusLabel}',
          ),
          if (job.receivedCount > 0 || job.missingCount > 0)
            Text(
              '${job.receivedCount} of ${job.parcelCount} parcels received'
              '${job.missingCount > 0 ? ' · ${job.missingCount} missing' : ''}',
              style: const TextStyle(fontSize: 12, color: Colors.black54),
            ),
          if (job.totalWeightKg != null)
            Text(
              'Manifest weight: ${job.totalWeightKg!.toStringAsFixed(2)} kg',
              style: const TextStyle(fontSize: 12, color: Colors.black54),
            ),
          if (job.plannedDispatchAt != null)
            Text(
              'Planned dispatch: ${_formatTimestamp(job.plannedDispatchAt)}',
            ),
          if (job.notes.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                'Instructions: ${job.notes}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 12, color: Colors.black54),
              ),
            ),
          const SizedBox(height: 8),
          FilledButton(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => HubTransferDetailScreen(transferId: job.id),
              ),
            ).then((_) => _refresh()),
            child: const Text('View Transfer'),
          ),
        ],
      ),
    ),
  );

  Future<void> _showReportIncidentDialog(int? activeTransferId) async {
    final typeCtrl = TextEditingController(text: 'accident');
    final sevCtrl = TextEditingController(text: 'minor');
    final descCtrl = TextEditingController();
    final locCtrl = TextEditingController();
    final dmgCtrl = TextEditingController();

    await showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Report Vehicle Incident / Damage'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Accident reports do not deduct your earnings. Please report accurately.',
                style: TextStyle(fontSize: 12, color: Colors.black54),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: typeCtrl.text,
                decoration: const InputDecoration(labelText: 'Incident Type'),
                items: const [
                  DropdownMenuItem(value: 'accident', child: Text('Collision / Accident')),
                  DropdownMenuItem(value: 'mechanical_breakdown', child: Text('Mechanical Breakdown')),
                  DropdownMenuItem(value: 'weather_damage', child: Text('Weather Damage')),
                  DropdownMenuItem(value: 'other', child: Text('Other')),
                ],
                onChanged: (v) => typeCtrl.text = v ?? 'accident',
              ),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                value: sevCtrl.text,
                decoration: const InputDecoration(labelText: 'Severity'),
                items: const [
                  DropdownMenuItem(value: 'minor', child: Text('Minor (Cosmetic)')),
                  DropdownMenuItem(value: 'moderate', child: Text('Moderate (Needs Repair)')),
                  DropdownMenuItem(value: 'severe', child: Text('Severe (Inoperable)')),
                  DropdownMenuItem(value: 'total_loss', child: Text('Total Loss')),
                ],
                onChanged: (v) => sevCtrl.text = v ?? 'minor',
              ),
              const SizedBox(height: 8),
              TextField(
                controller: locCtrl,
                decoration: const InputDecoration(labelText: 'Location (optional)'),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: descCtrl,
                maxLines: 2,
                decoration: const InputDecoration(labelText: 'Description *'),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: dmgCtrl,
                maxLines: 2,
                decoration: const InputDecoration(labelText: 'Damage Details'),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () async {
              if (descCtrl.text.trim().isEmpty) {
                _message('Please provide a description.');
                return;
              }
              Navigator.pop(ctx);
              try {
                await _service.reportIncident(
                  incidentType: typeCtrl.text,
                  severity: sevCtrl.text,
                  description: descCtrl.text.trim(),
                  location: locCtrl.text.trim(),
                  damageDetails: dmgCtrl.text.trim(),
                  hubTransferId: activeTransferId,
                );
                _message('Incident reported. Earnings preserved.');
                await _refresh();
              } on ApiException catch (e) {
                _message('Could not submit report: ${e.message}');
              } catch (e) {
                _message('Could not submit report: $e');
              }
            },
            child: const Text('Submit Report'),
          ),
        ],
      ),
    );
  }

  void _message(String message) {
    if (mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }
}

/// State-aware transfer detail. Only the action allowed by the transfer's
/// current status is shown, and every action is re-validated by the API.
class HubTransferDetailScreen extends StatefulWidget {
  const HubTransferDetailScreen({super.key, required this.transferId});

  final int transferId;

  @override
  State<HubTransferDetailScreen> createState() =>
      _HubTransferDetailScreenState();
}

class _HubTransferDetailScreenState extends State<HubTransferDetailScreen> {
  HubTransferJob? _job;
  bool _busy = false;
  String? _error;

  HubCourierService get _service =>
      HubCourierService(context.read<AuthProvider>().api);

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final result = await _service.transfer(widget.transferId);
      if (mounted) {
        setState(() {
          _job = result;
          _error = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  Future<bool> _confirm(String action) async {
    final job = _job;
    final summary = action == 'dispatch' && job != null
        ? 'Origin: ${job.origin['name']}\n'
              'Destination: ${job.destination['name']}\n'
              'Loaded: ${job.loadedCount} of ${job.parcelCount}\n'
              'Vehicle: ${asJsonString(job.vehicle['type'], 'Vehicle')} · '
              '${asJsonString(job.vehicle['plate_number'])}\n'
              'Dispatch this manifest?'
        : 'Record arrival at the destination hub? Parcels are not received '
              'until destination staff scan them.';
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(
          action == 'dispatch'
              ? 'Dispatch transfer?'
              : 'Confirm destination arrival',
        ),
        content: Text(summary),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    return ok == true;
  }

  Future<void> _act(
    String action, {
    Map<String, dynamic>? body,
    bool confirm = false,
  }) async {
    if (confirm && !await _confirm(action)) return;
    setState(() => _busy = true);
    try {
      final updated = await _service.action(
        widget.transferId,
        action,
        body: body,
      );
      if (!mounted) return;
      setState(() {
        _job = updated;
        _error = null;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _scanDialog() async {
    final manual = TextEditingController();
    String? seen;
    await showDialog<void>(
      context: context,
      builder: (dialog) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const Text('Scan manifest parcel'),
          content: SizedBox(
            width: 360,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(
                  height: 220,
                  child: MobileScanner(
                    onDetect: (capture) {
                      if (seen != null) return;
                      final value = capture.barcodes.firstOrNull?.rawValue;
                      if (value != null && value.isNotEmpty) {
                        seen = value;
                        Navigator.pop(dialog);
                      }
                    },
                  ),
                ),
                TextField(
                  controller: manual,
                  decoration: const InputDecoration(
                    labelText: 'Or enter tracking number',
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () {
                seen = manual.text.trim();
                Navigator.pop(dialog);
              },
              child: const Text('Submit scan'),
            ),
          ],
        ),
      ),
    );
    final tracking = seen;
    if (tracking != null && tracking.isNotEmpty) {
      await _act('load', body: {'tracking_number': tracking});
    }
  }

  Future<void> _navigate(Map<String, dynamic> location) async {
    final address = Uri.encodeComponent((location['address'] ?? '').toString());
    final uri = Uri.parse(
      'https://www.google.com/maps/search/?api=1&query=$address',
    );
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
        mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No navigation app could open this address.'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final job = _job;
    final status = job?.status ?? '';
    final headingToDestination = status == 'in_transit' || status == 'arrived';
    return Scaffold(
      appBar: AppBar(title: Text(job?.reference ?? 'Transfer details')),
      body: _error != null && job == null
          ? Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'Unable to load transfer: $_error',
                    textAlign: TextAlign.center,
                  ),
                  ElevatedButton(onPressed: _load, child: const Text('Retry')),
                ],
              ),
            )
          : job == null
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  _summaryCard(job, headingToDestination),
                  if (job.notes.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Card(
                      color: const Color(0xFFF7FAF9),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'DISPATCHER INSTRUCTIONS',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                                color: Color(0xFF16697A),
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(job.notes),
                          ],
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  _manifestCard(job),
                  const SizedBox(height: 12),
                  if (status == 'loading' &&
                      job.data['origin_arrived_at'] == null)
                    _action('Arrived at Origin Hub', 'origin-arrival'),
                  if (status == 'loading' &&
                      job.data['origin_arrived_at'] != null) ...[
                    _action('Scan Parcel', null, onPressed: _scanDialog),
                    if (job.parcelCount > 0 &&
                        job.loadedCount >= job.parcelCount)
                      _action('Complete Loading', 'complete-loading'),
                  ],
                  if (status == 'ready_for_dispatch')
                    _action('Dispatch Transfer', 'dispatch', confirm: true),
                  if (status == 'in_transit')
                    _action(
                      'Arrived at Destination Hub',
                      'destination-arrival',
                      confirm: true,
                    ),
                  if (status == 'arrived' ||
                      status == 'receiving' ||
                      status == 'exception')
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'At destination: unload and hand over the manifest. '
                          'Destination hub staff must scan every parcel. Your '
                          'arrival does not confirm receipt.',
                        ),
                      ),
                    ),
                  if (_busy)
                    const Center(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: CircularProgressIndicator(),
                      ),
                    ),
                  if (_error != null)
                    Text(_error!, style: const TextStyle(color: Colors.red)),
                  const SizedBox(height: 16),
                  const Text(
                    'Transfer history',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                  for (final event in asJsonMapList(job.data['events']))
                    ListTile(
                      dense: true,
                      leading: const Icon(Icons.history),
                      title: Text(
                        asJsonString(event['type'], 'update').replaceAll('_', ' '),
                      ),
                      subtitle: Text(
                        '${asJsonString(event['notes'])}\n'
                        '${asJsonString(event['at'])}',
                      ),
                    ),
                ],
              ),
            ),
    );
  }

  Widget _summaryCard(
    HubTransferJob job,
    bool headingToDestination,
  ) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'HUB TRANSFER',
            style: TextStyle(fontWeight: FontWeight.bold),
          ),
          Text(job.reference, style: Theme.of(context).textTheme.titleLarge),
          Text('Status: ${job.statusLabel}'),
          if (job.plannedDispatchAt != null)
            Text('Planned dispatch: ${_formatTimestamp(job.plannedDispatchAt)}'),
          if (job.dispatchedAt != null)
            Text('Dispatched: ${_formatTimestamp(job.dispatchedAt)}'),
          if (job.destinationArrivedAt != null)
            Text(
              'Arrived at destination: '
              '${_formatTimestamp(job.destinationArrivedAt)}',
            ),
          if (job.receivedAt != null)
            Text('Completed: ${_formatTimestamp(job.receivedAt)}'),
          Text(
            'Vehicle: ${asJsonString(job.vehicle['type'], 'Vehicle')} · '
            '${asJsonString(job.vehicle['plate_number'], 'Plate unavailable')}',
          ),
          Text(
            'Parcels: ${job.parcelCount} total · ${job.loadedCount} loaded · '
            '${job.receivedCount} received'
            '${job.missingCount > 0 ? ' · ${job.missingCount} missing' : ''}',
          ),
          Text(
            'Combined weight: ${job.totalWeightKg == null ? 'Not recorded' : '${job.totalWeightKg!.toStringAsFixed(2)} kg'}',
          ),
          const SizedBox(height: 12),
          Text(
            '${job.origin['name']}  →  ${job.destination['name']}',
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
          ),
          Text('ORIGIN · ${job.origin['address'] ?? 'Address not recorded'}'),
          Text(
            'DESTINATION · ${job.destination['address'] ?? 'Address not recorded'}',
          ),
          TextButton.icon(
            onPressed: () =>
                _navigate(headingToDestination ? job.destination : job.origin),
            icon: const Icon(Icons.navigation),
            label: Text(
              headingToDestination
                  ? 'Navigate to Destination Hub'
                  : 'Navigate to Origin Hub',
            ),
          ),
        ],
      ),
    ),
  );

  Widget _manifestCard(HubTransferJob job) {
    final receivedPhase = [
      'arrived',
      'receiving',
      'exception',
      'received',
    ].contains(job.status);
    final progressCount = receivedPhase ? job.receivedCount : job.loadedCount;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Manifest · ${job.parcelCount} parcels'
              '${job.totalWeightKg == null ? '' : ' · ${job.totalWeightKg!.toStringAsFixed(2)} kg'}',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 4),
            const Text(
              'One transfer carries every parcel below. Parcel journey is '
              'tracked individually; truck arrival does not mark a parcel '
              'received.',
              style: TextStyle(fontSize: 11, color: Colors.black54),
            ),
            const SizedBox(height: 8),
            LinearProgressIndicator(
              value: job.parcelCount == 0 ? 0 : progressCount / job.parcelCount,
            ),
            const SizedBox(height: 4),
            Text(
              '${job.loadedCount} of ${job.parcelCount} parcels loaded · '
              '${job.receivedCount} received'
              '${job.missingCount > 0 ? ' · ${job.missingCount} missing' : ''}',
            ),
            const SizedBox(height: 8),
            for (final parcel in job.parcels)
              ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                leading: Icon(
                  _parcelJourneyIcon(parcelJourneyStatus(parcel)),
                  color: _parcelJourneyColor(parcelJourneyStatus(parcel)),
                ),
                title: Text(
                  asJsonString(parcel['tracking_number'], 'Parcel'),
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                subtitle: Text(
                  '${parcelJourneyLabel(parcel)}'
                  '${parcelWeightKg(parcel) == null ? '' : ' · ${parcelWeightKg(parcel)!.toStringAsFixed(2)} kg'}'
                  '${parcel['condition'] == null ? '' : ' · ${parcel['condition']}'}'
                  '${parcel['exception_reason'] == null ? '' : ' (${parcel['exception_reason']})'}',
                ),
              ),
          ],
        ),
      ),
    );
  }

  IconData _parcelJourneyIcon(String status) {
    switch (status) {
      case 'received':
      case 'received_damaged':
        return Icons.check_circle;
      case 'missing':
        return Icons.cancel;
      case 'arrived':
        return Icons.warehouse;
      case 'dispatched':
        return Icons.local_shipping;
      case 'loaded':
        return Icons.inventory_2;
      case 'removed':
        return Icons.remove_circle;
      default:
        return Icons.radio_button_unchecked;
    }
  }

  Color _parcelJourneyColor(String status) {
    switch (status) {
      case 'received':
        return Colors.green.shade700;
      case 'received_damaged':
      case 'missing':
        return Colors.red.shade700;
      case 'arrived':
        return Colors.blue.shade700;
      case 'dispatched':
        return const Color(0xFF16697A);
      case 'loaded':
        return Colors.purple.shade700;
      default:
        return Colors.grey;
    }
  }

  Widget _action(
    String label,
    String? endpoint, {
    bool confirm = false,
    VoidCallback? onPressed,
  }) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: SizedBox(
      height: 54,
      child: FilledButton.icon(
        onPressed: _busy
            ? null
            : onPressed ?? () => _act(endpoint!, confirm: confirm),
        icon: const Icon(Icons.arrow_forward),
        label: Text(label),
      ),
    ),
  );
}
