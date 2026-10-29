class PaymentUtils {
  static const double _tolerance = 0.009;

  /// COD settlements must match the amount the customer was asked to pay.
  static bool codAmountMatches(double? received, double expected) {
    if (received == null) return false;
    return (received - expected).abs() < _tolerance;
  }
}
