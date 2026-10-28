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
  const CheckoutScreen({super.key, this.cartItemIds});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _api = ApiService();
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
    if (_selectedAddressId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please select a shipping address.'),
          backgroundColor: Colors.red,
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
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Order placed successfully! (Cash on Delivery)'),
          backgroundColor: AppColors.success,
        ),
      );
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const OrdersScreen()),
        (route) => false,
      );
    } on ApiException catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: Colors.red.shade700,
        ),
      );
    } catch (_) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Unable to place order.'),
          backgroundColor: Colors.red,
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
          : ListView(
              padding: const EdgeInsets.all(12),
              children: [
                _section('Shipping Address', _addressSection(context)),
                const SizedBox(height: 8),
                _section('Payment Method', _paymentSection()),
                const SizedBox(height: 8),
                _section('Voucher', _voucherSection()),
                const SizedBox(height: 8),
                _section('Order Summary', _summarySection()),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: _placing || _quoting || _quote == null
                      ? null
                      : _placeOrder,
                  style: ElevatedButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                  ),
                  child: _placing
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2,
                          ),
                        )
                      : const Text(
                          'Place Order (Cash on Delivery)',
                          style: TextStyle(fontSize: 15),
                        ),
                ),
                const SizedBox(height: 16),
              ],
            ),
    );
  }

  Widget _section(String title, Widget child) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: AppColors.border),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: Color(0xFF212121),
            ),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }

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
            value: a.id,
            groupValue: _selectedAddressId,
            activeColor: AppColors.primary,
            onChanged: (v) => setState(() => _selectedAddressId = v),
            title: Text(
              a.recipientName,
              style: const TextStyle(fontSize: 14, color: Color(0xFF212121)),
            ),
            subtitle: Text(
              '${a.phone}\n${a.fullAddress}',
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
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
          value: 'cash_on_delivery',
          groupValue: _paymentMethod,
          activeColor: AppColors.primary,
          onChanged: (v) => setState(() => _paymentMethod = v!),
          title: const Text(
            'Cash on Delivery (COD)',
            style: TextStyle(fontSize: 14, color: Color(0xFF212121)),
          ),
          subtitle: const Text(
            'Pay when your order arrives.',
            style: TextStyle(fontSize: 12),
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
              borderRadius: BorderRadius.circular(6),
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
                          color: Color(0xFF212121),
                        ),
                      ),
                      Text(
                        'Discount: -${_fmt(_validatedDiscount ?? 0)}',
                        style: TextStyle(
                          fontSize: 12,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
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
            style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
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
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: AppColors.secondary),
      ),
      child: Row(
        children: [
          Icon(Icons.auto_awesome, color: AppColors.secondary, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Best for you: ${best.name}',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF212121),
                  ),
                ),
                Text(
                  'Saves ${_fmt(bestSave)} on this order',
                  style: TextStyle(
                    fontSize: 11,
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

  Widget _summarySection() {
    final subtotal = _quote?.subtotal ?? 0;
    final discount = _quote?.discount ?? 0;
    final total = _quote?.total ?? 0;
    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Subtotal',
              style: TextStyle(fontSize: 13, color: Color(0xFF212121)),
            ),
            Text(
              _fmt(subtotal),
              style: const TextStyle(fontSize: 13, color: Color(0xFF212121)),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Voucher Discount',
              style: TextStyle(fontSize: 13, color: Color(0xFF212121)),
            ),
            Text(
              '-${_fmt(discount)}',
              style: TextStyle(fontSize: 13, color: AppColors.primary),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Shipping',
              style: TextStyle(fontSize: 13, color: Color(0xFF212121)),
            ),
            Text(
              _quote?.shippingFee == 0
                  ? 'FREE'
                  : _fmt(_quote?.shippingFee ?? 0),
              style: const TextStyle(fontSize: 13, color: Color(0xFF212121)),
            ),
          ],
        ),
        const Divider(height: 18),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Total',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.bold,
                color: Color(0xFF212121),
              ),
            ),
            Text(
              _fmt(total),
              style: TextStyle(
                color: AppColors.primary,
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
      ],
    );
  }

  String _fmt(double v) {
    final n = v.toStringAsFixed(2);
    return '₱${n.split('.')[0]}.${n.split('.')[1]}';
  }
}
