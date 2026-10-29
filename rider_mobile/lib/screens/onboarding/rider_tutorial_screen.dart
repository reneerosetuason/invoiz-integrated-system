import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../services/tutorial_completion_store.dart';

class RiderTutorialScreen extends StatefulWidget {
  const RiderTutorialScreen({
    super.key,
    this.riderId,
    this.store = const TutorialCompletionStore(),
  });

  /// Retained for callers from older screens; completion is install-wide.
  final int? riderId;
  final TutorialCompletionStore store;

  @override
  State<RiderTutorialScreen> createState() => _RiderTutorialScreenState();
}

class _TutorialPage {
  const _TutorialPage({
    required this.title,
    required this.description,
    required this.icon,
    required this.points,
  });

  final String title;
  final String description;
  final IconData icon;
  final List<String> points;
}

const _pages = [
  _TutorialPage(
    title: 'Welcome to INVOIZ Rider!',
    description:
        'Your delivery companion for managing assigned parcels, completing deliveries, and tracking your delivery activity.',
    icon: Icons.delivery_dining_rounded,
    points: [
      'Manage your delivery tasks from one place.',
      'Check your activity and account details whenever you need them.',
    ],
  ),
  _TutorialPage(
    title: 'Control Your Availability',
    description:
        "Go Online when you're ready to work and Offline when you're unavailable.",
    icon: Icons.power_settings_new_rounded,
    points: [
      'Use the Online/Offline control on the Home dashboard.',
      'Location permission may be needed to go Online.',
      'Going Online does not guarantee an immediate delivery assignment.',
    ],
  ),
  _TutorialPage(
    title: 'Manage Your Deliveries',
    description:
        'Review your assigned delivery information and follow the pickup instructions.',
    icon: Icons.inventory_2_outlined,
    points: [
      'Open Deliveries from the bottom navigation to see your jobs.',
      'Review the delivery, customer, and pickup details before acting.',
      'Scan the parcel QR code or enter its tracking number manually when verifying pickup.',
      'Check the tracking number and parcel details before confirming pickup.',
    ],
  ),
  _TutorialPage(
    title: 'Deliver and Confirm',
    description:
        'Follow the required delivery steps and submit the information needed to complete each delivery.',
    icon: Icons.task_alt_rounded,
    points: [
      'Follow the status steps shown in the delivery details, from acceptance through arrival.',
      'A delivery photo is required to complete a delivery. Enter the 6-digit OTP when that delivery requires one.',
      'For COD deliveries, enter the amount received and confirm it matches the amount to collect.',
      'If delivery cannot be completed, use the delivery’s failed-delivery action and provide the requested details.',
    ],
  ),
  _TutorialPage(
    title: 'Track Your Work',
    description:
        'Keep track of your delivery activity and account information.',
    icon: Icons.account_circle_outlined,
    points: [
      'Find earnings and delivery history in your Profile.',
      'Open Earnings, then Cash Remittances, to review COD collections and handovers.',
      'View your profile and vehicle information in Profile.',
      'Open Settings for account options and notifications.',
    ],
  ),
];

class _RiderTutorialScreenState extends State<RiderTutorialScreen> {
  final _controller = PageController();
  int _index = 0;
  bool _finishing = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _finish() async {
    if (_finishing) return;
    setState(() => _finishing = true);
    await widget.store.markCompleted();
    if (mounted) Navigator.of(context).pop();
  }

  void _next() {
    if (_index == _pages.length - 1) {
      _finish();
    } else {
      _controller.nextPage(
        duration: const Duration(milliseconds: 260),
        curve: Curves.easeOutCubic,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _finish();
      },
      child: Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          title: const Text('Rider Tutorial'),
          automaticallyImplyLeading: false,
          actions: [
            TextButton(
              onPressed: _finishing ? null : _finish,
              child: const Text('Skip'),
            ),
          ],
        ),
        body: SafeArea(
          top: false,
          child: Column(
            children: [
              Expanded(
                child: PageView.builder(
                  controller: _controller,
                  itemCount: _pages.length,
                  onPageChanged: (value) => setState(() => _index = value),
                  itemBuilder: (context, index) =>
                      _TutorialContent(page: _pages[index], index: index),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Semantics(
                      label: 'Page ${_index + 1} of ${_pages.length}',
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: List.generate(
                          _pages.length,
                          (i) => AnimatedContainer(
                            duration: const Duration(milliseconds: 180),
                            margin: const EdgeInsets.symmetric(horizontal: 4),
                            width: i == _index ? 22 : 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: i == _index
                                  ? AppColors.primary
                                  : AppColors.border,
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: [
                        if (_index > 0)
                          Expanded(
                            child: OutlinedButton(
                              onPressed: _finishing
                                  ? null
                                  : () => _controller.previousPage(
                                      duration: const Duration(
                                        milliseconds: 260,
                                      ),
                                      curve: Curves.easeOutCubic,
                                    ),
                              child: const Text('Previous'),
                            ),
                          ),
                        if (_index > 0) const SizedBox(width: 12),
                        Expanded(
                          flex: _index == 0 ? 1 : 2,
                          child: ElevatedButton(
                            onPressed: _finishing ? null : _next,
                            child: Text(
                              _index == 0
                                  ? 'Get Started'
                                  : _index == _pages.length - 1
                                  ? 'Finish Tutorial'
                                  : 'Next',
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TutorialContent extends StatelessWidget {
  const _TutorialContent({required this.page, required this.index});

  final _TutorialPage page;
  final int index;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) => SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(24, 20, 24, 24),
        child: ConstrainedBox(
          constraints: BoxConstraints(minHeight: constraints.maxHeight - 44),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 112,
                  height: 112,
                  decoration: BoxDecoration(
                    color: AppColors.tint,
                    borderRadius: BorderRadius.circular(32),
                  ),
                  child: Icon(page.icon, size: 56, color: AppColors.primary),
                ),
              ),
              const SizedBox(height: 28),
              Text(
                '0${index + 1}  /  05',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: AppColors.primary,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.2,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                page.title,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                page.description,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                  color: AppColors.textSecondary,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 24),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: page.points
                        .map(
                          (point) => Padding(
                            padding: const EdgeInsets.symmetric(vertical: 7),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Icon(
                                  Icons.check_circle_outline,
                                  color: AppColors.primary,
                                  size: 20,
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    point,
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodyMedium
                                        ?.copyWith(height: 1.45),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        )
                        .toList(),
                  ),
                ),
              ),
              if (index == _pages.length - 1) ...[
                const SizedBox(height: 14),
                const Text(
                  "You're ready to get started. Ride safely and deliver responsibly!",
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: AppColors.primaryDark,
                    fontWeight: FontWeight.w700,
                    height: 1.45,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
