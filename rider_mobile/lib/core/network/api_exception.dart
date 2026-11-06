/// Application error types raised by the API client.
enum ApiErrorType {
  network,
  unauthorized,
  forbidden,
  notFound,
  conflict,
  validation,
  server,
  timeout,
}

/// A structured API error with a user-friendly message.
class ApiException implements Exception {
  const ApiException({
    required this.type,
    required this.message,
    this.statusCode,
    this.errors,
    this.data,
  });

  final ApiErrorType type;
  final String message;
  final int? statusCode;
  final Map<String, List<String>>? errors;

  /// Parsed response body for error statuses that carry structured detail
  /// (for example a scan preview with the parcel's current status).
  final Map<String, dynamic>? data;

  @override
  String toString() => 'ApiException(${type.name}): $message';
}