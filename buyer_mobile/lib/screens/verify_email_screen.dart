import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/invoiz_logo.dart';
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
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(m, style: const TextStyle(color: Color(0xFF212121))),
        backgroundColor: error ? Colors.red.shade100 : Colors.green.shade100,
      ),
    );
  }

  Future<void> _verify() async {
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
            style: const TextStyle(color: Color(0xFF212121)),
          ),
          backgroundColor: Colors.green.shade100,
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
    if (_cooldown > 0) return;
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
            style: const TextStyle(color: Color(0xFF212121)),
          ),
          backgroundColor: Colors.green.shade100,
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
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Verify Email')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const SizedBox(height: 12),
          Center(child: InvoizLogo.logoWidget(size: 84, radius: 22)),
          const SizedBox(height: 16),
          const Text(
            'Check your email',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: Color(0xFF212121),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'We sent a 6-digit code to\n${widget.email}',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 24),
          TextField(
            controller: _codeCtrl,
            keyboardType: TextInputType.number,
            textAlign: TextAlign.center,
            maxLength: 6,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w800,
              letterSpacing: 10,
              color: Color(0xFF212121),
            ),
            decoration: const InputDecoration(
              hintText: '••••••',
              counterText: '',
            ),
            onSubmitted: (_) => _verify(),
          ),
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: _busy ? null : _verify,
            style: ElevatedButton.styleFrom(
              minimumSize: const Size.fromHeight(48),
            ),
            child: _busy
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2,
                    ),
                  )
                : const Text('Verify', style: TextStyle(fontSize: 15)),
          ),
          const SizedBox(height: 12),
          Center(
            child: _resending
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : TextButton(
                    onPressed: _cooldown > 0 ? null : _resend,
                    child: Text(
                      _cooldown > 0
                          ? 'Resend code in ${_cooldown}s'
                          : 'Resend code',
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}
