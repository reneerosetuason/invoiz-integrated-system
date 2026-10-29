import 'package:flutter/foundation.dart';

/// Keeps the shared cart badge in sync after successful cart or order changes.
class BuyerUpdates {
  static final inbox = ValueNotifier<int>(0);
  static void inboxChanged() => inbox.value++;
  static final cart = ValueNotifier<int>(0);
  static void cartChanged() => cart.value++;

  /// Fires whenever a product is saved to or removed from the wishlist so the
  /// header heart badge stays in step with the product cards.
  static final wishlist = ValueNotifier<int>(0);
  static void wishlistChanged() => wishlist.value++;
}
