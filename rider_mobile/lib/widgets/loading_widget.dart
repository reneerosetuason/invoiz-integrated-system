import 'package:flutter/material.dart';

import '../core/theme/app_theme.dart';
import 'app_ui.dart';

/// Skeleton loading page variant for different screen types.
enum LoadingVariant {
  auto,
  dashboard,
  deliveries,
  earnings,
  profile,
  detail,
  list,
}

/// Beautiful full-page skeleton loader that replaces generic spinners.
class LoadingWidget extends StatelessWidget {
  const LoadingWidget({
    super.key,
    this.label,
    this.variant = LoadingVariant.auto,
  });

  final String? label;
  final LoadingVariant variant;

  LoadingVariant _resolveVariant() {
    if (variant != LoadingVariant.auto) return variant;
    final l = (label ?? '').toLowerCase();
    if (l.contains('dashboard')) return LoadingVariant.dashboard;
    if (l.contains('deliveries') || l.contains('pickup')) return LoadingVariant.deliveries;
    if (l.contains('delivery')) return LoadingVariant.detail;
    if (l.contains('earning') || l.contains('remit') || l.contains('history')) {
      return LoadingVariant.earnings;
    }
    if (l.contains('profile') || l.contains('document')) return LoadingVariant.profile;
    if (l.contains('notif') || l.contains('conversation') || l.contains('message')) {
      return LoadingVariant.list;
    }
    return LoadingVariant.auto;
  }

