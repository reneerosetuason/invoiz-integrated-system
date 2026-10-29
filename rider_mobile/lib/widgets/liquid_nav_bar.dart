import 'dart:ui';

import 'package:flutter/material.dart';

import '../core/theme/app_theme.dart';

/// Floating white-glass dock for the rider app.
///
/// Five slots (0 = Home, 1 = Deliveries, 2 = Scan action, 3 = Settings,
/// 4 = Profile). The selected tab is marked by a teal bubble that glides
/// horizontally between slots while its icon turns white; unselected icons
/// stay quiet gray on the glass. Scan is a larger permanent teal action
/// floating above the bar's center and never takes selection. Tapping Scan
/// does not change selection; the shell opens the scanner as a pushed
/// route instead.
///
/// Selection state stays with the parent ([selectedIndex]/[onTap]); this
/// widget is presentation-only.
class LiquidNavBar extends StatelessWidget {
  const LiquidNavBar({
    super.key,
    required this.selectedIndex,
    required this.onTap,
  });

  final int selectedIndex;
  final ValueChanged<int> onTap;

  static const double _barHeight = 66;
  static const double _bubbleDiameter = 52;
  static const double _scanDiameter = 60;
  static const Duration _glideDuration = Duration(milliseconds: 300);

  static const _tabs = [
    _NavSlot(Icons.home_outlined, Icons.home, 'Home', 0),
    _NavSlot(Icons.inventory_2_outlined, Icons.inventory_2, 'Deliveries', 1),
    _NavSlot(Icons.settings_outlined, Icons.settings, 'Settings', 3),
    _NavSlot(Icons.person_outline, Icons.person, 'Profile', 4),
  ];

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).padding.bottom;
    final reduceMotion = MediaQuery.of(context).disableAnimations;
    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        bottom: bottomInset > 0 ? bottomInset : 14,
      ),
      // Slim glass bar with the Scan action floating above its center.
      child: SizedBox(
        height: 88,
        child: LayoutBuilder(
          builder: (context, constraints) {
            // Five equal slots across the bar; the middle one is Scan's gap.
            final slotWidth = constraints.maxWidth / 5;
            // Bubble center glides to the selected slot; Scan (2) is an
            // action so selection never rests on it.
            final bubbleLeft = slotWidth * selectedIndex +
                (slotWidth - _bubbleDiameter) / 2;
            return Stack(
              clipBehavior: Clip.none,
              children: [
                // White glass bar.
                Positioned(
                  left: 0,
                  right: 0,
                  top: 22,
                  bottom: 0,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(28),
                    child: BackdropFilter(
                      filter: ImageFilter.blur(sigmaX: 22, sigmaY: 22),
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.45),
                          borderRadius: BorderRadius.circular(28),
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.6),
                            width: 1,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color:
                                  Colors.black.withValues(alpha: 0.10),
                              blurRadius: 24,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                // Teal selection bubble gliding between slots.
                TweenAnimationBuilder<double>(
                  tween: Tween<double>(end: bubbleLeft),
                  duration:
                      reduceMotion ? Duration.zero : _glideDuration,
                  curve: Curves.easeOutCubic,
                  builder: (context, left, child) {
                    return Positioned(
                      left: left,
                      // Centered on the tab icons (no lift).
                      bottom: (_barHeight - _bubbleDiameter) / 2,
                      width: _bubbleDiameter,
                      height: _bubbleDiameter,
                      child: child!,
                    );
                  },
                  child: Container(
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.primary,
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.7),
                        width: 1,
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.primary
                              .withValues(alpha: 0.35),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                  ),
                ),
                // Tab icons row above the glass and bubble. Five equal slots
                // so every tab center matches the bubble glide positions.
                Positioned(
                  left: 0,
                  right: 0,
                  top: 22,
                  bottom: 0,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      for (final tab in _tabs.sublist(0, 2))
                        Expanded(
                          child: _DockButton(
                            slot: tab,
                            selected: tab.index == selectedIndex,
                            reduceMotion: reduceMotion,
                            onTap: () => onTap(tab.index),
                          ),
                        ),
                      // Gap reserving the floating Scan button's slot.
                      const Spacer(),
                      for (final tab in _tabs.sublist(2))
                        Expanded(
                          child: _DockButton(
                            slot: tab,
                            selected: tab.index == selectedIndex,
                            reduceMotion: reduceMotion,
                            onTap: () => onTap(tab.index),
                          ),
                        ),
                    ],
                  ),
                ),
                // Floating Scan action above the bar's center.
                Positioned(
                  left: 0,
                  right: 0,
                  top: 0,
                  child: Center(
                    child: Semantics(
                      label: 'Scan',
                      button: true,
                      selected: false,
                      child: Tooltip(
                        message: 'Scan',
                        child: InkWell(
                          onTap: () => onTap(2),
                          customBorder: const CircleBorder(),
                          splashColor: AppColors.primary
                              .withValues(alpha: 0.12),
                          highlightColor: AppColors.primary
                              .withValues(alpha: 0.08),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Container(
                                width: _scanDiameter,
                                height: _scanDiameter,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: AppColors.primary,
                                  border: Border.all(
                                    color: Colors.white
                                        .withValues(alpha: 0.85),
                                    width: 3,
                                  ),
                                  boxShadow: [
                                    BoxShadow(
                                      color: AppColors.primary
                                          .withValues(alpha: 0.45),
                                      blurRadius: 16,
                                      offset: const Offset(0, 6),
                                    ),
                                  ],
                                ),
                                child: const Icon(
                                  Icons.qr_code_scanner,
                                  color: Colors.white,
                                  size: 28,
                                ),
                              ),
                              const ExcludeSemantics(
                                child: Text(
                                  'Scan',
                                  style: TextStyle(
                                    color: AppColors.primary,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _NavSlot {
  const _NavSlot(this.icon, this.activeIcon, this.label, this.index);

  final IconData icon;
  final IconData activeIcon;
  final String label;
  final int index;
}

class _DockButton extends StatelessWidget {
  const _DockButton({
    required this.slot,
    required this.selected,
    required this.reduceMotion,
    required this.onTap,
  });

  final _NavSlot slot;
  final bool selected;
  final bool reduceMotion;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    // Selected tab icon rides the teal bubble in white; the rest stay
    // quiet gray on the glass.
    final Color iconColor =
        selected ? Colors.white : AppColors.textSecondary;
    return Semantics(
      label: slot.label,
      button: true,
      selected: selected,
      child: Tooltip(
        message: slot.label,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          splashColor: AppColors.primary.withValues(alpha: 0.10),
          highlightColor: AppColors.primary.withValues(alpha: 0.06),
          child: Padding(
            padding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            child: AnimatedScale(
              scale: selected ? 1.12 : 1.0,
              duration: reduceMotion
                  ? Duration.zero
                  : const Duration(milliseconds: 200),
              curve: Curves.easeOut,
              child: Icon(
                selected ? slot.activeIcon : slot.icon,
                color: iconColor,
                size: 24,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
