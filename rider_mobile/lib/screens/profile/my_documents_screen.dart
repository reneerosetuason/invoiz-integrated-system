import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/file_validation.dart';
import '../../models/document_change.dart';
import '../../models/rider.dart';
import '../../providers/auth_provider.dart';
import '../../services/rider_service.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/section_header.dart';

/// A file chosen for a change request, exposed as plain data so widget
/// tests can supply one without the native picker plugin.
class PickedChangeDocument {
  const PickedChangeDocument({required this.name, required this.readBytes});

  final String name;
  final Future<Uint8List> Function() readBytes;
}

/// Thin indirection around the native picker (fakeable in tests).
class DocumentChangePicker {
  const DocumentChangePicker();

  static const supportedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

  Future<PickedChangeDocument?> pickSingle() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: supportedExtensions,
    );
    if (result == null || result.files.isEmpty) return null;
    final file = result.files.first;
    return PickedChangeDocument(
      name: file.name,
      readBytes: () async =>
          file.bytes ??
          (file.path != null
              ? await file.xFile.readAsBytes()
              : Uint8List(0)),
    );
  }
}

/// Approved documents, pending replacements and license eligibility.
///
/// A rider can propose a replacement, but the approved document stays
/// official until a Logistics Admin approves the request.
class MyDocumentsScreen extends StatefulWidget {
  const MyDocumentsScreen({
    super.key,
    this.picker = const DocumentChangePicker(),
    this.apiClient,
  });

  final DocumentChangePicker picker;

  /// Injectable for tests; defaults to the authenticated API client.
  final ApiClient? apiClient;

  @override
  State<MyDocumentsScreen> createState() => _MyDocumentsScreenState();
}

class _MyDocumentsScreenState extends State<MyDocumentsScreen> {
  late final RiderService _service;
  DocumentChangeOverview? _overview;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _service = RiderService(
      widget.apiClient ?? context.read<AuthProvider>().api,
    );
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = _overview == null;
      _error = null;
    });
    try {
      final overview = await _service.getDocumentChangeOverview();
      if (!mounted) return;
      setState(() {
        _overview = overview;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Could not load your documents. Please try again.';
        _loading = false;
      });
    }
  }

  Future<void> _openChangeForm({String? initialType}) async {
    final overview = _overview;
    if (overview == null) return;

    final submitted = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => _DocumentChangeFormScreen(
          service: _service,
          picker: widget.picker,
          documentTypes: overview.documentTypes,
          initialType: initialType,
        ),
      ),
    );

    if (submitted == true) {
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Request submitted for review. Your current document stays '
            'official until a Logistics Admin approves the replacement.',
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Documents')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading && _overview == null) {
      return const LoadingWidget(label: 'Loading your documents...');
    }

    if (_error != null && _overview == null) {
      return ErrorView(message: _error!, onRetry: _load);
    }

    final overview = _overview;
    if (overview == null) return const SizedBox.shrink();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(AppGaps.lg),
        children: [
          _LicenseBanner(license: overview.license),
          const SizedBox(height: AppGaps.lg),
          const SectionHeader(title: 'Approved Documents'),
          const SizedBox(height: AppGaps.sm),
          if (overview.documents.isEmpty)
            const _EmptyCard(
              message:
                  'No approved documents are on file yet. Contact Logistics '
                  'if your documents are missing.',
            )
          else
            Card(
              child: Column(
                children: [
                  for (int i = 0; i < overview.documents.length; i++) ...[
                    if (i > 0) const Divider(height: 1, indent: 16),
                    _ApprovedDocumentRow(document: overview.documents[i]),
                  ],
                ],
              ),
            ),
          const SizedBox(height: AppGaps.lg),
          SectionHeader(
            title: 'Change Requests',
            action: TextButton(
              onPressed: () => _openChangeForm(),
              child: const Text('New Request'),
            ),
          ),
          const SizedBox(height: AppGaps.sm),
          if (overview.requests.isEmpty)
            const _EmptyCard(
              message:
                  'You have not requested a document change. Use “New '
                  'Request” to submit a replacement for review.',
            )
          else
            for (final request in overview.requests) ...[
              _ChangeRequestCard(
                request: request,
                onResubmit: request.canResubmit
                    ? () => _openChangeForm(initialType: request.type)
                    : null,
              ),
              const SizedBox(height: AppGaps.md),
            ],
          const SizedBox(height: AppGaps.sm),
          PrimaryButton(
            label: 'Request Document Change',
            onPressed: () => _openChangeForm(),
          ),
          const SizedBox(height: AppGaps.lg),
        ],
      ),
    );
  }
}

