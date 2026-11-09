import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../core/network/api_exception.dart';

/// One saved receiving account, reused by all order and return payments.
class PayoutAccountButton extends StatelessWidget {
  const PayoutAccountButton({super.key, required this.api});
  final ApiClient api;

  Future<void> _edit(BuildContext context) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _PayoutAccountEditor(api: api),
    );
    if (saved == true && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Payout details saved. Admin sees your latest account.',
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) => TextButton.icon(
    onPressed: () => _edit(context),
    icon: const Icon(Icons.account_balance_outlined, size: 18),
    label: const Text('Bank details'),
  );
}

class _PayoutAccountEditor extends StatefulWidget {
  const _PayoutAccountEditor({required this.api});
  final ApiClient api;

  @override
  State<_PayoutAccountEditor> createState() => _PayoutAccountEditorState();
}

class _PayoutAccountEditorState extends State<_PayoutAccountEditor> {
  final _form = GlobalKey<FormState>();
  final _provider = TextEditingController();
  final _holder = TextEditingController();
  final _account = TextEditingController();
  List<Map<String, dynamic>> _accounts = [];
  String? _target;
  String? _error;
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _provider.dispose();
    _holder.dispose();
    _account.dispose();
    super.dispose();
  }

  void _select(String key) {
    _target = key;
    final saved = _accounts.firstWhere((row) => row['key'] == key);
    final details = saved['details'] as Map? ?? {};
    _provider.text = details['provider'] as String? ?? '';
    _holder.text = details['holder'] as String? ?? '';
    _account.text = details['account'] as String? ?? '';
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data =
          await widget.api.getSharedAuthenticated('remittances/payout-account')
              as Map;
      final accounts = (data['accounts'] as List? ?? [])
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList();
      if (accounts.isEmpty) {
        throw const FormatException('Missing payout account');
      }
      if (!mounted) return;
      setState(() {
        _accounts = accounts;
        _select(accounts.first['key'] as String);
      });
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is ApiException
              ? error.message
              : 'Could not load payout details. Please retry.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    if (_saving || !_form.currentState!.validate()) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.api.postSharedAuthenticated('remittances/payout-account', {
        'target': _target,
        'provider': _provider.text.trim(),
        'holder': _holder.text.trim(),
        'account': _account.text.trim(),
      });
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is ApiException
              ? error.message
              : 'Could not save payout details. Please retry.',
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Widget _field(
    String label,
    TextEditingController controller,
    int limit, {
    int minimum = 1,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: TextFormField(
      controller: controller,
      enabled: !_saving,
      maxLength: limit,
      autocorrect: false,
      enableSuggestions: false,
      decoration: InputDecoration(labelText: label, counterText: ''),
      validator: (value) => (value?.trim().length ?? 0) < minimum
          ? 'Enter ${minimum > 1 ? 'the complete account number' : label.toLowerCase()}.'
          : null,
    ),
  );

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SafeArea(
      top: false,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * .85,
        ),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Saved payout account',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                  ),
                  IconButton(
                    onPressed: _saving
                        ? null
                        : () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                    tooltip: 'Close',
                  ),
                ],
              ),
              const Text(
                'Admin uses these details for your payments. Update them here whenever they change.',
              ),
              const SizedBox(height: 18),
              if (_loading)
                const Center(child: CircularProgressIndicator())
              else if (_accounts.isEmpty) ...[
                Text(_error ?? 'Could not load payout details.'),
                OutlinedButton(onPressed: _load, child: const Text('Retry')),
              ] else
                Form(
                  key: _form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (_accounts.length > 1)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 14),
                          child: DropdownButtonFormField<String>(
                            initialValue: _target,
                            isExpanded: true,
                            decoration: const InputDecoration(
                              labelText: 'Receiving hub',
                            ),
                            items: _accounts
                                .map(
                                  (row) => DropdownMenuItem(
                                    value: row['key'] as String,
                                    child: Text(
                                      row['label'] as String,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                )
                                .toList(),
                            onChanged: _saving
                                ? null
                                : (key) {
                                    if (key != null) {
                                      setState(() => _select(key));
                                    }
                                  },
                          ),
                        ),
                      _field('Bank / e-wallet name', _provider, 100),
                      _field('Account holder', _holder, 150),
                      _field(
                        'Account number / mobile number',
                        _account,
                        100,
                        minimum: 4,
                      ),
                      const Text(
                        'Keep any leading zeros. Never enter a PIN, password or OTP.',
                      ),
                      if (_error != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 12),
                          child: Text(
                            _error!,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                            ),
                          ),
                        ),
                      const SizedBox(height: 18),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton(
                          onPressed: _saving ? null : _save,
                          child: Text(
                            _saving ? 'Saving…' : 'Save payout details',
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    ),
  );
}
