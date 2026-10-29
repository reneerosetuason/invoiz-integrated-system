import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../core/theme/app_theme.dart';

/// Shared INVOIZ Rider building blocks: section eyebrows, icon tiles,
/// avatars, list rows, stat tiles, empty states, and skeleton loaders.
///
/// Every screen composes from these so spacing, typography, and surfaces
/// stay consistent without restyling Material defaults per screen.

// ── Section eyebrow ────────────────────────────────────────────────

/// Small-caps label that opens a screen section, with an optional action.
class SectionEyebrow extends StatelessWidget {
  const SectionEyebrow({super.key, required this.text, this.action});

  final String text;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: AppGaps.sm),
      child: Row(
        children: [
          Expanded(child: Text(text.toUpperCase(), style: AppText.eyebrow)),
          if (action case final Widget actionWidget) actionWidget,
        ],
      ),
    );
  }
}

// ── Icon tile ──────────────────────────────────────────────────────

/// Rounded brand-tinted tile for leading icons.
class IconTile extends StatelessWidget {
  const IconTile({
    super.key,
    required this.icon,
    this.size = 44,
    this.iconSize = 22,
    this.color = AppTheme.primary,
  });

  final IconData icon;
  final double size;
  final double iconSize;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.sm),
      ),
      child: Icon(icon, color: color, size: iconSize),
    );
  }
}

// ── Brand badge ────────────────────────────────────────────────────

/// INVOIZ brand badge from the reference system: amber ring, deeper inner
/// edge, white medallion holding the INVOIZ logo asset. Used on splash and
/// login so branding reads identically in both places.
class BrandBadge extends StatelessWidget {
  const BrandBadge({super.key, this.size = 120, this.elevation = true});

  final double size;
  final bool elevation;

  @override
  Widget build(BuildContext context) {
    final ring = size * 0.11;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: const LinearGradient(
          colors: [AppColors.gold, AppColors.secondary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        border: Border.all(
          color: const Color(0xFFB87700).withValues(alpha: 0.55),
          width: math.max(2, size * 0.018),
        ),
        boxShadow: elevation
            ? [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.28),
                  blurRadius: size * 0.22,
                  offset: Offset(0, size * 0.07),
                ),
              ]
            : null,
      ),
      padding: EdgeInsets.all(ring),
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
        ),
        padding: EdgeInsets.all(size * 0.13),
        child: ClipOval(
          child: Image.asset('images/logo.png', fit: BoxFit.contain),
        ),
      ),
    );
  }
}

// ── Avatar ─────────────────────────────────────────────────────────

/// Initials avatar with an optional online-status dot.
class AvatarView extends StatelessWidget {
  const AvatarView({
    super.key,
    required this.label,
    this.radius = 22,
    this.showStatus,
    this.isOnline = false,
  });

  final String label;
  final double radius;
  final bool? showStatus;
  final bool isOnline;

  @override
  Widget build(BuildContext context) {
    final initial = label.trim().isNotEmpty
        ? label.trim().characters.first.toUpperCase()
        : '?';
    return Stack(
      clipBehavior: Clip.none,
      children: [
        CircleAvatar(
          radius: radius,
          backgroundColor: AppTheme.primary.withValues(alpha: 0.12),
          child: Text(
            initial,
            style: TextStyle(
              fontSize: radius * 0.72,
              fontWeight: FontWeight.w800,
              color: AppTheme.primary,
            ),
          ),
        ),
        if (showStatus == true)
          Positioned(
            right: -1,
            bottom: -1,
            child: Container(
              width: 13,
              height: 13,
              decoration: BoxDecoration(
                color: isOnline ? AppTheme.success : AppColors.textSecondary,
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.card, width: 2.5),
              ),
            ),
          ),
      ],
    );
  }
}

// ── Info row ───────────────────────────────────────────────────────

