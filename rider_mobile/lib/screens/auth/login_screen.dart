import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/theme/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../apply/application_type_select_screen.dart';
import '../apply/application_status_screen.dart';
import '../home/home_shell.dart';
import '../../widgets/custom_text_field.dart';
import '../../widgets/primary_button.dart';
import 'forgot_password_screen.dart';

/// Rider sign-in using the shared buyer-app visual language.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  static const _rememberKey = 'invoiz_rider_remember';
  static const _savedEmailKey = 'invoiz_rider_saved_email';

  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _emailFocus = FocusNode();
  final _passwordFocus = FocusNode();

  static const _belowHeaderEstimate = 575.0;
  static const _minHeader = 100.0;
  static const _maxHeader = 140.0;

  bool _obscure = true;
  bool _busy = false;
  bool _rememberMe = false;
  String? _errorMessage;

  bool get _isLocked => _busy;

  @override
  void initState() {
    super.initState();
    _loadRemembered();
  }

  Future<void> _loadRemembered() async {
    final prefs = await SharedPreferences.getInstance();
    final remember = prefs.getBool(_rememberKey) ?? false;
    final savedEmail = prefs.getString(_savedEmailKey);
    if (mounted) {
      setState(() {
        _rememberMe = remember;
        if (remember && savedEmail != null) {
          _emailController.text = savedEmail;
        }
      });
    }
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _emailFocus.dispose();
    _passwordFocus.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_busy) return; // Prevent duplicate submissions (button + keyboard).
    if (!_formKey.currentState!.validate()) return;

    FocusScope.of(context).unfocus();
    setState(() {
      _busy = true;
      _errorMessage = null;
    });

    final auth = context.read<AuthProvider>();
    final ok = await auth.login(
      email: _emailController.text.trim(),
      password: _passwordController.text,
    );

    if (!mounted) return;
    setState(() => _busy = false);

    if (ok) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_rememberKey, _rememberMe);
      if (_rememberMe) {
        await prefs.setString(_savedEmailKey, _emailController.text.trim());
      } else {
        await prefs.remove(_savedEmailKey);
      }

      if (!mounted) return;
      // Clear the whole stack so a deep-link / prior shell can never leave a
      // duplicate HomeShell or a stale login route behind.
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const HomeShell()),
        (route) => false,
      );
    } else {
      if (!mounted) return;
      // Inline, persistent error is more accessible than a fleeting SnackBar.
      setState(() {
        _errorMessage = _friendlyError(auth.error);
      });
    }
  }

  String _friendlyError(String? error) {
    final message = (error ?? '').trim();
    if (message.isEmpty ||
        message.toLowerCase().contains('something went wrong')) {
      return 'Unable to log in. Check your credentials and connection, then try again.';
    }
    return message;
  }

  /// Rider logins have no self-service reset: credentials are issued and
  /// managed by INVOIZ Logistics, so the sheet directs riders to message
  /// logistics for replacement credentials (or to the application flow).
  void _showForgotPassword() {
    showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.border,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Forgot password?',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Reset your password via email, or contact INVOIZ '
                'Logistics to resend your login credentials.',
                style: TextStyle(
                  fontSize: 14,
                  height: 1.5,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 16),
              PrimaryButton(
                label: 'Reset Password via Email',
                onPressed: () {
                  Navigator.of(sheetContext).pop();
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const ForgotPasswordScreen(),
                    ),
                  );
                },
              ),
              const SizedBox(height: 8),
              PrimaryButton(
                label: 'Check Application Status',
                onPressed: () {
                  Navigator.of(sheetContext).pop();
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const ApplicationStatusScreen(),
                    ),
                  );
                },
              ),
              const SizedBox(height: 8),
              OutlinedButton(
                onPressed: () {
                  Navigator.of(sheetContext).pop();
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const ApplicationTypeSelectScreen(),
                    ),
                  );
                },
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.primary,
                  side: const BorderSide(color: AppTheme.primary),
                  minimumSize: const Size.fromHeight(48),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  textStyle: const TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                child: const Text('Apply as a Rider'),
              ),
              TextButton(
                onPressed: () => Navigator.of(sheetContext).pop(),
                style: TextButton.styleFrom(
                  foregroundColor: AppColors.textSecondary,
                ),
                child: const Text('Close'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            // The teal header absorbs leftover vertical space so it fills
            // the screen on typical phones, while staying compact on small
            // ones. _belowHeaderEstimate covers the intro, card, and links
            // below it. Very short screens (e.g. landscape) fall back to
            // scrolling rather than overflowing.
            final fitsOnePage =
                constraints.maxHeight >= _belowHeaderEstimate + _minHeader;
            final headerHeight = fitsOnePage
                ? (constraints.maxHeight - _belowHeaderEstimate).clamp(
                    _minHeader,
                    _maxHeader,
                  )
                : _minHeader;
            return SingleChildScrollView(
              padding: EdgeInsets.symmetric(
                horizontal:
                    ((constraints.maxWidth - 520).clamp(0, double.infinity)) /
                    2,
              ),
              physics: const ClampingScrollPhysics(),
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Column(
                  children: [
                    // ── Curved teal header ──
                    _CurvedHeader(height: headerHeight),
                    const SizedBox(height: 14),

                    // ── Login intro ──
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Rider Login',
                            style: TextStyle(
                              fontFamily: 'Inter',
                              fontSize: 30,
                              fontWeight: FontWeight.w600,
                              color: AppColors.primaryDark,
                            ),
                          ),
                          const SizedBox(height: 6),
                          const Text(
                            'Sign in with your approved Invoiz rider account.',
                            style: TextStyle(
                              fontSize: 14,
                              color: AppColors.textSecondary,
                              height: 1.4,
                            ),
                          ),
                          const SizedBox(height: 14),

                          // ── Login card ──
                          Container(
                            padding: const EdgeInsets.all(20),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(
                                color: AppColors.border,
                                width: 1,
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.02),
                                  blurRadius: 8,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: Form(
                              key: _formKey,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  // Email
                                  AutofillGroup(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.stretch,
                                      children: [
                                        CustomTextField(
                                          controller: _emailController,
                                          label: 'EMAIL ADDRESS',
                                          hint: 'you@example.com',
                                          keyboardType:
                                              TextInputType.emailAddress,
                                          textInputAction: TextInputAction.next,
                                          focusNode: _emailFocus,
                                          enabled: !_isLocked,
                                          autofillHints: const [
                                            AutofillHints.username,
                                            AutofillHints.email,
                                          ],
                                          validator: (v) {
                                            if (v == null || v.trim().isEmpty) {
                                              return 'Please enter your email.';
                                            }
                                            final emailRegex = RegExp(
                                              r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
                                            );
                                            if (!emailRegex.hasMatch(
                                              v.trim(),
                                            )) {
                                              return 'Please enter a valid email address.';
                                            }
                                            return null;
                                          },
                                          onFieldSubmitted: (_) =>
                                              _passwordFocus.requestFocus(),
                                        ),
                                        const SizedBox(height: 12),

                                        // Password
                                        CustomTextField(
                                          controller: _passwordController,
                                          label: 'PASSWORD',
                                          hint:
                                              '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022',
                                          obscure: _obscure,
                                          focusNode: _passwordFocus,
                                          enabled: !_isLocked,
                                          textInputAction: TextInputAction.done,
                                          autofillHints: const [
                                            AutofillHints.password,
                                          ],
                                          suffix: IconButton(
                                            tooltip: _obscure
                                                ? 'Show password'
                                                : 'Hide password',
                                            icon: Icon(
                                              // Icon always mirrors the current state:
                                              // slashed eye = hidden, open eye = visible.
                                              _obscure
                                                  ? Icons
                                                        .visibility_off_outlined
                                                  : Icons.visibility_outlined,
                                              color: AppColors.textSecondary,
                                              size: 20,
                                            ),
                                            onPressed: () => setState(
                                              () => _obscure = !_obscure,
                                            ),
                                          ),
                                          validator: (v) {
                                            if (v == null || v.isEmpty) {
                                              return 'Please enter your password.';
                                            }
                                            if (v.length < 6) {
                                              return 'Password must be at least 6 characters.';
                                            }
                                            return null;
                                          },
                                          onFieldSubmitted: (_) => _submit(),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(height: 2),

                                  // Forgot password (right aligned, like the
                                  // logistics web login)
                                  Align(
                                    alignment: Alignment.centerRight,
                                    child: TextButton(
                                      onPressed: _isLocked
                                          ? null
                                          : _showForgotPassword,
                                      style: TextButton.styleFrom(
                                        foregroundColor: AppTheme.primary,
                                        textStyle: const TextStyle(
                                          fontFamily: 'Inter',
                                          fontSize: 13,
                                          fontWeight: FontWeight.w600,
                                        ),
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 4,
                                        ),
                                        minimumSize: const Size(0, 36),
                                        tapTargetSize:
                                            MaterialTapTargetSize.shrinkWrap,
                                      ),
                                      child: const Text('Forgot password?'),
                                    ),
                                  ),
                                  const SizedBox(height: 2),

                                  // Remember me
                                  Row(
                                    children: [
                                      SizedBox(
                                        width: 24,
                                        height: 24,
                                        child: Checkbox(
                                          value: _rememberMe,
                                          onChanged: (v) => setState(
                                            () => _rememberMe = v ?? false,
                                          ),
                                          activeColor: AppTheme.primary,
                                          shape: RoundedRectangleBorder(
                                            borderRadius: BorderRadius.circular(
                                              4,
                                            ),
                                          ),
                                        ),
                                      ),
                                      const SizedBox(width: 8),
                                      const Text(
                                        'Remember me',
                                        style: TextStyle(
                                          fontSize: 14,
                                          color: AppColors.textSecondary,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),

                                  // Inline login error (accessible, persistent)
                                  if (_errorMessage != null) ...[
                                    Container(
                                      width: double.infinity,
                                      padding: const EdgeInsets.all(12),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFFFF1E8),
                                        borderRadius: BorderRadius.circular(12),
                                        border: Border.all(
                                          color: AppColors.warning.withValues(
                                            alpha: 0.5,
                                          ),
                                        ),
                                      ),
                                      child: Row(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          const Icon(
                                            Icons.error_outline,
                                            size: 20,
                                            color: AppColors.warning,
                                          ),
                                          const SizedBox(width: 10),
                                          Expanded(
                                            child: Text(
                                              _errorMessage!,
                                              style: const TextStyle(
                                                fontSize: 13,
                                                height: 1.4,
                                                color: AppColors.textPrimary,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(height: 16),
                                  ],

                                  // Sign in button
                                  PrimaryButton(
                                    label: 'Sign In',
                                    loading: _busy,
                                    onPressed: _submit,
                                  ),
                                  const SizedBox(height: 12),

                                  // Apply as a rider (below sign in)
                                  OutlinedButton.icon(
                                    onPressed: _isLocked
                                        ? null
                                        : () => Navigator.of(context).push(
                                            MaterialPageRoute(
                                              builder: (_) =>
                                                  const ApplicationTypeSelectScreen(),
                                            ),
                                          ),
                                    style: OutlinedButton.styleFrom(
                                      foregroundColor: AppTheme.primary,
                                      side: const BorderSide(
                                        color: AppColors.border,
                                      ),
                                      minimumSize: const Size.fromHeight(50),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(
                                          999,
                                        ),
                                      ),
                                      textStyle: const TextStyle(
                                        fontFamily: 'Inter',
                                        fontSize: 15,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    icon: const Icon(
                                      Icons.person_add_outlined,
                                      size: 20,
                                    ),
                                    label: const Text('Apply as a Rider'),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),

                          // ── Check application status (outside the card,
                          // like the logistics web login) ──
                          OutlinedButton.icon(
                            onPressed: () => Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const ApplicationStatusScreen(),
                              ),
                            ),
                            style: OutlinedButton.styleFrom(
                              backgroundColor: Colors.white,
                              foregroundColor: AppTheme.primary,
                              side: BorderSide(color: AppColors.border),
                              minimumSize: const Size.fromHeight(50),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(999),
                              ),
                              textStyle: const TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            icon: const Icon(Icons.search, size: 18),
                            label: const Text('Check Application Status'),
                          ),
                          const SizedBox(height: 12),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

/// Compact identity row, shared in spirit with buyer sign-in.
class _CurvedHeader extends StatelessWidget {
  const _CurvedHeader({required this.height});
  final double height;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: height,
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Row(
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: AppColors.card,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.border),
            ),
            padding: const EdgeInsets.all(8),
            child: Image.asset('images/logo.png', fit: BoxFit.contain),
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'INVOIZ',
                  style: TextStyle(
                    fontSize: 21,
                    fontWeight: FontWeight.w800,
                    color: AppColors.primaryDark,
                  ),
                ),
                SizedBox(height: 3),
                Text(
                  'RIDER CENTER',
                  style: TextStyle(
                    fontSize: 10,
                    letterSpacing: 1.5,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          const Icon(Icons.route_outlined, color: AppColors.primary, size: 24),
        ],
      ),
    ),
  );
}
