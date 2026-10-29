import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart'
    show DropdownMenuItem, FormField, FormFieldState, InputDecoration;
import '../theme.dart';

/// Retains Form validation and option values while presenting a native sheet.
class BuyerPicker<T> extends FormField<T> {
  final T? value;
  BuyerPicker({
    super.key,
    this.value,
    required List<DropdownMenuItem<T>> items,
    required ValueChanged<T?>? onChanged,
    InputDecoration decoration = const InputDecoration(),
    Widget? hint,
    super.validator,
    super.onSaved,
  }) : super(
         initialValue: value,
         builder: (field) {
           final selected = items
               .where((item) => item.value == field.value)
               .firstOrNull;
           return Column(
             crossAxisAlignment: CrossAxisAlignment.stretch,
             children: [
               if (decoration.labelText != null) ...[
                 Text(
                   decoration.labelText!,
                   style: const TextStyle(
                     fontFamily: 'Inter',
                     fontSize: 12,
                     fontWeight: FontWeight.w500,
                     color: AppColors.textSecondary,
                   ),
                 ),
                 const SizedBox(height: 6),
               ],
               DecoratedBox(
                 decoration: BoxDecoration(
                   color: AppColors.paper,
                   borderRadius: BorderRadius.circular(14),
                   border: Border.all(
                     color: field.hasError
                         ? AppColors.danger
                         : AppColors.border,
                     width: .5,
                   ),
                 ),
                 child: CupertinoButton(
                   padding: const EdgeInsets.symmetric(
                     horizontal: 12,
                     vertical: 12,
                   ),
                   minimumSize: const Size(44, 46),
                   onPressed: onChanged == null || items.isEmpty
                       ? null
                       : () async {
                           FocusManager.instance.primaryFocus?.unfocus();
                           final result = await showCupertinoModalPopup<T>(
                             context: field.context,
                             builder: (sheet) => CupertinoActionSheet(
                               title: decoration.labelText == null
                                   ? null
                                   : Text(
                                       decoration.labelText!,
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
                                         onPressed: () =>
                                             Navigator.pop(sheet, item.value),
                                         child: DefaultTextStyle(
                                           style: const TextStyle(
                                             fontFamily: 'Inter',
                                             fontSize: 15,
                                             color: AppColors.primaryDark,
                                           ),
                                           child: item.child,
                                         ),
                                       ),
                                     ),
                                   )
                                   .toList(),
                               cancelButton: CupertinoActionSheetAction(
                                 onPressed: () => Navigator.pop(sheet),
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
                           if (result != null && field.mounted) {
                             field.didChange(result);
                             onChanged(result);
                           }
                         },
                   child: Row(
                     children: [
                       Expanded(
                         child: DefaultTextStyle(
                           style: const TextStyle(
                             fontFamily: 'Inter',
                             fontSize: 14,
                             color: AppColors.textPrimary,
                           ),
                           maxLines: 1,
                           overflow: TextOverflow.ellipsis,
                           child: selected?.child ?? hint ?? const SizedBox(),
                         ),
                       ),
                       const SizedBox(width: 8),
                       const Icon(
                         CupertinoIcons.chevron_down,
                         color: AppColors.textMuted,
                         size: 14,
                       ),
                     ],
                   ),
                 ),
               ),
               if (field.hasError)
                 Padding(
                   padding: const EdgeInsets.only(top: 5),
                   child: Text(
                     field.errorText!,
                     style: const TextStyle(
                       fontFamily: 'Inter',
                       fontSize: 12,
                       color: AppColors.danger,
                     ),
                   ),
                 ),
             ],
           );
         },
       );
  @override
  FormFieldState<T> createState() => _BuyerPickerState<T>();
}

class _BuyerPickerState<T> extends FormFieldState<T> {
  @override
  void didUpdateWidget(covariant BuyerPicker<T> oldWidget) {
    super.didUpdateWidget(oldWidget);
    final picker = widget as BuyerPicker<T>;
    if (picker.value != oldWidget.value) setValue(picker.value);
  }
}
