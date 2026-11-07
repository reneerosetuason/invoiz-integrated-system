import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../services/api_service.dart';
import '../widgets/buyer_controls.dart';
import '../widgets/buyer_page.dart';
import '../widgets/image_preview.dart';

class ReturnAgreementScreen extends StatefulWidget {
  const ReturnAgreementScreen({super.key, required this.returnId, this.api});
  final int returnId;
  final ApiService? api;
  @override
  State<ReturnAgreementScreen> createState() => _ReturnAgreementScreenState();
}

class _ReturnAgreementScreenState extends State<ReturnAgreementScreen> {
  late final _api = widget.api ?? ApiService();
  Map<String, dynamic>? _data;
  String? _error;
  bool _busy = false;
  String get _path => 'return-center/${widget.returnId}/agreement';
  Map<String, dynamic> get _agreement =>
      Map<String, dynamic>.from(_data?['agreement'] as Map? ?? {});
  String _money(dynamic cents) =>
      '₱${((cents as num? ?? 0) / 100).toStringAsFixed(2)}';
  String _label(dynamic value) => '${value ?? 'Pending'}'.replaceAll('_', ' ');

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _api.get(_path);
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

  Future<bool> _confirm(String title, String message) async =>
      await showDialog<bool>(
        context: context,
        builder: (dialog) => AlertDialog(
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Confirm'),
            ),
          ],
        ),
      ) ==
      true;

  Future<Map<String, String>?> _ask(
    String title,
    Map<String, String> fields, {
    String? description,
    Map<String, String> defaults = const {},
  }) async {
    final controllers = fields.map(
      (key, label) =>
          MapEntry(key, TextEditingController(text: defaults[key] ?? '')),
    );
    final form = GlobalKey<FormState>();
    bool confirmed = false;
    final result = await showDialog<Map<String, String>>(
      context: context,
      builder: (dialog) => StatefulBuilder(
        builder: (dialog, update) => AlertDialog(
          title: Text(title),
          scrollable: true,
          content: Form(
            key: form,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (description != null) Text(description),
                for (final field in fields.entries)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: TextFormField(
                      controller: controllers[field.key],
                      decoration: InputDecoration(labelText: field.value),
                      maxLength: field.key == 'note' ? 1500 : 150,
                      keyboardType: field.key == 'amount'
                          ? const TextInputType.numberWithOptions(decimal: true)
                          : TextInputType.text,
                      validator: (value) =>
                          (value ?? '').trim().isEmpty ? 'Required' : null,
                    ),
                  ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: confirmed,
                  onChanged: (value) => update(() => confirmed = value == true),
                  title: const Text('I checked these details.'),
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
              onPressed: confirmed
                  ? () {
                      if (form.currentState!.validate()) {
                        Navigator.pop(
                          dialog,
                          controllers.map(
                            (key, value) => MapEntry(key, value.text.trim()),
                          ),
                        );
                      }
                    }
                  : null,
              child: const Text('Confirm'),
            ),
          ],
        ),
      ),
    );
    for (final controller in controllers.values) {
      controller.dispose();
    }
    return result;
  }

  Future<void> _send(
    String action, {
    Map<String, String> fields = const {},
    XFile? file,
  }) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final body = {
        'action': action,
        'version': '${_agreement['version']}',
        'confirm': '1',
        ...fields,
      };
      if (file != null) {
        await _api.postForm(
          _path,
          body,
          files: [
            http.MultipartFile.fromBytes(
              'proof',
              await file.readAsBytes(),
              filename: file.name,
            ),
          ],
        );
      } else {
        await _api.post(_path, body);
      }
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = '$error');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _payment() async {
    final account = Map<String, dynamic>.from(
      _data?['receiving_account'] as Map? ?? {},
    );
    final amount = _money(_data?['budget_outstanding_cents']);
    final fields = await _ask(
      'Pay return fee',
      {
        'amount': 'Exact amount transferred',
        'reference': 'Bank / e-wallet transfer reference',
      },
      description:
          'Transfer $amount to ${account['account_name'] ?? ''}: ${account['account_details'] ?? ''}. Admin must verify actual receipt before pickup.',
      defaults: {
        'amount': ((_data?['budget_outstanding_cents'] as num? ?? 0) / 100)
            .toStringAsFixed(2),
      },
    );
    if (fields == null || !mounted) return;
    final file = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1800,
      imageQuality: 85,
    );
    if (file == null || !mounted) return;
    await _send(
      'pay',
      fields: {'purpose': 'return_budget', ...fields},
      file: file,
    );
  }

  Future<void> _proof(String kind, int id) async {
    try {
      final bytes = await _api.getImage('return-files/$kind/$id');
      if (mounted) {
        await showBuyerImagePreview(
          context,
          title: 'Return payment proof',
          bytes: bytes,
        );
      }
    } catch (error) {
      if (mounted) setState(() => _error = '$error');
    }
  }

  @override
  Widget build(BuildContext context) {
    final a = _agreement;
    final closed = a['closed_at'] != null;
    final funded = a['funded_at'] != null;
    final physical = ['return_refund', 'exchange'].contains(a['resolution']);
    final account = Map<String, dynamic>.from(
      _data?['receiving_account'] as Map? ?? {},
    );
    return Scaffold(
      appBar: AppBar(title: const Text('Return agreement')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const BuyerPageHeading(
              title: 'Agreement, fees & funding',
              description:
                  'Review the resolution before paying or handing over your item.',
            ),
            if (_error != null)
              BuyerPanel(
                child: Column(
                  children: [
                    Text(_error!),
                    BuyerButton.plain(
                      onPressed: _busy ? null : _load,
                      label: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            if (_data == null && _error == null)
              const Center(child: CircularProgressIndicator()),
            if (_data != null) ...[
              BuyerPanel(
                title: 'Return #${widget.returnId}',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Version ${a['version']} · ${_label(a['status'])}'),
                    Text('Resolution: ${_label(a['resolution'])}'),
                    if (a['replacement_description'] != null)
                      Text(
                        'Agreed replacement: ${a['replacement_description']}',
                      ),
                    Text('Responsibility: ${_label(a['fault'])}'),
                    Text('Transport payer: ${_label(a['payer'])}'),
                    if (!physical)
                      const Text(
                        'You keep the item. No physical return or stock restoration.',
                      ),
                    for (final entry in {
                      'Item refund': 'merchandise_cents',
                      'Original shipping reimbursement': 'shipping_cents',
                      'Return transport': 'transport_cents',
                      'Seller compensation': 'compensation_cents',
                      'You receive': 'net_refund_cents',
                    }.entries)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text('${entry.key}: ${_money(a[entry.value])}'),
                      ),
                    Text(
                      a['funding_method'] == 'deduct_refund'
                          ? 'Agreed return fees are deducted from the refund. Do not pay them again.'
                          : 'The transport payer funds the return separately.',
                    ),
                    if (a['reason_note'] != null)
                      Text('Proposal: ${a['reason_note']}'),
                    if (a['decision_note'] != null)
                      Text('Admin decision: ${a['decision_note']}'),
                    if (a['dispute_note'] != null)
                      Text('Under review: ${a['dispute_note']}'),
                    if (a['collection_at'] != null)
                      Text('Collection: ${a['collection_at']}'),
                    if (!closed &&
                        !funded &&
                        a['quoted_at'] != null &&
                        a['dispute_note'] == null)
                      BuyerButton.primary(
                        onPressed: _busy
                            ? null
                            : () async {
                                if (await _confirm(
                                      'Accept agreement?',
                                      'I agree to this version, the charges and whether the item must be returned.',
                                    ) &&
                                    mounted) {
                                  await _send('accept');
                                }
                              },
                        label: const Text('Accept agreement'),
                      ),
                    if (!closed)
                      BuyerButton.plain(
                        onPressed: _busy
                            ? null
                            : () async {
                                final fields = await _ask(
                                  'Request admin review',
                                  {
                                    'note':
                                        'Explain the dispute (at least 10 characters)',
                                  },
                                );
                                if (fields != null && mounted) {
                                  await _send('dispute', fields: fields);
                                }
                              },
                        label: const Text('Request admin review'),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              if ((a['allocations'] as List? ?? []).isNotEmpty)
                BuyerPanel(
                  title: 'Quoted services',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      for (final row in a['allocations'] as List)
                        Text(
                          '${_label(row['role'])}: ${_money(row['amount_cents'])} · ${_label(row['status'])}',
                        ),
                    ],
                  ),
                ),
              if (!closed && a['resolution'] != 'exchange' && (!funded || a['destination_verified_at'] == null))
                BuyerButton.secondary(
                  onPressed: _busy
                      ? null
                      : () async {
                          final current = Map<String, dynamic>.from(
                            _data?['refund_destination'] as Map? ?? {},
                          );
                          final fields = await _ask(
                            'Verify refund account',
                            {
                              'provider': 'Bank / e-wallet',
                              'holder': 'Account holder',
                              'account': 'Account / e-wallet number',
                            },
                            description:
                                'Only you and admin can see the complete account. Never provide a PIN, password or OTP.',
                            defaults: current.map((k, v) => MapEntry(k, '$v')),
                          );
                          if (fields != null && mounted) {
                            await _send('destination', fields: fields);
                          }
                        },
                  label: const Text('Verify refund account'),
                ),
              if (!closed &&
                  !funded &&
                  a['payer'] == 'buyer' &&
                  a['funding_method'] == 'deposit' &&
                  a['buyer_accepted_at'] != null &&
                  a['seller_accepted_at'] != null &&
                  a['admin_decided_at'] != null &&
                  a['dispute_note'] == null &&
                  (_data?['budget_outstanding_cents'] as num? ?? 0) > 0) ...[
                BuyerPanel(
                  title: 'Platform receiving account',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${account['account_name'] ?? 'Not configured'}'),
                      Text('${account['account_details'] ?? ''}'),
                      if (account['qr_url'] != null)
                        BuyerButton.plain(
                          onPressed: () => showBuyerImagePreview(
                            context,
                            title: 'Platform receiving QR',
                            url: '${account['qr_url']}',
                          ),
                          label: const Text('View platform receiving QR'),
                        ),
                      BuyerButton.primary(
                        onPressed: _busy || account.isEmpty ? null : _payment,
                        label: const Text('Pay return fee'),
                      ),
                    ],
                  ),
                ),
              ],
              for (final payment in a['payments'] as List? ?? [])
                BuyerPanel(
                  title:
                      '${_label(payment['purpose'])} · ${_money(payment['amount_cents'])}',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${_label(payment['status'])} · ${payment['reference']}',
                      ),
                      if (payment['review_note'] != null)
                        Text('${payment['review_note']}'),
                      BuyerButton.plain(
                        onPressed: () =>
                            _proof('deposit', payment['id'] as int),
                        label: const Text('View deposit receipt'),
                      ),
                    ],
                  ),
                ),
              if (a['replacement_reference'] != null)
                BuyerPanel(
                  title: 'Replacement shipment',
                  child: Column(
                    children: [
                      Text('${a['replacement_reference']}'),
                      BuyerButton.plain(
                        onPressed: () => _proof('replacement', a['id'] as int),
                        label: const Text('View replacement dispatch proof'),
                      ),
                      if (!closed)
                        BuyerButton.primary(
                          onPressed: _busy
                              ? null
                              : () async {
                                  if (await _confirm(
                                        'Replacement received?',
                                        'Confirm only after receiving the agreed replacement in the expected condition.',
                                      ) &&
                                      mounted) {
                                    await _send('replacement_received');
                                  }
                                },
                          label: const Text('Confirm replacement received'),
                        ),
                    ],
                  ),
                ),
              if (_busy)
                const Padding(
                  padding: EdgeInsets.all(12),
                  child: Center(child: CircularProgressIndicator()),
                ),
            ],
          ],
        ),
      ),
    );
  }
}
