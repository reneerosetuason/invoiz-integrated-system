import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/buyer_updates.dart';
import '../screens/account_deletion_screen.dart';

class AccountDeletionBanner extends StatefulWidget {
  final ApiService? api;
  const AccountDeletionBanner({super.key, this.api});
  @override
  State<AccountDeletionBanner> createState() => _AccountDeletionBannerState();
}

class _AccountDeletionBannerState extends State<AccountDeletionBanner> {
  Map<String, dynamic>? _request;
  @override
  void initState() {
    super.initState();
    BuyerUpdates.account.addListener(_load);
    _load();
  }

  @override
  void dispose() {
    BuyerUpdates.account.removeListener(_load);
    super.dispose();
  }

  Future<void> _load() async {
    if (ApiService.token == null) return;
    try {
      final data = await (widget.api ?? ApiService()).get('account-deletion');
      if (mounted) {
        setState(
          () => _request = data['deletion_request'] as Map<String, dynamic>?,
        );
      }
    } catch (_) {
      /* Account settings shows errors; shopping remains available. */
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!['pending', 'on_hold'].contains(_request?['status'])) {
      return const SizedBox.shrink();
    }
    return Material(
      color: const Color(0xfffff7ed),
      child: InkWell(
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => AccountDeletionScreen(api: widget.api),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Row(
            children: [
              const Icon(Icons.info_outline, size: 18),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  _request!['status'] == 'on_hold'
                      ? 'Account deletion is on hold. View request.'
                      : 'Account deletion scheduled: ${_request!['remaining_days']} days remaining. View or cancel.',
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
