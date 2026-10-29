import 'package:flutter/cupertino.dart';
import '../theme.dart';
import 'buyer_controls.dart';

class BuyerSearch extends StatelessWidget {
  final TextEditingController controller;
  final String hint;
  final ValueChanged<String>? onChanged, onSubmitted;
  final VoidCallback? onSearch, onClear;

  /// Drops the filled capsule so the field sits straight on the page: only
  /// the icon, placeholder and action remain.
  final bool bare;
  final bool compact;
  const BuyerSearch({
    super.key,
    required this.controller,
    required this.hint,
    this.onChanged,
    this.onSubmitted,
    this.onSearch,
    this.onClear,
    this.bare = false,
    this.compact = false,
  });
  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: bare ? CupertinoColors.transparent : AppColors.cream,
      borderRadius: BorderRadius.circular(999),
    ),
    child: Row(
      children: [
        Expanded(
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: compact ? 44 : 46),
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
              suffixMode: onSearch == null || compact
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
              padding: EdgeInsets.symmetric(
                horizontal: compact ? 4 : 8,
                vertical: compact ? 4 : 12,
              ),
              prefixInsets: EdgeInsetsDirectional.only(
                start: compact ? 8 : 14,
                end: 2,
              ),
              suffixInsets: const EdgeInsetsDirectional.only(end: 12),
            ),
          ),
        ),
        if (onSearch != null && !compact)
          BuyerButton.icon(
            tooltip: 'Search products',
            onPressed: onSearch,
            icon: const Icon(CupertinoIcons.arrow_right, size: 20),
          ),
      ],
    ),
  );
}
