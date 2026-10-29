import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Unsent proof-of-delivery values kept on the phone until the delivery is
/// actually accepted by the backend.
///
/// When the network drops mid-submit the rider must not lose the live photo,
/// OTP, COD amount or signature they already captured (RID-DLV-009 / M5).
class PodDraft {
  const PodDraft({
    this.photoPath,
    this.otp = '',
    this.amount = '',
    this.name = '',
  });

  /// Local file path of the live camera photo, when one was taken.
  final String? photoPath;
  final String otp;
  final String amount;
  final String name;

  bool get hasContent =>
      (photoPath != null && photoPath!.isNotEmpty) ||
      otp.isNotEmpty ||
      amount.isNotEmpty ||
      name.isNotEmpty;

  Map<String, dynamic> toJson() => {
    'photo_path': photoPath,
    'otp': otp,
    'amount': amount,
    'name': name,
  };

  factory PodDraft.fromJson(Map<String, dynamic> json) => PodDraft(
    photoPath: json['photo_path'] as String?,
    otp: json['otp'] as String? ?? '',
    amount: json['amount'] as String? ?? '',
    name: json['name'] as String? ?? '',
  );
}

class PodDraftStore {
  PodDraftStore({Future<SharedPreferences> Function()? preferences})
    : _preferences = preferences ?? SharedPreferences.getInstance;

  final Future<SharedPreferences> Function() _preferences;

  static String _key(int deliveryId) => 'pod_draft_$deliveryId';

  Future<PodDraft?> load(int deliveryId) async {
    try {
      final prefs = await _preferences();
      final raw = prefs.getString(_key(deliveryId));
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw);
      if (decoded is! Map<String, dynamic>) return null;
      final draft = PodDraft.fromJson(decoded);
      return draft.hasContent ? draft : null;
    } catch (_) {
      return null;
    }
  }

  Future<void> save(int deliveryId, PodDraft draft) async {
    try {
      final prefs = await _preferences();
      if (!draft.hasContent) {
        await prefs.remove(_key(deliveryId));
        return;
      }
      await prefs.setString(_key(deliveryId), jsonEncode(draft.toJson()));
    } catch (_) {
      // Draft persistence is best-effort; never block delivery completion.
    }
  }

  Future<void> clear(int deliveryId) async {
    try {
      final prefs = await _preferences();
      await prefs.remove(_key(deliveryId));
    } catch (_) {}
  }
}
