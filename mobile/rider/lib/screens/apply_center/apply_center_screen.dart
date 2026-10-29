import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/name_validation.dart';
import '../../services/address_service.dart';
import '../../services/center_application_service.dart';
import '../../widgets/address_fields.dart';
import '../../widgets/primary_button.dart';
import 'center_application_status_screen.dart';

/// A staged center document, surfaced as plain data so the upload flow
/// never touches `dart:io` (web-safe), mirroring the rider apply flow.
class CenterPickedDoc {
  const CenterPickedDoc({required this.name, required this.bytes});
  final String name;
  final Uint8List bytes;
}

/// Thin indirection around the native file picker (fakeable in tests).
/// `file_picker` resolves to native code at build time, so widget tests
/// cannot drive the real picker; tests inject a fake.
class CenterDocPicker {
  const CenterDocPicker();

  /// Document types the backend `documents.<key>` rules accept.
  static const supportedExtensions = [
    'jpg',
    'jpeg',
    'png',
    'webp',
    'pdf',
    'doc',
    'docx',
  ];

  Future<CenterPickedDoc?> pickSingle() async {
    final file = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: supportedExtensions,
    );
    if (file == null) return null;
    final bytes = await file.readAsBytes();
    return CenterPickedDoc(name: file.name, bytes: bytes);
  }
}

/// "Open a Logistics Center" application form.
///
/// Submits to `POST /api/center/apply` (business + owner, structured PSGC
/// address, and supporting documents). After submission the applicant is
/// taken to the center status screen for their reference number.
class ApplyCenterScreen extends StatefulWidget {
  const ApplyCenterScreen({
    super.key,
    this.picker = const CenterDocPicker(),
    this.apiClient,
  });

  /// Picker boundary (fakeable in tests; defaults to the native picker).
  final CenterDocPicker picker;

  /// Shared API client (injectable in tests; defaults to a live client).
  final ApiClient? apiClient;

  @override
  State<ApplyCenterScreen> createState() => _ApplyCenterScreenState();
}

class _PickedDoc {
  const _PickedDoc({required this.name, required this.bytes});
  final String name;
  final Uint8List bytes;
}

class _DocSlot {
  const _DocSlot({
    required this.key,
    required this.label,
    required this.required,
  });
  final String key;
  final String label;
  final bool required;
}

class _ApplyCenterScreenState extends State<ApplyCenterScreen> {
  static const _maxDocBytes = 5 * 1024 * 1024;

  static const _docs = [
    _DocSlot(
      key: 'business_registration',
      label: 'Business Registration (DTI/SEC)',
      required: true,
    ),
    _DocSlot(key: 'valid_id', label: "Owner's Valid ID", required: true),
    _DocSlot(
      key: 'barangay_clearance',
      label: 'Barangay Clearance',
      required: false,
    ),
    _DocSlot(key: 'mayors_permit', label: "Mayor's Permit", required: false),
    _DocSlot(
      key: 'lease_contract',
      label: 'Lease / Proof of Location',
      required: false,
    ),
    _DocSlot(key: 'other', label: 'Other Supporting Document', required: false),
  ];

  final _businessName = TextEditingController();
  final _ownerFirstName = TextEditingController();
  final _ownerMiddleName = TextEditingController();
  final _ownerLastName = TextEditingController();
  final _secNumber = TextEditingController();
  final _tin = TextEditingController();
  final _postalCode = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  AddressSelection? _addressSel;
  final Map<String, _PickedDoc> _uploaded = {};
  bool _busy = false;

  @override
  void dispose() {
    _businessName.dispose();
    _ownerFirstName.dispose();
    _ownerMiddleName.dispose();
    _ownerLastName.dispose();
    _secNumber.dispose();
    _tin.dispose();
    _postalCode.dispose();
    _email.dispose();
    _phone.dispose();
    super.dispose();
  }

  String? _regionCode() {
    final code = _addressSel?.province?.regionCode;
    return code == null || code.isEmpty ? null : code;
  }

