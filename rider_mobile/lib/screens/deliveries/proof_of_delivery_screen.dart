import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/file_validation.dart';
import '../../core/utils/format_utils.dart';
import '../../core/utils/geolocation.dart';
import '../../core/utils/name_validation.dart';
import '../../core/utils/payment_utils.dart';
import '../../providers/delivery_provider.dart';
import '../../services/pod_draft_store.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';

/// Proof of delivery: photo + customer verification + COD settlement.
class ProofOfDeliveryScreen extends StatefulWidget {
  const ProofOfDeliveryScreen({
    super.key,
    required this.deliveryId,
    this.photoPicker,
    this.draftStore,
  });

  final int deliveryId;

  /// Test seam: replaces the live camera with a fake that returns bytes.
  final Future<Uint8List?> Function()? photoPicker;

  /// Test seam: replaces the shared-preferences draft store.
  final PodDraftStore? draftStore;

  @override
  State<ProofOfDeliveryScreen> createState() => _ProofOfDeliveryScreenState();
}

class _ProofOfDeliveryScreenState extends State<ProofOfDeliveryScreen> {
  final _amountController = TextEditingController();
  final _nameController = TextEditingController();
  final _otpController = TextEditingController();

  late final PodDraftStore _drafts = widget.draftStore ?? PodDraftStore();

