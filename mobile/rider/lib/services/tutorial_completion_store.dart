import 'package:shared_preferences/shared_preferences.dart';

/// Stores tutorial completion once per installation, independently of rider.
/// This local-only state is normally cleared when app data is cleared or the
/// app is reinstalled. Existing rider-specific completion flags are migrated.
class TutorialCompletionStore {
  const TutorialCompletionStore();

  static const _completionKey = 'invoiz_rider_tutorial_completed';
  static const _legacyPrefix = 'invoiz_rider_tutorial_completed_';

  Future<bool> isCompleted([int? riderId]) async {
    final preferences = await SharedPreferences.getInstance();
    if (preferences.getBool(_completionKey) == true) return true;

    // Preserve onboarding completion from older app versions that stored a
    // separate flag for each rider. Any completed rider means this install
    // has already seen the tutorial.
    final legacyCompletionExists = preferences.getKeys().any(
      (key) =>
          key.startsWith(_legacyPrefix) && preferences.getBool(key) == true,
    );
    if (legacyCompletionExists) {
      await preferences.setBool(_completionKey, true);
    }
    return legacyCompletionExists;
  }

  Future<void> markCompleted([int? riderId]) async {
    final preferences = await SharedPreferences.getInstance();
    await preferences.setBool(_completionKey, true);
  }
}
