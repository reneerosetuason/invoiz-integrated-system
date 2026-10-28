import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../theme.dart';

final _peso = NumberFormat.currency(
  locale: 'en_PH',
  symbol: '₱',
  decimalDigits: 2,
);
String shoppingMoney(num value) => _peso.format(value);

/// Shared reading and touch patterns for the buyer's shopping journey.
class ShoppingPrice extends StatelessWidget {
  final String value;
  final double size;
  const ShoppingPrice(this.value, {super.key, this.size = 22});

  @override
  Widget build(BuildContext context) => Text(
    value,
    style: TextStyle(
      fontFamily: 'Inter',
      fontSize: size,
      fontWeight: FontWeight.w700,
      letterSpacing: -.5,
      color: AppColors.primaryDark,
      fontFeatures: const [FontFeature.tabularFigures()],
    ),
  );
}

class ShoppingQuantity extends StatelessWidget {
  final int quantity;
  final VoidCallback? onDecrease, onIncrease;
  const ShoppingQuantity({
    super.key,
    required this.quantity,
    this.onDecrease,
    this.onIncrease,
  });

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: AppColors.paper,
      border: Border.all(color: AppColors.border),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          tooltip: 'Decrease quantity',
          onPressed: onDecrease,
          icon: const Icon(Icons.remove, size: 18),
          constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
        ),
        Semantics(
          label: 'Quantity',
          value: '$quantity',
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Text(
              '$quantity',
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
          ),
        ),
        IconButton(
          tooltip: 'Increase quantity',
          onPressed: onIncrease,
          icon: const Icon(Icons.add, size: 18),
          constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
        ),
      ],
    ),
  );
}

class ShoppingValueRow extends StatelessWidget {
  final String label, value;
  final bool total;
  const ShoppingValueRow(
    this.label,
    this.value, {
    super.key,
    this.total = false,
  });

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: SizedBox(
      width: double.infinity,
      child: Wrap(
        alignment: WrapAlignment.spaceBetween,
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 16,
        runSpacing: 4,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: total ? 16 : 14,
              fontWeight: total ? FontWeight.w600 : FontWeight.w400,
              color: AppColors.textPrimary,
            ),
          ),
          total
              ? ShoppingPrice(value)
              : Text(
                  value,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: AppColors.textPrimary,
                    fontFeatures: [FontFeature.tabularFigures()],
                  ),
                ),
        ],
      ),
    ),
  );
}

class ShoppingDock extends StatelessWidget {
  final Widget child;
  const ShoppingDock({super.key, required this.child});

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    decoration: const BoxDecoration(
      color: AppColors.paper,
      border: Border(top: BorderSide(color: AppColors.border)),
    ),
    child: SafeArea(
      top: false,
      child: Padding(padding: const EdgeInsets.all(16), child: child),
    ),
  );
}

class ShoppingSearch extends StatelessWidget {
  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onChanged;
  const ShoppingSearch({
    super.key,
    required this.controller,
    required this.hint,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) => TextField(
    controller: controller,
    onChanged: onChanged,
    onSubmitted: onChanged,
    textInputAction: TextInputAction.search,
    style: const TextStyle(fontSize: 14, color: AppColors.textPrimary),
    decoration: InputDecoration(
      hintText: hint,
      prefixIcon: const Icon(Icons.search, size: 21),
      suffixIcon: controller.text.isEmpty
          ? null
          : IconButton(
              tooltip: 'Clear search',
              icon: const Icon(Icons.close, size: 19),
              onPressed: () {
                controller.clear();
                onChanged('');
              },
            ),
    ),
  );
}
