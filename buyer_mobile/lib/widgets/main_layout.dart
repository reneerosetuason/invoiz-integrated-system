import 'dart:ui';
import 'package:flutter/material.dart';

import '../screens/account_screen.dart';
import '../screens/cart_screen.dart';
import '../screens/chat_screen.dart';
import '../screens/home_screen.dart';
import '../screens/login_screen.dart';
import '../screens/notifications_screen.dart';
import '../screens/orders_screen.dart';
import '../screens/product_list_screen.dart';
import '../services/api_service.dart';
import '../theme.dart';
import 'auth_service_provider.dart';
import 'invoiz_logo.dart';

class MainLayout extends StatelessWidget {
  final Widget child;
  final String title;
  final bool showAppBar;
  final bool showBottomNav;
  final bool showTopActions;
  final bool showMessageIcon;
  final bool showSettingsIcon;
  final bool showNotificationIcon;
  final int currentIndex;

  const MainLayout({
    super.key,
    required this.child,
    this.title = '',
    this.showAppBar = true,
    this.showBottomNav = true,
    this.showTopActions = true,
    this.showMessageIcon = true,
    this.showSettingsIcon = true,
    this.showNotificationIcon = true,
    this.currentIndex = 0,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      backgroundColor: AppColors.background,
      appBar: showAppBar ? _buildAppBar(context) : null,
      body: child,
      bottomNavigationBar: showBottomNav
          ? _BottomNav(currentIndex: currentIndex)
          : null,
    );
  }

  PreferredSizeWidget _buildAppBar(BuildContext context) {
    return AppBar(
      titleSpacing: 14,
      elevation: 0,
      scrolledUnderElevation: 0,
      backgroundColor: Colors.transparent,
      surfaceTintColor: Colors.transparent,
      flexibleSpace: ClipRRect(
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 16, sigmaY: 16),
          child: Container(color: Colors.white.withValues(alpha: 0.7)),
        ),
      ),
      title: Row(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppColors.border, width: 0.5),
            ),
            child: InvoizLogo.logoWidget(size: 26, radius: 6),
          ),
          if (title.isNotEmpty)
            Flexible(
              child: Padding(
                padding: const EdgeInsets.only(left: 12),
                child: Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w400,
                    letterSpacing: 0.3,
                    fontFamily: 'Georgia',
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
            ),
        ],
      ),
      actions: [
        if (showTopActions && showMessageIcon)
          AppMessageBadge(
            iconColor: AppColors.textPrimary,
            background: Colors.white,
            onTap: () {
              final auth = AuthServiceProvider.of(context);
              if (!auth.isLoggedIn) {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                );
                return;
              }
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ChatScreen()),
              );
            },
          ),
        if (showNotificationIcon) const AppNotificationBell(),
        if (showTopActions && showSettingsIcon)
          _AppBarIcon(
            icon: Icons.settings_outlined,
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const AccountScreen()),
            ),
          ),
        const SizedBox(width: 8),
      ],
    );
  }
}

class _BottomNav extends StatefulWidget {
  final int currentIndex;
  const _BottomNav({required this.currentIndex});

  @override
  State<_BottomNav> createState() => _BottomNavState();
}

class _BottomNavState extends State<_BottomNav> {
  int _cartCount = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadCart());
  }

  Future<void> _loadCart() async {
    if (!mounted) return;
    final auth = AuthServiceProvider.of(context);
    if (!auth.isLoggedIn) return;
    try {
      final data = await ApiService().get('cart');
      final items = (data['cart']?['items'] as List?) ?? [];
      if (mounted) setState(() => _cartCount = items.length);
    } catch (_) {}
  }

  void _go(BuildContext context, int index) {
    if (index == widget.currentIndex) return;
    final auth = AuthServiceProvider.of(context);
    Widget target;
    switch (index) {
      case 0:
        target = const HomeScreen();
        break;
      case 1:
        target = const ProductListScreen();
        break;
      case 2:
        if (!auth.isLoggedIn) {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const LoginScreen()),
          );
          return;
        }
        target = const CartScreen();
        break;
      case 3:
        if (!auth.isLoggedIn) {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const LoginScreen()),
          );
          return;
        }
        target = const OrdersScreen();
        break;
      case 4:
        target = auth.isLoggedIn ? const AccountScreen() : const LoginScreen();
        break;
      default:
        return;
    }
    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => target),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 0, 18, 12),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.08),
                blurRadius: 24,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 6),
              color: AppColors.background,
              child: Row(
                children: [
                  Expanded(
                    child: _NavButton(
                      icon: Icons.home_outlined,
                      activeIcon: Icons.home,
                      label: 'Home',
                      selected: widget.currentIndex == 0,
                      onTap: () => _go(context, 0),
                    ),
                  ),
                  Expanded(
                    child: _NavButton(
                      icon: Icons.local_offer_outlined,
                      activeIcon: Icons.local_offer,
                      label: 'Sale',
                      selected: widget.currentIndex == 1,
                      onTap: () => _go(context, 1),
                    ),
                  ),
                  Expanded(
                    child: Stack(
                      clipBehavior: Clip.none,
                      alignment: Alignment.center,
                      children: [
                        _NavButton(
                          icon: Icons.shopping_cart_outlined,
                          activeIcon: Icons.shopping_cart,
                          label: 'Cart',
                          selected: widget.currentIndex == 2,
                          onTap: () => _go(context, 2),
                        ),
                        if (_cartCount > 0)
                          Positioned(
                            right: 8,
                            top: 2,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 5,
                                vertical: 1,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(999),
                                border: Border.all(
                                  color: AppColors.primary,
                                  width: 1,
                                ),
                              ),
                              child: Text(
                                '$_cartCount',
                                style: TextStyle(
                                  color: AppColors.primary,
                                  fontSize: 9,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: _NavButton(
                      icon: Icons.receipt_long_outlined,
                      activeIcon: Icons.receipt_long,
                      label: 'Orders',
                      selected: widget.currentIndex == 3,
                      onTap: () => _go(context, 3),
                    ),
                  ),
                  Expanded(
                    child: _NavButton(
                      icon: Icons.person_outline,
                      activeIcon: Icons.person,
                      label: 'Me',
                      selected: widget.currentIndex == 4,
                      onTap: () => _go(context, 4),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavButton extends StatelessWidget {
  final IconData icon;
  final IconData activeIcon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _NavButton({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOutCubic,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? AppColors.primary : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 200),
              child: Icon(
                selected ? activeIcon : icon,
                key: ValueKey(selected),
                size: 22,
                color: selected
                    ? const Color(0xFFFAECE7)
                    : AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: 2),
            AnimatedDefaultTextStyle(
              duration: const Duration(milliseconds: 200),
              style: TextStyle(
                fontSize: 10,
                fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
                color: selected
                    ? const Color(0xFFFAECE7)
                    : AppColors.textSecondary,
                letterSpacing: 0.1,
                fontFamily: 'Segoe UI',
              ),
              child: Text(label),
            ),
          ],
        ),
      ),
    );
  }
}

class _AppBarIcon extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _AppBarIcon({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 3),
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.border, width: 0.5),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Icon(icon, color: AppColors.textPrimary, size: 20),
      ),
    );
  }
}

