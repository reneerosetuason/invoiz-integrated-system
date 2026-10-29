/// Global application constants.
class AppConstants {
  AppConstants._();

  static const String appName = 'Invoize Rider';

  static const String tokenStorageKey = 'invoize_auth_token';
  static const String userCacheKey = 'invoize_user_cache';

  /// How often the app syncs the rider's live GPS position (seconds).
  static const int locationUpdateInterval = 15;

  /// Delivery status values returned by the API.
  static const List<String> deliveryStatuses = [
    'waiting_for_rider',
    'assigned',
    'accepted',
    'going_to_pickup',
    'arrived_at_shop',
    'picked_up',
    'out_for_delivery',
    'arrived_at_customer',
    'delivered',
    'delivery_failed',
    'cancelled',
  ];

  /// Default compensation used by the backend when no delivery fee is set.
  static const double defaultDeliveryFee = 50.0;

  /// Reasons a delivery can fail.
  static const List<String> failureReasons = [
    'Customer unavailable',
    'Incorrect address',
    'Customer refused order',
    'Unable to contact customer',
    'Shop/package issue',
    'Other',
  ];
}