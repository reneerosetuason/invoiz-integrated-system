/// Shared name normalization and validation for rider-facing forms.
///
/// The Laravel API remains authoritative; these helpers only keep the app from
/// submitting values the backend would reject.
class NameValidation {
  NameValidation._();

  static final _person = RegExp(
    r"^[\p{L}\p{M}]+(?:[\s'’.\-,]+[\p{L}\p{M}]+)*\.?$",
    unicode: true,
  );
  static final _business = RegExp(
    r"^[\p{L}\p{M}\p{N}][\p{L}\p{M}\p{N}\s&'’.,()+\-\/#]*$",
    unicode: true,
  );
  static final _control = RegExp(r'\p{Cc}', unicode: true);

  static String normalize(String value) =>
      value.trim().replaceAll(RegExp(r' {2,}'), ' ');

  static bool isValidPerson(String value, {int maxLength = 100}) {
    final name = normalize(value);
    return name.isNotEmpty &&
        name.runes.length <= maxLength &&
        !_control.hasMatch(name) &&
        _person.hasMatch(name);
  }

  static bool isValidBusiness(String value, {int maxLength = 255}) {
    final name = normalize(value);
    return name.isNotEmpty &&
        name.runes.length <= maxLength &&
        !_control.hasMatch(name) &&
        _business.hasMatch(name);
  }
}
