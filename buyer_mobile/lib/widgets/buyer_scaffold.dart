import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart' show Material, MaterialType, Scaffold;
import '../theme.dart';

/// Cupertino owns page geometry. Material stays underneath for the existing
/// ScaffoldMessenger, dropdown overlays and Form-based workflows.
class BuyerScaffold extends StatelessWidget {
  final String title;
  final Widget child;
  final Widget? leading, trailing, titleWidget, header, bottomBar;
  final bool showNavigationBar;
  final Color backgroundColor;
  const BuyerScaffold({
    super.key,
    required this.child,
    this.title = '',
    this.leading,
    this.trailing,
    this.titleWidget,
    this.header,
    this.bottomBar,
    this.showNavigationBar = true,
    this.backgroundColor = AppColors.background,
  });
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: backgroundColor,
    resizeToAvoidBottomInset: false,
    body: CupertinoPageScaffold(
      backgroundColor: backgroundColor,
      navigationBar: showNavigationBar
          ? _BuyerNavigationBar(
              child: CupertinoNavigationBar(
                backgroundColor: AppColors.paper,
                automaticBackgroundVisibility: false,
                transitionBetweenRoutes: false,
                border: null,
                leading: leading,
                middle:
                    titleWidget ??
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                        color: AppColors.primaryDark,
                      ),
                    ),
                trailing: trailing,
              ),
            )
          : null,
      child: SafeArea(
        bottom: bottomBar == null,
        child: Column(
          children: [
            if (header != null) header!,
            Expanded(
              child: MediaQuery.removePadding(
                context: context,
                removeBottom: bottomBar != null,
                child: Material(type: MaterialType.transparency, child: child),
              ),
            ),
            if (bottomBar != null) bottomBar!,
          ],
        ),
      ),
    ),
  );
}

class _BuyerNavigationBar extends StatelessWidget
    implements ObstructingPreferredSizeWidget {
  final Widget child;
  const _BuyerNavigationBar({required this.child});
  @override
  Size get preferredSize => const Size.fromHeight(60);
  @override
  bool shouldFullyObstruct(BuildContext context) => true;
  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: const BoxDecoration(
      color: AppColors.paper,
      border: Border(bottom: BorderSide(color: AppColors.border, width: .3)),
    ),
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: child,
    ),
  );
}
