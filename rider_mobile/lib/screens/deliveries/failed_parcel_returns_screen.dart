import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../providers/auth_provider.dart';

/// Funded seller-return jobs. Buyer return/refund jobs keep their existing screen.
class FailedParcelReturnsScreen extends StatefulWidget {
  const FailedParcelReturnsScreen({super.key, this.api});
  final ApiClient? api;

  @override
  State<FailedParcelReturnsScreen> createState() =>
      _FailedParcelReturnsScreenState();
}

class _FailedParcelReturnsScreenState extends State<FailedParcelReturnsScreen> {
  late final ApiClient _api = widget.api ?? context.read<AuthProvider>().api;
  List<Map<String, dynamic>> _cases = [];
  bool _loading = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final rows = <Map<String, dynamic>>[];
      var page = 1;
      var last = 1;
      do {
        final data = await _api.get(
          '/failed-deliveries',
          query: {'page': page},
        );
        rows.addAll(
          (data['cases'] as List? ?? []).map(
            (row) => Map<String, dynamic>.from(row as Map),
          ),
        );
        last = (data['last_page'] as num?)?.toInt() ?? 1;
        page++;
      } while (page <= last);
      if (!mounted) return;
      setState(() {
        _cases = rows;
        _error = null;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _act(Map<String, dynamic> job, String action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      if (action == 'arrive' || action == 'seller_arrival') {
        final photo = await ImagePicker().pickImage(
          source: ImageSource.camera,
          imageQuality: 75,
        );
        if (photo == null) return;
        final bytes = await photo.readAsBytes();
        if (bytes.length > 8 * 1024 * 1024) {
          throw Exception('The handover photo must be smaller than 8 MB.');
        }
        await _api.postMultipart(
          '/failed-deliveries/${job['delivery_id']}',
          fileField: 'proof',
          fileBytes: bytes,
          filename: photo.name,
          fields: {'action': action},
        );
      } else {
        final confirmed = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Confirm physical parcel custody'),
            content: const Text(
              'Continue only when this transport or collection step has actually happened.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Not yet'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Confirm'),
              ),
            ],
          ),
        );
        if (confirmed != true) return;
        await _api.post(
          '/failed-deliveries/${job['delivery_id']}',
          body: {'action': action},
        );
      }
      await _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Failed-parcel returns')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_error != null) ...[
                  Text(_error!),
                  TextButton(onPressed: _load, child: const Text('Retry')),
                ],
                if (_error == null && _cases.isEmpty)
                  const Text(
                    'No failed-parcel return jobs are assigned to you.',
                  ),
                for (final job in _cases) _job(job),
              ],
            ),
          ),
  );

  Widget _job(Map<String, dynamic> job) {
    final stage = job['stage'] as String? ?? '';
    String? action;
    String? label;
    if (job['funded'] == true && job['decision'] == 'return') {
      if (job['is_driver'] == true) {
        if (stage == 'at_hub') {
          action = job['driver_accepted'] == true ? 'depart' : 'driver_accept';
          label = job['driver_accepted'] == true
              ? 'Depart with return parcel'
              : 'Accept return trip';
        } else if (stage == 'in_transfer') {
          action = 'arrive';
          label = 'Record hub handover photo';
        }
      }
      if (job['is_rider'] == true) {
        if (stage == 'seller_assigned') {
          action = 'rider_collect';
          label = 'Confirm collection for seller';
        } else if (stage == 'seller_delivery') {
          action = 'seller_arrival';
          label = 'Record seller handover photo';
        }
      }
    }
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              job['tracking_number'] as String? ?? 'Return parcel',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            Text(stage.replaceAll('_', ' ')),
            Text('Collection hub: ${job['center_name'] ?? ''}'),
            Text(job['center_address'] as String? ?? ''),
            Text('Seller-side hub: ${job['seller_center_name'] ?? ''}'),
            if (job['is_rider'] == true) ...[
              Text(job['seller_name'] as String? ?? ''),
              Text(job['seller_address'] as String? ?? ''),
            ],
            if (job['funded'] != true)
              const Text('Waiting for verified seller funding.'),
            if (action != null)
              FilledButton(
                onPressed: _busy ? null : () => _act(job, action!),
                child: Text(_busy ? 'Working...' : label!),
              ),
          ],
        ),
      ),
    );
  }
}
