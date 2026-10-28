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
import '../services/buyer_updates.dart';
import '../theme.dart';
import 'auth_service_provider.dart';
import 'invoiz_logo.dart';

class MainLayout extends StatelessWidget {
  final Widget child;
  final String title;
  final bool showAppBar,
      showBottomNav,
      showTopActions,
      showMessageIcon,
      showSettingsIcon,
      showNotificationIcon;
  static const destinationCount = 5;
  final int currentIndex;
  final ApiService? api;
  final List<Widget> actions;
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
    this.api,
    this.actions = const [],
  });
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.background,
    extendBody: false,
    appBar: showAppBar
        ? AppBar(
            titleSpacing: 12,
            title: Row(
              children: [
                InvoizLogo.logoWidget(size: 30, radius: 8),
                const SizedBox(width: 10),
                Flexible(
                  child: Text(
                    title.isEmpty ? 'INVOIZ' : title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            actions: [
              ...actions,
              if (showTopActions && showMessageIcon)
                AppMessageBadge(
                  api: api,
                  iconColor: AppColors.primaryDark,
                  background: AppColors.surfaceTint,
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => AuthServiceProvider.of(context).isLoggedIn
                          ? ChatScreen(api: api)
                          : const LoginScreen(),
                    ),
                  ),
                ),
              if (showNotificationIcon)
                AppNotificationBell(
                  api: api,
                  background: AppColors.surfaceTint,
                ),
              if (showTopActions && showSettingsIcon)
                IconButton(
                  tooltip: 'Account',
                  icon: const Icon(Icons.person_outline),
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => AuthServiceProvider.of(context).isLoggedIn
                          ? const AccountScreen()
                          : const LoginScreen(),
                    ),
                  ),
                ),
              const SizedBox(width: 8),
            ],
          )
        : null,
    body: Align(
      alignment: Alignment.topCenter,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: showBottomNav ? 1200 : 760),
        child: SizedBox(width: double.infinity, child: child),
      ),
    ),
    bottomNavigationBar:
        showBottomNav && currentIndex >= 0 && currentIndex < destinationCount
        ? _BottomNav(currentIndex: currentIndex)
        : null,
  );
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
    BuyerUpdates.cart.addListener(_loadCart);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _loadCart();
  }

  @override
  void dispose() {
    BuyerUpdates.cart.removeListener(_loadCart);
    super.dispose();
  }

  Future<void> _loadCart() async {
    if (!mounted) return;
    final auth = AuthServiceProvider.of(context);
    if (!auth.isLoggedIn) {
      if (_cartCount != 0) setState(() => _cartCount = 0);
      return;
    }
    final token = ApiService.token;
    try {
      final data = await ApiService().get('cart');
      final items = data['cart']?['items'] as List? ?? [];
      if (mounted && token == ApiService.token)
        setState(
          () => _cartCount = items.fold<int>(
            0,
            (sum, item) => sum + ((item['quantity'] as num?)?.toInt() ?? 0),
          ),
        );
    } catch (_) {}
  }

  void _go(int index) {
    if (index == widget.currentIndex) return;
    final signedIn = AuthServiceProvider.of(context).isLoggedIn;
    if (!signedIn && [2, 3, 4].contains(index)) {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
      return;
    }
    final Widget target = switch (index) {
      0 => const HomeScreen(),
      1 => const ProductListScreen(showAll: true),
      2 => const CartScreen(),
      3 => const OrdersScreen(),
      _ => const AccountScreen(),
    };
    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => target),
      (_) => false,
    );
  }

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: const BoxDecoration(
      border: Border(top: BorderSide(color: AppColors.border)),
    ),
    child: NavigationBar(
      selectedIndex: widget.currentIndex,
      onDestinationSelected: _go,
      destinations: [
        const NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home),
          label: 'Home',
        ),
        const NavigationDestination(
          icon: Icon(Icons.grid_view_outlined),
          selectedIcon: Icon(Icons.grid_view_rounded),
          label: 'Shop',
        ),
        NavigationDestination(
          icon: Badge(
            isLabelVisible: _cartCount > 0,
            label: Text(_cartCount > 99 ? '99+' : '$_cartCount'),
            child: const Icon(Icons.shopping_bag_outlined),
          ),
          selectedIcon: const Icon(Icons.shopping_bag),
          label: 'Cart',
        ),
        const NavigationDestination(
          icon: Icon(Icons.receipt_long_outlined),
          selectedIcon: Icon(Icons.receipt_long),
          label: 'Orders',
        ),
        const NavigationDestination(
          icon: Icon(Icons.person_outline),
          selectedIcon: Icon(Icons.person),
          label: 'Account',
        ),
      ],
    ),
  );
}

/// Shared notification bell with unread badge. Used on every top bar
/// (main layout, home header, cart header, orders header) so the count
/// is always identical no matter which page the user is on.
class AppNotificationBell extends StatefulWidget {
  final Color iconColor;
  final double iconSize;
  final Color? background;
  final ApiService? api;
  const AppNotificationBell({
    super.key,
    this.iconColor = AppColors.textPrimary,
    this.iconSize = 22,
    this.background,
    this.api,
  });

  @override
  State<AppNotificationBell> createState() => _AppNotificationBellState();
}

class _AppNotificationBellState extends State<AppNotificationBell> {
  late final ApiService _api;
  int _unread = 0;

  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiService();
    BuyerUpdates.inbox.addListener(_load);
    _load();
  }

  @override
  void dispose() {
    BuyerUpdates.inbox.removeListener(_load);
    super.dispose();
  }

  Future<void> _load() async {
    if (!mounted) return;
    final token = ApiService.token;
    if (token == null) {
      if (_unread != 0) setState(() => _unread = 0);
      return;
    }
    try {
      final data = await _api.get('notifications');
      if (!mounted || token != ApiService.token) return;
      setState(() => _unread = data['unread_count'] as int? ?? 0);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final auth = AuthServiceProvider.of(context);
    if (!auth.isLoggedIn) return const SizedBox.shrink();
    final bell = Container(
      margin: const EdgeInsets.symmetric(horizontal: 3),
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.border, width: 0.5),
      ),
      child: InkWell(
        onTap: () async {
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => NotificationsScreen(api: widget.api),
            ),
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
                color: AppColors.primaryDark,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                _unread > 99 ? '99+' : '$_unread',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
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
  final ApiService? api;
  final Future<void> Function() onTap;
  const AppMessageBadge({
    super.key,
    this.iconColor = Colors.white,
    this.iconSize = 20,
    this.background,
    this.api,
    required this.onTap,
  });

  @override
  State<AppMessageBadge> createState() => _AppMessageBadgeState();
}

class _AppMessageBadgeState extends State<AppMessageBadge> {
  late final ApiService _api;
  int _unread = 0;

  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiService();
    BuyerUpdates.inbox.addListener(_load);
    _load();
  }

  @override
  void dispose() {
    BuyerUpdates.inbox.removeListener(_load);
    super.dispose();
  }

  Future<void> _load() async {
    if (!mounted) return;
    final token = ApiService.token;
    if (token == null) {
      if (_unread != 0) setState(() => _unread = 0);
      return;
    }
    try {
      final data = await _api.get('conversations/unread-count');
      if (!mounted || token != ApiService.token) return;
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
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: widget.background ?? Colors.white.withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
          ),
          child: InkWell(
            onTap: () async {
              await widget.onTap();
              if (mounted) _load();
            },
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
                color: AppColors.primaryDark,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                _unread > 99 ? '99+' : '$_unread',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
      ],
    );
  }
}