  String? _validate() {
    if (_businessName.text.trim().isEmpty) {
      return 'Please enter your business name.';
    }
    if (!NameValidation.isValidBusiness(_businessName.text, maxLength: 255)) {
      return 'Please enter a valid business name.';
    }
    if (_ownerFirstName.text.trim().isEmpty) {
      return 'Please enter the owner first name.';
    }
    if (!NameValidation.isValidPerson(_ownerFirstName.text, maxLength: 50)) {
      return 'Please enter a valid owner first name.';
    }
    if (_ownerMiddleName.text.trim().isNotEmpty &&
        !NameValidation.isValidPerson(_ownerMiddleName.text, maxLength: 50)) {
      return 'Please enter a valid owner middle name.';
    }
    if (_ownerLastName.text.trim().isEmpty) {
      return 'Please enter the owner last name.';
    }
    if (!NameValidation.isValidPerson(_ownerLastName.text, maxLength: 50)) {
      return 'Please enter a valid owner last name.';
    }
    if (!RegExp(r'^[A-Za-z]{2}\d{9}$').hasMatch(_secNumber.text.trim())) {
      return 'SEC number must be 2 letters followed by 9 digits (e.g., AB123456789).';
    }
    if (!RegExp(r'^\d{3}-\d{3}-\d{3}-\d{3}$').hasMatch(_tin.text.trim())) {
      return 'TIN must be in the format 000-000-000-000.';
    }
    if (!RegExp(r'^\d{4}$').hasMatch(_postalCode.text.trim())) {
      return 'Please enter a valid 4-digit postal code.';
    }
    if (_email.text.trim().isEmpty) return 'Please enter your email.';
    final email = _email.text.trim();
    if (!RegExp(r'^[^@]+@[^@]+\.[^@]+$').hasMatch(email)) {
      return 'Please enter a valid email address.';
    }
    final phone = _phone.text.replaceAll(RegExp(r'[\s\-()]'), '');
    if (!RegExp(r'^09\d{9}$').hasMatch(phone)) {
      return 'Please enter a valid 11-digit Philippine mobile number (09xxxxxxxxx).';
    }
    if (_addressSel?.complete != true) {
      return 'Please select your Province, City/Municipality and Barangay.';
    }
    if (_regionCode() == null) {
      return 'Please select a province with a valid region.';
    }
    for (final slot in _docs) {
      if (slot.required && !_uploaded.containsKey(slot.key)) {
        return '${slot.label} is required.';
      }
    }
    return null;
  }

  Future<void> _pickDoc(_DocSlot slot) async {
    try {
      final picked = await widget.picker.pickSingle();
      if (picked == null) return;
      if (picked.bytes.length > _maxDocBytes) {
        _snack('File is larger than 5 MB.');
        return;
      }
      if (!mounted) return;
      setState(() {
        _uploaded[slot.key] = _PickedDoc(
          name: picked.name,
          bytes: picked.bytes,
        );
      });
    } on PlatformException {
      if (!mounted) return;
      _snack('Unable to open the file picker.');
    } catch (_) {
      if (!mounted) return;
      _snack('Unable to read the selected file.');
    }
  }

