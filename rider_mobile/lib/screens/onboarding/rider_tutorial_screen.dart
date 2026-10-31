import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../services/tutorial_completion_store.dart';

class RiderTutorialScreen extends StatefulWidget {
  const RiderTutorialScreen({
    super.key,
    this.riderId,
    this.workflowType = 'delivery_rider',
    this.store = const TutorialCompletionStore(),
  });

  /// Retained for callers from older screens; completion is install-wide.
  final int? riderId;
  final String workflowType;
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

const _courierSteps = [
  'Sign in and check the assigned four-wheel vehicle and plate number.',
  'Open the Hub Transport Driver dashboard and check online status and assigned work.',
  'Open the assigned transfer with View Transfer.',
  'Check the origin and destination hubs before moving.',
  'Review the transfer reference, parcel manifest, count and vehicle capacity.',
  'Use Navigate to Origin Hub, then press Arrived at Origin Hub.',
  'Press Scan Parcel for each parcel in the manifest and load it on the vehicle.',
  'Check that every manifest parcel shows as loaded, then press Complete Loading.',
  'Review the dispatch summary and confirm Dispatch Transfer.',
  'Use Navigate to Destination Hub and press Arrived at Destination Hub.',
  'Unload parcels and hand the manifest to destination hub staff.',
  'Wait while destination staff scan and confirm each parcel; arrival alone does not confirm receipt.',
  'View transfer completion and history after receiving is finalized by hub staff.',
];

const _deliverySteps = [
  'Sign in and check your assigned motorcycle or scooter and plate number.',
  'Open the Delivery Rider dashboard and check your online status.',
  'Open My Deliveries and select an assigned delivery.',
  'Check the shop pickup point, buyer address, tracking number and instructions.',
  'Press Navigate to Shop and then I Arrived at Shop when you reach the pickup point.',
  'Scan each assigned parcel and press Confirm Pickup after verification.',
  'Press Navigate to open directions to the buyer, then I Arrived at Customer on arrival.',
  'Contact the buyer with Call or Message when needed.',
  'Press Complete Delivery and complete the proof photo, recipient name or OTP step required for that parcel.',
  'If delivery fails, press Report Failed Delivery and provide an allowed reason and details.',
  'Open completed deliveries and history to review the result.',
];

class _RiderTutorialScreenState extends State<RiderTutorialScreen> {
  final _controller = PageController();
  int _index = 0;
  bool _finishing = false;
  List<_TutorialPage> get _pages => widget.workflowType == 'hub_courier'
      ? List.generate(
          _courierSteps.length,
          (i) => _TutorialPage(
            title: [
              'Check Your Vehicle',
              'Hub Transport Driver Dashboard',
              'View Transfer',
              'Check the Route',
              'Review Manifest',
              'Go to Origin',
              'Scan and Load',
              'Complete Loading',
              'Dispatch Transfer',
              'Go to Destination',
              'Hand Over Parcels',
              'Wait for Receiving',
              'Transfer History',
            ][i],
            description: _courierSteps[i],
            icon: [
              Icons.local_shipping,
              Icons.dashboard,
              Icons.assignment,
              Icons.route,
              Icons.inventory_2,
              Icons.location_on,
              Icons.qr_code_scanner,
              Icons.task_alt,
              Icons.send,
              Icons.navigation,
              Icons.move_to_inbox,
              Icons.pending_actions,
              Icons.history,
            ][i],
            points: const [
              'Use the named action shown on the transfer screen. The next action appears only when the transfer state allows it.',
            ],
          ),
        )
      : List.generate(
          _deliverySteps.length,
          (i) => _TutorialPage(
            title: [
              'Check Your Vehicle',
              'Delivery Rider Dashboard',
              'My Deliveries',
              'Check Pickup and Destination',
              'Go to the Shop',
              'Confirm Pickup',
              'Go to the Buyer',
              'Contact the Buyer',
              'Complete Delivery',
              'Report Failed Delivery',
              'Delivery History',
            ][i],
            description: _deliverySteps[i],
            icon: [
              Icons.two_wheeler,
              Icons.dashboard,
              Icons.list_alt,
              Icons.route,
              Icons.location_on,
              Icons.qr_code_scanner,
              Icons.navigation,
              Icons.call,
              Icons.task_alt,
              Icons.report_problem,
              Icons.history,
            ][i],
            points: const [
              'Use the named action shown on the delivery screen. The existing delivery instructions and verification requirements remain in effect.',
            ],
          ),
        );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _finish() async {
    if (_finishing) return;
    setState(() => _finishing = true);
    await widget.store.markCompleted(workflow: widget.workflowType);
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
          title: Text(
            widget.workflowType == 'hub_courier'
                ? 'Hub Transport Driver Tutorial'
                : 'Delivery Rider Tutorial',
          ),
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
                  itemBuilder: (context, index) => _TutorialContent(
                    page: _pages[index],
                    index: index,
                    total: _pages.length,
                  ),
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
                              child: const Text('Back'),
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
  const _TutorialContent({
    required this.page,
    required this.index,
    required this.total,
  });

  final _TutorialPage page;
  final int index;
  final int total;

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
                '${index + 1}  /  $total',
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
              if (index == total - 1) ...[
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