class _LicenseBanner extends StatelessWidget {
  const _LicenseBanner({required this.license});

  final DocumentLicenseStatus license;

  @override
  Widget build(BuildContext context) {
    final (Color color, IconData icon, String title, String message) =
        _content();
    return Container(
      padding: const EdgeInsets.all(AppGaps.lg),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 22),
          const SizedBox(width: AppGaps.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: AppText.cardTitle.copyWith(color: color),
                ),
                const SizedBox(height: 2),
                Text(message, style: AppText.secondary),
                if (license.pendingChange) ...[
                  const SizedBox(height: AppGaps.sm),
                  Text(
                    'A license replacement is pending review. Your current '
                    'license remains the official record, and eligibility is '
                    'not restored until it is approved.',
                    style: AppText.caption.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
                if (!license.canGoOnline && license.blockReason != null) ...[
                  const SizedBox(height: AppGaps.sm),
                  Text(
                    license.blockReason!,
                    style: AppText.caption.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  (Color, IconData, String, String) _content() {
    final expires = license.expiresOn == null
        ? null
        : _formatDate(license.expiresOn!);
    if (license.expired) {
      return (
        AppTheme.danger,
        Icons.error_outline,
        'Driver’s license expired',
        expires == null
            ? 'Your license is expired. New assignments are blocked until '
                'an approved replacement is on file.'
            : 'Expired on $expires. New assignments are blocked until an '
                'approved replacement is on file.',
      );
    }
    if (license.expiringSoon) {
      return (
        AppColors.secondary,
        Icons.warning_amber_outlined,
        'Driver’s license expiring soon',
        expires == null
            ? 'Your license expires soon. Submit a replacement before it '
                'expires to avoid assignment blocks.'
            : 'Expires on $expires. Submit a replacement before then to '
                'avoid assignment blocks.',
      );
    }
    if (license.expiresOn == null) {
      return (
        AppColors.textSecondary,
        Icons.badge_outlined,
        'No license expiration on file',
        'Contact Logistics if your driver’s license details are missing.',
      );
    }
    return (
      AppTheme.success,
      Icons.verified_outlined,
      'Driver’s license valid',
      'Expires on $expires.',
    );
  }
}

class _ApprovedDocumentRow extends StatelessWidget {
  const _ApprovedDocumentRow({required this.document});

  final RiderDocument document;

  @override
  Widget build(BuildContext context) {
    final expiring = document.expiresOn;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        children: [
          Icon(
            document.isImage
                ? Icons.image_outlined
                : Icons.description_outlined,
            color: AppColors.textSecondary,
          ),
          const SizedBox(width: AppGaps.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(document.label, style: AppText.cardTitle),
                Text(
                  document.name.isEmpty ? 'Unknown file' : document.name,
                  style: AppText.secondary,
                  overflow: TextOverflow.ellipsis,
                ),
                if (expiring != null)
                  Text(
                    'Expires ${_formatDate(expiring)}',
                    style: AppText.caption,
                  ),
              ],
            ),
          ),
          const SizedBox(width: AppGaps.sm),
          _StatusChip(
            label: 'Approved',
            color: AppTheme.success,
          ),
          const SizedBox(width: AppGaps.xs),
          IconButton(
            tooltip: 'View document',
            onPressed: document.url == null
                ? null
                : () => _openUrl(context, document.url!),
            icon: const Icon(Icons.open_in_new, size: 20),
          ),
        ],
      ),
    );
  }
}

class _ChangeRequestCard extends StatelessWidget {
  const _ChangeRequestCard({required this.request, this.onResubmit});