/// Icon + title + description + trailing chevron/value row for grouped
/// settings, profile, and detail lists.
class InfoTile extends StatelessWidget {
  const InfoTile({
    super.key,
    required this.icon,
    required this.title,
    this.subtitle,
    this.trailing,
    this.onTap,
    this.iconColor = AppTheme.primary,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final Color iconColor;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
        child: Row(
          children: [
            IconTile(icon: icon, size: 40, iconSize: 20, color: iconColor),
            const SizedBox(width: AppGaps.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: AppText.cardTitle.copyWith(fontSize: 14)),
                  if (subtitle != null && subtitle!.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(subtitle!, style: AppText.secondary.copyWith(fontSize: 12)),
                  ],
                ],
              ),
            ),
            trailing ??
                (onTap != null
                    ? const Icon(Icons.chevron_right, color: AppColors.textSecondary)
                    : const SizedBox.shrink()),
          ],
        ),
      ),
    );
  }
}

// ── Stat tile ──────────────────────────────────────────────────────

/// Big number + small label tile for KPI groups.
class StatTile extends StatelessWidget {
  const StatTile({
    super.key,
    required this.value,
    required this.label,
    this.color,
    this.onTap,
  });

  final String value;
  final String label;
  final Color? color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final content = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(value, style: AppText.kpi(26).copyWith(color: color)),
        const SizedBox(height: 2),
        Text(label, style: AppText.caption),
      ],
    );
    if (onTap == null) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: content,
      );
    }
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: content,
      ),
    );
  }
}

// ── Empty state ────────────────────────────────────────────────────

/// Branded empty state: tinted icon medallion, title, explanation, action.
class EmptyView extends StatelessWidget {
  const EmptyView({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String? message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 48),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 76,
              height: 76,
              decoration: BoxDecoration(
                color: AppTheme.primary.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(24),
              ),
              child: Icon(icon, size: 34, color: AppTheme.primary),
            ),
            const SizedBox(height: AppGaps.lg),
            Text(title, textAlign: TextAlign.center, style: AppText.section.copyWith(fontSize: 17)),
            if (message != null) ...[
              const SizedBox(height: AppGaps.sm),
              Text(message!,
                  textAlign: TextAlign.center, style: AppText.secondary),
            ],
            if (action != null) ...[
              const SizedBox(height: AppGaps.lg),
              action!,
            ],
          ],
        ),
      ),
    );
  }
}

// ── Skeleton loaders ───────────────────────────────────────────────

/// Content-shaped shimmer placeholders (static when reduced motion set).
class SkeletonBox extends StatelessWidget {
  const SkeletonBox({super.key, this.height = 72, this.radius = AppRadius.md});

  final double height;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    if (reduce) {
      return Container(
        height: height,
        decoration: BoxDecoration(
          color: AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(radius),
        ),
      );
    }
    return _Pulse(
      child: Container(
        height: height,
        decoration: BoxDecoration(
          color: AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(radius),
        ),
      ),
    );
  }
}

/// Skeleton shaped like a delivery card (status row + route rows + total).
class DeliveryCardSkeleton extends StatelessWidget {
  const DeliveryCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppGaps.lg),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.border),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SkeletonBox(height: 14),
          SizedBox(height: AppGaps.sm),
          SkeletonBox(height: 22),
          SizedBox(height: AppGaps.md),
          SkeletonBox(height: 14),
          SizedBox(height: AppGaps.sm),
          SkeletonBox(height: 14),
        ],
      ),
    );
  }
}

/// Skeleton shaped like a conversation row (avatar + lines).
class ConversationSkeleton extends StatelessWidget {
  const ConversationSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SkeletonBox(height: 44),
        SizedBox(width: AppGaps.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SkeletonBox(height: 14),
              SizedBox(height: AppGaps.sm),
              SkeletonBox(height: 12),
            ],
          ),
        ),
      ],
    );
  }
}

class _Pulse extends StatefulWidget {
  const _Pulse({required this.child});

  final Widget child;

  @override
  State<_Pulse> createState() => _PulseState();
}

class _PulseState extends State<_Pulse> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _opacity;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..repeat(reverse: true);
    _opacity = Tween<double>(begin: 0.55, end: 1).animate(
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
    return FadeTransition(opacity: _opacity, child: widget.child);
  }
}
