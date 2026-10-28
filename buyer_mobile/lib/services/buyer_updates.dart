import 'package:flutter/foundation.dart';

/// Keeps the shared cart badge in sync after successful cart or order changes.
class BuyerUpdates {
  static final inbox = ValueNotifier<int>(0);
  static void inboxChanged() => inbox.value++;
  static final cart = ValueNotifier<int>(0);
  static void cartChanged() => cart.value++;
}
