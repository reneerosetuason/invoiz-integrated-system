import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/utils/file_validation.dart';
import '../lib/core/utils/input_validation.dart';

Uint8List _bytes(List<int> header, {int size = 32}) {
  final out = Uint8List(size < header.length ? header.length : size);
  out.setRange(0, header.length, header);
  for (var i = header.length; i < out.length; i++) {
    out[i] = 0x20;
  }
  return out;
}

final _pdf = _bytes([0x25, 0x50, 0x44, 0x46, 0x2D, 0x31, 0x2E, 0x37]);
final _jpg = _bytes([0xFF, 0xD8, 0xFF, 0xE0]);
final _png = _bytes([0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A]);
final _webp = _bytes([
  0x52, 0x49, 0x46, 0x46, 0x00, 0x00, 0x00, 0x00, //
  0x57, 0x45, 0x42, 0x50,
]);

void main() {
  group('PhilippineMobile', () {
    test('accepts 09 and +639 forms and normalizes to 09', () {
      expect(PhilippineMobile.isValid('09171234567'), isTrue);
      expect(PhilippineMobile.isValid('+63 917 123 4567'), isTrue);
      expect(PhilippineMobile.normalize('+63 917 123 4567'), '09171234567');
      expect(PhilippineMobile.normalize('639171234567'), '09171234567');
      expect(PhilippineMobile.normalize('9171234567'), '09171234567');
    });

    test('rejects wrong lengths, prefixes and non-phone text', () {
      expect(PhilippineMobile.isValid(null), isFalse);
      expect(PhilippineMobile.isValid(''), isFalse);
      expect(PhilippineMobile.isValid('0917123456'), isFalse);
      expect(PhilippineMobile.isValid('08171234567'), isFalse);
      expect(PhilippineMobile.isValid('12345'), isFalse);
      expect(PhilippineMobile.isValid('0917-CALL-NOW'), isFalse);
    });

    test('validation message is actionable', () {
      expect(
        PhilippineMobile.validate('12345'),
        contains('09XXXXXXXXX or +639XXXXXXXXX'),
      );
      expect(PhilippineMobile.validate(''), 'Please enter your phone number.');
    });
  });

  group('LicensePlate', () {
    test('accepts common PH plate shapes', () {
      expect(LicensePlate.isValid('ABC 1234'), isTrue);
      expect(LicensePlate.isValid('abc1234'), isTrue);
      expect(LicensePlate.isValid('AB 1234'), isTrue);
      expect(LicensePlate.isValid('1234 AB'), isTrue);
      expect(LicensePlate.isValid('1234'), isTrue);
      expect(LicensePlate.isValid('ABC-1234'), isTrue);
      expect(LicensePlate.normalize('  abc  1234 '), 'ABC 1234');
    });

    test('rejects too-short, punctuation-only and letter-only plates', () {
      expect(LicensePlate.isValid(''), isFalse);
      expect(LicensePlate.isValid('A1'), isFalse);
      expect(LicensePlate.isValid('!!!!'), isFalse);
      expect(LicensePlate.isValid('ABCDEFGH'), isFalse);
      expect(LicensePlate.isValid('12345678901'), isFalse);
    });

    test('empty plate gets the required message', () {
      expect(LicensePlate.validate(' '), 'Please enter the license plate.');
    });
  });

  group('LicenseNumber', () {
    test('accepts modern, legacy and temporary LTO numbers', () {
      expect(LicenseNumber.isValid('N01-23-456789'), isTrue);
      expect(LicenseNumber.isValid('L123456'), isTrue);
      expect(LicenseNumber.isValid('12345678'), isTrue);
      expect(LicenseNumber.normalize(' n01-23-456789 '), 'N01-23-456789');
    });

    test('rejects short, empty and non-numeric identifiers', () {
      expect(LicenseNumber.isValid(''), isFalse);
      expect(LicenseNumber.isValid('ABC'), isFalse);
      expect(LicenseNumber.isValid('ABCDEFG'), isFalse);
      expect(LicenseNumber.isValid('12'), isFalse);
    });
  });

  group('VehicleRegistration', () {
    test('accepts OR/CR style numbers and rejects symbols-only input', () {
      expect(VehicleRegistration.isValid('REG-1'), isTrue);
      expect(VehicleRegistration.isValid('1234567890'), isTrue);
      expect(VehicleRegistration.isValid(''), isFalse);
      expect(VehicleRegistration.isValid('---'), isFalse);
    });
  });

  group('AgeValidation', () {
    final now = DateTime(2026, 10, 28);

    test('18th birthday today is old enough', () {
      expect(
        AgeValidation.isAtLeast18(DateTime(2008, 10, 28), now: now),
        isTrue,
      );
    });

    test('one day short is not old enough', () {
      expect(
        AgeValidation.isAtLeast18(DateTime(2008, 10, 29), now: now),
        isFalse,
      );
    });
  });

  group('PasswordPolicy', () {
    test('enforces the backend minimum of 8 characters', () {
      expect(PasswordPolicy.validate('short'), contains('at least 8'));
      expect(PasswordPolicy.validate('longenough'), isNull);
      expect(PasswordPolicy.validate(''), 'Please enter your password.');
    });
  });

  group('FileContentValidation documents', () {
    test('accepts real signatures for each supported extension', () {
      expect(
        FileContentValidation.validateDocument(
          filename: 'license.pdf',
          bytes: _pdf,
        ).isValid,
        isTrue,
      );
      expect(
        FileContentValidation.validateDocument(
          filename: 'id.jpg',
          bytes: _jpg,
        ).isValid,
        isTrue,
      );
      expect(
        FileContentValidation.validateDocument(
          filename: 'id.jpeg',
          bytes: _jpg,
        ).isValid,
        isTrue,
      );
      expect(
        FileContentValidation.validateDocument(
          filename: 'id.png',
          bytes: _png,
        ).isValid,
        isTrue,
      );
    });

    test('rejects wrong extension, empty and oversized files', () {
      final wrongType = FileContentValidation.validateDocument(
        filename: 'license.pdf',
        bytes: _jpg,
      );
      expect(wrongType.problem, FileProblem.contentMismatch);

      expect(
        FileContentValidation.validateDocument(
          filename: 'license.pdf',
          bytes: Uint8List(0),
        ).problem,
        FileProblem.empty,
      );

      final oversized = Uint8List(FileContentValidation.maxDocumentBytes + 1)
        ..setRange(0, _pdf.length, _pdf);
      final tooLarge = FileContentValidation.validateDocument(
        filename: 'license.pdf',
        bytes: oversized,
      );
      expect(tooLarge.problem, FileProblem.tooLarge);
      expect(tooLarge.message, contains('5 MB'));

      expect(
        FileContentValidation.validateDocument(
          filename: 'payload.exe',
          bytes: _pdf,
        ).problem,
        FileProblem.unsupportedType,
      );
    });
  });

  group('FileContentValidation photos', () {
    test('accepts jpeg, png and webp under 8 MB', () {
      for (final (name, bytes) in [
        ('photo.jpg', _jpg),
        ('photo.png', _png),
        ('photo.webp', _webp),
      ]) {
        expect(
          FileContentValidation.validatePhoto(
            filename: name,
            bytes: bytes,
          ).isValid,
          isTrue,
        );
      }
    });

    test('rejects non-image bytes and oversized photos', () {
      expect(
        FileContentValidation.validatePhoto(
          filename: 'photo.jpg',
          bytes: _pdf,
        ).problem,
        FileProblem.contentMismatch,
      );
      final oversized = Uint8List(FileContentValidation.maxPhotoBytes + 1)
        ..setRange(0, _jpg.length, _jpg);
      expect(
        FileContentValidation.validatePhoto(
          filename: 'photo.jpg',
          bytes: oversized,
        ).problem,
        FileProblem.tooLarge,
      );
    });
  });
}
