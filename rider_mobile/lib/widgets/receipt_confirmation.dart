import 'package:flutter/cupertino.dart';

/// Receipt confirmation stays explicit; dismissing the alert never accepts money.
Future<bool?> showReceiptConfirmation(
  BuildContext context, {
  required String title,
  required String message,
  required String cancelLabel,
  required String confirmLabel,
}) => showCupertinoDialog<bool>(
  context: context,
  barrierDismissible: true,
  builder: (dialogContext) => CupertinoAlertDialog(
    title: Text(title),
    content: Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Text(message),
    ),
    actions: [
      CupertinoDialogAction(
        onPressed: () => Navigator.pop(dialogContext, false),
        child: Text(cancelLabel),
      ),
      CupertinoDialogAction(
        isDefaultAction: true,
        onPressed: () => Navigator.pop(dialogContext, true),
        child: Text(confirmLabel),
      ),
    ],
  ),
);
