import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart'
    show
        Material,
        MaterialType,
        PopupMenuEntry,
        PopupMenuItem,
        CheckedPopupMenuItem,
        Tooltip;
import '../theme.dart';

class BuyerDialogAction extends StatelessWidget {
  final VoidCallback? onPressed;
  final Widget child;
  final bool isDestructiveAction, isDefaultAction;
  const BuyerDialogAction({
    super.key,
    required this.onPressed,
    required this.child,
    this.isDestructiveAction = false,
    this.isDefaultAction = false,
  });
  @override
  Widget build(BuildContext context) => CupertinoDialogAction(
    onPressed: onPressed,
    isDefaultAction: isDefaultAction,
    isDestructiveAction: isDestructiveAction,
    textStyle: TextStyle(
      fontFamily: 'Inter',
      fontSize: 15,
      color: isDestructiveAction ? AppColors.danger : AppColors.primaryDark,
    ),
    child: child,
  );
}

Future<DateTime?> showBuyerDatePicker({
  required BuildContext context,
  required DateTime initialDate,
  required DateTime firstDate,
  required DateTime lastDate,
}) {
  var selected = initialDate;
  FocusManager.instance.primaryFocus?.unfocus();
  return showCupertinoModalPopup<DateTime>(
    context: context,
    builder: (sheet) => Container(
      color: AppColors.paper,
      height: 300 + MediaQuery.paddingOf(sheet).bottom,
      child: SafeArea(
        top: false,
        child: Column(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                CupertinoButton(
                  onPressed: () => Navigator.pop(sheet),
                  child: const Text(
                    'Cancel',
                    style: TextStyle(fontFamily: 'Inter', fontSize: 14),
                  ),
                ),
                CupertinoButton(
                  onPressed: () => Navigator.pop(sheet, selected),
                  child: const Text(
                    'Done',
                    style: TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
            Expanded(
              child: CupertinoDatePicker(
                mode: CupertinoDatePickerMode.date,
                initialDateTime: initialDate,
                minimumDate: firstDate,
                maximumDate: lastDate,
                onDateTimeChanged: (value) => selected = value,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Native dialog geometry with support for existing validated forms/menus.
class BuyerAlertDialog extends StatelessWidget {
  final Widget? title, content;
  final List<Widget> actions;
  const BuyerAlertDialog({
    super.key,
    this.title,
    this.content,
    this.actions = const [],
  });
  @override
  Widget build(BuildContext context) => CupertinoAlertDialog(
    title: title == null
        ? null
        : DefaultTextStyle(
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 17,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
            child: title!,
          ),
    content: content == null
        ? null
        : Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Material(
              type: MaterialType.transparency,
              child: DefaultTextStyle(
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 13,
                  height: 1.4,
                  color: AppColors.textSecondary,
                ),
                child: content!,
              ),
            ),
          ),
    actions: actions,
  );
}

/// Existing sort options and callbacks presented as an iOS action sheet.
class BuyerActionMenu<T> extends StatelessWidget {
  final String? tooltip;
  final T? initialValue;
  final ValueChanged<T>? onSelected;
  final List<PopupMenuEntry<T>> Function(BuildContext) itemBuilder;
  final Widget? child, icon;
  const BuyerActionMenu({
    super.key,
    this.tooltip,
    this.initialValue,
    this.onSelected,
    required this.itemBuilder,
    this.child,
    this.icon,
  });
  @override
  Widget build(BuildContext context) {
    final control = CupertinoButton(
      padding: EdgeInsets.zero,
      minimumSize: const Size(44, 44),
      onPressed: () async {
        final items = itemBuilder(
          context,
        ).whereType<PopupMenuItem<T>>().toList();
        final selected = await showCupertinoModalPopup<T>(
          context: context,
          builder: (sheetContext) => CupertinoActionSheet(
            title: tooltip == null
                ? null
                : Text(
                    tooltip!,
                    style: const TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
            actions: items
                .map(
                  (item) => IgnorePointer(
                    ignoring: !item.enabled,
                    child: CupertinoActionSheetAction(
                      onPressed: () => Navigator.pop(sheetContext, item.value),
                      child: DefaultTextStyle(
                        style: const TextStyle(
                          fontFamily: 'Inter',
                          fontSize: 15,
                          color: AppColors.primaryDark,
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            if (item.value == initialValue ||
                                item is CheckedPopupMenuItem<T> && item.checked)
                              const Padding(
                                padding: EdgeInsets.only(right: 8),
                                child: Icon(
                                  CupertinoIcons.check_mark,
                                  size: 18,
                                ),
                              ),
                            Flexible(child: item.child ?? const SizedBox()),
                          ],
                        ),
                      ),
                    ),
                  ),
                )
                .toList(),
            cancelButton: CupertinoActionSheetAction(
              onPressed: () => Navigator.pop(sheetContext),
              child: const Text(
                'Cancel',
                style: TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: AppColors.primaryDark,
                ),
              ),
            ),
          ),
        );
        if (selected != null && context.mounted) onSelected?.call(selected);
      },
      child: child ?? icon ?? const Icon(CupertinoIcons.sort_down, size: 21),
    );
    return tooltip == null
        ? control
        : Tooltip(message: tooltip!, child: control);
  }
}
