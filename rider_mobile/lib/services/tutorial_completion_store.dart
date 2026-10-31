import 'package:shared_preferences/shared_preferences.dart';

/// Stores tutorial completion once per installation, independently of rider.
/// Delivery Rider and Hub Transport Driver progress are tracked separately so one
/// workflow never hides the other's onboarding.
class TutorialCompletionStore {
  const TutorialCompletionStore();

  static const _deliveryKey = 'invoiz_delivery_rider_tutorial_completed';
  static const _courierKey = 'invoiz_hub_courier_tutorial_completed';
  static const _legacyPrefix = 'invoiz_rider_tutorial_completed_';

  Future<bool> isCompleted({
    int? riderId,
    String workflow = 'delivery_rider',
  }) async {
    final preferences = await SharedPreferences.getInstance();
    final key = workflow == 'hub_courier' ? _courierKey : _deliveryKey;
    if (preferences.getBool(key) == true) return true;

    // Preserve onboarding completion from older app versions that stored a
    // separate flag for each rider. Only the delivery workflow inherits it;
    // Hub Transport Driver is a new workflow and must see its own tutorial.
    final legacyCompletionExists = preferences.getKeys().any(
      (legacyKey) =>
          legacyKey.startsWith(_legacyPrefix) &&
          preferences.getBool(legacyKey) == true,
    );
    if (legacyCompletionExists) {
      await preferences.setBool(_deliveryKey, true);
    }
    return workflow == 'delivery_rider' && legacyCompletionExists;
  }

  Future<void> markCompleted({
    int? riderId,
    String workflow = 'delivery_rider',
  }) async {
    final preferences = await SharedPreferences.getInstance();
    await preferences.setBool(
      workflow == 'hub_courier' ? _courierKey : _deliveryKey,
      true,
    );
  }
}
