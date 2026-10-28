import '../widgets/buyer_page.dart';
import '../widgets/product_photo.dart';
import '../widgets/shopping_ui.dart';
import '../services/buyer_updates.dart';
import 'package:flutter/material.dart';
import '../models/address.dart';
import '../models/cart.dart';
import '../models/checkout_quote.dart';
import '../models/voucher.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/main_layout.dart';
import 'add_address_screen.dart';
import 'orders_screen.dart';

class CheckoutScreen extends StatefulWidget {
  final List<int>? cartItemIds;
  final ApiService? api;
  const CheckoutScreen({super.key, this.cartItemIds, this.api});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  late final _api = widget.api ?? ApiService();
  Cart? _cart;
  List<Address> _addresses = [];
  List<Voucher> _vouchers = [];
  int? _selectedAddressId;
  String? _selectedVoucher;
  double? _validatedDiscount;
  int? _voucherClaimId;
  CheckoutQuote? _quote;
  bool _quoting = false;
  String _paymentMethod = 'cash_on_delivery';
  bool _loading = true;
  bool _placing = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final cartData = await _api.get('cart');
      final addrData = await _api.get('addresses');
      final voucherData = await _api.get('vouchers');
      final quoteData = await _api.post('orders/preview', _selection());
      if (!mounted) return;
      setState(() {
        _cart = Cart.fromJson(cartData).select(widget.cartItemIds);
        _quote = CheckoutQuote.fromJson(
          quoteData['quote'] as Map<String, dynamic>,
        );
        _addresses = (addrData['addresses'] as List)
            .whereType<Map<String, dynamic>>()
            .map(Address.fromJson)
            .toList();
        _vouchers = (voucherData['vouchers'] as List)
            .whereType<Map<String, dynamic>>()
            .map(Voucher.fromJson)
            .toList();
        if (_selectedAddressId == null && _addresses.isNotEmpty) {
          final def =
              _addresses.where((a) => a.isDefault).firstOrNull ??
              _addresses.first;
          _selectedAddressId = def.id;
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

  Map<String, dynamic> _selection() => {
    if (widget.cartItemIds != null) 'cart_item_ids': widget.cartItemIds,
    if (_voucherClaimId != null) 'voucher_claim_id': _voucherClaimId,
  };

  Future<void> _applyVoucher(Voucher voucher) async {
    if (_quoting) return;
    setState(() => _quoting = true);
    try {
      final claim = await _api.post('vouchers/${voucher.id}/claim', {});
      final claimId = claim['claim']['id'] as int;
      final data = await _api.post('orders/preview', {
        ..._selection(),
        'voucher_claim_id': claimId,
      });
      if (!mounted) return;
      final quote = CheckoutQuote.fromJson(
        data['quote'] as Map<String, dynamic>,
      );
      setState(() {
        _selectedVoucher = voucher.name;
        _voucherClaimId = claimId;
        _quote = quote;
        _validatedDiscount = quote.discount;
      });
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _quoting = false);
    }
  }

  Future<void> _removeVoucher() async {
    if (_quoting) return;
    setState(() => _quoting = true);
    try {
      final data = await _api.post('orders/preview', {
        if (widget.cartItemIds != null) 'cart_item_ids': widget.cartItemIds,
      });
      if (!mounted) return;
      setState(() {
        _quote = CheckoutQuote.fromJson(data['quote'] as Map<String, dynamic>);
        _selectedVoucher = null;
        _voucherClaimId = null;
        _validatedDiscount = null;
      });
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _quoting = false);
    }
  }

  Future<void> _placeOrder() async {
    if (_placing || _quoting) return;
    if (_selectedAddressId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please select a shipping address.'),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }
    setState(() => _placing = true);
    try {
      await _api.post('orders/checkout', {
        'address_id': _selectedAddressId,
        'payment_method': _paymentMethod,
        ..._selection(),
      });
      BuyerUpdates.cartChanged();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Order placed. Pay when it arrives.'),
          backgroundColor: AppColors.success,
        ),
      );
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const OrdersScreen()),
        (route) => false,
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.danger),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Unable to place order.'),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _placing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      title: 'Checkout',
      showBottomNav: false,
      showSettingsIcon: false,
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : _cart == null || _cart!.items.isEmpty
          ? const Center(child: Text('Your cart is empty.'))
          : Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 24, 16, 24),
                    children: [
                      const BuyerPageHeading(
                        title: 'Review your order',
                        description:
                            'Check your items and delivery details. Pay when it arrives.',
                      ),
                      _section('Your items', _itemsSection()),
                      const SizedBox(height: 16),
                      _section('Delivery address', _addressSection(context)),
                      const SizedBox(height: 16),
                      _section('Payment method', _paymentSection()),
                      const SizedBox(height: 16),
                      _section('Voucher', _voucherSection()),
                      const SizedBox(height: 16),
                      _section('Order summary', _summarySection()),
                    ],
                  ),
                ),
                ShoppingDock(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      ShoppingValueRow(
                        'Total to pay',
                        _fmt(_quote?.total ?? 0),
                        total: true,
                      ),
                      const SizedBox(height: 10),
                      FilledButton(
                        onPressed:
                            _placing ||
                                _quoting ||
                                _quote == null ||
                                _selectedAddressId == null
                            ? null
                            : _placeOrder,
                        child: _placing
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  color: AppColors.surface,
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text(
                                'Place order · Cash on delivery',
                                textAlign: TextAlign.center,
                              ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _itemsSection() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: _cart!.items
        .map(
          (item) => Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: SizedBox(
                    width: 60,
                    height: 76,
                    child: ProductPhoto(
                      path: item.product.image,
                      label: item.product.name,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.product.name,
                        style: const TextStyle(
                          fontSize: 15,
                          height: 1.4,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      if (item.variantLabel.isNotEmpty) Text(item.variantLabel),
                      const SizedBox(height: 6),
                      Text(
                        '${item.quantity} × ${_fmt(item.unitPrice)}',
                        style: const TextStyle(fontSize: 13),
                      ),
                      const SizedBox(height: 6),
                      ShoppingPrice(_fmt(item.lineTotal), size: 17),
                    ],
                  ),
                ),
              ],
            ),
          ),
        )
        .toList(),
  );

  Widget _section(String title, Widget child) =>
      BuyerPanel(title: title, child: child);

  Widget _addressSection(BuildContext context) {
    if (_addresses.isEmpty) {
      return Column(
        children: [
          Text(
            'No saved addresses yet.',
            style: TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 8),
          ElevatedButton(
            onPressed: () async {
              await Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const AddAddressScreen()),
              );
              _load();
            },
            child: const Text('Add Address'),
          ),
        ],
      );
    }
    return Column(
      children: [
        ..._addresses.map((a) {
          return RadioListTile<int>(
            contentPadding: EdgeInsets.zero,
            value: a.id,
            groupValue: _selectedAddressId,
            activeColor: AppColors.primary,
            onChanged: (v) => setState(() => _selectedAddressId = v),
            title: Text(
              a.recipientName,
              style: const TextStyle(
                fontSize: 14,
                color: AppColors.textPrimary,
              ),
            ),
            subtitle: Text(
              '${a.phone}\n${a.fullAddress}',
              style: TextStyle(fontSize: 14, color: AppColors.textSecondary),
            ),
          );
        }),
        Align(
          alignment: Alignment.centerRight,
          child: TextButton.icon(
            icon: const Icon(Icons.add),
            label: const Text('Add Address'),
            onPressed: () async {
              await Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const AddAddressScreen()),
              );
              _load();
            },
          ),
        ),
      ],
    );
  }

  Widget _paymentSection() {
    return Column(
      children: [
        RadioListTile<String>(
          contentPadding: EdgeInsets.zero,
          value: 'cash_on_delivery',
          groupValue: _paymentMethod,
          activeColor: AppColors.primary,
          onChanged: (v) => setState(() => _paymentMethod = v!),
          title: const Text(
            'Cash on Delivery (COD)',
            style: TextStyle(fontSize: 14, color: AppColors.textPrimary),
          ),
          subtitle: const Text(
            'Pay when your order arrives.',
            style: TextStyle(fontSize: 14),
          ),
        ),
      ],
    );
  }

  Widget _voucherSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_selectedVoucher != null)
          Container(
            width: double.infinity,
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppColors.accent,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.primary),
            ),
            child: Row(
              children: [
                Icon(Icons.confirmation_number, color: AppColors.primary),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Voucher: $_selectedVoucher',
                        style: const TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      Text(
                        'Discount: -${_fmt(_validatedDiscount ?? 0)}',
                        style: TextStyle(
                          fontSize: 14,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Remove voucher',
                  icon: const Icon(Icons.close, size: 18),
                  onPressed: _quoting ? null : _removeVoucher,
                ),
              ],
            ),
          ),
        if (_selectedVoucher == null) _bestVoucherHint(),
        if (_vouchers.isNotEmpty)
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _vouchers.map((v) {
              return ActionChip(
                avatar: Icon(
                  Icons.local_offer,
                  size: 16,
                  color: AppColors.primary,
                ),
                label: Text(v.name),
                onPressed: _quoting ? null : () => _applyVoucher(v),
              );
            }).toList(),
          )
        else
          Text(
            'No active vouchers.',
            style: TextStyle(fontSize: 14, color: AppColors.textSecondary),
          ),
      ],
    );
  }

  /// Finds the voucher that saves the most for the current cart subtotal
  /// and surfaces it as a one-tap "best for you" suggestion.
  Widget _bestVoucherHint() {
    final subtotal = _cart?.subtotal ?? 0;
    Voucher? best;
    double bestSave = 0;

    for (final v in _vouchers) {
      if (subtotal < v.minSpend) continue;
      double save;
      if (v.discountType == 'free_shipping') {
        save = v.discountValue > 0
            ? v.discountValue
            : (_quote?.shippingFee ?? 0);
      } else if (v.discountType == 'fixed') {
        save = v.discountValue;
      } else {
        save = subtotal * v.discountValue / 100;
        if (v.maxDiscount != null && save > v.maxDiscount!)
          save = v.maxDiscount!;
      }
      if (save > subtotal) save = subtotal;
      if (save > bestSave) {
        bestSave = save;
        best = v;
      }
    }

    if (best == null || bestSave <= 0) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.secondary.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.secondary),
      ),
      child: Row(
        children: [
          Icon(
            Icons.local_offer_outlined,
            color: AppColors.secondary,
            size: 18,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Suggested voucher: ${best.name}',
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                Text(
                  'Saves ${_fmt(bestSave)} on this order',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: _quoting ? null : () => _applyVoucher(best!),
            child: const Text('Apply'),
          ),
        ],
      ),
    );
  }

  Widget _summarySection() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      ShoppingValueRow('Subtotal', _fmt(_quote?.subtotal ?? 0)),
      ShoppingValueRow('Voucher discount', '-${_fmt(_quote?.discount ?? 0)}'),
      ShoppingValueRow(
        'Shipping',
        _quote?.shippingFee == 0 ? 'Free' : _fmt(_quote?.shippingFee ?? 0),
      ),
      const Divider(height: 24),
      ShoppingValueRow('Total', _fmt(_quote?.total ?? 0), total: true),
    ],
  );

  String _fmt(double value) => shoppingMoney(value);
}
