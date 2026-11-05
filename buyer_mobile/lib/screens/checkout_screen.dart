import '../widgets/buyer_controls.dart';
import 'package:flutter/cupertino.dart';
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
import 'dart:typed_data';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;

class CheckoutScreen extends StatefulWidget {
  final List<int>? cartItemIds;
  final ApiService? api;
  final Future<XFile?> Function()? pickReceipt;
  const CheckoutScreen({
    super.key,
    this.cartItemIds,
    this.api,
    this.pickReceipt,
  });

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
  bool _pickingReceipt = false;
  final Map<int, Uint8List> _receipts = {};
  final Map<int, String> _receiptNames = {};
  String? _loadError;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _loadError = null;
      _quote = null;
    });
    try {
      final cartData = await _api.get('cart');
      if (!mounted) return;
      final fullCart = Cart.fromJson(cartData);
      setState(() => _cart = fullCart.select(widget.cartItemIds));
      if (_cart!.items.isEmpty) {
        setState(() {
          _loading = false;
          if (fullCart.items.isNotEmpty) {
            _loadError =
                'The selected items are no longer in your cart. Return to your cart and choose your items again.';
          }
        });
        return;
      }
      final addrData = await _api.get('addresses');
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
        _vouchers = (quoteData['vouchers'] as List? ?? [])
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
      setState(() {
        _loading = false;
        _loadError = e is ApiException
            ? e.message
            : 'Checkout could not be loaded. Please try again.';
      });
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
        _receipts.clear();
        _receiptNames.clear();
        _selectedVoucher = voucher.name;
        _voucherClaimId = claimId;
        _quote = quote;
        _validatedDiscount = quote.discount + quote.shippingDiscount;
        _vouchers = (data['vouchers'] as List? ?? [])
            .whereType<Map<String, dynamic>>()
            .map(Voucher.fromJson)
            .toList();
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
        _receipts.clear();
        _receiptNames.clear();
        _selectedVoucher = null;
        _voucherClaimId = null;
        _validatedDiscount = null;
        _vouchers = (data['vouchers'] as List? ?? [])
            .whereType<Map<String, dynamic>>()
            .map(Voucher.fromJson)
            .toList();
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
    if (_placing ||
        _pickingReceipt ||
        _quoting ||
        _quote == null ||
        _loadError != null)
      return;
    if (_paymentMethod == 'gcash' &&
        (_quote!.gcashAccounts.isEmpty ||
            _quote!.gcashAccounts.any(
              (account) => account['available'] != true,
            ))) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'A store receiving QR is unavailable. Please choose cash on delivery.',
          ),
        ),
      );
      return;
    }
    if (_paymentMethod == 'gcash' &&
        _quote!.gcashAccounts.any(
          (account) => !_receipts.containsKey(account['seller_id']),
        )) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please upload your GCash receipt.')),
      );
      return;
    }
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
      final body = <String, dynamic>{
        'address_id': _selectedAddressId,
        'payment_method': _paymentMethod,
        ..._selection(),
      };
      if (_paymentMethod == 'gcash') {
        final fields = <String, String>{};
        for (final entry in body.entries) {
          if (entry.value is List) {
            final values = entry.value as List;
            for (var i = 0; i < values.length; i++) {
              fields['${entry.key}[$i]'] = '${values[i]}';
            }
          } else if (entry.value != null) {
            fields[entry.key] = '${entry.value}';
          }
        }
        await _api.postForm(
          'orders/checkout',
          fields,
          files: [
            for (final account in _quote!.gcashAccounts)
              http.MultipartFile.fromBytes(
                _quote!.gcashAccounts.length == 1
                    ? 'gcash_proof'
                    : 'gcash_proofs[${account['seller_id']}]',
                _receipts[account['seller_id']]!,
                filename: _receiptNames[account['seller_id']]!,
              ),
          ],
        );
      } else {
        await _api.post('orders/checkout', body);
      }
      BuyerUpdates.cartChanged();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _paymentMethod == 'gcash'
                ? 'Order placed. Your GCash receipt was submitted for seller review.'
                : 'Order placed. Pay when it arrives.',
          ),
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

  Future<void> _pickReceipt(int sellerId) async {
    if (_placing || _pickingReceipt) return;
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    setState(() => _pickingReceipt = true);
    try {
      final file =
          await (widget.pickReceipt?.call() ??
              ImagePicker().pickImage(source: ImageSource.gallery));
      if (file == null) return;
      if (![
            'jpg',
            'jpeg',
            'png',
            'webp',
          ].contains(file.name.split('.').last.toLowerCase()) ||
          await file.length() > 5 * 1024 * 1024) {
        throw const FormatException(
          'Choose a JPG, PNG or WebP receipt no larger than 5 MB.',
        );
      }
      final bytes = await file.readAsBytes();
      if (!mounted) return;
      setState(() {
        _receipts[sellerId] = bytes;
        _receiptNames[sellerId] = file.name;
      });
    } catch (error) {
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error is FormatException
                  ? error.message
                  : 'Unable to open the receipt. Please try again.',
            ),
          ),
        );
    } finally {
      if (mounted) setState(() => _pickingReceipt = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      title: 'Checkout',
      showBottomNav: false,
      showSettingsIcon: false,
      child: _loading
          ? const Center(child: CupertinoActivityIndicator())
          : _loadError != null
          ? ListView(
              padding: const EdgeInsets.all(16),
              children: [
                const BuyerPageHeading(title: 'Checkout unavailable'),
                Text(_loadError!, style: const TextStyle(fontSize: 15)),
                const SizedBox(height: 12),
                if (_cart?.items.isNotEmpty == true) ...[
                  const Text('Your items are still in your cart.'),
                  const SizedBox(height: 12),
                  _section('Your items', _itemsSection()),
                  const SizedBox(height: 12),
                ],
                BuyerButton.primary(
                  onPressed: _load,
                  child: const Text('Try again'),
                ),
                const SizedBox(height: 8),
                BuyerButton.plain(
                  onPressed: () => Navigator.maybePop(context),
                  child: const Text('Go back'),
                ),
              ],
            )
          : _cart == null || _cart!.items.isEmpty
          ? const Center(child: Text('Your cart is empty.'))
          : Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
                    children: [
                      const BuyerPageHeading(
                        title: 'Review your order',
                        description:
                            'Check your items, payment and delivery details.',
                      ),
                      _section('Your items', _itemsSection()),
                      const SizedBox(height: 12),
                      _section('Delivery address', _addressSection(context)),
                      const SizedBox(height: 12),
                      _section('Payment method', _paymentSection()),
                      const SizedBox(height: 12),
                      _section('Voucher', _voucherSection()),
                      const SizedBox(height: 12),
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
                      BuyerButton.primary(
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
                                child: CupertinoActivityIndicator(
                                  color: AppColors.surface,
                                ),
                              )
                            : Text(
                                _paymentMethod == 'gcash'
                                    ? 'Place order · GCash'
                                    : 'Place order · Cash on delivery',
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
          BuyerButton.primary(
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
          return BuyerChoiceRow<int>(
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
          child: BuyerButton.plain(
            icon: const Icon(CupertinoIcons.add),
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
        BuyerChoiceRow<String>(
          value: 'cash_on_delivery',
          groupValue: _paymentMethod,
          activeColor: AppColors.primary,
          onChanged: _placing || _pickingReceipt
              ? null
              : (v) => setState(() {
                  _paymentMethod = v!;
                  _receipts.clear();
                  _receiptNames.clear();
                }),
          title: const Text(
            'Cash on Delivery (COD)',
            style: TextStyle(fontSize: 14, color: AppColors.textPrimary),
          ),
          subtitle: const Text(
            'Pay when your order arrives.',
            style: TextStyle(fontSize: 14),
          ),
        ),
        BuyerChoiceRow<String>(
          value: 'gcash',
          groupValue: _paymentMethod,
          activeColor: AppColors.primary,
          onChanged: _placing || _pickingReceipt
              ? null
              : (value) => setState(() => _paymentMethod = value!),
          title: const Text('GCash'),
          subtitle: const Text('Upload your payment receipt for the seller.'),
        ),
        if (_paymentMethod == 'gcash') ...[
          const SizedBox(height: 12),
          const Text(
            'Upload a photo or screenshot of the GCash payment you sent. JPG, PNG or WebP, up to 5 MB. Your seller can view it; uploading does not automatically confirm payment.',
          ),
          if (_quote!.gcashAccounts.isEmpty)
            const Text(
              'GCash receiving details are unavailable. Choose COD or reload checkout.',
            ),
          for (final account in _quote!.gcashAccounts) _storePayment(account),
        ],
      ],
    );
  }

  Widget _storePayment(Map<String, dynamic> account) {
    final sellerId = account['seller_id'] as int;
    final ready = account['available'] == true;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${account['store_name']} · ${_fmt(double.tryParse('${account['amount']}') ?? 0)}',
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          Text('GCash account: ${account['account_name'] ?? 'Not configured'}'),
          if (!ready)
            Text(
              'GCash is unavailable for this store. Choose COD or check out other stores separately.',
            ),
          if (account['qr_url'] != null) ...[
            if (ready)
              const Text(
                'Scan or import this QR in your GCash app and pay the amount above. No cash is due on delivery.',
              ),
            GestureDetector(
              onTap: () => showDialog<void>(
                context: context,
                builder: (context) => Dialog(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Flexible(
                          child: InteractiveViewer(
                            child: Image.network(
                              account['qr_url'] as String,
                              errorBuilder: (_, __, ___) =>
                                  const Text('Could not load receiving QR.'),
                            ),
                          ),
                        ),
                        TextButton(
                          onPressed: () => Navigator.pop(context),
                          child: const Text('Close'),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              child: Image.network(
                account['qr_url'] as String,
                height: 220,
                fit: BoxFit.contain,
                errorBuilder: (_, __, ___) => const Text(
                  'Could not load QR. Check your connection before paying.',
                ),
              ),
            ),
            if (ready)
              BuyerButton.plain(
                onPressed: _placing || _pickingReceipt
                    ? null
                    : () => _pickReceipt(sellerId),
                child: Text(
                  _pickingReceipt
                      ? 'Opening photos...'
                      : _receiptNames[sellerId] == null
                      ? 'Upload GCash receipt'
                      : 'Replace GCash receipt',
                ),
              ),
            if (ready && _receipts[sellerId] != null) ...[
              Text(
                _receiptNames[sellerId]!,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              Image.memory(
                _receipts[sellerId]!,
                height: 180,
                fit: BoxFit.contain,
                errorBuilder: (_, __, ___) => const Text(
                  'Unable to preview this image. Choose a readable receipt.',
                ),
              ),
            ],
          ],
        ],
      ),
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
                BuyerButton.icon(
                  tooltip: 'Remove voucher',
                  icon: const Icon(CupertinoIcons.xmark, size: 18),
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
            'No vouchers apply to the selected items.',
            style: TextStyle(fontSize: 14, color: AppColors.textSecondary),
          ),
      ],
    );
  }

  /// Savings are calculated by the server using each voucher's store scope.
  Widget _bestVoucherHint() {
    Voucher? best;
    double bestSave = 0;

    for (final v in _vouchers) {
      final save = v.checkoutSavings ?? 0;
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
          BuyerButton.plain(
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