  @override
  Widget build(BuildContext context) {
    final v = _resolveVariant();
    Widget content;
    switch (v) {
      case LoadingVariant.dashboard:
        content = _buildDashboardSkeleton();
        break;
      case LoadingVariant.deliveries:
        content = _buildDeliveriesSkeleton();
        break;
      case LoadingVariant.earnings:
        content = _buildEarningsSkeleton();
        break;
      case LoadingVariant.profile:
        content = _buildProfileSkeleton();
        break;
      case LoadingVariant.detail:
        content = _buildDetailSkeleton();
        break;
      case LoadingVariant.list:
        content = _buildListSkeleton();
        break;
      case LoadingVariant.auto:
      default:
        content = _buildGeneralSkeleton();
        break;
    }

    return SingleChildScrollView(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (label != null && label!.trim().isNotEmpty) ...[
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    margin: const EdgeInsets.only(right: 8),
                    decoration: const BoxDecoration(
                      color: AppColors.primary,
                      shape: BoxShape.circle,
                    ),
                  ),
                  Text(
                    label!,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
          ],
          content,
        ],
      ),
    );
  }

  Widget _buildDashboardSkeleton() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Header with greeting and status toggle pill
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: const [
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SkeletonBox(height: 12, radius: 4),
                SizedBox(height: 6),
                SkeletonBox(height: 22, radius: 6),
              ],
            ),
            SkeletonBox(height: 38, radius: AppRadius.pill),
          ],
        ),
        const SizedBox(height: 16),
        // Active duty card skeleton
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  SkeletonBox(height: 14, radius: 4),
                  SkeletonBox(height: 20, radius: 10),
                ],
              ),
              SizedBox(height: 12),
              SkeletonBox(height: 16, radius: 4),
              SizedBox(height: 8),
              SkeletonBox(height: 12, radius: 4),
            ],
          ),
        ),
        const SizedBox(height: 16),
        // Stats 2x2 grid
        Row(
          children: const [
            Expanded(child: SkeletonBox(height: 84, radius: AppRadius.md)),
            SizedBox(width: 12),
            Expanded(child: SkeletonBox(height: 84, radius: AppRadius.md)),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: const [
            Expanded(child: SkeletonBox(height: 84, radius: AppRadius.md)),
            SizedBox(width: 12),
            Expanded(child: SkeletonBox(height: 84, radius: AppRadius.md)),
          ],
        ),
        const SizedBox(height: 20),
        // Deliveries section header
        const SkeletonBox(height: 16, radius: 4),
        const SizedBox(height: 12),
        const DeliveryCardSkeleton(),
      ],
    );
  }

  Widget _buildDeliveriesSkeleton() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Filter tabs row
        Row(
          children: const [
            SkeletonBox(height: 32, radius: AppRadius.pill),
            SizedBox(width: 8),
            SkeletonBox(height: 32, radius: AppRadius.pill),
            SizedBox(width: 8),
            SkeletonBox(height: 32, radius: AppRadius.pill),
          ],
        ),
        const SizedBox(height: 16),
        const DeliveryCardSkeleton(),
        const SizedBox(height: 12),
        const DeliveryCardSkeleton(),
        const SizedBox(height: 12),
        const DeliveryCardSkeleton(),
      ],
    );
  }

  Widget _buildEarningsSkeleton() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Total balance / wallet card
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              SkeletonBox(height: 13, radius: 4),
              SizedBox(height: 10),
              SkeletonBox(height: 32, radius: 8),
              SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  SkeletonBox(height: 14, radius: 4),
                  SkeletonBox(height: 14, radius: 4),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        // 2 stat tiles
        Row(
          children: const [
            Expanded(child: SkeletonBox(height: 76, radius: AppRadius.md)),
            SizedBox(width: 12),
            Expanded(child: SkeletonBox(height: 76, radius: AppRadius.md)),
          ],
        ),
        const SizedBox(height: 20),
        const SkeletonBox(height: 16, radius: 4),
        const SizedBox(height: 12),
        for (int i = 0; i < 4; i++) ...[
          Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(AppRadius.md),
              border: Border.all(color: AppColors.border),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: const [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SkeletonBox(height: 14, radius: 4),
                    SizedBox(height: 6),
                    SkeletonBox(height: 11, radius: 4),
                  ],
                ),
                SkeletonBox(height: 16, radius: 4),
              ],
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildProfileSkeleton() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        const SizedBox(height: 12),
        const SkeletonBox(height: 84, radius: 42),
        const SizedBox(height: 14),
        const Center(child: SkeletonBox(height: 20, radius: 6)),
        const SizedBox(height: 6),
        const Center(child: SkeletonBox(height: 13, radius: 4)),
        const SizedBox(height: 10),
        const Center(child: SkeletonBox(height: 22, radius: AppRadius.pill)),
        const SizedBox(height: 24),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            children: const [
              SkeletonBox(height: 20, radius: 4),
              SizedBox(height: 14),
              SkeletonBox(height: 20, radius: 4),
              SizedBox(height: 14),
              SkeletonBox(height: 20, radius: 4),
              SizedBox(height: 14),
              SkeletonBox(height: 20, radius: 4),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildDetailSkeleton() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Tracking banner
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  SkeletonBox(height: 14, radius: 4),
                  SkeletonBox(height: 22, radius: AppRadius.pill),
                ],
              ),
              SizedBox(height: 10),
              SkeletonBox(height: 22, radius: 6),
            ],
          ),
        ),
        const SizedBox(height: 14),
        // Customer / Route Card
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              SkeletonBox(height: 14, radius: 4),
              SizedBox(height: 12),
              SkeletonBox(height: 16, radius: 4),
              SizedBox(height: 6),
              SkeletonBox(height: 13, radius: 4),
              SizedBox(height: 12),
              SkeletonBox(height: 38, radius: AppRadius.sm),
            ],
          ),
        ),
        const SizedBox(height: 14),
        // Package Card
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              SkeletonBox(height: 14, radius: 4),
              SizedBox(height: 12),
              SkeletonBox(height: 13, radius: 4),
              SizedBox(height: 6),
              SkeletonBox(height: 13, radius: 4),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildListSkeleton() {
    return Column(
      children: const [
        ConversationSkeleton(),
        SizedBox(height: 14),
        ConversationSkeleton(),
        SizedBox(height: 14),
        ConversationSkeleton(),
        SizedBox(height: 14),
        ConversationSkeleton(),
        SizedBox(height: 14),
        ConversationSkeleton(),
      ],
    );
  }

  Widget _buildGeneralSkeleton() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              SkeletonBox(height: 16, radius: 4),
              SizedBox(height: 10),
              SkeletonBox(height: 13, radius: 4),
              SizedBox(height: 6),
              SkeletonBox(height: 13, radius: 4),
            ],
          ),
        ),
        const SizedBox(height: 14),
        const DeliveryCardSkeleton(),
        const SizedBox(height: 14),
        const DeliveryCardSkeleton(),
      ],
    );
  }
}
