import '../widgets/buyer_controls.dart';
import '../widgets/buyer_search.dart';
import 'package:flutter/cupertino.dart';
import '../widgets/product_photo.dart';
import '../services/buyer_updates.dart';
import 'package:flutter/material.dart';
import '../models/cart.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import '../widgets/main_layout.dart';
import '../widgets/buyer_page.dart';
import '../widgets/shopping_ui.dart';
import 'checkout_screen.dart';
import 'home_screen.dart';
import 'login_screen.dart';
import 'product_detail_screen.dart';

class CartScreen extends StatefulWidget {
  final ApiService? api;
  const CartScreen({super.key, this.api});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  late final ApiService _api;
  final _searchCtrl = TextEditingController();
  Cart? _cart;
  bool _loading = true;
  final Set<int> _selected = {};
  String _search = '';

  List<CartItem> get _visibleItems {
    final items = _cart?.items ?? [];
    if (_search.isEmpty) return items;
    final q = _search.toLowerCase();
    return items
        .where((i) => i.product.name.toLowerCase().contains(q))
        .toList();
  }

  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiService();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await _api.get('cart');
      final cart = Cart.fromJson(data);
      if (!mounted) return;
      final initial = _cart == null;
      setState(() {
        _cart = cart;
        if (initial) {
          _selected.addAll(
            cart.items
                .where((i) => i.availableStock >= i.quantity && i.quantity > 0)
                .map((i) => i.id),
          );
        } else {
          final ids = cart.items
              .where((i) => i.availableStock >= i.quantity && i.quantity > 0)
              .map((i) => i.id)
              .toSet();
          _selected.removeWhere((id) => !ids.contains(id));
        }
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _loading = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  double get _selectedSubtotal {
    if (_cart == null) return 0;
    return _cart!.items
        .where((i) => _selected.contains(i.id))
        .fold(0.0, (s, i) => s + i.lineTotal);
  }

  Future<void> _updateQty(CartItem item, int qty) async {
    if (qty < 1) return;
    final stock = item.availableStock;
    if (qty > 200 || qty > stock) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Only $stock available.')));
      return;
    }
    try {
      await _api.put('cart/${item.id}', {'quantity': qty});
      BuyerUpdates.cartChanged();
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _remove(CartItem item) async {
    try {
      await _api.delete('cart/${item.id}');
      BuyerUpdates.cartChanged();
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final signedIn = AuthServiceProvider.of(context).isLoggedIn;
    return MainLayout(
      currentIndex: 2,
      title: 'Cart',
      showSettingsIcon: false,
      api: _api,
      headerSearch: signedIn && _cart?.items.isNotEmpty == true
          ? BuyerSearch(
              controller: _searchCtrl,
              hint: 'Search cart',
              compact: true,
              onChanged: (value) => setState(() => _search = value.trim()),
              onSubmitted: (value) => setState(() => _search = value.trim()),
            )
          : null,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Your cart',
                        style: Theme.of(
                          context,
                        ).textTheme.titleLarge?.copyWith(fontSize: 22),
                      ),
                    ),
                    if (_cart?.items.isNotEmpty == true)
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.paleTeal,
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          '${_cart!.items.length} item${_cart!.items.length == 1 ? '' : 's'}',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                            color: AppColors.primaryDark,
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  _cart == null || _cart!.items.isEmpty
                      ? 'A place for your next finds.'
                      : 'Select items to check out.',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: !signedIn
                ? BuyerEmptyState(
                    icon: CupertinoIcons.bag,
                    title: 'Your cart awaits',
                    description: 'Log in to see the items you saved.',
                    action: BuyerButton.primary(
                      onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const LoginScreen()),
                      ),
                      child: const Text('Login'),
                    ),
                  )
                : _loading
                ? const Center(child: CupertinoActivityIndicator())
                : _cart == null || _cart!.items.isEmpty
                ? BuyerEmptyState(
                    icon: CupertinoIcons.bag,
                    title: 'Your cart is empty',
                    description: 'Find something you would love to bring home.',
                    action: BuyerButton.primary(
                      onPressed: () => Navigator.pushAndRemoveUntil(
                        context,
                        MaterialPageRoute(builder: (_) => const HomeScreen()),
                        (route) => false,
                      ),
                      child: const Text('Browse products'),
                    ),
                  )
                : _visibleItems.isEmpty
                ? const BuyerEmptyState(
                    icon: CupertinoIcons.search,
                    title: 'No matching items',
                    description:
                        'Try another product name or clear your search.',
                  )
                : RefreshIndicator.adaptive(
                    onRefresh: _load,
                    child: ListView.separated(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                      itemCount: _visibleItems.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (_, i) => _cartItemTile(_visibleItems[i]),
                    ),
                  ),
          ),
          if (signedIn && !_loading && _cart?.items.isNotEmpty == true)
            Offstage(
              offstage: MediaQuery.viewInsetsOf(context).bottom > 0,
              child: Padding(
                padding: EdgeInsets.fromLTRB(
                  16,
                  8,
                  16,
                  AppSizes.bottomNavClearance(context) -
                      AppSizes.floatingNavBreathingRoom,
                ),
                child: Align(
                  heightFactor: 1,
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 540),
                    child: _checkoutBar(context),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _cartItemTile(CartItem item) => BuyerPanel(
    key: ValueKey('cart-item-${item.id}'),
    padding: const EdgeInsets.all(8),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Semantics(
                key: ValueKey('cart-selection-${item.id}'),
                label: 'Select ${item.product.name}',
                child: CupertinoCheckbox(
                  shape: const CircleBorder(),
                  fillColor: WidgetStateProperty.resolveWith(
                    (states) => states.contains(WidgetState.selected)
                        ? AppColors.primaryDark
                        : Colors.transparent,
                  ),
                  value: _selected.contains(item.id),
                  onChanged: item.availableStock < item.quantity
                      ? null
                      : (value) => setState(() {
                          if (value == true) {
                            _selected.add(item.id);
                          } else {
                            _selected.remove(item.id);
                          }
                        }),
                ),
              ),
            ),
            InkWell(
              borderRadius: BorderRadius.circular(10),
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) =>
                      ProductDetailScreen(productId: item.productId),
                ),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: SizedBox(
                  width: 48,
                  height: 56,
                  child: ProductPhoto(
                    path: item.product.image,
                    label: item.product.name,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.product.name,
                    maxLines: MediaQuery.textScalerOf(context).scale(1) > 1.2
                        ? null
                        : 2,
                    overflow: MediaQuery.textScalerOf(context).scale(1) > 1.2
                        ? TextOverflow.visible
                        : TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 14,
                      height: 1.3,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  if (item.variantLabel.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(
                      item.variantLabel,
                      style: const TextStyle(fontSize: 12, height: 1.3),
                    ),
                  ],
                  const SizedBox(height: 3),
                  Text(
                    '${_fmt(item.unitPrice)} each',
                    style: const TextStyle(
                      fontSize: 12,
                      height: 1.3,
                      color: AppColors.textSecondary,
                      fontFeatures: [FontFeature.tabularFigures()],
                    ),
                  ),
                ],
              ),
            ),
            BuyerButton.icon(
              tooltip: 'Remove item',
              onPressed: () => _remove(item),
              icon: const Icon(
                CupertinoIcons.trash,
                size: 18,
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
        if (item.availableStock < item.quantity)
          const Padding(
            padding: EdgeInsets.only(top: 6),
            child: Text(
              'Unavailable in this quantity',
              style: TextStyle(color: AppColors.danger, fontSize: 12),
            ),
          ),
        const SizedBox(height: 4),
        SizedBox(
          width: double.infinity,
          child: Wrap(
            spacing: 12,
            runSpacing: 6,
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Item total',
                    style: TextStyle(fontSize: 12, height: 1.2),
                  ),
                  const SizedBox(height: 2),
                  ShoppingPrice(_fmt(item.lineTotal), size: 16),
                ],
              ),
              ShoppingQuantity(
                compact: true,
                quantity: item.quantity,
                onDecrease: item.availableStock < 1 || item.quantity <= 1
                    ? null
                    : () => _updateQty(item, item.quantity - 1),
                onIncrease:
                    item.quantity >= item.availableStock || item.quantity >= 200
                    ? null
                    : () => _updateQty(item, item.quantity + 1),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _checkoutBar(BuildContext context) => Container(
    key: const ValueKey('cart-checkout-dock'),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: AppColors.paper,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(
        color: AppColors.border.withValues(alpha: .8),
        width: .5,
      ),
      boxShadow: [
        BoxShadow(
          color: AppColors.primaryDark.withValues(alpha: .10),
          blurRadius: 20,
          offset: const Offset(0, 4),
        ),
      ],
    ),
    child: LayoutBuilder(
      builder: (context, constraints) {
        final amount = Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Subtotal · ${_selected.length} selected',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: 2),
            ShoppingPrice(_fmt(_selectedSubtotal), size: 18),
          ],
        );
        final checkout = BuyerButton.primary(
          onPressed: _selected.isEmpty
              ? null
              : () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        CheckoutScreen(cartItemIds: _selected.toList()),
                  ),
                ),
          child: const Text('Checkout'),
        );
        final stacked =
            constraints.maxWidth < 250 ||
            MediaQuery.textScalerOf(context).scale(1) > 1.2;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            if (stacked)
              Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [amount, const SizedBox(height: 8), checkout],
              )
            else
              Row(
                children: [
                  Expanded(child: amount),
                  const SizedBox(width: 12),
                  checkout,
                ],
              ),
            const SizedBox(height: 6),
            const Text(
              'Shipping calculated at checkout',
              style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
            ),
          ],
        );
      },
    ),
  );

  String _fmt(double value) => shoppingMoney(value);
}
