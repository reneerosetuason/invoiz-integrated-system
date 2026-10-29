import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../providers/rider_provider.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/custom_text_field.dart';
import '../../widgets/primary_button.dart';

/// Change Password form (Account/Security section of Profile).
///
/// Sends the passwords only in the `PATCH /rider/password` request body;
/// nothing is stored or logged. Each field has an eye toggle where the
/// slashed eye means hidden and the open eye means visible.
class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _new = TextEditingController();
  final _confirm = TextEditingController();

  bool _obscureCurrent = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;
  bool _busy = false;

  @override
  void dispose() {
    _current.dispose();
    _new.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Widget _eye(bool obscured, VoidCallback onTap) {
    return IconButton(
      icon: Icon(
        obscured ? Icons.visibility_off_outlined : Icons.visibility_outlined,
        color: AppColors.textSecondary,
        size: 20,
      ),
      onPressed: onTap,
    );
  }

  String? _required(String? v, String field) {
    if (v == null || v.isEmpty) return 'Please enter your $field.';
    return null;
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() => _busy = true);

    final ok = await context.read<RiderProvider>().changePassword(
          currentPassword: _current.text,
          newPassword: _new.text,
          confirmPassword: _confirm.text,
        );

    if (!mounted) return;
    setState(() => _busy = false);

    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Password changed successfully.')),
      );
      Navigator.of(context).pop();
    } else {
      final error = context.read<RiderProvider>().error;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error ?? 'Unable to change password.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Change Password')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Center(
                  child: IconTile(
                    icon: Icons.lock_outline,
                    size: 56,
                    iconSize: 26,
                  ),
                ),
                const SizedBox(height: AppGaps.lg),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.card,
                    borderRadius: BorderRadius.circular(AppRadius.lg),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Column(
                    children: [
                      CustomTextField(
                        controller: _current,
                        label: 'CURRENT PASSWORD',
                        hint: 'Enter current password',
                        icon: Icons.lock_outline,
                        obscure: _obscureCurrent,
                        suffix: _eye(
                          _obscureCurrent,
                          () => setState(
                              () => _obscureCurrent = !_obscureCurrent),
                        ),
                        validator: (v) =>
                            _required(v, 'current password'),
                      ),
                      const SizedBox(height: 16),
                      CustomTextField(
                        controller: _new,
                        label: 'NEW PASSWORD',
                        hint: 'At least 8 characters',
                        icon: Icons.lock_outline,
                        obscure: _obscureNew,
                        suffix: _eye(
                          _obscureNew,
                          () => setState(
                              () => _obscureNew = !_obscureNew),
                        ),
                        validator: (v) {
                          final req =
                              _required(v, 'new password');
                          if (req != null) return req;
                          if (v!.length < 8) {
                            return 'Password must be at least 8 characters.';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),
                      CustomTextField(
                        controller: _confirm,
                        label: 'CONFIRM NEW PASSWORD',
                        hint: 'Repeat new password',
                        icon: Icons.lock_outline,
                        obscure: _obscureConfirm,
                        suffix: _eye(
                          _obscureConfirm,
                          () => setState(() =>
                              _obscureConfirm = !_obscureConfirm),
                        ),
                        validator: (v) {
                          final req = _required(
                              v, 'password confirmation');
                          if (req != null) return req;
                          if (v != _new.text) {
                            return 'Passwords do not match.';
                          }
                          return null;
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                PrimaryButton(
                  label: 'Change Password',
                  loading: _busy,
                  onPressed: _submit,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