/// Shared notification bell with unread badge. Used on every top bar
/// (main layout, home header, cart header, orders header) so the count
/// is always identical no matter which page the user is on.
class AppNotificationBell extends StatefulWidget {
  final Color iconColor;
  final double iconSize;
  final Color? background;
  const AppNotificationBell({
    super.key,
    this.iconColor = AppColors.textPrimary,
    this.iconSize = 22,
    this.background,
  });

  @override
  State<AppNotificationBell> createState() => _AppNotificationBellState();
}

class _AppNotificationBellState extends State<AppNotificationBell> {
  final _api = ApiService();
  int _unread = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (ApiService.token == null) return;
    try {
      final data = await _api.get('notifications');
      if (!mounted) return;
      setState(() => _unread = data['unread_count'] as int? ?? 0);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final auth = AuthServiceProvider.of(context);
    if (!auth.isLoggedIn) return const SizedBox.shrink();
    final bell = Container(
      margin: const EdgeInsets.symmetric(horizontal: 3),
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.border, width: 0.5),
      ),
      child: InkWell(
        onTap: () async {
          await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const NotificationsScreen()),
          );
          _load();
        },
        borderRadius: BorderRadius.circular(12),
        child: Icon(
          Icons.notifications_outlined,
          size: widget.iconSize,
          color: widget.iconColor,
        ),
      ),
    );
    return Stack(
      clipBehavior: Clip.none,
      alignment: Alignment.center,
      children: [
        bell,
        if (_unread > 0)
          Positioned(
            right: 0,
            top: 0,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(999),
                border: Border.all(color: AppColors.warning, width: 1.5),
              ),
              child: Text(
                '$_unread',
                style: const TextStyle(
                  color: AppColors.warning,
                  fontSize: 9,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
      ],
    );
  }
}

/// Shared message badge widget for the chat icon.
class AppMessageBadge extends StatefulWidget {
  final Color iconColor;
  final double iconSize;
  final Color? background;
  final VoidCallback onTap;
  const AppMessageBadge({
    super.key,
    this.iconColor = Colors.white,
    this.iconSize = 20,
    this.background,
    required this.onTap,
  });

  @override
  State<AppMessageBadge> createState() => _AppMessageBadgeState();
}

class _AppMessageBadgeState extends State<AppMessageBadge> {
  final _api = ApiService();
  int _unread = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (ApiService.token == null) return;
    try {
      final data = await _api.get('conversations/unread-count');
      if (!mounted) return;
      setState(() => _unread = data['unread_count'] as int? ?? 0);
    } catch (_) {}
  }

  void refresh() => _load();

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      alignment: Alignment.center,
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: widget.background ?? Colors.white.withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
          ),
          child: InkWell(
            onTap: widget.onTap,
            borderRadius: BorderRadius.circular(12),
            child: Icon(
              Icons.chat_bubble_outline_rounded,
              size: widget.iconSize,
              color: widget.iconColor,
            ),
          ),
        ),
        if (_unread > 0)
          Positioned(
            right: 0,
            top: 0,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(999),
                border: Border.all(color: AppColors.warning, width: 1.5),
              ),
              child: Text(
                '$_unread',
                style: const TextStyle(
                  color: AppColors.warning,
                  fontSize: 9,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
      ],
    );
  }
}
