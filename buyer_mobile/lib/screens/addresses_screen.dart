import '../widgets/buyer_page.dart';
import 'package:flutter/material.dart';
import '../models/address.dart';
import '../services/api_service.dart';
import '../widgets/main_layout.dart';
import 'add_address_screen.dart';

class AddressesScreen extends StatefulWidget {
  final ApiService? api;
  const AddressesScreen({super.key, this.api});
  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  late final _api = widget.api ?? ApiService();
  List<Address> _addresses = [];
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
      final data = await _api.get('addresses');
      if (!mounted) return;
      setState(() {
        _addresses = (data['addresses'] as List)
            .whereType<Map<String, dynamic>>()
            .map(Address.fromJson)
            .toList();
        _error = null;
      });
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _edit([Address? address]) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => AddAddressScreen(address: address, api: _api),
      ),
    );
    if (mounted) await _load();
  }

  Future<void> _change(Address address, {bool delete = false}) async {
    if (_busy) return;
    if (delete) {
      final yes = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Delete address?'),
          content: Text(address.fullAddress),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Keep'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Delete'),
            ),
          ],
        ),
      );
      if (yes != true || !mounted) return;
    }
    setState(() => _busy = true);
    try {
      if (delete) {
        await _api.delete('addresses/${address.id}');
      } else {
        await _api.put('addresses/${address.id}', {'is_default': true});
      }
      await _load();
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => MainLayout(
    title: 'My addresses',
    showBottomNav: false,
    child: _loading
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 24, 20, 32),
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                const BuyerPageHeading(
                  title: 'Your delivery addresses',
                  description: 'Choose where your next order should arrive.',
                ),
                FilledButton.icon(
                  onPressed: _busy ? null : () => _edit(),
                  icon: const Icon(Icons.add_location_alt_outlined),
                  label: const Text('Add address'),
                ),
                const SizedBox(height: 12),
                if (_error != null) ...[
                  Text(_error!),
                  TextButton(onPressed: _load, child: const Text('Try again')),
                ],
                if (_addresses.isEmpty && _error == null)
                  const Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('No saved addresses. Add one for delivery.'),
                  ),
                for (final address in _addresses)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              address.recipientName,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Text(address.phone),
                            const SizedBox(height: 6),
                            Text(address.fullAddress),
                            if (address.isDefault)
                              const Chip(
                                label: Text('Default delivery address'),
                                avatar: Icon(Icons.check, size: 16),
                              ),
                            Wrap(
                              spacing: 8,
                              children: [
                                TextButton.icon(
                                  onPressed: _busy
                                      ? null
                                      : () => _edit(address),
                                  icon: const Icon(
                                    Icons.edit_outlined,
                                    size: 18,
                                  ),
                                  label: const Text('Edit'),
                                ),
                                if (!address.isDefault)
                                  TextButton(
                                    onPressed: _busy
                                        ? null
                                        : () => _change(address),
                                    child: const Text('Make default'),
                                  ),
                                if (!address.isDefault)
                                  TextButton(
                                    onPressed: _busy
                                        ? null
                                        : () => _change(address, delete: true),
                                    child: const Text('Delete'),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                if (_addresses.any((a) => a.isDefault))
                  const Text(
                    'Choose another default address before deleting the current default.',
                  ),
              ],
            ),
          ),
  );
}
