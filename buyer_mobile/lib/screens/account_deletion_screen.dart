import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/buyer_updates.dart';
import '../widgets/main_layout.dart';
import '../widgets/buyer_page.dart';

class AccountDeletionScreen extends StatefulWidget {
  final ApiService? api;
  const AccountDeletionScreen({super.key, this.api});

  @override
  State<AccountDeletionScreen> createState() => _AccountDeletionScreenState();
}

class _AccountDeletionScreenState extends State<AccountDeletionScreen> {
  late final _api = widget.api ?? ApiService();
  final _form = GlobalKey<FormState>();
  final _password = TextEditingController();
  final _comments = TextEditingController();
  Map<String, dynamic>? _request;
  List<String> _reasons = [];
  String? _reason;
  String _retention = '';
  String? _error;
  bool _loading = true;
  bool _busy = false;
  bool _confirmed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _password.dispose();
    _comments.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await _api.get('account-deletion');
      if (!mounted) return;
      setState(() {
        _request = data['deletion_request'] as Map<String, dynamic>?;
        _reasons = (data['reasons'] as List).cast<String>();
        _retention = data['retention_notice'] as String;
        _loading = false;
        _error = null;
      });
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = error.toString();
        });
      }
    }
  }

  Future<bool> _confirm(String message, String action) async =>
      await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(action),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(action),
            ),
          ],
        ),
      ) ??
      false;

  Future<void> _submit() async {
    if (_busy || !_form.currentState!.validate()) return;
    if (!_confirmed) {
      setState(
        () => _error =
            'Please confirm that you understand the 30-day grace period.',
      );
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _api.post('account-deletion/verify', {
        'current_password': _password.text,
      });
      if (!mounted ||
          !await _confirm(
            'Are you sure you want to delete your INVOIZ account? Your account will be scheduled for deletion in 30 days.',
            'Confirm Account Deletion',
          )) {
        return;
      }
      await _api.post('account-deletion', {
        'reason': _reason,
        'additional_feedback': _comments.text.trim(),
        'current_password': _password.text,
        'confirm_deletion': true,
      });
      _password.clear();
      BuyerUpdates.accountChanged();
      BuyerUpdates.inboxChanged();
      await _load();
    } catch (error) {
      _password.clear();
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cancel() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      if (!await _confirm(
        'Are you sure you want to cancel your account deletion request? Your account will remain active.',
        'Cancel Account Deletion',
      )) {
        return;
      }
      await _api.post('account-deletion/cancel', {
        'confirm_cancellation': true,
      });
      BuyerUpdates.accountChanged();
      BuyerUpdates.inboxChanged();
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final active = ['pending', 'on_hold'].contains(_request?['status']);
    return MainLayout(
      title: 'Account deletion',
      api: widget.api,
      showBottomNav: false,
      showSettingsIcon: false,
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: EdgeInsets.fromLTRB(
                16,
                16,
                16,
                16 + MediaQuery.viewInsetsOf(context).bottom,
              ),
              children: [
                const BuyerPageHeading(
                  title: 'Delete Account',
                  description:
                      'A 30-day grace period before account archiving.',
                ),
                const Text(
                  'You can still log in, view account information and order history, and cancel before the scheduled date. After 30 days, your account will be deactivated and archived once unresolved transactions are settled. Only an administrator can restore it after archiving.',
                ),
                const SizedBox(height: 12),
                Text(_retention),
                const SizedBox(height: 16),
                if (_error != null) ...[
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                  TextButton(
                    onPressed: _busy ? null : _load,
                    child: const Text('Refresh status'),
                  ),
                ],
                if (active)
                  BuyerPanel(
                    title: 'Account Deletion Scheduled',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Request date: ${_request!['requested_at']}'),
                        Text(
                          'Scheduled deletion: ${_request!['scheduled_archive_at']}',
                        ),
                        Text(
                          'Status: ${_request!['status'].toString().replaceAll('_', ' ')}',
                        ),
                        Text('Remaining: ${_request!['remaining_days']} days'),
                        const SizedBox(height: 12),
                        const Text(
                          'Your account is scheduled for deletion. You can cancel this request anytime before the scheduled deletion date. After deletion is completed, only an administrator can restore your account.',
                        ),
                        if (_request!['hold_reason'] != null)
                          Text(
                            '${_request!['hold_reason']} We will automatically recheck these transactions.',
                          ),
                        if (_request!['can_cancel'] == true)
                          TextButton(
                            onPressed: _busy ? null : _cancel,
                            child: const Text('Cancel Account Deletion'),
                          ),
                      ],
                    ),
                  )
                else
                  Form(
                    key: _form,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (_request != null)
                          Text('Previous request: ${_request!['status']}'),
                        DropdownButtonFormField<String>(
                          initialValue: _reason,
                          isExpanded: true,
                          itemHeight: null,
                          decoration: const InputDecoration(
                            labelText: 'Reason for deletion',
                          ),
                          items: _reasons
                              .map(
                                (reason) => DropdownMenuItem(
                                  value: reason,
                                  child: Text(
                                    reason,
                                    maxLines: 3,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: _busy
                              ? null
                              : (reason) => setState(() => _reason = reason),
                          validator: (value) =>
                              value == null ? 'Select a reason.' : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _comments,
                          maxLines: 3,
                          maxLength: 2000,
                          enabled: !_busy,
                          decoration: InputDecoration(
                            labelText: _reason == 'Other (please specify).'
                                ? 'Explanation (required)'
                                : 'Additional comments (optional)',
                          ),
                          validator: (value) =>
                              _reason == 'Other (please specify).' &&
                                  (value?.trim().isEmpty ?? true)
                              ? 'Explain your reason.'
                              : null,
                        ),
                        TextFormField(
                          controller: _password,
                          obscureText: true,
                          enabled: !_busy,
                          autocorrect: false,
                          enableSuggestions: false,
                          autofillHints: const [AutofillHints.password],
                          decoration: const InputDecoration(
                            labelText: 'Current password',
                          ),
                          validator: (value) => value == null || value.isEmpty
                              ? 'Enter your current password.'
                              : null,
                        ),
                        CheckboxListTile(
                          value: _confirmed,
                          controlAffinity: ListTileControlAffinity.leading,
                          contentPadding: EdgeInsets.zero,
                          title: const Text(
                            'I understand that my account will be scheduled for deletion after 30 days and that only an administrator can restore it once deletion is finalized.',
                          ),
                          onChanged: _busy
                              ? null
                              : (value) =>
                                    setState(() => _confirmed = value ?? false),
                        ),
                        FilledButton(
                          onPressed: _busy || _reasons.isEmpty ? null : _submit,
                          child: Text(
                            _busy ? 'Verifying...' : 'Submit deletion request',
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
    );
  }
}
