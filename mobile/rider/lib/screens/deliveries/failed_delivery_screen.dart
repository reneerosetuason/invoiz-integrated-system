import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_constants.dart';
import '../../core/theme/app_theme.dart';
import '../../providers/delivery_provider.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/loading_widget.dart';

/// Report a failed delivery with a reason + notes.
class FailedDeliveryScreen extends StatefulWidget {
  const FailedDeliveryScreen({super.key, required this.deliveryId});

  final int deliveryId;

  @override
  State<FailedDeliveryScreen> createState() => _FailedDeliveryScreenState();
}

class _FailedDeliveryScreenState extends State<FailedDeliveryScreen> {
  String? _reason;
  final _notesController = TextEditingController();
  bool _failedReported = false;
  bool _submitting = false;

  /// Reasons where sending the parcel back usually makes more sense than
  /// trying again. Everything else defaults to recommending a re-attempt.
  static const _returnOrientedReasons = {
    'Customer refused order',
    'Shop/package issue',
  };

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<DeliveryProvider>();
      if (provider.selected?.id != widget.deliveryId) {
        provider.loadDetail(widget.deliveryId);
      }
    });
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_submitting) return;
    if (_reason == null) {
      _snack('Please select a failure reason.');
      return;
    }

    setState(() => _submitting = true);
    final provider = context.read<DeliveryProvider>();
    final ok = await provider.failed(
      widget.deliveryId,
      reason: _reason!,
      notes: _notesController.text.trim(),
    );

    if (!mounted) return;
    setState(() => _submitting = false);

    if (ok) {
      _snack('Failed delivery reported.');
      setState(() => _failedReported = true);
    } else {
      _snack(provider.error ?? 'Unable to report the failed delivery.');
    }
  }

  Future<void> _requestReattempt(DeliveryProvider provider) async {
    final ok = await provider.requestReattempt(widget.deliveryId);
    if (!mounted) return;
    if (ok) {
      _snack('Re-attempt requested. Logistics will review and reassign.');
      Navigator.of(context).popUntil((route) => route.isFirst);
    } else {
      _snack(provider.error ?? 'Unable to request a re-attempt.');
      setState(() {});
    }
  }

  Future<void> _returnToCenter(DeliveryProvider provider) async {
    final ok = await provider.returnToCenter(widget.deliveryId);
    if (!mounted) return;
    if (ok) {
      _snack('Parcel marked as returned to the sorting center.');
      Navigator.of(context).popUntil((route) => route.isFirst);
    } else {
      _snack(provider.error ?? 'Unable to return the parcel.');
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<DeliveryProvider>();
    final delivery = provider.selected;

    if (provider.loading && delivery == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Failed Delivery')),
        body: const LoadingWidget(),
      );
    }

    if (delivery == null || delivery.id != widget.deliveryId) {
      return Scaffold(
        appBar: AppBar(title: const Text('Failed Delivery')),
        body: ErrorView(
          message: provider.error ?? 'Unable to load the delivery.',
          onRetry: () => provider.loadDetail(widget.deliveryId),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Failed Delivery'),
        backgroundColor: AppTheme.danger,
        foregroundColor: Colors.white,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(AppGaps.lg),
              child: Row(
                children: [
                  const IconTile(
                    icon: Icons.error_outline,
                    color: AppTheme.danger,
                    size: 40,
                    iconSize: 20,
                  ),
                  const SizedBox(width: AppGaps.md),
                  Expanded(
                    child: Text(
                      delivery.trackingNumber,
                      style: AppText.tracking.copyWith(fontSize: 15),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (_failedReported && delivery.status == 'delivery_failed') ...[
            _NextStepSection(
              reason: _reason,
              busy: provider.actionBusy,
              alreadyRequested: delivery.hasReattemptRequest,
              alreadyReturned: delivery.hasCenterReturn,
              onReattempt: () => _requestReattempt(provider),
              onReturn: () => _returnToCenter(provider),
            ),
          ] else ...[
            const SizedBox(height: AppGaps.xl),
            const SectionEyebrow(text: 'Why did the delivery fail?'),
            const SizedBox(height: AppGaps.sm),
            Card(
              child: RadioGroup<String>(
                groupValue: _reason,
                onChanged: (v) => setState(() => _reason = v),
                child: Column(
                  children: AppConstants.failureReasons
                      .map(
                        (reason) => RadioListTile<String>(
                          title: Text(reason),
                          value: reason,
                          dense: true,
                        ),
                      )
                      .toList(),
                ),
              ),
            ),
            if (_reason == 'Customer refused order') ...[
              const SizedBox(height: AppGaps.sm),
              const Text(
                'Customer refused: the parcel goes straight to Return to '
                'Sender — no further attempt is scheduled.',
                style: AppText.secondary,
              ),
            ],
            const SizedBox(height: AppGaps.lg),
            const SectionEyebrow(text: 'Notes'),
            const SizedBox(height: AppGaps.sm),
            TextField(
              controller: _notesController,
              maxLines: 3,
              maxLength: 500,
              decoration: const InputDecoration(
                hintText: 'Add details about what happened...',
              ),
            ),
          ],
        ],
      ),
      bottomNavigationBar: _failedReported
          ? null
          : SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 14),
                child: ElevatedButton.icon(
                  onPressed: (provider.actionBusy || _submitting)
                      ? null
                      : _submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.danger,
                  ),
                  icon: provider.actionBusy || _submitting
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.report_problem_outlined),
                  label: Text(
                    provider.actionBusy || _submitting
                        ? 'Submitting...'
                        : 'Submit Failed Delivery',
                  ),
                ),
              ),
            ),
    );
  }

  void _snack(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

/// Next step after a failure is reported: ask Logistics for a re-attempt
/// or hand the parcel back to the sorting center. The failure record,
/// tracking, and history stay intact either way.
class _NextStepSection extends StatelessWidget {
  const _NextStepSection({
    required this.reason,
    required this.busy,
    required this.alreadyRequested,
    required this.alreadyReturned,
    required this.onReattempt,
    required this.onReturn,
  });

  final String? reason;
  final bool busy;
  final bool alreadyRequested;
  final bool alreadyReturned;
  final VoidCallback onReattempt;
  final VoidCallback onReturn;

  @override
  Widget build(BuildContext context) {
    final suggestReturn =
        reason != null &&
        _FailedDeliveryScreenState._returnOrientedReasons.contains(reason);
    final refused = reason == 'Customer refused order';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: AppGaps.xl),
        const SectionEyebrow(text: 'What should happen next?'),
        const SizedBox(height: AppGaps.sm),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppGaps.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    IconTile(
                      icon: Icons.check_circle_outline,
                      color: AppTheme.success,
                      size: 40,
                      iconSize: 20,
                    ),
                    SizedBox(width: AppGaps.md),
                    Expanded(
                      child: Text(
                        'Failure reported. Choose the next step — Logistics sees it either way.',
                        style: AppText.body,
                      ),
                    ),
                  ],
                ),
                if (reason != null) ...[
                  const SizedBox(height: AppGaps.md),
                  Text(
                    suggestReturn
                        ? 'For this reason, returning the parcel is usually best — or request another try.'
                        : 'For this reason, trying again is usually best — or return the parcel instead.',
                    style: AppText.secondary,
                  ),
                ],
                if (refused) ...[
                  const SizedBox(height: AppGaps.md),
                  const Text(
                    'Customer refused: this parcel must go back to the sorting '
                    'center for Return to Sender. A re-attempt is not scheduled '
                    'for a refusal.',
                    style: AppText.secondary,
                  ),
                ] else ...[
                  const SizedBox(height: AppGaps.md),
                  const Text(
                    'After the 3rd failed attempt the parcel must be returned to '
                    'the sorting center for Return to Sender.',
                    style: AppText.secondary,
                  ),
                ],
                if (alreadyRequested) ...[
                  const SizedBox(height: AppGaps.md),
                  const Text(
                    'Re-attempt already requested — waiting on Logistics.',
                    style: AppText.secondary,
                  ),
                ],
                if (alreadyReturned) ...[
                  const SizedBox(height: AppGaps.md),
                  const Text(
                    'Parcel already returned to the sorting center.',
                    style: AppText.secondary,
                  ),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(height: AppGaps.lg),
        if (!refused) ...[
          ElevatedButton.icon(
            onPressed: busy || alreadyRequested || alreadyReturned
                ? null
                : onReattempt,
            icon: busy
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.5,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.replay_outlined),
            label: Text(busy ? 'Working...' : 'Request Re-attempt'),
          ),
          const SizedBox(height: AppGaps.md),
        ],
        OutlinedButton.icon(
          onPressed: busy || alreadyReturned ? null : onReturn,
          icon: const Icon(Icons.warehouse_outlined),
          label: const Text('Return to Sorting Center (RTS)'),
          style: OutlinedButton.styleFrom(
            minimumSize: const Size.fromHeight(50),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
          ),
        ),
      ],
    );
  }
}
