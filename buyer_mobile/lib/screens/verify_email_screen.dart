import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/buyer_auth.dart';
import 'login_screen.dart';

class VerifyEmailScreen extends StatefulWidget {
  final String email;
  const VerifyEmailScreen({super.key, required this.email});

  @override
  State<VerifyEmailScreen> createState() => _VerifyEmailScreenState();
}

class _VerifyEmailScreenState extends State<VerifyEmailScreen> {
  final _api = ApiService();
  final _codeCtrl = TextEditingController();
  bool _busy = false;
  bool _resending = false;
  int _cooldown = 0;
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    _codeCtrl.dispose();
    super.dispose();
  }

  void _startCooldown() {
    _timer?.cancel();
    setState(() => _cooldown = 60);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return;
      if (_cooldown <= 1) {
        t.cancel();
        setState(() => _cooldown = 0);
      } else {
        setState(() => _cooldown--);
      }
    });
  }

  void _show(String m, {bool error = true}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(m, style: const TextStyle(color: AppColors.textPrimary)),
        backgroundColor: error ? AppColors.errorTint : AppColors.paleTeal,
      ),
    );
  }

  Future<void> _verify() async {
    if (_busy) return;
    final code = _codeCtrl.text.trim();
    if (code.length != 6) {
      _show('Enter the 6-digit code from your email.');
      return;
    }
    setState(() => _busy = true);
    try {
      final r = await _api.post('mobile/buyer/verify-email', {
        'email': widget.email,
        'code': code,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${r['message'] ?? 'Email verified.'}',
            style: const TextStyle(color: AppColors.textPrimary),
          ),
          backgroundColor: AppColors.paleTeal,
        ),
      );
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    } on ApiException catch (e) {
      _show(e.message);
    } catch (_) {
      _show('Unable to connect to server.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    if (_cooldown > 0 || _resending) return;
    setState(() => _resending = true);
    try {
      final r = await _api.post('mobile/buyer/resend-code', {
        'email': widget.email,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${r['message'] ?? 'Code resent.'}',
            style: const TextStyle(color: AppColors.textPrimary),
          ),
          backgroundColor: AppColors.paleTeal,
        ),
      );
      _startCooldown();
    } on ApiException catch (e) {
      _show(e.message);
    } catch (_) {
      _show('Unable to connect to server.');
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  @override
  Widget build(BuildContext context) => BuyerAuthPage(
    title: 'Check your email',
    description: 'Enter the 6-digit code sent to ${widget.email}.',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Align(
          alignment: Alignment.centerLeft,
          child: Icon(
            Icons.mark_email_read_outlined,
            size: 30,
            color: AppColors.primary,
          ),
        ),
        const SizedBox(height: 22),
        TextField(
          controller: _codeCtrl,
          keyboardType: TextInputType.number,
          autofillHints: const [AutofillHints.oneTimeCode],
          textAlign: TextAlign.center,
          maxLength: 6,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          style: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 26,
            fontWeight: FontWeight.w600,
            letterSpacing: 7,
            color: AppColors.primaryDark,
          ),
          decoration: const InputDecoration(
            labelText: 'Verification code',
            hintText: '000000',
            counterText: '',
          ),
          onSubmitted: (_) => _verify(),
        ),
        const SizedBox(height: 22),
        ElevatedButton(
          onPressed: _busy ? null : _verify,
          child: _busy
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(
                    color: Colors.white,
                    strokeWidth: 2,
                  ),
                )
              : const Text('Verify'),
        ),
        const SizedBox(height: 12),
        TextButton(
          onPressed: _cooldown > 0 || _resending ? null : _resend,
          child: Text(
            _resending
                ? 'Sending code…'
                : _cooldown > 0
                ? 'Resend code in ${_cooldown}s'
                : 'Resend code',
          ),
        ),
      ],
    ),
    footer: const Text(
      'After verifying, your account follows the same approval process as the website.',
      textAlign: TextAlign.center,
    ),
  );
}
