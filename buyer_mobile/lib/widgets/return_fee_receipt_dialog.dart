import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

class ReturnFeeReceiptSubmission {
  const ReturnFeeReceiptSubmission({
    required this.reference,
    required this.receipt,
  });
  final String reference;
  final XFile receipt;
}

class ReturnFeeReceiptDialog extends StatefulWidget {
  const ReturnFeeReceiptDialog({
    super.key,
    required this.amount,
    required this.accountName,
    required this.accountDetails,
    this.pickReceipt,
  });
  final String amount;
  final String accountName;
  final String accountDetails;
  final Future<XFile?> Function()? pickReceipt;

  @override
  State<ReturnFeeReceiptDialog> createState() => _ReturnFeeReceiptDialogState();
}

class _ReturnFeeReceiptDialogState extends State<ReturnFeeReceiptDialog> {
  final _form = GlobalKey<FormState>();
  final _reference = TextEditingController();
  XFile? _receipt;
  Uint8List? _preview;
  bool _confirmed = false;
  bool _picking = false;
  String? _error;

  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  Future<void> _chooseReceipt() async {
    setState(() {
      _picking = true;
      _error = null;
    });
    try {
      final file =
          await (widget.pickReceipt?.call() ??
              ImagePicker().pickImage(
                source: ImageSource.gallery,
                maxWidth: 1800,
                imageQuality: 85,
              ));
      if (file == null || !mounted) return;
      final bytes = await file.readAsBytes();
      if (!mounted) return;
      if (bytes.length > 5 * 1024 * 1024) {
        setState(() => _error = 'Choose a receipt smaller than 5 MB.');
        return;
      }
      setState(() {
        _receipt = file;
        _preview = bytes;
      });
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Unable to open that photo. Please select the receipt again.',
        );
      }
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Upload return-fee receipt'),
    scrollable: true,
    content: SizedBox(
      width: 320,
      child: Form(
        key: _form,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Transfer ${widget.amount} to ${widget.accountName}.'),
            Text(widget.accountDetails),
            const SizedBox(height: 12),
            const Text(
              'Attach the screenshot for this transfer. Admin opens the receipt and checks the real incoming payment before marking it successful.',
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _reference,
              maxLength: 150,
              decoration: const InputDecoration(
                labelText: 'Bank / GCash transfer reference',
              ),
              validator: (value) => (value ?? '').trim().length < 4
                  ? 'Enter the transfer reference (at least 4 characters).'
                  : null,
            ),
            OutlinedButton.icon(
              onPressed: _picking ? null : _chooseReceipt,
              icon: const Icon(Icons.add_photo_alternate_outlined),
              label: Text(
                _receipt == null
                    ? 'Choose receipt screenshot'
                    : 'Change receipt screenshot',
              ),
            ),
            if (_picking) const LinearProgressIndicator(),
            if (_receipt != null) ...[
              const SizedBox(height: 8),
              Text(
                _receipt!.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 8),
              Image.memory(
                _preview!,
                height: 144,
                width: double.infinity,
                fit: BoxFit.contain,
                semanticLabel: 'Selected return-fee receipt',
                errorBuilder: (_, _, _) => const Text(
                  'Preview unavailable. Choose a JPG, PNG or WebP receipt.',
                ),
              ),
            ],
            if (_error != null)
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _confirmed,
              onChanged: (value) => setState(() => _confirmed = value == true),
              title: const Text(
                'I transferred this amount and attached its actual receipt.',
              ),
            ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      TextButton(
        onPressed: !_confirmed || _receipt == null || _picking
            ? null
            : () {
                if (_form.currentState!.validate()) {
                  Navigator.pop(
                    context,
                    ReturnFeeReceiptSubmission(
                      reference: _reference.text.trim(),
                      receipt: _receipt!,
                    ),
                  );
                }
              },
        child: const Text('Submit receipt for verification'),
      ),
    ],
  );
}
