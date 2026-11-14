import 'package:flutter/material.dart';
import '../theme.dart';

/// Content-shaped pulse animation that mimics the final page layout.
class _SkeletonPulse extends StatefulWidget {
  final Widget child;
  const _SkeletonPulse({required this.child});

  @override
  State<_SkeletonPulse> createState() => _SkeletonPulseState();
}

class _SkeletonPulseState extends State<_SkeletonPulse>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _opacity;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..repeat(reverse: true);
    _opacity = Tween<double>(begin: 0.45, end: 1.0).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.of(context).disableAnimations) {
      return widget.child;
    }
    return FadeTransition(opacity: _opacity, child: widget.child);
  }
}

/// A versatile skeleton placeholder box with smooth pulsing animation.
class SkeletonBox extends StatelessWidget {
  final double? width;
  final double? height;
  final double radius;
  final Color? color;
  final EdgeInsetsGeometry? margin;

  const SkeletonBox({
    super.key,
    this.width,
    this.height = 16,
    this.radius = 8,
    this.color,
    this.margin,
  });

  const SkeletonBox.circle({
    super.key,
    required double size,
    this.color,
    this.margin,
  })  : width = size,
        height = size,
        radius = size / 2;

  @override
  Widget build(BuildContext context) {
    final box = Container(
      width: width,
      height: height,
      margin: margin,
      decoration: BoxDecoration(
        color: color ?? AppColors.sandMid,
        borderRadius: BorderRadius.circular(radius),
      ),
    );
    return _SkeletonPulse(child: box);
  }
}

/// A product card skeleton matching BuyerProductCard.
class ProductCardSkeleton extends StatelessWidget {
  const ProductCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: SkeletonBox(
              width: double.infinity,
              height: double.infinity,
              radius: 0,
              color: AppColors.sandLight,
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                const SkeletonBox(width: 50, height: 10, radius: 4),
                const SizedBox(height: 6),
                const SkeletonBox(width: double.infinity, height: 13, radius: 4),
                const SizedBox(height: 4),
                const SkeletonBox(width: 90, height: 13, radius: 4),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: const [
                    SkeletonBox(width: 65, height: 15, radius: 4),
                    SkeletonBox(width: 35, height: 12, radius: 4),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Grid of product card skeletons for catalog, home, wishlist, or store.
class ProductGridSkeleton extends StatelessWidget {
  final int count;
  final EdgeInsetsGeometry padding;

  const ProductGridSkeleton({
    super.key,
    this.count = 6,
    this.padding = const EdgeInsets.all(12),
  });

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      physics: const NeverScrollableScrollPhysics(),
      shrinkWrap: true,
      padding: padding,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 0.68,
      ),
      itemCount: count,
      itemBuilder: (_, __) => const ProductCardSkeleton(),
    );
  }
}

/// Full product detail page skeleton.
class ProductDetailSkeleton extends StatelessWidget {
  const ProductDetailSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const NeverScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      children: [
        // Hero image skeleton
        const SkeletonBox(
          width: double.infinity,
          height: 320,
          radius: 0,
          color: AppColors.sandLight,
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Price block
              Row(
                crossAxisAlignment: CrossAxisAlignment.baseline,
                textBaseline: TextBaseline.alphabetic,
                children: const [
                  SkeletonBox(width: 120, height: 26, radius: 6),
                  SizedBox(width: 10),
                  SkeletonBox(width: 60, height: 16, radius: 4),
                ],
              ),
              const SizedBox(height: 12),
              // Title lines
              const SkeletonBox(width: double.infinity, height: 18, radius: 4),
              const SizedBox(height: 6),
              const SkeletonBox(width: 200, height: 18, radius: 4),
              const SizedBox(height: 16),
              // Rating / sold row
              Row(
                children: const [
                  SkeletonBox(width: 80, height: 14, radius: 4),
                  SizedBox(width: 12),
                  SkeletonBox(width: 70, height: 14, radius: 4),
                ],
              ),
              const SizedBox(height: 24),
              // Seller Card
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.paper,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.border),
                ),
                child: Row(
                  children: const [
                    SkeletonBox.circle(size: 46),
                    SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          SkeletonBox(width: 110, height: 14, radius: 4),
                          SizedBox(height: 6),
                          SkeletonBox(width: 70, height: 11, radius: 4),
                        ],
                      ),
                    ),
                    SkeletonBox(width: 76, height: 30, radius: 8),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              // Description heading & paragraphs
              const SkeletonBox(width: 130, height: 16, radius: 4),
              const SizedBox(height: 10),
              const SkeletonBox(width: double.infinity, height: 12, radius: 4),
              const SizedBox(height: 6),
              const SkeletonBox(width: double.infinity, height: 12, radius: 4),
              const SizedBox(height: 6),
              const SkeletonBox(width: 240, height: 12, radius: 4),
            ],
          ),
        ),
      ],
    );
  }
}

/// Order card skeleton matching BuyerOrderCard.
class OrderCardSkeleton extends StatelessWidget {
  const OrderCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Order header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: const [
              SkeletonBox(width: 110, height: 13, radius: 4),
              SkeletonBox(width: 70, height: 20, radius: 6),
            ],
          ),
          const Divider(height: 20, color: AppColors.border),
          // Item snippet
          Row(
            children: [
              const SkeletonBox(width: 56, height: 56, radius: 8),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    SkeletonBox(width: double.infinity, height: 13, radius: 4),
                    SizedBox(height: 6),
                    SkeletonBox(width: 100, height: 11, radius: 4),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          // Price and action row
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: const [
              SkeletonBox(width: 100, height: 16, radius: 4),
              SkeletonBox(width: 80, height: 28, radius: 8),
            ],
          ),
        ],
      ),
    );
  }
}

