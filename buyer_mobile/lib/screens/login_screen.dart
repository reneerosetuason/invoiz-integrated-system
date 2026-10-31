import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import '../widgets/buyer_auth.dart';
import '../widgets/buyer_controls.dart';
import '../widgets/buyer_field.dart';
import '../widgets/invoiz_logo.dart';
import 'home_screen.dart';
import 'register_screen.dart';
import 'verify_email_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  bool _obscure = true;
  bool _busy = false;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passCtrl.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (_busy) return;
    if (_emailCtrl.text.trim().isEmpty || _passCtrl.text.isEmpty) {
      _show('Please enter your email and password.');
      return;
    }
    setState(() => _busy = true);
    try {
      final auth = AuthServiceProvider.of(context);
      await auth.login(_emailCtrl.text.trim(), _passCtrl.text);
      if (!mounted) return;
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const HomeScreen()),
        (route) => false,
      );
    } on ApiException catch (e) {
      if (e.message.toLowerCase().contains('verify')) {
        final email = _emailCtrl.text.trim();
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => VerifyEmailScreen(email: email)),
        );
        return;
      }
      _show(e.message);
    } catch (e) {
      _show('Unable to connect to server.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _show(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message, style: const TextStyle(fontWeight: FontWeight.w500)),
        backgroundColor: AppColors.danger,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return BuyerAuthPage(
      title: 'Welcome to INVOIZ',
      description: 'Sign in to explore curated finds and track every delivery.',
      child: AutofillGroup(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Center Logo Header inside panel
            Center(
              child: Container(
                margin: const EdgeInsets.only(top: 4, bottom: 18),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.primary.withValues(alpha: 0.1),
                      blurRadius: 14,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: InvoizLogo.logoWidget(size: 52, radius: 14),
              ),
            ),

            // Email Field with clean styling
            BuyerField(
              controller: _emailCtrl,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.username, AutofillHints.email],
              decoration: const InputDecoration(
                labelText: 'E-mail address',
                prefixIcon: Icon(CupertinoIcons.mail, size: 20),
              ),
            ),
            const SizedBox(height: 16),

            // Password Field
            BuyerField(
              controller: _passCtrl,
              obscureText: _obscure,
              autofillHints: const [AutofillHints.password],
              onSubmitted: (_) => _login(),
              decoration: InputDecoration(
                labelText: 'Password',
                prefixIcon: const Icon(CupertinoIcons.lock, size: 20),
                suffixIcon: BuyerButton.icon(
                  tooltip: _obscure ? 'Show password' : 'Hide password',
                  icon: Icon(
                    _obscure ? CupertinoIcons.eye_slash : CupertinoIcons.eye,
                    size: 19,
                    color: AppColors.textMuted,
                  ),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
              ),
            ),
            const SizedBox(height: 22),

            // Primary Sign-in Button
            BuyerButton.primary(
              onPressed: _busy ? null : _login,
              child: _busy
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CupertinoActivityIndicator(color: Colors.white),
                    )
                  : const Text('Sign In', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
            ),
            const SizedBox(height: 12),

            // Guest Button
            BuyerButton.secondary(
              onPressed: _busy
                  ? null
                  : () => Navigator.pushReplacement(
                        context,
                        MaterialPageRoute(builder: (_) => const HomeScreen()),
                      ),
              icon: const Icon(CupertinoIcons.compass, size: 18),
              label: const Text('Continue as Guest', style: TextStyle(fontWeight: FontWeight.w600)),
            ),
          ],
        ),
      ),
      footer: Wrap(
        alignment: WrapAlignment.center,
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 4,
        runSpacing: 4,
        children: [
          Text(
            'New to Invoiz?',
            style: TextStyle(color: AppColors.textSecondary, fontSize: 13.5),
          ),
          BuyerButton.plain(
            onPressed: _busy
                ? null
                : () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const RegisterScreen()),
                    ),
            child: const Text(
              'Create account',
              style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.primary),
            ),
          ),
        ],
      ),
    );
  }
}