  Future<void> _submit() async {
    final error = _validate();
    if (error != null) {
      _snack(error);
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() => _busy = true);
    try {
      final result =
          await CenterApplicationService(
            widget.apiClient ?? ApiClient(),
          ).submit(
            businessName: NameValidation.normalize(_businessName.text),
            ownerFirstName: NameValidation.normalize(_ownerFirstName.text),
            ownerMiddleName: NameValidation.normalize(_ownerMiddleName.text),
            ownerLastName: NameValidation.normalize(_ownerLastName.text),
            secNumber: _secNumber.text.trim(),
            tin: _tin.text.trim(),
            postalCode: _postalCode.text.trim(),
            region: _regionCode()!,
            email: _email.text.trim(),
            phone: _phone.text.replaceAll(RegExp(r'[\s\-()]'), ''),
            address: _addressSel?.composed ?? '',
            houseNumber: _addressSel?.houseNumber ?? '',
            street: _addressSel?.street ?? '',
            barangay: _addressSel?.barangay?.name ?? '',
            municipality: _addressSel?.municipality?.name ?? '',
            province: _addressSel?.province?.name ?? '',
            documents: {
              for (final e in _uploaded.entries)
                e.key: (bytes: e.value.bytes, filename: e.value.name),
            },
          );
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => _CenterApplySuccess(
            result: result,
            email: _email.text.trim(),
            apiClient: widget.apiClient,
          ),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      switch (e.type) {
        case ApiErrorType.validation:
          _snack(
            e.message.isNotEmpty
                ? e.message
                : 'Please complete the required fields.',
          );
        case ApiErrorType.network:
        case ApiErrorType.timeout:
          _snack('Unable to connect. Please try again.');
        default:
          _snack(
            e.message.isNotEmpty
                ? e.message
                : 'Submission failed. Please try again.',
          );
      }
    } catch (_) {
      if (!mounted) return;
      _snack('Submission failed. Please try again.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  InputDecoration _decoration(String label, {IconData? icon}) {
    return InputDecoration(
      labelText: label,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      prefixIcon: icon == null ? null : Icon(icon, size: 20),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Open a Logistics Center')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Apply to open an INVOIZ Logistics Center',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              const Text(
                'Submit your business details and documents. Logistics will review your application and email the staff login credentials to you.',
                style: TextStyle(
                  fontSize: 14,
                  color: AppColors.textSecondary,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 20),
              TextField(
                controller: _businessName,
                textCapitalization: TextCapitalization.words,
                inputFormatters: [LengthLimitingTextInputFormatter(255)],
                decoration: _decoration(
                  'Business Name *',
                  icon: Icons.storefront_outlined,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _ownerFirstName,
                textCapitalization: TextCapitalization.words,
                inputFormatters: [LengthLimitingTextInputFormatter(50)],
                decoration: _decoration(
                  'Owner First Name *',
                  icon: Icons.person_outline,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _ownerMiddleName,
                textCapitalization: TextCapitalization.words,
                inputFormatters: [LengthLimitingTextInputFormatter(50)],
                decoration: _decoration(
                  'Owner Middle Name',
                  icon: Icons.person_outline,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _ownerLastName,
                textCapitalization: TextCapitalization.words,
                inputFormatters: [LengthLimitingTextInputFormatter(50)],
                decoration: _decoration(
                  'Owner Last Name *',
                  icon: Icons.person_outline,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _secNumber,
                textCapitalization: TextCapitalization.characters,
                decoration: _decoration(
                  'SEC Registration Number *',
                  icon: Icons.badge_outlined,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _tin,
                keyboardType: TextInputType.number,
                decoration: _decoration(
                  'TIN * (000-000-000-000)',
                  icon: Icons.receipt_long_outlined,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _postalCode,
                keyboardType: TextInputType.number,
                decoration: _decoration(
                  'Postal Code *',
                  icon: Icons.markunread_mailbox_outlined,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                decoration: _decoration('Email *', icon: Icons.email_outlined),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: _decoration(
                  'Mobile Number *',
                  icon: Icons.phone_outlined,
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Center Address *',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              AddressFields(
                onChanged: (sel) => setState(() => _addressSel = sel),
                apiClient: widget.apiClient,
              ),
              const SizedBox(height: 20),
              const Text(
                'Supporting Documents',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Business Registration and the Owner\'s Valid ID are required. Documents can be photos or PDFs (max 5 MB each).',
                style: TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 12),
              for (final slot in _docs) _docTile(slot),
              const SizedBox(height: 24),
              PrimaryButton(
                label: _busy ? 'Submitting...' : 'Submit Application',
                loading: _busy,
                onPressed: _busy ? null : _submit,
              ),
              const SizedBox(height: 12),
            ],
          ),
        ),
      ),
    );
  }

  Widget _docTile(_DocSlot slot) {
    final picked = _uploaded[slot.key];
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Icon(
            picked != null
                ? Icons.upload_file
                : slot.required
                ? Icons.description_outlined
                : Icons.drive_file_rename_outline,
            size: 22,
            color: picked != null ? AppTheme.primary : AppColors.textSecondary,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${slot.label}${slot.required ? ' *' : ''}',
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (picked != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      picked.name,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
            ),
          ),
          TextButton(
            onPressed: () => _pickDoc(slot),
            child: Text(picked != null ? 'Change' : 'Upload'),
          ),
        ],
      ),
    );
  }
}

class _CenterApplySuccess extends StatelessWidget {
  const _CenterApplySuccess({
    required this.result,
    required this.email,
    this.apiClient,
  });
  final CenterApplicationSubmitResult result;
  final String email;
  final ApiClient? apiClient;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Application Submitted'),
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Icon(
                Icons.check_circle_outline,
                size: 64,
                color: AppColors.success,
              ),
              const SizedBox(height: 16),
              const Text(
                'Your center application has been submitted!',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              Text(
                'Reference number: ${result.referenceNumber}',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: AppTheme.primary,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Logistics will review your application and send the staff login credentials to your email when it is approved.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textSecondary, height: 1.5),
              ),
              const Spacer(),
              PrimaryButton(
                label: 'Check Application Status',
                onPressed: () => Navigator.of(context).pushReplacement(
                  MaterialPageRoute(
                    builder: (_) => CenterApplicationStatusScreen(
                      email: email,
                      apiClient: apiClient,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
