import 'package:flutter/cupertino.dart';
import '../theme.dart';
import 'buyer_controls.dart';

class BuyerSearch extends StatelessWidget {
  final TextEditingController controller;
  final String hint;
  final ValueChanged<String>? onChanged, onSubmitted;
  final VoidCallback? onSearch, onClear;
  const BuyerSearch({
    super.key,
    required this.controller,
    required this.hint,
    this.onChanged,
    this.onSubmitted,
    this.onSearch,
    this.onClear,
  });
  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: AppColors.cream,
      borderRadius: BorderRadius.circular(999),
    ),
    child: Row(
      children: [
        Expanded(
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 46),
            child: CupertinoSearchTextField(
              controller: controller,
              placeholder: hint,
              onChanged: onChanged,
              onSubmitted: onSubmitted,
              onSuffixTap:
                  onClear ??
                  () {
                    controller.clear();
                    onChanged?.call('');
                  },
              suffixMode: onSearch == null
                  ? OverlayVisibilityMode.editing
                  : OverlayVisibilityMode.never,
              decoration: const BoxDecoration(
                color: CupertinoColors.transparent,
              ),
              style: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 14,
                color: AppColors.textPrimary,
              ),
              placeholderStyle: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 14,
                color: AppColors.textMuted,
              ),
              itemColor: AppColors.textSecondary,
              itemSize: 19,
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
              prefixInsets: const EdgeInsetsDirectional.only(start: 14, end: 2),
              suffixInsets: const EdgeInsetsDirectional.only(end: 12),
            ),
          ),
        ),
        if (onSearch != null)
          BuyerButton.icon(
            tooltip: 'Search products',
            onPressed: onSearch,
            icon: const Icon(CupertinoIcons.arrow_right, size: 20),
          ),
      ],
    ),
  );
}
