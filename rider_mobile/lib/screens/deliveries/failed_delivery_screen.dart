import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_constants.dart';
import '../../core/theme/app_theme.dart';
import '../../providers/delivery_provider.dart';
import '../../widgets/app_ui.dart';
import '../../widgets/error_widget.dart';
import '../../widgets/failed_delivery_hub_return.dart';
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

  Future<void> _returnToCenter(DeliveryProvider provider) async {
    if (!await confirmFailedParcelHandover(context) || !mounted) return;
    final ok = await provider.returnToCenter(widget.deliveryId);
    if (!mounted) return;
    if (ok) {
      _snack(
        'Parcel returned. Waiting for logistics staff to assign the next delivery attempt.',
      );
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
            const SizedBox(height: AppGaps.xl),
            const SectionEyebrow(text: 'What should happen next?'),
            const SizedBox(height: AppGaps.sm),
            FailedDeliveryHubReturn(
              busy: provider.actionBusy,
              returned: delivery.hasCenterReturn,
              failedAttempts: delivery.failedAttempts,
              centerName:
                  delivery.destinationCenterName ?? delivery.handlingCenterName,
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
