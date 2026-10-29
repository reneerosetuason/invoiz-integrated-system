/// Shared, client-side input validation for rider-facing forms.
///
/// The Laravel API remains authoritative. These helpers only catch values the
/// backend would reject (or that are obviously mistyped) before a round trip,
/// and they intentionally accept every format the backend accepts today.
library;

import 'package:flutter/services.dart';

/// Uppercases plate/license input as the rider types. The transformation is
/// length-preserving for ASCII input, so the cursor stays in place.
class UpperCaseTextFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) => newValue.copyWith(text: newValue.text.toUpperCase());
}

class PhilippineMobile {
  PhilippineMobile._();

  static final _dials = RegExp(r'[\s\-()]');

  /// Canonical `09XXXXXXXXX` value, mirroring Laravel's `PhilippinePhone`
  /// rule. Returns the trimmed input when it cannot be normalized.
  static String normalize(String? value) {
    if (value == null) return '';
    var digits = value.trim().replaceAll(_dials, '');
    if (digits.startsWith('+63')) {
      digits = '0${digits.substring(3)}';
    } else if (digits.startsWith('63') && digits.length == 12) {
      digits = '0${digits.substring(2)}';
    } else if (digits.startsWith('9') && digits.length == 10) {
      digits = '0$digits';
    }
    return digits;
  }

  /// Backend-compatible shape: `09` + 9 digits (or `+639` + 9 digits).
  static bool isValid(String? value) {
    final normalized = normalize(value);
    return RegExp(r'^09\d{9}$').hasMatch(normalized);
  }

  static String? validate(String? value, {String field = 'phone number'}) {
    if (value == null || value.trim().isEmpty) {
      return 'Please enter your $field.';
    }
    if (!isValid(value)) {
      return 'Please enter a valid Philippine mobile number '
          '(09XXXXXXXXX or +639XXXXXXXXX).';
    }
    return null;
  }
}

/// LTO-style license plate / conduction sticker helpers.
///
/// Accepts the formats riders actually carry (e.g. `ABC 1234`, `AB 1234`,
/// `A 1234`, `1234 AB`, `1234`) without pretending to know every LTO series.
class LicensePlate {
  LicensePlate._();

  static String normalize(String? value) =>
      (value ?? '').trim().toUpperCase().replaceAll(RegExp(r'\s+'), ' ');

  static bool isValid(String? value) {
    final raw = normalize(value).replaceAll(RegExp(r'[\s-]'), '');
    if (raw.length < 4 || raw.length > 10) return false;
    if (RegExp(r'^\d{4,8}$').hasMatch(raw)) return true;
    // At least one letter and one digit, e.g. ABC1234 / 1234AB.
    return RegExp(r'^(?=.*[A-Z])(?=.*\d)[A-Z0-9]{4,8}$').hasMatch(raw);
  }

  static String? validate(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Please enter the license plate.';
    }
    if (!isValid(value)) {
      return 'Please enter a valid license plate (e.g. ABC 1234).';
    }
    return null;
  }
}

/// Driver's license number helpers.
///
/// Modern LTO numbers look like `N01-23-456789`; older and temporary numbers
/// vary, so the check is deliberately permissive while still rejecting
/// placeholder/garbage values.
class LicenseNumber {
  LicenseNumber._();

  static String normalize(String? value) =>
      (value ?? '').trim().toUpperCase().replaceAll(RegExp(r'\s+'), '');

  static bool isValid(String? value) {
    final compact = normalize(value).replaceAll('-', '');
    if (compact.length < 6 || compact.length > 20) return false;
    if (!RegExp(r'^[A-Z0-9]+$').hasMatch(compact)) return false;
    // Must contain at least one digit; a name is not a license number.
    return RegExp(r'\d').hasMatch(compact);
  }

  static String? validate(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Please enter the license number.';
    }
    if (!isValid(value)) {
      return 'Please enter a valid license number (e.g. N01-23-456789).';
    }
    return null;
  }
}

/// Vehicle registration (OR/CR) number.
class VehicleRegistration {
  VehicleRegistration._();

  static String normalize(String? value) =>
      (value ?? '').trim().toUpperCase().replaceAll(RegExp(r'\s+'), ' ');

  static bool isValid(String? value) {
    final value0 = normalize(value);
    return value0.length >= 2 &&
        value0.length <= 100 &&
        RegExp(r'^[A-Z0-9][A-Z0-9\s./-]*$').hasMatch(value0);
  }

  static String? validate(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Please enter the vehicle registration.';
    }
    if (!isValid(value)) {
      return 'Please enter a valid vehicle registration number.';
    }
    return null;
  }
}

/// Age helper used by the application form.
class AgeValidation {
  AgeValidation._();

  static int ageOn(DateTime birthday, {DateTime? now}) {
    final today = now ?? DateTime.now();
    var age = today.year - birthday.year;
    if (today.month < birthday.month ||
        (today.month == birthday.month && today.day < birthday.day)) {
      age--;
    }
    return age;
  }

  static bool isAtLeast18(DateTime birthday, {DateTime? now}) =>
      ageOn(birthday, now: now) >= 18;
}

/// Password rule shared by the change-password and reset forms.
class PasswordPolicy {
  PasswordPolicy._();

  static const minLength = 8;

  static String? validate(String? value, {String field = 'password'}) {
    if (value == null || value.isEmpty) {
      return 'Please enter your $field.';
    }
    if (value.length < minLength) {
      return 'Password must be at least $minLength characters.';
    }
    return null;
  }
}
