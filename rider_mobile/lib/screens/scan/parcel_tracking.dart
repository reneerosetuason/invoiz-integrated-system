/// Validation for INVOIZ parcel tracking numbers scanned from shipping
/// labels (QR payload, barcode value, or manual entry).
///
/// The QR code printed by the Logistics Web shipping label encodes the
/// delivery's existing `tracking_number`. This pattern mirrors the backend
/// scan contract (`TRK-YYYYMMDD-XXXX`); the backend remains the source of
/// truth — a well-formed but unknown number still resolves through the
/// lookup endpoint ("No parcel found"), never locally.
class ParcelTracking {
  ParcelTracking._();

  /// INVOIZ waybill shape: `TRK-` + 8 digits + `-` + 4 alphanumerics.
  static final RegExp pattern = RegExp(r'^TRK-\d{8}-[A-Z0-9]{4}$');

  /// Normalize raw scanner payload (trim + uppercase). Returns empty string
  /// when there is nothing usable.
  static String normalize(String? raw) {
    if (raw == null) return '';
    return raw.trim().toUpperCase();
  }

  /// Whether [raw] is a usable INVOIZ tracking number after normalization.
  static bool isValid(String? raw) => pattern.hasMatch(normalize(raw));

  /// Normalized tracking number, or null when [raw] is not usable.
  static String? validated(String? raw) {
    final value = normalize(raw);
    return pattern.hasMatch(value) ? value : null;
  }
}
