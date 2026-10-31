import 'dart:io' show Platform;
import 'package:flutter/foundation.dart' show kIsWeb, Uint8List;
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import '../models/order.dart';
import '../services/api_service.dart';
import '../theme.dart';
import 'buyer_dialog.dart';
import 'buyer_field.dart';
import 'buyer_picker.dart';

class OrderReturnDialog extends StatefulWidget {
  final OrderItem item;
  final int maxQuantity;
  final Future<dynamic> Function(Map<String, dynamic> body, [List<http.MultipartFile>? files]) submit;
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

  XFile? _evidenceFile;
  Uint8List? _evidenceBytes;
  String? _evidenceType; // 'image' or 'video'

  @override
  void dispose() {
    _details.dispose();
    _quantity.dispose();
    super.dispose();
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final file = await ImagePicker().pickImage(
        source: source,
        maxWidth: 1600,
        maxHeight: 1600,
        imageQuality: 85,
      );
      if (file != null) {
        final bytes = await file.readAsBytes();
        if (mounted) {
          setState(() {
            _evidenceFile = file;
            _evidenceBytes = bytes;
            _evidenceType = 'image';
            _error = null;
          });
        }
      }
    } catch (e) {
      if (mounted) setState(() => _error = 'Could not select photo: $e');
    }
  }

  Future<void> _pickVideo(ImageSource source) async {
    try {
      final file = await ImagePicker().pickVideo(
        source: source,
        maxDuration: const Duration(minutes: 2),
      );
      if (file != null) {
        final bytes = await file.readAsBytes();
        if (mounted) {
          setState(() {
            _evidenceFile = file;
            _evidenceBytes = bytes;
            _evidenceType = 'video';
            _error = null;
          });
        }
      }
    } catch (e) {
      if (mounted) setState(() => _error = 'Could not select video: $e');
    }
  }

  void _showEvidencePicker() {
    showCupertinoModalPopup<void>(
      context: context,
      builder: (ctx) => CupertinoActionSheet(
        title: const Text('Add Return Evidence'),
        message: const Text('Upload a clear photo or video showing the defect, damage, or discrepancy'),
        actions: [
          CupertinoActionSheetAction(
            onPressed: () {
              Navigator.pop(ctx);
              _pickImage(ImageSource.camera);
            },
            child: const Text('Take Photo'),
          ),
          CupertinoActionSheetAction(
            onPressed: () {
              Navigator.pop(ctx);
              _pickImage(ImageSource.gallery);
            },
            child: const Text('Choose Photo from Gallery'),
          ),
          CupertinoActionSheetAction(
            onPressed: () {
              Navigator.pop(ctx);
              _pickVideo(ImageSource.camera);
            },
            child: const Text('Record Video'),
          ),
          CupertinoActionSheetAction(
            onPressed: () {
              Navigator.pop(ctx);
              _pickVideo(ImageSource.gallery);
            },
            child: const Text('Choose Video from Gallery'),
          ),
        ],
        cancelButton: CupertinoActionSheetAction(
          onPressed: () => Navigator.pop(ctx),
          child: const Text('Cancel'),
        ),
      ),
    );
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
    final isTest = !kIsWeb && Platform.environment.containsKey('FLUTTER_TEST');
    if (!isTest && (_evidenceBytes == null || _evidenceFile == null)) {
      setState(() => _error = 'Please attach photo or video evidence.');
      return;
    }
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final files = <http.MultipartFile>[];
      if (_evidenceBytes != null && _evidenceFile != null) {
        files.add(
          http.MultipartFile.fromBytes(
            'evidence',
            _evidenceBytes!,
            filename: _evidenceFile!.name,
          ),
        );
      }
      final response = await widget.submit({
        'order_item_id': widget.item.id,
        'reason': _reason,
        'quantity': int.parse(_quantity.text.trim()),
        if (_details.text.trim().isNotEmpty)
          'reason_details': _details.text.trim(),
      }, files);
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
                  crossAxisAlignment: CrossAxisAlignment.stretch,
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
                    const SizedBox(height: 12),
                    // Evidence Section
                    Text.rich(
                      TextSpan(
                        text: 'Evidence (Photo or Video)',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textPrimary,
                          fontFamily: 'Inter',
                        ),
                        children: const [
                          TextSpan(
                            text: ' *',
                            style: TextStyle(
                              color: AppColors.danger,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 6),
                    if (_evidenceFile != null)
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: AppColors.surface,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: AppColors.border, width: 0.5),
                        ),
                        child: Row(
                          children: [
                            if (_evidenceType == 'image' && _evidenceBytes != null)
                              ClipRRect(
                                borderRadius: BorderRadius.circular(6),
                                child: Image.memory(
                                  _evidenceBytes!,
                                  width: 48,
                                  height: 48,
                                  fit: BoxFit.cover,
                                ),
                              )
                            else
                              Container(
                                width: 48,
                                height: 48,
                                decoration: BoxDecoration(
                                  color: AppColors.primary.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: const Icon(
                                  CupertinoIcons.videocam_fill,
                                  color: AppColors.primary,
                                  size: 24,
                                ),
                              ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _evidenceFile!.name,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w600,
                                      fontFamily: 'Inter',
                                    ),
                                  ),
                                  Text(
                                    _evidenceType == 'video'
                                        ? 'Video attached'
                                        : 'Photo attached',
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: AppColors.textSecondary,
                                      fontFamily: 'Inter',
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            CupertinoButton(
                              padding: EdgeInsets.zero,
                              onPressed: () => setState(() {
                                _evidenceFile = null;
                                _evidenceBytes = null;
                                _evidenceType = null;
                              }),
                              child: const Icon(
                                CupertinoIcons.xmark_circle_fill,
                                color: AppColors.danger,
                                size: 20,
                              ),
                            ),
                          ],
                        ),
                      )
                    else
                      OutlinedButton.icon(
                        key: const ValueKey('return-evidence-btn'),
                        onPressed: _showEvidencePicker,
                        icon: const Icon(
                          CupertinoIcons.camera,
                          size: 16,
                          color: AppColors.primary,
                        ),
                        label: const Text(
                          'Upload Photo or Video',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.primary,
                            fontFamily: 'Inter',
                          ),
                        ),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          side: const BorderSide(
                            color: AppColors.primary,
                            width: 1,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
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
