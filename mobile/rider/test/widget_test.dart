import 'package:flutter_test/flutter_test.dart';

import 'package:invoize_rider/core/utils/format_utils.dart';

void main() {
  test('peso formats whole numbers with separators', () {
    expect(FormatUtils.peso(850), '₱850.00');
    expect(FormatUtils.peso(1850), '₱1,850.00');
  });

  test('peso formats decimals', () {
    expect(FormatUtils.peso(1234.5), '₱1,234.50');
  });

  test('date returns an em dash for null', () {
    expect(FormatUtils.date(null), '—');
  });
}