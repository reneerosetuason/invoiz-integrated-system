/// Formatting helpers used across the app.
class FormatUtils {
  FormatUtils._();

  /// Formats a number as Philippine pesos: `850.0` -> `₱850.00`.
  static String peso(double value) {
    final parts = value.toStringAsFixed(2).split('.');
    final whole = parts[0];
    final digits = whole.replaceAll(RegExp(r'\D'), '');
    final buf = StringBuffer();
    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && (digits.length - i) % 3 == 0) buf.write(',');
      buf.write(digits[i]);
    }
    final neg = value < 0 ? '-' : '';
    return '$neg₱${buf.toString()}.${parts[1]}';
  }

  /// `2026-08-19T12:12:58+00:00` -> `Aug 19, 2026, 8:12 PM`
  static String dateTime(DateTime? dt) {
    if (dt == null) return '—';
    final local = dt.toLocal();
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    final hour12 = local.hour % 12 == 0 ? 12 : local.hour % 12;
    final period = local.hour < 12 ? 'AM' : 'PM';
    final minute = local.minute.toString().padLeft(2, '0');
    return '${months[local.month - 1]} ${local.day}, ${local.year}, '
        '$hour12:$minute $period';
  }

  /// `2026-08-19T12:12:58+00:00` -> `Aug 19, 2026`
  static String date(DateTime? dt) {
    if (dt == null) return '—';
    final local = dt.toLocal();
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    return '${months[local.month - 1]} ${local.day}, ${local.year}';
  }

  /// A short relative label: `Just now`, `5m ago`, `2h ago`.
  static String timeAgo(DateTime? dt) {
    if (dt == null) return '';
    final diff = DateTime.now().difference(dt.toLocal());
    if (diff.inSeconds < 60) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return date(dt);
  }
}