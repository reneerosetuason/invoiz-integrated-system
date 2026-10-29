import 'buyer_controls.dart';
import 'buyer_scaffold.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import '../screens/account_screen.dart';
import '../screens/cart_screen.dart';
import '../screens/chat_screen.dart';
import '../screens/home_screen.dart';
import '../screens/login_screen.dart';
import '../screens/notifications_screen.dart';
import '../screens/orders_screen.dart';
import '../screens/product_list_screen.dart';
import '../screens/wishlist_screen.dart';
import '../services/api_service.dart';
import '../services/buyer_updates.dart';
import '../theme.dart';
import 'auth_service_provider.dart';
import 'invoiz_logo.dart';

class MainLayout extends StatelessWidget {
  final Widget child;
  final Widget? headerSearch;
  final String title;
  final bool showAppBar,
      showBottomNav,
      showTopActions,
      showMessageIcon,
      showSettingsIcon,
      showNotificationIcon,
      showWishlistIcon;
  static const destinationCount = 5;
  final int currentIndex;
  final ApiService? api;
  final List<Widget> actions;
  const MainLayout({
    super.key,
    required this.child,
    this.headerSearch,
    this.title = '',
    this.showAppBar = true,
    this.showBottomNav = true,
    this.showTopActions = true,
    this.showMessageIcon = true,
    this.showSettingsIcon = true,
    this.showNotificationIcon = true,
    this.showWishlistIcon = true,
    this.currentIndex = 0,
    this.api,
    this.actions = const [],
  });
  @override
  Widget build(BuildContext context) {
    final headerActions = <Widget>[
      ...actions,
      if (showTopActions && showMessageIcon)
        AppMessageBadge(
          api: api,
          iconSize: 23,
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
          iconSize: 23,
          background: AppColors.surfaceTint,
        ),
      if (showWishlistIcon)
        AppWishlistBadge(
          api: api,
          iconSize: 23,
          background: AppColors.surfaceTint,
        ),
      if (showTopActions && showSettingsIcon)
        BuyerButton.icon(
          tooltip: 'Account',
          icon: const Icon(CupertinoIcons.person, size: 23),
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
    ];
    final stackActions =
        headerSearch == null &&
        MediaQuery.sizeOf(context).width < 380 &&
        headerActions.length > 4;
    return BuyerScaffold(
      title: title.isEmpty ? 'INVOIZ' : title,
      showNavigationBar: showAppBar,
      navigationContent: headerSearch == null
          ? null
          : Padding(
              key: const ValueKey('catalog-navigation'),
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Row(
                children: [
                  InvoizLogo.logoWidget(size: 32, radius: 9),
                  const SizedBox(width: 8),
                  Expanded(child: headerSearch!),
                  const SizedBox(width: 4),
                  ...headerActions.take(headerActions.length - 1),
                ],
              ),
            ),
      leading:
          showBottomNav && currentIndex >= 0 && currentIndex < destinationCount
          ? InvoizLogo.logoWidget(size: 32, radius: 9)
          : null,
      trailing: stackActions
          ? null
          : Row(mainAxisSize: MainAxisSize.min, children: headerActions),
      header: stackActions
          ? Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: headerActions,
            )
          : null,
      child: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: showBottomNav ? 1200 : 760),
          child: SizedBox(width: double.infinity, child: child),
        ),
      ),
      bottomBar:
          showBottomNav && currentIndex >= 0 && currentIndex < destinationCount
          ? Offstage(
              offstage: MediaQuery.viewInsetsOf(context).bottom > 0,
              child: _BottomNav(currentIndex: currentIndex),
            )
          : null,
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
  Widget build(BuildContext context) => SafeArea(
    top: false,
    minimum: const EdgeInsets.fromLTRB(16, 8, 16, 12),
    child: Align(
      heightFactor: 1,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 540),
        child: DecoratedBox(
          key: const ValueKey('floating-tab-pill'),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            boxShadow: [
              BoxShadow(
                color: AppColors.primaryDark.withValues(alpha: .16),
                blurRadius: 28,
                offset: const Offset(0, 8),
              ),
            ],
            border: Border.all(
              color: AppColors.border.withValues(alpha: .8),
              width: .5,
            ),
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: MediaQuery.withNoTextScaling(
              child: CupertinoTabBar(
                currentIndex: widget.currentIndex,
                onTap: _go,
                activeColor: AppColors.primaryDark,
                inactiveColor: AppColors.textMuted,
                backgroundColor: AppColors.paper.withValues(alpha: .96),
                border: null,
                height: 62,
                iconSize: 25,
                items: [
                  const BottomNavigationBarItem(
                    icon: Icon(CupertinoIcons.house),
                    activeIcon: Icon(CupertinoIcons.house_fill),
                    label: 'Home',
                  ),
                  const BottomNavigationBarItem(
                    icon: Icon(CupertinoIcons.square_grid_2x2),
                    activeIcon: Icon(CupertinoIcons.square_grid_2x2_fill),
                    label: 'Shop',
                  ),
                  BottomNavigationBarItem(
                    icon: Badge(
                      isLabelVisible: _cartCount > 0,
                      label: Text(_cartCount > 99 ? '99+' : '$_cartCount'),
                      child: const Icon(CupertinoIcons.bag),
                    ),
                    activeIcon: Badge(
                      isLabelVisible: _cartCount > 0,
                      label: Text(_cartCount > 99 ? '99+' : '$_cartCount'),
                      child: const Icon(CupertinoIcons.bag_fill),
                    ),
                    label: 'Cart',
                  ),
                  const BottomNavigationBarItem(
                    icon: Icon(CupertinoIcons.doc_text),
                    activeIcon: Icon(CupertinoIcons.doc_text_fill),
                    label: 'Orders',
                  ),
                  const BottomNavigationBarItem(
                    icon: Icon(CupertinoIcons.person),
                    activeIcon: Icon(CupertinoIcons.person_fill),
                    label: 'Account',
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
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
    final bell = BuyerButton.icon(
      tooltip: 'Notifications',
      onPressed: () async {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => NotificationsScreen(api: widget.api),
          ),
        );
        _load();
      },
      icon: Icon(
        CupertinoIcons.bell,
        size: widget.iconSize,
        color: widget.iconColor,
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
        BuyerButton.icon(
          tooltip: 'Messages',
          onPressed: () async {
            await widget.onTap();
            if (mounted) _load();
          },
          icon: Icon(
            CupertinoIcons.chat_bubble,
            size: widget.iconSize,
            color: widget.iconColor,
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

/// Header heart with the number of saved products. Mirrors the wishlist
/// counter shown in the website sidebar and refreshes whenever a product is
/// saved or removed anywhere in the app.
class AppWishlistBadge extends StatefulWidget {
  final Color iconColor;
  final double iconSize;
  final Color? background;
  final ApiService? api;
  const AppWishlistBadge({
    super.key,
    this.iconColor = AppColors.primaryDark,
    this.iconSize = 22,
    this.background,
    this.api,
  });

  @override
  State<AppWishlistBadge> createState() => _AppWishlistBadgeState();
}

class _AppWishlistBadgeState extends State<AppWishlistBadge> {
  late final ApiService _api;
  int _count = 0;

  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiService();
    BuyerUpdates.wishlist.addListener(_load);
    _load();
  }

  @override
  void dispose() {
    BuyerUpdates.wishlist.removeListener(_load);
    super.dispose();
  }

  Future<void> _load() async {
    if (!mounted) return;
    final token = ApiService.token;
    if (token == null) {
      if (_count != 0) setState(() => _count = 0);
      return;
    }
    try {
      final data = await _api.get('favorites');
      if (!mounted || token != ApiService.token) return;
      setState(() => _count = (data['favorites'] as List?)?.length ?? 0);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    if (!AuthServiceProvider.of(context).isLoggedIn) {
      return const SizedBox.shrink();
    }
    return Stack(
      clipBehavior: Clip.none,
      alignment: Alignment.center,
      children: [
        BuyerButton.icon(
          tooltip: 'Wishlist',
          onPressed: () async {
            await Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => WishlistScreen(api: widget.api),
              ),
            );
            _load();
          },
          icon: Icon(
            CupertinoIcons.heart,
            size: widget.iconSize,
            color: widget.iconColor,
          ),
        ),
        if (_count > 0)
          Positioned(
            right: 0,
            top: 0,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
              decoration: BoxDecoration(
                color: AppColors.danger,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                _count > 99 ? '99+' : '$_count',
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
