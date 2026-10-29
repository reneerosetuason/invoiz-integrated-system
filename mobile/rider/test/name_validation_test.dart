import 'package:flutter_test/flutter_test.dart';
import 'package:invoize_rider/core/utils/name_validation.dart';

void main() {
  group('NameValidation.normalize', () {
    test('trims and collapses repeated spaces', () {
      expect(
        NameValidation.normalize('  María   José  Rizal  '),
        'María José Rizal',
      );
      expect(NameValidation.normalize(' J&T   Express '), 'J&T Express');
      expect(NameValidation.normalize('   '), '');
    });
  });

  group('NameValidation.isValidPerson', () {
    test('accepts Unicode names and common punctuation', () {
      for (final name in [
        'María José',
        'Dela Cruz-Santos',
        "O'Connor",
        'O’Connor',
        'Juan Jr.',
        'Ñoño',
        "D'Angelo",
      ]) {
        expect(
          NameValidation.isValidPerson(name),
          isTrue,
          reason: 'Expected person name to pass: $name',
        );
      }
    });

    test('rejects digits, markup, control characters and overlong names', () {
      for (final name in [
        'Juan123',
        '<script>',
        'José@example.com',
        'Juan\nDela Cruz',
        'Juan_Dela Cruz',
        'A' * 101,
      ]) {
        expect(
          NameValidation.isValidPerson(name),
          isFalse,
          reason: 'Expected person name to fail: $name',
        );
      }
    });

    test('honours the caller max length', () {
      expect(NameValidation.isValidPerson('Juan', maxLength: 4), isTrue);
      expect(NameValidation.isValidPerson('Juana', maxLength: 4), isFalse);
      expect(NameValidation.isValidPerson('J', maxLength: 1), isTrue);
    });
  });

  group('NameValidation.isValidBusiness', () {
    test('accepts Unicode and common business punctuation', () {
      for (final name in [
        'J&T Express',
        '7-Eleven',
        "McDonald's",
        'Acme, Inc.',
        'A.B.C. Trading (Branch #2)',
        'Café 24/7',
      ]) {
        expect(
          NameValidation.isValidBusiness(name),
          isTrue,
          reason: 'Expected business name to pass: $name',
        );
      }
    });

    test('rejects markup, leading punctuation and control characters', () {
      for (final name in [
        '<script>',
        '-Leading Hyphen',
        'Acme; DROP TABLE users',
        'Acme\nInc.',
        'A' * 256,
      ]) {
        expect(
          NameValidation.isValidBusiness(name),
          isFalse,
          reason: 'Expected business name to fail: $name',
        );
      }
    });
  });
}
