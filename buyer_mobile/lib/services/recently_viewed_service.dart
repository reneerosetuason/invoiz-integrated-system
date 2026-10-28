import 'dart:convert';
import '../config.dart';
import 'api_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Stores the last few products a buyer viewed so the home screen
/// can show a "Recently Viewed" row. Refresh against the live catalog before display.
class RecentlyViewedService {
  static String get _key => 'invoiz:${AppConfig.apiBaseUrl}:recently_viewed';
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

  static Future<List<Map<String, dynamic>>> available(ApiService api) async {
    final saved = (await load())
        .where((p) => p['id'] is int && (p['id'] as int) > 0)
        .take(_max)
        .toList();
    if (saved.isEmpty) return [];
    try {
      final result = await api.get(
        'products',
        query: {'ids': saved.map((p) => p['id']).join(','), 'per_page': '12'},
      );
      final live = {
        for (final p
            in (result['data'] as List).whereType<Map<String, dynamic>>())
          p['id']: p,
      };
      final recent = [
        for (final p in saved)
          if (live.containsKey(p['id'])) live[p['id']]!,
      ];
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_key, jsonEncode(recent));
      return recent;
    } catch (_) {
      // Do not advertise a removed or unavailable product from stale local data.
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
      'effective_price': product['effective_price'],
      'stock': product['stock'] ?? 0,
      'rating': product['rating'],
      'gallery': product['gallery'] ?? [],
      'discount_percent': product['discount_percent'] ?? 0,
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
