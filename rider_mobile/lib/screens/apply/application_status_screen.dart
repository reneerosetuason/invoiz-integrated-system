import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../services/application_service.dart';

/// Dedicated application-status screen.
///
/// Uses `GET /api/rider/application-status?email=` and optional `reference_number=`.
/// If email verification is needed, provides an in-place verification option.
class ApplicationStatusScreen extends StatefulWidget {
  const ApplicationStatusScreen({
    super.key,
    this.email,
    this.reference,
    this.token,
    this.apiClient,
  });
  final String? email;
  final String? reference;
  final String? token;
  final ApiClient? apiClient;
  @override
  State<ApplicationStatusScreen> createState() =>
      _ApplicationStatusScreenState();
}

class _ApplicationStatusScreenState extends State<ApplicationStatusScreen> {
  final _email = TextEditingController();
  final _reference = TextEditingController();
  final _otpCode = TextEditingController();
  RiderApplicationStatus? _app;
  bool _busy = false;
  String? _error;
  bool _refMismatch = false;
  String? _currentToken;
  bool _showOtpSection = false;
  bool _sendingOtp = false;
  bool _verifyingOtp = false;
  String? _otpMessage;

  @override
  void initState() {
    super.initState();
    _currentToken = widget.token;
    if (widget.email != null) {
      _email.text = widget.email!;
    }
    if (widget.reference != null) {
      _reference.text = widget.reference!;
    }
    if (widget.email != null) {
      _check(unfocus: false);
    }
  }

