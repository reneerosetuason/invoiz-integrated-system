import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import '../models/order.dart';
import '../services/api_service.dart';
import '../theme.dart';
import 'buyer_dialog.dart';
import 'buyer_field.dart';
import 'buyer_picker.dart';

class OrderReturnDialog extends StatefulWidget {
  final OrderItem item;
  final int maxQuantity;
  final Future<dynamic> Function(Map<String, dynamic>) submit;
  const OrderReturnDialog({
    super.key,
    required this.item,
    required this.maxQuantity,
    required this.submit,
  });

  @override
  State<OrderReturnDialog> createState() => _OrderReturnDialogState();
}

class _OrderReturnDialogState extends State<OrderReturnDialog> {
  final _form = GlobalKey<FormState>();
  final _details = TextEditingController();
  final _quantity = TextEditingController(text: '1');
  String _reason = 'damaged';
  String? _error;
  bool _sending = false;

  @override
  void dispose() {
    _details.dispose();
    _quantity.dispose();
    super.dispose();
  }

  Map<String, dynamic>? _savedRequest(dynamic response) {
    final record = response is Map ? response['return'] : null;
    if (record is! Map<String, dynamic> ||
        (int.tryParse('${record['id']}') ?? 0) < 1 ||
        int.tryParse('${record['order_item_id']}') != widget.item.id ||
        ![
          'requested',
          'approved',
          'item_received',
        ].contains(record['status'])) {
      return null;
    }
    return record;
  }

  Future<void> _send() async {
    if (_sending || !_form.currentState!.validate()) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final response = await widget.submit({
        'order_item_id': widget.item.id,
        'reason': _reason,
        'quantity': int.parse(_quantity.text.trim()),
        if (_details.text.trim().isNotEmpty)
          'reason_details': _details.text.trim(),
      });
      final saved = _savedRequest(response);
      if (saved == null) {
        throw const FormatException('The return request was not confirmed.');
      }
      if (mounted) Navigator.pop(context, saved);
    } catch (error) {
      // A timed-out POST may already have saved a request. A retry's conflict
      // response identifies that same open line, without creating a duplicate.
      final existing = error is ApiException && error.statusCode == 409
          ? _savedRequest(error.data)
          : null;
      if (!mounted) return;
      if (existing != null) {
        Navigator.pop(context, existing);
        return;
      }
      setState(() {
        _sending = false;
        _error = error is ApiException
            ? error.message
            : 'Could not confirm your request. Check your connection and try again. Your details are kept here.';
      });
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_sending,
    child: BuyerAlertDialog(
      title: const Text('Request a return'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_error != null) ...[
              Semantics(
                liveRegion: true,
                child: Text(
                  _error!,
                  key: const ValueKey('return-submit-error'),
                  style: const TextStyle(color: AppColors.danger),
                ),
              ),
              const SizedBox(height: 12),
            ],
            AbsorbPointer(
              absorbing: _sending,
              child: Form(
                key: _form,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(widget.item.productName),
                    if (widget.item.variantLabel?.isNotEmpty == true)
                      Text(widget.item.variantLabel!),
                    const SizedBox(height: 12),
                    BuyerPicker<String>(
                      value: _reason,
                      decoration: const InputDecoration(labelText: 'Reason'),
                      items: const [
                        DropdownMenuItem(
                          value: 'damaged',
                          child: Text('Damaged item'),
                        ),
                        DropdownMenuItem(
                          value: 'wrong_item',
                          child: Text('Wrong item'),
                        ),
                        DropdownMenuItem(
                          value: 'not_as_described',
                          child: Text('Not as described'),
                        ),
                        DropdownMenuItem(
                          value: 'changed_mind',
                          child: Text('Changed my mind'),
                        ),
                        DropdownMenuItem(value: 'other', child: Text('Other')),
                      ],
                      onChanged: (value) => setState(() => _reason = value!),
                    ),
                    const SizedBox(height: 12),
                    BuyerField.form(
                      key: const ValueKey('return-quantity'),
                      controller: _quantity,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(
                        labelText: 'Quantity (up to ${widget.maxQuantity})',
                      ),
                      validator: (value) {
                        final parsed = int.tryParse(value?.trim() ?? '');
                        return parsed == null ||
                                parsed < 1 ||
                                parsed > widget.maxQuantity
                            ? 'Enter a remaining returnable quantity.'
                            : null;
                      },
                    ),
                    const SizedBox(height: 12),
                    BuyerField.form(
                      key: const ValueKey('return-details'),
                      controller: _details,
                      maxLength: 300,
                      maxLines: 3,
                      decoration: InputDecoration(
                        labelText: _reason == 'other'
                            ? 'Details (required)'
                            : 'Details (optional)',
                      ),
                      validator: (value) =>
                          _reason == 'other' && (value?.trim().length ?? 0) < 10
                          ? 'Please provide at least 10 characters.'
                          : null,
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      actions: [
        BuyerDialogAction(
          onPressed: _sending ? null : () => Navigator.pop(context),
          child: const Text('Keep item'),
        ),
        BuyerDialogAction(
          onPressed: _sending ? null : _send,
          child: _sending
              ? const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CupertinoActivityIndicator(),
                    SizedBox(width: 8),
                    Text('Sending…'),
                  ],
                )
              : const Text('Send request'),
        ),
      ],
    ),
  );
}