/// Orders list skeleton.
class OrderListSkeleton extends StatelessWidget {
  final int count;
  const OrderListSkeleton({super.key, this.count = 3});

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: count,
      itemBuilder: (_, __) => const OrderCardSkeleton(),
    );
  }
}

/// Cart list skeleton.
class CartListSkeleton extends StatelessWidget {
  const CartListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        for (int store = 0; store < 2; store++) ...[
          Container(
            margin: const EdgeInsets.only(bottom: 16),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: const [
                    SkeletonBox(width: 20, height: 20, radius: 4),
                    SizedBox(width: 10),
                    SkeletonBox(width: 120, height: 14, radius: 4),
                  ],
                ),
                const Divider(height: 20, color: AppColors.border),
                for (int item = 0; item < 2; item++) ...[
                  if (item > 0) const SizedBox(height: 12),
                  Row(
                    children: [
                      const SkeletonBox(width: 20, height: 20, radius: 4),
                      const SizedBox(width: 10),
                      const SkeletonBox(width: 64, height: 64, radius: 8),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: const [
                            SkeletonBox(width: double.infinity, height: 13, radius: 4),
                            SizedBox(height: 6),
                            SkeletonBox(width: 80, height: 11, radius: 4),
                            SizedBox(height: 8),
                            SkeletonBox(width: 70, height: 15, radius: 4),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ],
    );
  }
}

/// Order detail screen skeleton.
class OrderDetailSkeleton extends StatelessWidget {
  const OrderDetailSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        // Status header card
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              SkeletonBox(width: 140, height: 18, radius: 4),
              SizedBox(height: 8),
              SkeletonBox(width: double.infinity, height: 12, radius: 4),
              SizedBox(height: 16),
              SkeletonBox(width: double.infinity, height: 40, radius: 8),
            ],
          ),
        ),
        const SizedBox(height: 16),
        // Address card
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              SkeletonBox(width: 110, height: 14, radius: 4),
              SizedBox(height: 10),
              SkeletonBox(width: 150, height: 13, radius: 4),
              SizedBox(height: 6),
              SkeletonBox(width: double.infinity, height: 12, radius: 4),
            ],
          ),
        ),
        const SizedBox(height: 16),
        // Items card
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SkeletonBox(width: 120, height: 14, radius: 4),
              const Divider(height: 20, color: AppColors.border),
              for (int i = 0; i < 2; i++) ...[
                if (i > 0) const SizedBox(height: 12),
                Row(
                  children: const [
                    SkeletonBox(width: 52, height: 52, radius: 8),
                    SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          SkeletonBox(width: double.infinity, height: 13, radius: 4),
                          SizedBox(height: 6),
                          SkeletonBox(width: 70, height: 13, radius: 4),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

/// Notifications list skeleton.
class NotificationsListSkeleton extends StatelessWidget {
  final int count;
  const NotificationsListSkeleton({super.key, this.count = 5});

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: count,
      itemBuilder: (_, __) => Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: const [
            SkeletonBox.circle(size: 38),
            SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SkeletonBox(width: 140, height: 14, radius: 4),
                  SizedBox(height: 6),
                  SkeletonBox(width: double.infinity, height: 11, radius: 4),
                  SizedBox(height: 6),
                  SkeletonBox(width: 80, height: 9, radius: 4),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Seller store screen skeleton.
class StoreSkeleton extends StatelessWidget {
  const StoreSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const NeverScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      children: [
        // Store banner + profile
        Container(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 16),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(bottom: BorderSide(color: AppColors.border)),
          ),
          child: Column(
            children: [
              Row(
                children: const [
                  SkeletonBox.circle(size: 64),
                  SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SkeletonBox(width: 140, height: 16, radius: 4),
                        SizedBox(height: 8),
                        SkeletonBox(width: 100, height: 12, radius: 4),
                      ],
                    ),
                  ),
                  SkeletonBox(width: 80, height: 32, radius: 8),
                ],
              ),
              const SizedBox(height: 20),
              // Tab bar placeholder
              Row(
                children: const [
                  SkeletonBox(width: 80, height: 26, radius: 6),
                  SizedBox(width: 12),
                  SkeletonBox(width: 80, height: 26, radius: 6),
                  SizedBox(width: 12),
                  SkeletonBox(width: 80, height: 26, radius: 6),
                ],
              ),
            ],
          ),
        ),
        // Product grid
        const ProductGridSkeleton(count: 4),
      ],
    );
  }
}

/// Generic full-page skeleton for any simple screen.
class GenericPageSkeleton extends StatelessWidget {
  final int cardCount;
  final bool shrinkWrap;
  const GenericPageSkeleton({super.key, this.cardCount = 3, this.shrinkWrap = true});

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      shrinkWrap: shrinkWrap,
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: cardCount,
      itemBuilder: (_, __) => Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: const [
            SkeletonBox(width: 120, height: 16, radius: 4),
            SizedBox(height: 10),
            SkeletonBox(width: double.infinity, height: 12, radius: 4),
            SizedBox(height: 6),
            SkeletonBox(width: 180, height: 12, radius: 4),
          ],
        ),
      ),
    );
  }
}