  Uint8List? _photoBytes;
  String? _photoName;
  String? _photoPath;
  bool _collectingGps = false;
  bool _takingPhoto = false;
  bool _submitting = false;
  bool _draftRestored = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<DeliveryProvider>();
      if (provider.selected?.id != widget.deliveryId) {
        provider.loadDetail(widget.deliveryId);
      }
      _restoreDraft();
    });
  }

  @override
  void dispose() {
    _amountController.dispose();
    _nameController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _takePhoto() async {
    if (_takingPhoto) return;
    setState(() => _takingPhoto = true);
    try {
      Uint8List? bytes;
      String name = 'proof.jpg';
      String? path;

      if (widget.photoPicker != null) {
        bytes = await widget.photoPicker!();
        if (bytes == null) return;
      } else {
        final picker = ImagePicker();
        final image = await picker.pickImage(
          source: ImageSource.camera,
          maxWidth: 1600,
          maxHeight: 1600,
          imageQuality: 70,
        );
        if (image == null) return;
        bytes = await image.readAsBytes();
        name = image.name;
        path = image.path;
      }

      final result = FileContentValidation.validatePhoto(
        filename: name,
        bytes: bytes,
      );
      if (!result.isValid) {
        if (mounted) _snack(result.message!);
        return;
      }
      if (!mounted) return;
      setState(() {
        _photoBytes = bytes;
        _photoName = name;
        _photoPath = path;
      });
      _saveDraft();
    } on MissingPluginException {
      if (mounted) {
        _snack(
          'The camera is not available in this app build. '
          'Please reinstall the latest version.',
        );
      }
    } on PlatformException catch (e) {
      if (mounted) {
        _snack(
          e.code == 'camera_access_denied'
              ? 'Camera permission is required. Enable it in system settings.'
              : 'Unable to open the camera. Please try again.',
        );
      }
    } catch (_) {
      if (mounted) _snack('Unable to take the photo. Please try again.');
    } finally {
      if (mounted) setState(() => _takingPhoto = false);
    }
  }

  /// Bring back an unsent proof captured before the app was closed or the
  /// network dropped. The photo bytes are re-read from the camera file.
  Future<void> _restoreDraft() async {
    final draft = await _drafts.load(widget.deliveryId);
    if (draft == null || !mounted || draft.hasContent == false) return;

    setState(() {
      if (draft.otp.isNotEmpty) _otpController.text = draft.otp;
      if (draft.amount.isNotEmpty) _amountController.text = draft.amount;
      if (draft.name.isNotEmpty) _nameController.text = draft.name;
      _draftRestored = true;
    });

    final path = draft.photoPath;
    if (path != null && path.isNotEmpty && _photoBytes == null) {
      try {
        final bytes = await File(path).readAsBytes();
        if (bytes.isNotEmpty && mounted) {
          setState(() {
            _photoBytes = bytes;
            _photoPath = path;
            _photoName = path.split(Platform.pathSeparator).last;
          });
        }
      } catch (_) {
        // The camera cache may have been cleared; the text values still load.
      }
    }
  }

  void _saveDraft() {
    _drafts.save(
      widget.deliveryId,
      PodDraft(
        photoPath: _photoPath,
        otp: _otpController.text,
        amount: _amountController.text,
        name: _nameController.text,
      ),
    );
  }

  double? _getReceived() {
    if (_amountController.text.trim().isEmpty) return null;
    return double.tryParse(_amountController.text.trim());
  }

  Future<void> _submit() async {
    if (_submitting) return;
    final provider = context.read<DeliveryProvider>();
    final delivery = provider.selected;
    if (delivery == null) return;

    // A32: Delivered always needs a live proof photo (JPG/PNG, max 5 MB).
    // The backend still accepts photo-less proofs, so the client enforces it.
    if (_photoBytes == null) {
      _saveDraft();
      _snack('Take a live delivery photo before completing the delivery.');
      return;
    }

    final otp = _otpController.text.trim();

    if (delivery.otpRequired) {
      if (otp.isEmpty) {
        _snack('Enter the 6-digit delivery OTP provided by the buyer.');
        return;
      }
      if (!RegExp(r'^\d{6}$').hasMatch(otp)) {
        _snack('The delivery OTP must be exactly 6 digits.');
        return;
      }
    }

    // COD: amount received is required and must not be negative.
    double? received;
    if (delivery.isCashOnDelivery) {
      received = _getReceived();
      if (received == null) {
        _snack('Enter a valid amount received from the customer.');
        return;
      }
      final expected = delivery.amountToCollect ?? 0;
      if (!PaymentUtils.codAmountMatches(received, expected)) {
        _snack(
          'Amount received must match the amount to collect '
          '(${FormatUtils.peso(expected)}).',
        );
        return;
      }
    }

    final rawName = _nameController.text.trim();
    if (rawName.isNotEmpty &&
        !NameValidation.isValidPerson(rawName, maxLength: 255)) {
      _snack('Please enter a valid name for the customer signature.');
      return;
    }

    final signatureName = rawName.isEmpty ? delivery.customer.name : rawName;

    setState(() {
      _submitting = true;
      _collectingGps = true;
    });
    final pos = await Geolocation.currentPosition();
    if (!mounted) return;
    setState(() => _collectingGps = false);

    final ok = await provider.complete(
      delivery.id,
      proofType: _photoBytes != null
          ? 'photo'
          : (otp.isNotEmpty ? 'otp' : 'signature'),
      photoBytes: _photoBytes,
      photoFilename: _photoName,
      signatureName: otp.isNotEmpty ? null : signatureName,
      otp: otp.isEmpty ? null : otp,
      amountReceived: received,
      latitude: pos?.latitude,
      longitude: pos?.longitude,
    );

    if (!mounted) return;

    if (ok) {
      await _drafts.clear(delivery.id);
      if (!mounted) return;
      // Stop the submit spinner before leaving so the screen is never left
      // showing an endless "Completing..." state if navigation does not pop.
      setState(() => _submitting = false);
      _snack('Delivery completed. Thank you!');
      Navigator.of(context).popUntil((route) => route.isFirst);
    } else {
      _saveDraft();
      setState(() => _submitting = false);
      _snack(
        'Delivery not sent. ${provider.error ?? 'Please try again.'} '
        'Your photo, OTP and amount are saved on this phone.',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<DeliveryProvider>();
    final delivery = provider.selected;

    if (provider.loading && delivery == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Proof of Delivery')),
        body: const LoadingWidget(),
      );
    }

    if (delivery == null || delivery.id != widget.deliveryId) {
      return Scaffold(
        appBar: AppBar(title: const Text('Proof of Delivery')),
        body: ErrorView(
          message: provider.error ?? 'Unable to load the delivery.',
          onRetry: () => provider.loadDetail(widget.deliveryId),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Proof of Delivery'),
        backgroundColor: AppTheme.primary,
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
          if (_draftRestored) ...[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color: AppColors.primary.withValues(alpha: 0.18),
                ),
              ),
              child: const Row(
                children: [
                  Icon(Icons.restore, size: 18, color: AppColors.primary),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Restored your unsent proof from this phone. '
                      'Complete the delivery when you have a connection.',
                      style: TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],
          if (delivery.isCashOnDelivery) ...[
            _Section(
              title: 'Cash on Delivery',
              icon: Icons.payments_outlined,
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Amount to Collect',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      Text(
                        FormatUtils.peso(delivery.amountToCollect ?? 0),
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: AppTheme.secondary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    key: const Key('codAmountField'),
                    controller: _amountController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                      LengthLimitingTextInputFormatter(10),
                    ],
                    decoration: const InputDecoration(
                      labelText: 'Amount Received',
                      prefixText: '₱ ',
                      hintText: '0.00',
                    ),
                    onChanged: (_) {
                      setState(() {});
                      _saveDraft();
                    },
                  ),
                  const SizedBox(height: 8),
                  _ChangeRow(
                    amountToCollect: delivery.amountToCollect ?? 0,
                    received: _getReceived(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],
          _Section(
            title: 'Delivery Photo',
            icon: Icons.camera_alt_outlined,
            child: Column(
              children: [
                const Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'A live camera photo is required (JPG or PNG, max 5 MB). '
                    'Gallery photos are not accepted.',
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                if (_photoBytes == null)
                  OutlinedButton.icon(
                    onPressed: _takingPhoto ? null : _takePhoto,
                    icon: _takingPhoto
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.camera_alt_outlined),
                    label: Text(
                      _takingPhoto ? 'Opening camera…' : 'Take Photo',
                    ),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(48),
                    ),
                  )
                else
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Image.memory(
                      _photoBytes!,
                      height: 180,
                      width: double.infinity,
                      fit: BoxFit.cover,
                    ),
                  ),
                if (_photoBytes != null) ...[
                  const SizedBox(height: 8),
                  TextButton.icon(
                    onPressed: _takingPhoto ? null : _takePhoto,
                    icon: const Icon(Icons.refresh),
                    label: const Text('Retake Photo'),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          _Section(
            title: 'Customer Verification',
            icon: Icons.verified_outlined,
            child: Column(
              children: [
                TextField(
                  key: const Key('signatureNameField'),
                  controller: _nameController,
                  textCapitalization: TextCapitalization.words,
                  maxLength: 255,
                  decoration: InputDecoration(
                    labelText: 'Customer name (signature)',
                    hintText: delivery.customer.name,
                    counterText: '',
                  ),
                  onChanged: (_) => _saveDraft(),
                ),
                const SizedBox(height: 12),
                TextField(
                  key: const Key('deliveryOtpField'),
                  controller: _otpController,
                  keyboardType: TextInputType.number,
                  textInputAction: TextInputAction.done,
                  autofillHints: const [AutofillHints.oneTimeCode],
                  maxLength: 6,
                  inputFormatters: [
                    FilteringTextInputFormatter.digitsOnly,
                    LengthLimitingTextInputFormatter(6),
                  ],
                  decoration: InputDecoration(
                    labelText: delivery.otpRequired
                        ? 'Delivery OTP'
                        : 'Delivery OTP (optional)',
                    helperText: delivery.otpRequired
                        ? 'Ask the buyer for the 6-digit code shown in their app.'
                        : null,
                    counterText: '',
                  ),
                  onChanged: (_) => _saveDraft(),
                ),
              ],
            ),
          ),
        ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
          child: ElevatedButton.icon(
            onPressed: (provider.actionBusy || _collectingGps || _submitting)
                ? null
                : _submit,
            icon: provider.actionBusy || _collectingGps || _submitting
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.5,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.check_circle_outline),
            label: Text(
              provider.actionBusy || _submitting
                  ? 'Submitting...'
                  : _collectingGps
                  ? 'Getting location...'
                  : 'Complete Delivery',
            ),
          ),
        ),
      ),
    );
  }

  void _snack(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _ChangeRow extends StatelessWidget {
  const _ChangeRow({required this.amountToCollect, required this.received});

  final double amountToCollect;
  final double? received;

  @override
  Widget build(BuildContext context) {
    if (received == null) {
      return const SizedBox.shrink();
    }

    final change = received! - amountToCollect;
    final color = change < 0 ? AppTheme.danger : AppTheme.success;

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        const Text(
          'Change',
          style: TextStyle(fontSize: 14, color: AppColors.textSecondary),
        ),
        Text(
          FormatUtils.peso(change),
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w800,
            color: color,
          ),
        ),
      ],
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.child, this.icon});

  final String title;
  final Widget child;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final sectionIcon = icon;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppGaps.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                if (sectionIcon != null) ...[
                  IconTile(icon: sectionIcon, size: 34, iconSize: 18),
                  const SizedBox(width: AppGaps.sm),
                ],
                Text(title, style: AppText.section),
              ],
            ),
            const SizedBox(height: AppGaps.md),
            child,
          ],
        ),
      ),
    );
  }
}
