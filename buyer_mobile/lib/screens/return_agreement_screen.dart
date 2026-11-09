import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../services/api_service.dart';
import '../widgets/buyer_controls.dart';
import '../widgets/buyer_page.dart';
import '../widgets/image_preview.dart';
import '../widgets/return_fee_receipt_dialog.dart';

class ReturnAgreementScreen extends StatefulWidget {
  const ReturnAgreementScreen({
    super.key,
    required this.returnId,
    this.api,
    this.receiptPicker,
  });
  final int returnId;
  final ApiService? api;
  final Future<XFile?> Function()? receiptPicker;
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
    final route = DialogRoute<Map<String, String>>(
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
    final result = await Navigator.of(context).push(route);
    await route.completed;
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
      // The server has confirmed acceptance. Keep that visible even if the
      // following refresh fails, so a slow connection cannot invite a replay.
      if (action == 'accept' && mounted && _data != null) {
        setState(() {
          final accepted = _agreement;
          accepted['buyer_accepted_at'] ??= DateTime.now()
              .toUtc()
              .toIso8601String();
          _data = {..._data!, 'agreement': accepted};
        });
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
    final submission = await showDialog<ReturnFeeReceiptSubmission>(
      context: context,
      builder: (_) => ReturnFeeReceiptDialog(
        amount: amount,
        accountName: '${account['account_name'] ?? ''}',
        accountDetails: '${account['account_details'] ?? ''}',
        pickReceipt: widget.receiptPicker,
      ),
    );
    if (submission == null || !mounted) return;
    await _send(
      'pay',
      fields: {
        'purpose': 'return_budget',
        'amount': ((_data?['budget_outstanding_cents'] as num? ?? 0) / 100)
            .toStringAsFixed(2),
        'reference': submission.reference,
      },
      file: submission.receipt,
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

  Future<void> _respond(bool decline) async {
    final fields = await _ask(
      decline ? 'Decline proposal' : 'Suggest changes',
      {'note': 'Your explanation or suggestion (at least 10 characters)'},
      description:
          'The seller can edit and resend. You must accept the revised proposal before any fee payment.',
    );
    if (fields != null && mounted) {
      await _send(decline ? 'decline' : 'comment', fields: fields);
    }
  }

  Future<void> _refundProof() async {
    try {
      final bytes = await _api.getImage('returns/${widget.returnId}/proof');
      if (mounted) {
        await showBuyerImagePreview(
          context,
          title: 'Seller refund receipt',
          bytes: bytes,
        );
      }
    } catch (error) {
      if (mounted) setState(() => _error = '$error');
    }
  }

  Future<void> _confirmRefund() async {
    if (_busy ||
        !await _confirm(
          'Refund received?',
          'Confirm only after checking that the exact net refund reached your receiving account. A screenshot alone is not payment confirmation.',
        ) ||
        !mounted) {
      return;
    }
    setState(() => _busy = true);
    try {
      await _api.post('returns/${widget.returnId}/confirm', {});
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = '$error');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final a = _agreement;
    final closed = a['closed_at'] != null;
    final funded = a['funded_at'] != null;
    final physical = ['return_refund', 'exchange'].contains(a['resolution']);
    final sellerDirect = a['workflow_policy'] == 'buyer_fault_direct';
    final direct = a['workflow_policy'] == 'direct' || sellerDirect;
    final collectionAuthorized = a['collection_authorized_at'] != null;
    final progress = Map<String, dynamic>.from(
      _data?['progress'] as Map? ?? {},
    );
    final buyerAccepted = a['buyer_accepted_at'] != null;
    final outstanding = _data?['budget_outstanding_cents'] as num? ?? 0;
    final feeSubmitted = (a['payments'] as List? ?? []).any(
      (payment) =>
          payment['purpose'] == 'return_budget' &&
          payment['status'] == 'submitted',
    );
    final editable =
        !closed &&
        !funded &&
        !collectionAuthorized &&
        !(a['payments'] as List? ?? []).any(
          (payment) => ['submitted', 'verified'].contains(payment['status']),
        );
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
                    Text(
                      '${sellerDirect ? 'Delivery escrow depositor' : 'Transport payer'}: ${_label(a['payer'])}',
                    ),
                    if (sellerDirect) ...[
                      const Text(
                        'You do not pay a separate return fee. The agreed delivery deduction funds the return trip.',
                      ),
                      Text('${a['deduction_note'] ?? ''}'),
                      Text(
                        collectionAuthorized
                            ? 'Collection authorized. Delivery payouts still require verified funds and completed work.'
                            : 'Waiting for agreement, your receiving account and the seller’s delivery deposit receipt.',
                      ),
                    ],
                    if (buyerAccepted) ...[
                      const Text('Agreement accepted'),
                      if (direct &&
                          !funded &&
                          a['payer'] == 'buyer' &&
                          outstanding > 0)
                        Text(
                          feeSubmitted
                              ? 'Return fee receipt submitted. Waiting for payment verification.'
                              : 'Return fee unpaid: ${_money(outstanding)}. Pay it separately below.',
                        ),
                      if (direct && !funded && a['payer'] == 'seller')
                        const Text('The seller pays the return service fees.'),
                    ],
                    if (direct)
                      const Text(
                        'Buyer and seller agree directly. No admin approval of this proposal is required.',
                      ),
                    if (!physical)
                      const Text(
                        'You keep the item. No physical return or stock restoration.',
                      ),
                    for (final entry in {
                      'Item refund': 'merchandise_cents',
                      'Original shipping reimbursement': 'shipping_cents',
                      'Return transport': 'transport_cents',
                      (sellerDirect
                              ? 'Less original seller-funded costs'
                              : 'Seller compensation'):
                          'compensation_cents',
                      'You receive': 'net_refund_cents',
                    }.entries)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text('${entry.key}: ${_money(a[entry.value])}'),
                      ),
                    Text(
                      sellerDirect
                          ? 'Original seller-funded costs and new return delivery are deducted. The seller sends your net refund directly after receiving the returned item. Only new delivery fees go to escrow.'
                          : a['funding_method'] == 'deduct_refund'
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
                        !buyerAccepted &&
                        a['quoted_at'] != null &&
                        (!direct ||
                            (a['status'] == 'proposed' &&
                                a['buyer_accepted_at'] == null)) &&
                        a['dispute_note'] == null)
                      BuyerButton.primary(
                        onPressed: _busy
                            ? null
                            : () async {
                                if (await _confirm(
                                      'Accept agreement?',
                                      sellerDirect
                                          ? 'I agree to the original seller-funded costs, new delivery deduction, net refund and sharing my receiving details with this seller.'
                                          : 'I agree to this version, the charges and whether the item must be returned.',
                                    ) &&
                                    mounted) {
                                  await _send('accept');
                                }
                              },
                        label: const Text('Accept agreement'),
                      ),
                    if (direct && editable) ...[
                      BuyerButton.secondary(
                        onPressed: _busy ? null : () => _respond(false),
                        label: const Text('Suggest changes'),
                      ),
                      BuyerButton.plain(
                        onPressed: _busy ? null : () => _respond(true),
                        label: const Text('Decline proposal'),
                      ),
                    ],
                    if (sellerDirect &&
                        (_data?['permissions'] as Map?)?['can_resolve_issue'] ==
                            true)
                      BuyerButton.secondary(
                        onPressed: _busy
                            ? null
                            : () async {
                                final fields = await _ask(
                                  'Resolve your reported issue',
                                  {
                                    'note':
                                        'Agreed resolution (at least 10 characters)',
                                  },
                                );
                                if (fields != null && mounted) {
                                  await _send('resolve_issue', fields: fields);
                                }
                              },
                        label: const Text('Resolve my reported issue'),
                      ),
                    if (!closed && (!direct || !editable))
                      BuyerButton.plain(
                        onPressed: _busy
                            ? null
                            : () async {
                                final fields = await _ask(
                                  direct
                                      ? 'Report payment or parcel issue'
                                      : 'Request admin review',
                                  {
                                    'note':
                                        'Explain the dispute (at least 10 characters)',
                                  },
                                );
                                if (fields != null && mounted) {
                                  await _send('dispute', fields: fields);
                                }
                              },
                        label: Text(
                          direct
                              ? 'Report payment or parcel issue'
                              : 'Request admin review',
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              if ((a['allocations'] as List? ?? []).isNotEmpty)
                BuyerPanel(
                  title: direct ? 'Automatic return fees' : 'Quoted services',
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
              if (!closed &&
                  a['resolution'] != 'exchange' &&
                  (sellerDirect
                      ? progress['refund_sent_at'] == null
                      : (!funded || a['destination_verified_at'] == null)))
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
                            description: sellerDirect
                                ? 'After mutual agreement, this seller can see your receiving details to send the net refund. Never provide a PIN, password or OTP.'
                                : 'Only you and payment staff can see the complete account. Never provide a PIN, password or OTP.',
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
                  !feeSubmitted &&
                  (direct || a['admin_decided_at'] != null) &&
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
                        label: const Text('Upload return-fee receipt'),
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
              if (sellerDirect && progress['refund_sent_at'] != null)
                BuyerPanel(
                  title: progress['buyer_confirmed_at'] == null
                      ? 'Seller recorded refund sent'
                      : 'Refund received',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${_money(a['net_refund_cents'])} · ${progress['refund_reference'] ?? ''}',
                      ),
                      BuyerButton.plain(
                        onPressed: _refundProof,
                        label: const Text('View seller refund receipt'),
                      ),
                      if (progress['buyer_confirmed_at'] == null)
                        BuyerButton.primary(
                          onPressed: _busy ? null : _confirmRefund,
                          label: const Text('Confirm refund received'),
                        ),
                    ],
                  ),
                ),
              if (direct && (a['events'] as List? ?? []).isNotEmpty)
                BuyerPanel(
                  title: 'Proposal conversation',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      for (final event in a['events'] as List)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Text(
                            '${event['actor_label'] ?? 'System'} · ${_label(event['action'])}\n${event['note'] ?? ''}',
                          ),
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
