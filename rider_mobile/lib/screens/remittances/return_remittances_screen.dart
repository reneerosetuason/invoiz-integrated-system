import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/receipt_confirmation.dart';

class ReturnRemittancesScreen extends StatefulWidget {
  const ReturnRemittancesScreen({super.key});
  @override
  State<ReturnRemittancesScreen> createState() =>
      _ReturnRemittancesScreenState();
}

class _ReturnRemittancesScreenState extends State<ReturnRemittancesScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _busy = false;
  int _page = 1;
  String _money(dynamic cents) =>
      '₱${((cents as num? ?? 0) / 100).toStringAsFixed(2)}';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await context
          .read<AuthProvider>()
          .api
          .getSharedAuthenticated('return-remittances?page=$_page');
      if (mounted) {
        setState(() {
          _data = Map<String, dynamic>.from(data as Map);
          _error = null;
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = '$error');
    }
  }

  Future<void> _send(int id, Map<String, dynamic> fields) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await context.read<AuthProvider>().api.postSharedAuthenticated(
        'return-remittances/$id',
        {'confirm': 1, ...fields},
      );
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = '$error');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _account(Map<String, dynamic> row) async {
    final existing = Map<String, dynamic>.from(
      row['receiving_destination'] as Map? ?? {},
    );
    final controllers = {
      for (final key in ['provider', 'holder', 'account'])
        key: TextEditingController(text: '${existing[key] ?? ''}'),
    };
    final form = GlobalKey<FormState>();
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('Verify remittance account'),
        scrollable: true,
        content: Form(
          key: form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Check your receiving details. Never enter a PIN, password or OTP.',
              ),
              for (final entry in {
                'provider': 'Bank / e-wallet',
                'holder': 'Account holder',
                'account': 'Account number',
              }.entries)
                TextFormField(
                  controller: controllers[entry.key],
                  decoration: InputDecoration(labelText: entry.value),
                  maxLength: 100,
                  validator: (value) =>
                      (value ?? '').trim().isEmpty ? 'Required' : null,
                ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () {
              if (form.currentState!.validate()) {
                Navigator.pop(dialog, {
                  for (final entry in controllers.entries)
                    entry.key: entry.value.text.trim(),
                });
              }
            },
            child: const Text('I checked these details'),
          ),
        ],
      ),
    );
    for (final controller in controllers.values) {
      controller.dispose();
    }
    if (result != null && mounted) {
      await _send(row['id'] as int, {'action': 'destination', ...result});
    }
  }

  Future<void> _proof(int id) async {
    try {
      final bytes = await context.read<AuthProvider>().api.getSharedImage(
        'return-files/remittance/$id',
      );
      if (mounted) {
        await showDialog<void>(
          context: context,
          builder: (dialog) => Dialog(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Flexible(
                  child: InteractiveViewer(
                    child: Image.memory(bytes, fit: BoxFit.contain),
                  ),
                ),
                TextButton(
                  onPressed: () => Navigator.pop(dialog),
                  child: const Text('Close proof'),
                ),
              ],
            ),
          ),
        );
      }
    } catch (error) {
      if (mounted) setState(() => _error = '$error');
    }
  }

  @override
  Widget build(BuildContext context) {
    final page = Map<String, dynamic>.from(_data?['allocations'] as Map? ?? {});
    return Scaffold(
      appBar: AppBar(title: const Text('Return payments & earnings')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Confirmed return service earnings: ${_money(_data?['accepted_earnings_cents'])}',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const Text(
              'Return services are separate from original delivery earnings. Check your bank/e-wallet before accepting a remittance.',
            ),
            if (_error != null) ...[
              Text(_error!),
              TextButton(onPressed: _load, child: const Text('Retry')),
            ],
            if (_data == null && _error == null)
              const Center(child: CircularProgressIndicator()),
            for (final value in page['data'] as List? ?? [])
              Builder(
                builder: (context) {
                  final row = Map<String, dynamic>.from(value as Map);
                  return Card(
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${row['role']}'.replaceAll('_', ' '),
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          Text(
                            'Return #${(row['settlement'] as Map?)?['return_request_id']} · ${_money(row['amount_cents'])}',
                          ),
                          Text('Status: ${row['status']}'),
                          if (row['reference'] != null) ...[
                            Text('Transfer: ${row['reference']}'),
                            TextButton(
                              onPressed: () => _proof(row['id'] as int),
                              child: const Text('View remittance proof'),
                            ),
                          ],
                          if (row['can_accept'] == true &&
                              ['reserved', 'eligible'].contains(row['status']))
                            TextButton(
                              onPressed: _busy ? null : () => _account(row),
                              child: const Text('Verify remittance account'),
                            ),
                          if (row['can_accept'] == true &&
                              row['status'] == 'remitted')
                            TextButton(
                              onPressed: _busy
                                  ? null
                                  : () async {
                                      final ok = await showReceiptConfirmation(
                                        context,
                                        title: 'Payment received?',
                                        message:
                                            'I checked my actual receiving account and received ${_money(row['amount_cents'])}.',
                                        cancelLabel: 'Not yet',
                                        confirmLabel: 'Accept receipt',
                                      );
                                      if (ok == true && mounted) {
                                        await _send(row['id'] as int, {
                                          'action': 'accept',
                                        });
                                      }
                                    },
                              child: const Text('Accept remittance received'),
                            ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            if ((page['data'] as List? ?? []).isEmpty && _data != null)
              const Text('No return allocations assigned to you.'),
            Row(
              children: [
                Expanded(
                  child: TextButton(
                    onPressed: _page > 1
                        ? () {
                            setState(() => _page--);
                            _load();
                          }
                        : null,
                    child: const Text('Previous'),
                  ),
                ),
                Expanded(
                  child: TextButton(
                    onPressed: page['next_page_url'] != null
                        ? () {
                            setState(() => _page++);
                            _load();
                          }
                        : null,
                    child: const Text('Next'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
