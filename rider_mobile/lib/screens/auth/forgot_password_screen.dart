import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../services/auth_service.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/custom_text_field.dart';
import '../../widgets/primary_button.dart';

/// Rider self-service password reset.
///
/// Step 1: enter the registered email → generic confirmation (identical
/// text whether or not the account exists). Step 2: paste the code from
/// the reset email (or arrive prefilled via the deep link) and choose a
/// new password. Success returns the rider to the login screen; the app
/// never logs anyone in from this flow and never reveals account existence.
class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, this.initialEmail, this.initialToken});

  final String? initialEmail;
  final String? initialToken;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

enum _Step { enterEmail, emailSent, enterCode, done }

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _tokenController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();

  late _Step _step;
  bool _busy = false;
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  String? _errorMessage;
  String? _confirmationMessage;

  @override
  void initState() {
    super.initState();
    _emailController.text = widget.initialEmail ?? '';
    _tokenController.text = widget.initialToken ?? '';
    _step = widget.initialToken != null && widget.initialToken!.isNotEmpty
        ? _Step.enterCode
        : _Step.enterEmail;
  }

  @override
  void dispose() {
    _emailController.dispose();
    _tokenController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  AuthService _service() =>
      AuthService(context.read<AuthProvider>().api);

  String? _emailValidator(String? v) {
    if (v == null || v.trim().isEmpty) {
      return 'Please enter your email.';
    }
    final emailRegex =
        RegExp(r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$');
    if (!emailRegex.hasMatch(v.trim())) {
      return 'Please enter a valid email address.';
    }
    return null;
  }

  Future<void> _requestLink() async {
    if (_busy || !_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _busy = true;
      _errorMessage = null;
    });
    try {
      final message = await _service().requestPasswordReset(
        email: _emailController.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        _busy = false;
        _confirmationMessage = message;
        _step = _Step.emailSent;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _errorMessage = e.message;
      });
    }
  }

  Future<void> _resetPassword() async {
    if (_busy || !_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _busy = true;
      _errorMessage = null;
    });
    try {
      final message = await _service().resetPassword(
        email: _emailController.text.trim(),
        token: _tokenController.text.trim(),
        newPassword: _passwordController.text,
      );
      if (!mounted) return;
      setState(() {
        _busy = false;
        _confirmationMessage = message;
        _step = _Step.done;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _errorMessage = e.message;
      });
    }
  }

  Widget _eye(bool obscured, VoidCallback onTap) {
    return IconButton(
      tooltip: obscured ? 'Show password' : 'Hide password',
      icon: Icon(
        obscured ? Icons.visibility_off_outlined : Icons.visibility_outlined,
        color: AppColors.textSecondary,
        size: 20,
      ),
      onPressed: onTap,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Reset Password')),
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Center(
                  child: IconTile(
                    icon: Icons.lock_reset_outlined,
                    size: 56,
                    iconSize: 26,
                  ),
                ),
                const SizedBox(height: AppGaps.lg),
                if (_step == _Step.enterEmail) ...[
                  const Text(
                    'Enter the email address on your rider account. If it matches an active account, a reset link will be sent.',
                    textAlign: TextAlign.center,
                    style: AppText.secondary,
                  ),
                  const SizedBox(height: AppGaps.lg),
                  CustomTextField(
                    controller: _emailController,
                    label: 'EMAIL ADDRESS',
                    hint: 'you@example.com',
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.done,
                    enabled: !_busy,
                    autofillHints: const [AutofillHints.email],
                    validator: _emailValidator,
                    onFieldSubmitted: (_) => _requestLink(),
                  ),
                  const SizedBox(height: AppGaps.xl),
                  if (_errorMessage != null) ...[
                    _InlineError(message: _errorMessage!),
                    const SizedBox(height: AppGaps.lg),
                  ],
                  PrimaryButton(
                    label: 'Send Reset Link',
                    loading: _busy,
                    onPressed: _requestLink,
                  ),
                ],
                if (_step == _Step.emailSent) ...[
                  const Icon(
                    Icons.mark_email_read_outlined,
                    size: 40,
                    color: AppTheme.primary,
                  ),
                  const SizedBox(height: AppGaps.md),
                  const Text(
                    'Check your email',
                    textAlign: TextAlign.center,
                    style: AppText.section,
                  ),
                  const SizedBox(height: AppGaps.sm),
                  Text(
                    _confirmationMessage ?? '',
                    textAlign: TextAlign.center,
                    style: AppText.secondary,
                  ),
                  const SizedBox(height: AppGaps.xl),
                  PrimaryButton(
                    label: 'I Have a Reset Code',
                    onPressed: () => setState(() => _step = _Step.enterCode),
                  ),
                  const SizedBox(height: AppGaps.md),
                  OutlinedButton(
                    onPressed: () => Navigator.of(context).pop(),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(50),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    child: const Text('Back to Login'),
                  ),
                ],
                if (_step == _Step.enterCode) ...[
                  const Text(
                    'Paste the code from the reset email, then choose a new password.',
                    textAlign: TextAlign.center,
                    style: AppText.secondary,
                  ),
                  const SizedBox(height: AppGaps.lg),
                  CustomTextField(
                    controller: _tokenController,
                    label: 'RESET CODE',
                    hint: 'Paste the code from your email',
                    textInputAction: TextInputAction.next,
                    enabled: !_busy,
                    validator: (v) => v == null || v.trim().isEmpty
                        ? 'Please enter the reset code.'
                        : null,
                  ),
                  const SizedBox(height: AppGaps.md),
                  CustomTextField(
                    controller: _emailController,
                    label: 'EMAIL ADDRESS',
                    hint: 'you@example.com',
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    enabled: !_busy,
                    validator: _emailValidator,
                  ),
                  const SizedBox(height: AppGaps.md),
                  CustomTextField(
                    controller: _passwordController,
                    label: 'NEW PASSWORD',
                    hint: 'At least 8 characters',
                    obscure: _obscurePassword,
                    textInputAction: TextInputAction.next,
                    enabled: !_busy,
                    autofillHints: const [AutofillHints.newPassword],
                    suffix: _eye(
                      _obscurePassword,
                      () => setState(
                          () => _obscurePassword = !_obscurePassword),
                    ),
                    validator: (v) {
                      if (v == null || v.isEmpty) {
                        return 'Please enter a new password.';
                      }
                      if (v.length < 8) {
                        return 'Password must be at least 8 characters.';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: AppGaps.md),
                  CustomTextField(
                    controller: _confirmController,
                    label: 'CONFIRM NEW PASSWORD',
                    hint: 'Repeat the new password',
                    obscure: _obscureConfirm,
                    textInputAction: TextInputAction.done,
                    enabled: !_busy,
                    suffix: _eye(
                      _obscureConfirm,
                      () => setState(
                          () => _obscureConfirm = !_obscureConfirm),
                    ),
                    validator: (v) {
                      if (v == null || v.isEmpty) {
                        return 'Please confirm the new password.';
                      }
                      if (v != _passwordController.text) {
                        return 'Passwords do not match.';
                      }
                      return null;
                    },
                    onFieldSubmitted: (_) => _resetPassword(),
                  ),
                  const SizedBox(height: AppGaps.xl),
                  if (_errorMessage != null) ...[
                    _InlineError(message: _errorMessage!),
                    const SizedBox(height: AppGaps.lg),
                  ],
                  PrimaryButton(
                    label: 'Reset Password',
                    loading: _busy,
                    onPressed: _resetPassword,
                  ),
                ],
                if (_step == _Step.done) ...[
                  const Icon(
                    Icons.check_circle_outline,
                    size: 40,
                    color: AppTheme.success,
                  ),
                  const SizedBox(height: AppGaps.md),
                  const Text(
                    'Password reset',
                    textAlign: TextAlign.center,
                    style: AppText.section,
                  ),
                  const SizedBox(height: AppGaps.sm),
                  Text(
                    _confirmationMessage ?? '',
                    textAlign: TextAlign.center,
                    style: AppText.secondary,
                  ),
                  const SizedBox(height: AppGaps.xl),
                  PrimaryButton(
                    label: 'Back to Login',
                    onPressed: () => Navigator.of(context)
                        .popUntil((route) => route.isFirst),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1E8),
        borderRadius: BorderRadius.circular(AppRadius.sm),
        border: Border.all(
          color: AppColors.warning.withValues(alpha: 0.5),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.error_outline,
            size: 20,
            color: AppColors.warning,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: AppText.body.copyWith(fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}