  Future<void> _check({bool unfocus = true}) async {
    final email = _email.text.trim();
    if (email.isEmpty) {
      setState(() => _error = 'Enter your email to check status.');
      return;
    }
    if (unfocus) FocusScope.of(context).unfocus();
    final ref = _reference.text.trim();
    setState(() {
      _busy = true;
      _error = null;
      _refMismatch = false;
    });
    try {
      final client = widget.apiClient ?? ApiClient();
      final app = await ApplicationService(client).getStatus(
        email,
        reference: ref.isNotEmpty ? ref : null,
        token: _currentToken,
      );
      if (!mounted) return;
      if (app == null) {
        setState(() {
          _app = null;
          _error = 'No application found for this email.';
        });
        return;
      }
      setState(() {
        _app = app;
        _showOtpSection = false;
        _refMismatch =
            ref.isNotEmpty && ref.toUpperCase() != app.referenceNumber;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      final isAuthError = e.message.contains('Verify this email with a fresh code') ||
          e.message.contains('fresh code');
      setState(() {
        _app = null;
        if (isAuthError) {
          _showOtpSection = true;
          _error =
              'Please verify this email with a one-time code to view full application details.';
        } else {
          _error = switch (e.type) {
            ApiErrorType.network || ApiErrorType.timeout =>
              'Unable to connect. Please try again.',
            _ => e.message.isNotEmpty
                ? e.message
                : 'No application found for this email.',
          };
        }
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _app = null;
          _error = 'Something went wrong. Please try again.';
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _sendOtp() async {
    final email = _email.text.trim();
    if (email.isEmpty) {
      setState(() => _error = 'Enter your email to request a verification code.');
      return;
    }
    setState(() {
      _sendingOtp = true;
      _otpMessage = null;
    });
    try {
      final client = widget.apiClient ?? ApiClient();
      final msg = await ApplicationService(client).requestEmailCode(email: email);
      if (!mounted) return;
      setState(() {
        _otpMessage = msg;
        _showOtpSection = true;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Unable to send code. Please try again.');
    } finally {
      if (mounted) setState(() => _sendingOtp = false);
    }
  }

  Future<void> _verifyOtp() async {
    final email = _email.text.trim();
    final code = _otpCode.text.trim();
    if (code.length != 6) {
      setState(() => _error = 'Enter the 6-digit code sent to your email.');
      return;
    }
    setState(() {
      _verifyingOtp = true;
      _error = null;
    });
    try {
      final client = widget.apiClient ?? ApiClient();
      await ApplicationService(client).verifyEmailCode(
        email: email,
        code: code,
        onTokenReceived: (tok) => _currentToken = tok,
      );
      if (!mounted) return;
      setState(() {
        _otpCode.clear();
        _showOtpSection = false;
        _otpMessage = null;
      });
      await _check();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Verification failed. Please try again.');
    } finally {
      if (mounted) setState(() => _verifyingOtp = false);
    }
  }

  @override
  void dispose() {
    _email.dispose();
    _reference.dispose();
    _otpCode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Application Status')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Check your application',
                style: TextStyle(
                    fontSize: 22, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              const Text(
                'Enter the email you applied with to see the actual status from Logistics.',
                style: TextStyle(
                    fontSize: 14, color: AppColors.textSecondary, height: 1.5),
              ),
              const SizedBox(height: 20),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.card,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(
                        labelText: 'Email *',
                        prefixIcon:
                            Icon(Icons.email_outlined, size: 20),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _reference,
                      textCapitalization: TextCapitalization.characters,
                      decoration: const InputDecoration(
                        labelText: 'Reference Number (optional)',
                        hintText: 'RID-2026-0007',
                        prefixIcon: Icon(
                            Icons.confirmation_number_outlined,
                            size: 20),
                      ),
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      height: 52,
                      child: ElevatedButton(
                        onPressed: _busy ? null : _check,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.primary,
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                        child: _busy
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                  color: Colors.white,
                                ),
                              )
                            : const Text('Check Status',
                                style:
                                    TextStyle(fontWeight: FontWeight.w700)),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              if (_busy && _app == null)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('Checking application...',
                        style: TextStyle(color: AppColors.textSecondary)),
                  ),
                ),
              if (_error != null) _errorBox(_error!),
              if (_showOtpSection) ...[
                const SizedBox(height: 16),
                _verificationBox(),
              ],
              if (_refMismatch)
                Container(
                  margin: const EdgeInsets.only(top: 12),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.accent,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                        color: AppColors.primary
                            .withValues(alpha: 0.3)),
                  ),
                  child: const Text(
                    'The reference number does not match the application found for this email. Showing the actual record from Logistics.',
                    style: TextStyle(
                        fontSize: 12, color: AppColors.primary),
                  ),
                ),
              if (_app != null) ...[
                const SizedBox(height: 16),
                _resultCard(_app!),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _errorBox(String message) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.warning.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
            color: AppColors.warning.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline,
              size: 18, color: AppTheme.danger),
          const SizedBox(width: 8),
          Expanded(
            child: Text(message,
                style: const TextStyle(
                    fontSize: 13, color: AppTheme.danger)),
          ),
        ],
      ),
    );
  }

  Widget _verificationBox() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Verify Email Ownership',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'To protect applicant privacy, request a 6-digit code sent to your email to verify you own this application.',
            style: TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
              height: 1.4,
            ),
          ),
          if (_otpMessage != null) ...[
            const SizedBox(height: 10),
            Text(
              _otpMessage!,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: AppColors.success,
              ),
            ),
          ],
          const SizedBox(height: 14),
          TextField(
            controller: _otpCode,
            keyboardType: TextInputType.number,
            maxLength: 6,
            decoration: const InputDecoration(
              labelText: '6-digit Verification Code',
              hintText: '123456',
              counterText: '',
              prefixIcon: Icon(Icons.lock_clock_outlined, size: 20),
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: _sendingOtp ? null : _sendOtp,
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: Text(_sendingOtp ? 'Sending...' : 'Send Code'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: ElevatedButton(
                  onPressed: _verifyingOtp ? null : _verifyOtp,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.white,
                    minimumSize: const Size.fromHeight(48),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: Text(_verifyingOtp ? 'Verifying...' : 'Verify & View'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _resultCard(RiderApplicationStatus app) {
    final status = app.status.toLowerCase();
    final Color color;
    final IconData icon;
    final String title;
    final String desc;
    if (status == 'approved') {
      color = AppColors.success;
      icon = Icons.check_circle_outline;
      title = 'Congratulations!';
      desc =
          'Your rider application has been approved and your rider account has been created. Login credentials have been sent to your registered email address. Please check your email for your temporary password.';
    } else if (status == 'rejected' || status == 'disapproved') {
      color = AppColors.warning;
      icon = Icons.cancel_outlined;
      title = 'Application Not Approved';
      desc = (app.notes != null && app.notes!.isNotEmpty)
          ? app.notes!
          : 'Your application was not approved.';
    } else {
      color = AppColors.primary;
      icon = Icons.hourglass_top_outlined;
      title = 'Application Under Review';
      desc =
          'Your rider application is currently being reviewed by Logistics.';
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              padding: const EdgeInsets.symmetric(
                  horizontal: 14, vertical: 6),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: color),
              ),
              child: Text(
                app.status.toUpperCase(),
                style: TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                    color: color),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(
                horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              children: [
                const Text('Reference',
                    style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1,
                        color: AppColors.textSecondary)),
                const SizedBox(height: 4),
                Text(
                  app.referenceNumber,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 20,
                    letterSpacing: 0.5,
                    color: AppTheme.primary,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Icon(icon, size: 44, color: color),
          const SizedBox(height: 8),
          Text(title,
              textAlign: TextAlign.center,
              style:
                  const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          Text(desc,
              textAlign: TextAlign.center,
              style: const TextStyle(
                  color: AppColors.textSecondary, height: 1.5)),
          const Divider(height: 28),
          _row('Applicant', app.name),
          _row('Application Role', app.roleLabel),
          _row('Email', app.email),
          if (app.riderType != null)
            _row('Rider type',
                app.riderType!.replaceAll('_', ' ').toUpperCase()),
          if (app.vehicleOwnership != null)
            _row('Ownership',
                app.vehicleOwnership!.replaceAll('_', ' ').toUpperCase()),
          if (app.createdAt != null)
            _row('Submitted', _formatDate(app.createdAt!)),
          if (app.reviewedAt != null)
            _row('Reviewed', _formatDate(app.reviewedAt!)),
          if (app.documents.isNotEmpty) ...[
            const SizedBox(height: 8),
            const Text('Documents',
                style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary)),
            const SizedBox(height: 6),
            for (final d in app.documents)
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Row(
                  children: [
                    const Icon(Icons.check_circle,
                        size: 16, color: AppTheme.primary),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        '${d.label} · ${d.name}',
                        style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.textPrimary),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ],
      ),
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(label,
                style: const TextStyle(
                    fontSize: 13, color: AppColors.textSecondary)),
          ),
          Expanded(
            child: Text(value,
                style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary)),
          ),
        ],
      ),
    );
  }

  String _formatDate(String iso) {
    final parsed = DateTime.tryParse(iso)?.toLocal();
    if (parsed == null) return iso;
    return '${parsed.year}-${parsed.month.toString().padLeft(2, '0')}-${parsed.day.toString().padLeft(2, '0')}';
  }
}
