import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';

class ReturnCollectionsScreen extends StatefulWidget {
  const ReturnCollectionsScreen({super.key});

  @override
  State<ReturnCollectionsScreen> createState() =>
      _ReturnCollectionsScreenState();
}

class _ReturnCollectionsScreenState extends State<ReturnCollectionsScreen> {
  List<Map<String, dynamic>> _jobs = [];
  bool _loading = true;
  int? _busy;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await context.read<AuthProvider>().api.get('/rider/returns');
      if (!mounted) return;
      setState(() {
        _jobs = ((data as Map)['returns'] as List)
            .map((row) => Map<String, dynamic>.from(row as Map))
            .toList();
        _error = null;
      });
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _collect(int id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Parcel collected?'),
        content: const Text(
          'Confirm only when the returned item is physically with you. Collect no cash and bring it to the handling hub.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm collection'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted || _busy != null) return;
    setState(() => _busy = id);
    try {
      await context.read<AuthProvider>().api.post(
        '/rider/returns/$id/pickup',
        body: {},
      );
      await _load();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Return collections')),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Buyer returns collect no cash. Confirm collection, then hand the parcel to hub staff. These jobs do not create a second forward-delivery earning.',
          ),
          const SizedBox(height: 16),
          if (_loading) const Center(child: CircularProgressIndicator()),
          if (_error != null) ...[
            Text(_error!),
            TextButton(onPressed: _load, child: const Text('Retry')),
          ],
          if (!_loading && _error == null && _jobs.isEmpty)
            const Text('No assigned return collections.'),
          for (final job in _jobs)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Return #${job['id']} · Order #${job['order_id']}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    Text('${job['product']} · ${job['quantity']} item(s)'),
                    const SizedBox(height: 8),
                    Text(
                      '${job['buyer_name'] ?? ''} · ${job['buyer_phone'] ?? ''}',
                    ),
                    Text(
                      '${job['collection_address'] ?? 'Contact the handling hub for the collection address.'}',
                    ),
                    Text(
                      'Handling hub #${job['center_id']} · No cash to collect',
                    ),
                    if (job['stage'] == 'pickup_assigned')
                      TextButton.icon(
                        onPressed: _busy != null
                            ? null
                            : () => _collect(job['id'] as int),
                        icon: const Icon(Icons.assignment_return_outlined),
                        label: const Text('Confirm collection'),
                      ),
                    if (job['stage'] == 'collected')
                      const Text('Collected. Awaiting hub staff receipt.'),
                  ],
                ),
              ),
            ),
        ],
      ),
    ),
  );
}
