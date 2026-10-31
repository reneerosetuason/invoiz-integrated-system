import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/network/api_exception.dart';
import '../../models/hub_courier.dart';
import '../../providers/auth_provider.dart';
import '../../services/hub_courier_service.dart';
import '../onboarding/rider_tutorial_screen.dart';

/// Hub Transport Driver workspace. Hub Transport Drivers sign in with their own
/// `hub_transport_drivers` credentials and only ever see hub-to-hub
/// transfers assigned to their own four-wheel vehicle.
class HubCourierHomeScreen extends StatefulWidget {
  const HubCourierHomeScreen({super.key});

  @override
  State<HubCourierHomeScreen> createState() => _HubCourierHomeScreenState();
}

class _HubCourierHomeScreenState extends State<HubCourierHomeScreen> {
  late Future<Map<String, dynamic>> _dashboard;
  HubCourierService get _service =>
      HubCourierService(context.read<AuthProvider>().api);

  @override
  void initState() {
    super.initState();
    _dashboard = _service.dashboard();
  }

  Future<void> _refresh() async {
    setState(() => _dashboard = _service.dashboard());
    await _dashboard;
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>>(
    future: _dashboard,
    builder: (context, snapshot) => Scaffold(
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
      body:
          snapshot.connectionState == ConnectionState.waiting &&
              !snapshot.hasData
          ? const Center(child: CircularProgressIndicator())
          : snapshot.hasError
          ? _error(snapshot.error.toString())
          : RefreshIndicator(
              onRefresh: _refresh,
              child: _content(snapshot.data ?? {}),
            ),
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

  Widget _content(Map<String, dynamic> data) {
    final courier = HubCourier.fromJson(
      Map<String, dynamic>.from(data['courier'] as Map? ?? {}),
    );
    final vehicle = courier.vehicle ?? {};
    final jobs = (data['available_transfers'] as List? ?? [])
        .map((e) => HubTransferJob(Map<String, dynamic>.from(e as Map)))
        .toList();
    final completed = (data['completed_transfers'] as List? ?? [])
        .map((e) => HubTransferJob(Map<String, dynamic>.from(e as Map)))
        .toList();
    final active = data['active_transfer'] is Map
        ? HubTransferJob(
            Map<String, dynamic>.from(data['active_transfer'] as Map),
          )
        : null;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'HUB TRANSPORT DRIVER',
                  style: Theme.of(context).textTheme.labelLarge,
                ),
                const SizedBox(height: 6),
                Text(
                  courier.name,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                Text(
                  '${courier.company} · ${vehicle['type'] ?? 'Vehicle'} · '
                  '${vehicle['plate_number'] ?? 'No plate'}',
                ),
                const SizedBox(height: 4),
                Text(
                  'Vehicle capacity: ${vehicle['parcel_capacity'] ?? 0} parcels · '
                  '${vehicle['capacity_kg'] ?? 0} kg',
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    courier.online
                        ? 'Online for assigned transfers'
                        : 'Offline',
                  ),
                  value: courier.online,
                  onChanged: (value) async {
                    try {
                      await _service.setStatus(value);
                      await _refresh();
                    } on ApiException catch (e) {
                      _message('Could not change status: ${e.message}');
                    } catch (e) {
                      _message('Could not change status: $e');
                    }
                  },
                ),
              ],
            ),
          ),
        ),
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
            '${job.status.replaceAll('_', ' ')}',
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
              'Vehicle: ${job.data['vehicle']?['type'] ?? 'Vehicle'} · '
              '${job.data['vehicle']?['plate_number'] ?? ''}\n'
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
                  _summaryCard(job, status, headingToDestination),
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
                  for (final event in (job.data['events'] as List? ?? []))
                    ListTile(
                      dense: true,
                      leading: const Icon(Icons.history),
                      title: Text(
                        (event['type'] ?? '').toString().replaceAll('_', ' '),
                      ),
                      subtitle: Text(
                        '${event['notes'] ?? ''}\n${event['at'] ?? ''}',
                      ),
                    ),
                ],
              ),
            ),
    );
  }

  Widget _summaryCard(
    HubTransferJob job,
    String status,
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
          Text('Status: ${status.replaceAll('_', ' ')}'),
          Text(
            'Vehicle: ${job.data['vehicle']?['type'] ?? 'Vehicle'} · '
            '${job.data['vehicle']?['plate_number'] ?? 'Plate unavailable'}',
          ),
          Text(
            'Manifest weight: ${job.data['total_weight_kg'] == null ? 'Not recorded' : '${job.data['total_weight_kg']} kg'}',
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

  Widget _manifestCard(HubTransferJob job) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Manifest · ${job.parcelCount} parcels'),
          LinearProgressIndicator(
            value: job.parcelCount == 0 ? 0 : job.loadedCount / job.parcelCount,
          ),
          Text('${job.loadedCount} of ${job.parcelCount} parcels loaded'),
          const SizedBox(height: 8),
          for (final parcel in job.parcels)
            ListTile(
              dense: true,
              leading: Icon(
                parcel['loading_status'] == 'loaded'
                    ? Icons.check_circle
                    : Icons.radio_button_unchecked,
              ),
              title: Text(parcel['tracking_number']?.toString() ?? 'Parcel'),
              subtitle: Text(
                'Loading: ${parcel['loading_status']} · '
                'Receiving: ${parcel['receiving_status']}'
                '${parcel['condition'] == null ? '' : ' · ${parcel['condition']}'}',
              ),
            ),
        ],
      ),
    ),
  );

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