  final DocumentChangeRequest request;
  final VoidCallback? onResubmit;

  @override
  Widget build(BuildContext context) {
    final color = request.isApproved
        ? AppTheme.success
        : request.isRejected
            ? AppTheme.danger
            : AppColors.secondary;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppGaps.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(request.typeLabel, style: AppText.cardTitle),
                ),
                _StatusChip(label: request.statusLabel, color: color),
              ],
            ),
            const SizedBox(height: 2),
            Text(
              'Submitted ${_formatDateTime(request.submittedAt)}',
              style: AppText.caption,
            ),
            if (request.proposedExpiry != null) ...[
              const SizedBox(height: AppGaps.sm),
              _DetailLine(
                label: 'Proposed expiry',
                value: _formatDate(request.proposedExpiry!),
              ),
            ],
            if (request.previousExpiry != null)
              _DetailLine(
                label: 'Current expiry',
                value: _formatDate(request.previousExpiry!),
              ),
            if (request.document != null) ...[
              const SizedBox(height: AppGaps.sm),
              InkWell(
                onTap: request.document!.url == null
                    ? null
                    : () => _openUrl(context, request.document!.url!),
                child: Row(
                  children: [
                    const Icon(
                      Icons.attach_file,
                      size: 16,
                      color: AppColors.textSecondary,
                    ),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        request.document!.name,
                        style: AppText.secondary,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (request.document!.url != null)
                      Text(
                        'View file',
                        style: AppText.caption.copyWith(
                          color: AppTheme.primary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                  ],
                ),
              ),
            ],
            if (request.isRejected && request.rejectionReason != null) ...[
              const SizedBox(height: AppGaps.md),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(AppGaps.md),
                decoration: BoxDecoration(
                  color: AppTheme.danger.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Reason for rejection',
                      style: AppText.caption.copyWith(
                        color: AppTheme.danger,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(request.rejectionReason!, style: AppText.body),
                  ],
                ),
              ),
            ],
            if (!request.isPending && request.reviewedAt != null) ...[
              const SizedBox(height: AppGaps.sm),
              Text(
                'Reviewed ${_formatDateTime(request.reviewedAt)}'
                '${request.reviewerName == null ? '' : ' by ${request.reviewerName}'}',
                style: AppText.caption,
              ),
            ],
            if (onResubmit != null) ...[
              const SizedBox(height: AppGaps.sm),
              Align(
                alignment: Alignment.centerRight,
                child: OutlinedButton.icon(
                  onPressed: onResubmit,
                  icon: const Icon(Icons.upload_file, size: 18),
                  label: const Text('Submit a corrected document'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(0, 40),
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    textStyle: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 2),
      child: Row(
        children: [
          Text(label, style: AppText.secondary),
          const Spacer(),
          Text(
            value,
            style: AppText.secondary.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.pill),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Text(
        label,
        style: AppText.caption.copyWith(
          color: color,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppGaps.lg),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Text(message, style: AppText.secondary),
    );
  }
}

/// Full-screen form to propose a replacement document.
class _DocumentChangeFormScreen extends StatefulWidget {
  const _DocumentChangeFormScreen({
    required this.service,
    required this.picker,
    required this.documentTypes,
    this.initialType,
  });

  final RiderService service;
  final DocumentChangePicker picker;
  final List<DocumentTypeOption> documentTypes;
  final String? initialType;

  @override
  State<_DocumentChangeFormScreen> createState() =>
      _DocumentChangeFormScreenState();
}

class _DocumentChangeFormScreenState
    extends State<_DocumentChangeFormScreen> {
  final _reasonController = TextEditingController();

  String? _type;
  PickedChangeDocument? _file;
  Uint8List? _fileBytes;
  String? _fileError;
  DateTime? _expiry;
  String? _formError;
  bool _picking = false;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    final available = widget.documentTypes.where((t) => !t.pending);
    if (widget.initialType != null &&
        available.any((t) => t.value == widget.initialType)) {
      _type = widget.initialType;
    }
  }

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  bool get _isLicense => _type == 'drivers_license';

  Future<void> _pickFile() async {
    setState(() {
      _picking = true;
      _fileError = null;
    });
    try {
      final picked = await widget.picker.pickSingle();
      if (picked == null) return;
      final bytes = await picked.readBytes();
      final result = FileContentValidation.validateDocument(
        filename: picked.name,
        bytes: bytes,
      );
      if (!mounted) return;
      setState(() {
        if (!result.isValid) {
          _file = null;
          _fileBytes = null;
          _fileError = result.message;
        } else {
          _file = picked;
          _fileBytes = bytes;
        }
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _fileError = 'Could not read that file. Please choose another one.';
      });
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  Future<void> _pickExpiry() async {
    final today = DateUtils.dateOnly(DateTime.now());
    final selected = await showDatePicker(
      context: context,
      initialDate: _expiry ?? today.add(const Duration(days: 365)),
      firstDate: today,
      lastDate: DateTime(today.year + 15, 12, 31),
      helpText: 'New license expiration date',
    );
    if (selected != null && mounted) {
      setState(() => _expiry = selected);
    }
  }

  Future<void> _submit() async {
    final type = _type;
    if (type == null) {
      setState(() => _formError = 'Select the document you want to replace.');
      return;
    }
    if (_fileBytes == null || _file == null) {
      setState(() => _formError = 'Choose the replacement file to upload.');
      return;
    }
    if (_isLicense && _expiry == null) {
      setState(
        () => _formError = 'Enter the new driver’s license expiration date.',
      );
      return;
    }

    setState(() {
      _submitting = true;
      _formError = null;
    });

    try {
      await widget.service.submitDocumentChange(
        documentType: type,
        fileBytes: _fileBytes!,
        filename: _file!.name,
        expiryDate:
            _isLicense ? DateFormat('yyyy-MM-dd').format(_expiry!) : null,
        reason: _reasonController.text,
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _formError = e.message;
        _submitting = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _formError = 'Could not submit the request. Please try again.';
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final availableTypes =
        widget.documentTypes.where((t) => !t.pending).toList();

    return Scaffold(
      appBar: AppBar(title: const Text('Request Document Change')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppGaps.lg),
          children: [
            Container(
              padding: const EdgeInsets.all(AppGaps.md),
              decoration: BoxDecoration(
                color: AppColors.accent,
                borderRadius: BorderRadius.circular(AppRadius.sm),
              ),
              child: Text(
                'Your uploaded file will not replace the approved document '
                'until a Logistics Admin reviews and approves it.',
                style: AppText.secondary.copyWith(
                  color: AppColors.primaryDark,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const SizedBox(height: AppGaps.lg),
            const Text('Document type', style: AppText.cardTitle),
            const SizedBox(height: AppGaps.sm),
            if (availableTypes.isEmpty)
              const _EmptyCard(
                message:
                    'Every document type already has a change request under '
                    'review. Wait for the Logistics Admin to review it first.',
              )
            else
              DropdownButtonFormField<String>(
                initialValue: _type,
                decoration: const InputDecoration(
                  hintText: 'Select document type',
                ),
                items: [
                  for (final type in availableTypes)
                    DropdownMenuItem(
                      value: type.value,
                      child: Text(
                        type.hasCurrent ? type.label : '${type.label} (new)',
                      ),
                    ),
                ],
                onChanged: _submitting
                    ? null
                    : (value) => setState(() {
                          _type = value;
                          if (value != 'drivers_license') _expiry = null;
                        }),
              ),
            const SizedBox(height: AppGaps.lg),
            const Text('Replacement file', style: AppText.cardTitle),
            const SizedBox(height: AppGaps.sm),
            InkWell(
              borderRadius: BorderRadius.circular(AppRadius.sm),
              onTap: _picking || _submitting ? null : _pickFile,
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(AppGaps.lg),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                  border: Border.all(color: AppColors.border),
                ),
                child: Row(
                  children: [
                    Icon(
                      _file == null
                          ? Icons.upload_file_outlined
                          : Icons.check_circle_outline,
                      color: _file == null
                          ? AppColors.textSecondary
                          : AppTheme.success,
                    ),
                    const SizedBox(width: AppGaps.md),
                    Expanded(
                      child: Text(
                        _picking
                            ? 'Opening file picker...'
                            : _file?.name ??
                                'Choose a PDF, JPG, or PNG (max 5 MB)',
                        style: _file == null
                            ? AppText.secondary
                            : AppText.body.copyWith(
                                fontWeight: FontWeight.w600,
                              ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            if (_fileError != null) ...[
              const SizedBox(height: AppGaps.xs),
              Text(
                _fileError!,
                style: AppText.caption.copyWith(color: AppTheme.danger),
              ),
            ],
            if (_isLicense) ...[
              const SizedBox(height: AppGaps.lg),
              const Text('New license expiration date', style: AppText.cardTitle),
              const SizedBox(height: AppGaps.sm),
              InkWell(
                borderRadius: BorderRadius.circular(AppRadius.sm),
                onTap: _submitting ? null : _pickExpiry,
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(AppGaps.lg),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceSoft,
                    borderRadius: BorderRadius.circular(AppRadius.sm),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.event_outlined,
                        color: AppColors.textSecondary,
                      ),
                      const SizedBox(width: AppGaps.md),
                      Text(
                        _expiry == null
                            ? 'Select expiration date'
                            : DateFormat.yMMMMd().format(_expiry!),
                        style: _expiry == null
                            ? AppText.secondary
                            : AppText.body.copyWith(
                                fontWeight: FontWeight.w600,
                              ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
            const SizedBox(height: AppGaps.lg),
            const Text('Reason (optional)', style: AppText.cardTitle),
            const SizedBox(height: AppGaps.sm),
            TextField(
              controller: _reasonController,
              enabled: !_submitting,
              maxLines: 3,
              maxLength: 500,
              decoration: const InputDecoration(
                hintText: 'e.g. License renewed at the LTO branch',
              ),
            ),
            if (_formError != null) ...[
              const SizedBox(height: AppGaps.sm),
              Text(
                _formError!,
                style: AppText.body.copyWith(color: AppTheme.danger),
              ),
            ],
            const SizedBox(height: AppGaps.lg),
            PrimaryButton(
              label: 'Submit for Approval',
              loading: _submitting,
              onPressed: availableTypes.isEmpty ? null : _submit,
            ),
            const SizedBox(height: AppGaps.lg),
          ],
        ),
      ),
    );
  }
}

Future<void> _openUrl(BuildContext context, String url) async {
  final uri = Uri.tryParse(url);
  if (uri == null) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Invalid document URL.')),
    );
    return;
  }
  try {
    final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open the document.')),
      );
    }
  } catch (_) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Failed to open the document.')),
      );
    }
  }
}

String _formatDate(String value) {
  final parsed = DateTime.tryParse(value);
  if (parsed == null) return value;
  return DateFormat('MMM d, yyyy').format(parsed);
}

String _formatDateTime(String? value) {
  if (value == null) return '—';
  final parsed = DateTime.tryParse(value);
  if (parsed == null) return value;
  return DateFormat('MMM d, yyyy • h:mm a').format(parsed.toLocal());
}
