import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

/// Stores the last few products a buyer viewed so the home screen
/// can show a "Recently Viewed" row without extra API calls.
class RecentlyViewedService {
  static const _key = 'recently_viewed';
  static const _max = 12;

  static Future<List<Map<String, dynamic>>> load() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null) return [];
    try {
      final list = jsonDecode(raw) as List;
      return list.whereType<Map<String, dynamic>>().toList();
    } catch (_) {
      return [];
    }
  }

  static Future<void> record(Map<String, dynamic> product) async {
    final items = await load();
    items.removeWhere((e) => e['id'] == product['id']);
    items.insert(0, {
      'id': product['id'],
      'name': product['name'],
      'price': product['price'],
      'image': product['image'],
      'sold': product['sold'] ?? 0,
    });
    if (items.length > _max) {
      items.removeRange(_max, items.length);
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(items));
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }
}
