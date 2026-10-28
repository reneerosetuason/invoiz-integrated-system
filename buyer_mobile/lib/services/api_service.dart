import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config.dart';

class ApiService {
  final http.Client _client;
  ApiService({http.Client? client}) : _client = client ?? http.Client();
  static const _timeout = Duration(seconds: 25);
  static String? _token;
  static void Function(String token)? onSessionInvalidated;

  static void setToken(String? token) => _token = token;
  static String? get token => _token;

  Uri _uri(String path, [Map<String, String>? query]) {
    final base = Uri.parse(AppConfig.apiBaseUrl);
    return base.replace(
      path: '${base.path.replaceFirst(RegExp(r'/$'), '')}/$path',
      queryParameters: query,
    );
  }

  Future<dynamic> _send(
    Future<http.Response> Function(Map<String, String>) send, {
    bool json = true,
  }) async {
    // A late failure from an old request must never clear a newer login.
    final requestToken = _token;
    final headers = <String, String>{
      'Accept': 'application/json',
      if (requestToken != null) 'Authorization': 'Bearer $requestToken',
      if (json) 'Content-Type': 'application/json',
    };
    final response = await send(headers).timeout(_timeout);
    return _decode(response, requestToken);
  }

  Future<dynamic> get(String path, {Map<String, String>? query}) =>
      _send((headers) => _client.get(_uri(path, query), headers: headers));
  Future<dynamic> post(String path, Map<String, dynamic> body) => _send(
    (headers) =>
        _client.post(_uri(path), headers: headers, body: jsonEncode(body)),
  );
  Future<dynamic> put(String path, Map<String, dynamic> body) => _send(
    (headers) =>
        _client.put(_uri(path), headers: headers, body: jsonEncode(body)),
  );
  Future<dynamic> delete(String path) =>
      _send((headers) => _client.delete(_uri(path), headers: headers));

  Future<dynamic> postForm(
    String path,
    Map<String, String> fields, {
    List<http.MultipartFile>? files,
  }) => _send((headers) async {
    final request = http.MultipartRequest('POST', _uri(path));
    request.headers.addAll(headers);
    request.fields.addAll(fields);
    request.files.addAll(files ?? []);
    // The timeout covers uploading AND consuming the response body.
    return http.Response.fromStream(await _client.send(request));
  }, json: false);

  Future<dynamic> request(
    String path, {
    required String method,
    Map<String, dynamic>? body,
  }) {
    switch (method.toUpperCase()) {
      case 'GET':
        return get(path);
      case 'POST':
        return post(path, body ?? {});
      case 'PUT':
        return put(path, body ?? {});
      case 'PATCH':
        return _send(
          (headers) => _client.patch(
            _uri(path),
            headers: headers,
            body: jsonEncode(body ?? {}),
          ),
        );
      case 'DELETE':
        return delete(path);
      default:
        throw ArgumentError('Unsupported method: $method');
    }
  }

  dynamic _decode(http.Response response, String? requestToken) {
    dynamic body;
    try {
      body = response.bodyBytes.isEmpty
          ? null
          : jsonDecode(utf8.decode(response.bodyBytes));
    } on FormatException {
      // Keep the status handling below even for an HTML expired-session page.
    }
    final invalidSession =
        response.statusCode == 401 ||
        (response.statusCode == 403 &&
            body is Map &&
            ['account_inactive', 'buyer_access_denied'].contains(body['code']));
    if (invalidSession && requestToken != null) {
      onSessionInvalidated?.call(requestToken);
    }
    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (body == null && response.bodyBytes.isNotEmpty) {
        throw ApiException(
          statusCode: response.statusCode,
          message:
              'The server returned an unexpected response. Please try again.',
        );
      }
      return body;
    }
    var message = 'Request failed (${response.statusCode}). Please try again.';
    if (body is Map) {
      if (body['message'] is String && (body['message'] as String).isNotEmpty)
        message = body['message'];
      // Show actionable field validation instead of only "The given data was invalid".
      if (response.statusCode == 422 && body['errors'] is Map) {
        final errors = (body['errors'] as Map).values
            .expand((value) => value is List ? value : [value])
            .whereType<String>()
            .toSet();
        if (errors.isNotEmpty) message = errors.take(3).join('\n');
      }
    }
    if (invalidSession && requestToken != null)
      message = 'Your session has ended. Please sign in again.';
    throw ApiException(
      statusCode: response.statusCode,
      message: message,
      data: body,
    );
  }
}

class ApiException implements Exception {
  final int statusCode;
  final String message;
  final dynamic data;
  ApiException({required this.statusCode, required this.message, this.data});
  @override
  String toString() => message;
}
