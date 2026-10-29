import 'package:flutter_test/flutter_test.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/utils/payment_utils.dart';

void main() {
  group('COD amount validation', () {
    test('accepts exact and cent-level matches', () {
      expect(PaymentUtils.codAmountMatches(500, 500), isTrue);
      expect(PaymentUtils.codAmountMatches(500.00, 500), isTrue);
      expect(PaymentUtils.codAmountMatches(499.995, 500), isTrue);
    });

    test('rejects short, over, missing and negative amounts', () {
      expect(PaymentUtils.codAmountMatches(499.00, 500), isFalse);
      expect(PaymentUtils.codAmountMatches(501.00, 500), isFalse);
      expect(PaymentUtils.codAmountMatches(null, 500), isFalse);
      expect(PaymentUtils.codAmountMatches(-500, 500), isFalse);
    });
  });
}
