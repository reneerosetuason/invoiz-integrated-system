import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../providers/auth_provider.dart';
import '../../core/network/api_client.dart';

class ReturnCollectionsScreen extends StatefulWidget {
  const ReturnCollectionsScreen({
    super.key,
    this.takeHandoverPhoto,
    this.courier = false,
    this.api,
  });
  final Future<XFile?> Function()? takeHandoverPhoto;
  final bool courier;
  final ApiClient? api;

  @override
  State<ReturnCollectionsScreen> createState() =>
      _ReturnCollectionsScreenState();
}

class _ReturnCollectionsScreenState extends State<ReturnCollectionsScreen> {
  List<Map<String, dynamic>> _jobs = [];
  bool _loading = true;
  int? _busy;
  String? _error;
  ApiClient get _api => widget.api ?? context.read<AuthProvider>().api;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _api.get(
        widget.courier ? '/courier/returns' : '/rider/returns',
      );
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

  Future<void> _collect(
    int id, {
    bool deliverToSeller = false,
    bool driverArrival = false,
  }) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          driverArrival
              ? 'Arrived at destination hub?'
              : deliverToSeller
              ? 'Delivered to seller?'
              : 'Parcel collected?',
        ),
        content: Text(
          driverArrival
              ? 'Photograph the actual handover at the destination hub. Hub staff must separately confirm parcel receipt.'
              : deliverToSeller
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
              driverArrival
                  ? 'Record arrival'
                  : deliverToSeller
                  ? 'Confirm delivery'
                  : 'Confirm collection',
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
      await _api.postMultipart(
        driverArrival ? '/courier/returns/$id' : '/rider/returns/$id/pickup',
        fileField: 'proof',
        fileBytes: bytes,
        filename: photo.name,
        fields: {
          'action': driverArrival
              ? 'arrive'
              : deliverToSeller
              ? 'deliver_seller'
              : 'pickup',
          'confirm': '1',
        },
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

  Future<void> _accept(int id) async {
    if (_busy != null) return;
    setState(() => _busy = id);
    try {
      await _api.post(
        '/courier/returns/$id',
        body: {'action': 'accept', 'confirm': true},
      );
      await _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$e')));
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
            'Collect no cash. Photograph the handover and wait for the receiving party to confirm it. Your service pay appears in Remittances alongside order payments; confirm actual receipt there.',
          ),
          const SizedBox(height: 16),
          if (_loading) const Center(child: CircularProgressIndicator()),
          if (_error != null) ...[
            Text(_error!),
            TextButton(onPressed: _load, child: const Text('Retry')),
          ],
          if (!_loading && _error == null && _jobs.isEmpty)
            const Text('No assigned return jobs. Pull down to refresh.'),
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
                    if (widget.courier) ...[
                      Text('From: ${job['origin_hub'] ?? 'Collection hub'}'),
                      Text('${job['origin_address'] ?? ''}'),
                      Text(
                        'To: ${job['destination_hub'] ?? 'Seller-side hub'}',
                      ),
                      Text('${job['destination_address'] ?? ''}'),
                      Text('Reference: ${job['transport_reference'] ?? ''}'),
                      if (job['driver_accepted_at'] == null)
                        TextButton(
                          onPressed: _busy != null
                              ? null
                              : () => _accept(job['id'] as int),
                          child: const Text('Accept return transfer'),
                        ),
                      if (job['driver_accepted_at'] != null &&
                          job['driver_arrived_at'] == null)
                        TextButton(
                          onPressed: _busy != null
                              ? null
                              : () => _collect(
                                  job['id'] as int,
                                  driverArrival: true,
                                ),
                          child: const Text(
                            'Record arrival at destination hub',
                          ),
                        ),
                      if (job['driver_arrived_at'] != null)
                        const Text(
                          'Arrival recorded. Waiting for destination hub receipt.',
                        ),
                    ] else ...[
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
                  ],
                ),
              ),
            ),
        ],
      ),
    ),
  );
}
