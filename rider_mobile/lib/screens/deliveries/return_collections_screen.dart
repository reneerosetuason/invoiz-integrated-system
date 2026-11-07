import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../providers/auth_provider.dart';

class ReturnCollectionsScreen extends StatefulWidget {
  const ReturnCollectionsScreen({super.key, this.takeHandoverPhoto});
  final Future<XFile?> Function()? takeHandoverPhoto;

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

  Future<void> _collect(int id, {bool deliverToSeller = false}) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          deliverToSeller ? 'Delivered to seller?' : 'Parcel collected?',
        ),
        content: Text(
          deliverToSeller
              ? 'Record the actual seller handover. The seller must separately confirm inspection and receipt.'
              : 'Confirm only when the returned item is physically with you. Collect no cash and bring it to the handling hub.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(
              deliverToSeller ? 'Confirm delivery' : 'Confirm collection',
            ),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted || _busy != null) return;
    setState(() => _busy = id);
    try {
      final photo = widget.takeHandoverPhoto != null
          ? await widget.takeHandoverPhoto!()
          : await ImagePicker().pickImage(
              source: ImageSource.camera,
              maxWidth: 1800,
              imageQuality: 85,
            );
      if (photo == null || !mounted) return;
      final bytes = await photo.readAsBytes();
      if (!mounted) return;
      await context.read<AuthProvider>().api.postMultipart(
        '/rider/returns/$id/pickup',
        fileField: 'proof',
        fileBytes: bytes,
        filename: photo.name,
        fields: {'action': deliverToSeller ? 'deliver_seller' : 'pickup'},
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
    appBar: AppBar(title: const Text('Return jobs')),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Collect no cash. Photograph the handover and wait for the receiving party to confirm it. Return service fees use their own remittances and need your receipt acceptance.',
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
                    if ([
                      'sent_to_seller',
                      'seller_arrival',
                    ].contains(job['stage'])) ...[
                      Text(
                        'Deliver to: ${job['seller_name'] ?? ''} · ${job['seller_phone'] ?? ''}',
                      ),
                      Text('${job['seller_address'] ?? ''}'),
                    ],
                    if (job['stage'] == 'sent_to_seller')
                      TextButton.icon(
                        onPressed: _busy != null
                            ? null
                            : () => _collect(
                                job['id'] as int,
                                deliverToSeller: true,
                              ),
                        icon: const Icon(Icons.local_shipping_outlined),
                        label: const Text('Record delivery to seller'),
                      ),
                    if (job['stage'] == 'seller_arrival')
                      const Text('Awaiting seller receipt confirmation.'),
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
