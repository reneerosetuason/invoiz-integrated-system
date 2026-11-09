import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/api_client.dart';
import '../../models/hub_courier.dart';
import '../../providers/auth_provider.dart';
import '../../services/hub_courier_service.dart';
import '../../widgets/receipt_confirmation.dart';
import '../../widgets/payout_account_button.dart';

class HubCourierRemittancesScreen extends StatefulWidget {
  const HubCourierRemittancesScreen({
    super.key,
    this.title = 'Interhub remittances',
    this.api,
  });
  final String title;
  final ApiClient? api;

  @override
  State<HubCourierRemittancesScreen> createState() =>
      _HubCourierRemittancesScreenState();
}

class _HubCourierRemittancesScreenState
    extends State<HubCourierRemittancesScreen>
    with WidgetsBindingObserver {
  late Future<Map<String, dynamic>> _result;
  int _page = 1;
  String? _accepting;

  HubCourierService get _service =>
      HubCourierService(widget.api ?? context.read<AuthProvider>().api);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _result = _service.remittances();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _reload();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> _reload({int? page}) async {
    if (page != null) _page = page;
    final future = _service.remittances(page: _page);
    setState(() {
      _result = future;
    });
    try {
      await future;
    } catch (_) {
      // The FutureBuilder shows a retry action without optimistic credit.
    }
  }

  Future<void> _accept(Map<String, dynamic> payout) async {
    if (_accepting != null) return;
    final id = asJsonInt(payout['id']);
    final amount = asJsonDouble(payout['amount']);
    final confirmed = await showReceiptConfirmation(
      context,
      title: 'Confirm payment received',
      message:
          'Have you received ₱${amount.toStringAsFixed(2)}? Confirm only after checking the payment in your receiving account.',
      cancelLabel: 'Cancel',
      confirmLabel: 'Confirm receipt',
    );
    if (confirmed != true || !mounted) return;
    setState(() => _accepting = '${payout['source'] ?? 'order'}-$id');
    try {
      await _service.acceptRemittance(
        id,
        source: payout['source'] as String? ?? 'order',
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Payment receipt confirmed.')),
      );
      await _reload();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error is ApiException
                ? error.message
                : 'Unable to confirm receipt. Please retry.',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _accepting = null);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.title),
      actions: [PayoutAccountButton(api: _service.api)],
    ),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _result,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('Could not load your remittances.'),
                  const SizedBox(height: 12),
                  FilledButton(
                    onPressed: () => _reload(),
                    child: const Text('Retry'),
                  ),
                ],
              ),
            ),
          );
        }
        final data = snapshot.data ?? {};
        final payouts = asJsonMapList(data['remittances']);
        final pagination = asJsonMapOrEmpty(data['pagination']);
        final lastPage = asJsonInt(pagination['last_page']);
        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            children: [
              const Text(
                'Admin releases your remittance first. Confirm receipt after the money reaches your account. '
                'Order and return-delivery payments use this same list.',
              ),
              const SizedBox(height: 16),
              if (payouts.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 32),
                  child: Text(
                    'No remittances yet.',
                    textAlign: TextAlign.center,
                  ),
                ),
              ...payouts.map((raw) {
                final payout = asJsonMapOrEmpty(raw);
                final status = payout['status']?.toString() ?? '';
                final id = asJsonInt(payout['id']);
                final label = switch (status) {
                  'accepted' => 'Receipt confirmed',
                  'remitted' => 'Ready to confirm receipt',
                  'pending_remittance' => 'Awaiting admin transfer',
                  'voided' => 'Voided',
                  _ => payout['hold_reason'] as String? ?? 'Held in escrow',
                };
                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '₱${asJsonDouble(payout['amount']).toStringAsFixed(2)}',
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        const SizedBox(height: 4),
                        if (payout['source'] == 'return')
                          Text(
                            'Return #${payout['return_id']} · ${payout['role_label'] ?? 'Return service'}',
                          ),
                        Text(
                          label,
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                        if (payout['transfer_reference'] != null)
                          Text('Trip: ${payout['transfer_reference']}'),
                        if (payout['tracking_number'] != null)
                          Text('Parcel: ${payout['tracking_number']}'),
                        if (payout['bank_reference'] != null)
                          Text(
                            'Transfer / release reference: ${payout['bank_reference']}',
                          ),
                        if (status == 'remitted') ...[
                          const SizedBox(height: 12),
                          SizedBox(
                            width: double.infinity,
                            child: FilledButton(
                              onPressed: _accepting != null
                                  ? null
                                  : () => _accept(payout),
                              child: Text(
                                _accepting ==
                                        '${payout['source'] ?? 'order'}-$id'
                                    ? 'Confirming…'
                                    : 'Accept remittance',
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                );
              }),
              if (lastPage > 1) ...[
                Text('Page $_page of $lastPage', textAlign: TextAlign.center),
                Wrap(
                  alignment: WrapAlignment.center,
                  spacing: 12,
                  children: [
                    TextButton(
                      onPressed: _page > 1 && _accepting == null
                          ? () => _reload(page: _page - 1)
                          : null,
                      child: const Text('Previous'),
                    ),
                    TextButton(
                      onPressed: _page < lastPage && _accepting == null
                          ? () => _reload(page: _page + 1)
                          : null,
                      child: const Text('Next'),
                    ),
                  ],
                ),
              ],
            ],
          ),
        );
      },
    ),
  );
}
