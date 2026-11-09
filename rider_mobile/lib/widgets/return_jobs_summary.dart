import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/network/api_client.dart';
import '../providers/auth_provider.dart';
import '../screens/deliveries/return_collections_screen.dart';

class ReturnJobsSummary extends StatefulWidget {
  const ReturnJobsSummary({
    super.key,
    this.courier = false,
    this.refreshKey,
    this.api,
  });
  final bool courier;
  final Object? refreshKey;
  final ApiClient? api;
  @override
  State<ReturnJobsSummary> createState() => _ReturnJobsSummaryState();
}

class _ReturnJobsSummaryState extends State<ReturnJobsSummary>
    with WidgetsBindingObserver {
  List<Map<String, dynamic>> _jobs = [];
  bool _loading = true;
  String? _error;
  int _generation = 0;
  ApiClient get _api => widget.api ?? context.read<AuthProvider>().api;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
  }

  @override
  void didUpdateWidget(covariant ReturnJobsSummary oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.refreshKey, widget.refreshKey)) _load();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> _load() async {
    final generation = ++_generation;
    try {
      final raw = await _api.get(
        widget.courier ? '/courier/returns' : '/rider/returns',
      );
      if (!mounted || generation != _generation) return;
      setState(() {
        _jobs = raw is Map
            ? ((raw['returns'] as List?) ?? [])
                  .map((e) => Map<String, dynamic>.from(e as Map))
                  .toList()
            : [];
        _error = null;
        _loading = false;
      });
    } catch (e) {
      if (mounted && generation == _generation) {
        setState(() {
          _error = 'Could not refresh return assignments.';
          _loading = false;
        });
      }
    }
  }

  Future<void> _open() async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) =>
            ReturnCollectionsScreen(courier: widget.courier, api: _api),
      ),
    );
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      leading: const Icon(Icons.assignment_return_outlined),
      title: Text(
        _loading
            ? 'Checking return assignments…'
            : _jobs.isEmpty
            ? (widget.courier ? 'Return transfers' : 'Return jobs')
            : '${_jobs.length} return assignment${_jobs.length == 1 ? '' : 's'}',
      ),
      subtitle: Text(
        _error ??
            (_jobs.isEmpty
                ? 'Buyer-to-seller returns · Tap to view'
                : _jobs
                      .take(3)
                      .map(
                        (j) =>
                            'Return #${j['id']} · ${widget.courier
                                ? 'Hub-to-hub transfer'
                                : j['stage'] == 'pickup_assigned'
                                ? 'Collect from buyer'
                                : 'Return delivery'}',
                      )
                      .join('\n')),
      ),
      trailing: IconButton(
        tooltip: 'Refresh return jobs',
        onPressed: _load,
        icon: const Icon(Icons.refresh),
      ),
      onTap: _open,
    ),
  );
}
