import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import 'api_exception.dart';

/// Thin HTTP wrapper around the Laravel REST API.
class ApiClient {
  ApiClient({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;

  String? _token;

  /// Attach the bearer token to every request.
  void setToken(String? token) => _token = token;

  static const Duration _timeout = Duration(seconds: 30);
  static const Duration _uploadTimeout = Duration(seconds: 90);

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final base = AppConfig.apiBaseUrl;
    final clean = path.startsWith('/') ? path : '/$path';
    return Uri.parse('$base$clean')
        .replace(queryParameters: _stringifyQuery(query));
  }

  /// `Uri.queryParameters` only accepts `String` / `Iterable<String>` values.
  /// Callers pass ints (e.g. `page: 1`) and nulls (absent optional filters);
  /// forwarding those raw makes `Uri.replace` throw
  /// `type 'int' is not a subtype of type 'Iterable<dynamic>'` before any
  /// HTTP request is sent (this broke the Deliveries list).
  static Map<String, dynamic>? _stringifyQuery(Map<String, dynamic>? query) {
    if (query == null) return null;
    final out = <String, dynamic>{};
    query.forEach((key, value) {
      if (value == null) return; // drop absent optional filters
      if (value is String) {
        out[key] = value;
      } else if (value is Iterable) {
        out[key] = value.map((e) => e.toString()).toList();
      } else {
        out[key] = value.toString();
      }
    });
    return out;
  }

  Map<String, String> _headers({bool json = true}) {
    final headers = <String, String>{
      if (json) 'Accept': 'application/json',
      // Declare JSON so Laravel parses the JSON-encoded body into request
      // input. Without this, package:http defaults to text/plain and
      // $request->validate() sees no fields (e.g. "email field is required").
      if (json) 'Content-Type': 'application/json',
      if (_token != null && _token!.isNotEmpty)
        'Authorization': 'Bearer $_token',
    };
    return headers;
  }

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    final response = await _guard(
      () => _client.get(_uri(path, query), headers: _headers()).timeout(_timeout),
    );
    return _decode(response);
  }

  Future<dynamic> post(String path, {Map<String, dynamic>? body}) async {
    final response = await _guard(
      () => _client
          .post(_uri(path), headers: _headers(), body: jsonEncode(body ?? {}))
          .timeout(_timeout),
    );
    return _decode(response);
  }

  Future<dynamic> patch(String path, {Map<String, dynamic>? body}) async {
    final response = await _guard(
      () => _client
          .patch(_uri(path), headers: _headers(), body: jsonEncode(body ?? {}))
          .timeout(_timeout),
    );
    return _decode(response);
  }

  Future<dynamic> delete(String path) async {
    final response = await _guard(
      () => _client.delete(_uri(path), headers: _headers()).timeout(_timeout),
    );
    return _decode(response);
  }

  /// Multipart upload (used for proof-of-delivery photos). [fileBytes] are
  /// read by the caller so this works on every platform.
  Future<dynamic> postMultipart(
    String path, {
    required String fileField,
    required Uint8List fileBytes,
    required String filename,
    Map<String, String>? fields,
  }) async {
    final request = http.MultipartRequest('POST', _uri(path));

    request.headers.addAll(_headers(json: false));

    request.files.add(http.MultipartFile.fromBytes(
      fileField,
      fileBytes,
      filename: filename,
    ));

    if (fields != null) {
      fields.forEach((key, value) {
        request.fields.putIfAbsent(key, () => value);
      });
    }

    final streamed = await _guard(() async {
      final s = await _client.send(request).timeout(_uploadTimeout);
      return http.Response.fromStream(s);
    });

    return _decode(streamed);
  }

  /// Multipart with multiple files (rider application documents).
  Future<dynamic> postMultipartMany(
    String path, {
    required Map<String, ({Uint8List bytes, String filename})> files,
    Map<String, String>? fields,
  }) async {
    final request = http.MultipartRequest('POST', _uri(path));
    request.headers.addAll(_headers(json: false));
    files.forEach((field, data) {
      request.files.add(http.MultipartFile.fromBytes(field, data.bytes, filename: data.filename));
    });
    if (fields != null) {
      fields.forEach((k, v) => request.fields.putIfAbsent(k, () => v));
    }
    final streamed = await _guard(() async {
      final s = await _client.send(request).timeout(_uploadTimeout);
      return http.Response.fromStream(s);
    });
    return _decode(streamed);
  }

  Future<http.Response> _guard(Future<http.Response> Function() run) async {
    try {
      return await run();
    } on TimeoutException {
      throw const ApiException(
        type: ApiErrorType.timeout,
        message: 'The request timed out. Please try again.',
      );
    } on http.ClientException {
      throw ApiException(
        type: ApiErrorType.network,
        message:
            'Unable to reach the server. Please check your connection and try again.',
      );
    } on Exception catch (_) {
      throw const ApiException(
        type: ApiErrorType.network,
        message:
            'Unable to reach the server. Please check your connection and try again.',
      );
    }
  }

  dynamic _decode(http.Response response) {
    dynamic data;
    try {
      data = jsonDecode(utf8.decode(response.bodyBytes));
    } catch (_) {
      data = null;
    }

    final status = response.statusCode;

    if (status >= 200 && status < 300) {
      return data;
    }

    throw _toApiException(status, data);
  }

  ApiException _toApiException(int status, dynamic data) {
    final message = (data is Map && data['message'] != null)
        ? data['message'].toString()
        : 'Something went wrong. Please try again.';

    Map<String, List<String>>? errors;
    if (data is Map && data['errors'] is Map) {
      errors = (data['errors'] as Map).map(
        (key, value) => MapEntry(
          key.toString(),
          (value as List).map((e) => e.toString()).toList(),
        ),
      );
    }

    switch (status) {
      case 401:
        return ApiException(
          type: ApiErrorType.unauthorized,
          message: message,
          statusCode: status,
          errors: errors,
        );
      case 403:
        return ApiException(
          type: ApiErrorType.forbidden,
          message: message,
          statusCode: status,
          errors: errors,
        );
      case 404:
        return ApiException(
          type: ApiErrorType.notFound,
          message: message,
          statusCode: status,
          errors: errors,
        );
      case 409:
        return ApiException(
          type: ApiErrorType.conflict,
          message: message,
          statusCode: status,
          errors: errors,
        );
      case 422:
        return ApiException(
          type: ApiErrorType.validation,
          message: message,
          statusCode: status,
          errors: errors,
        );
      default:
        return ApiException(
          type: ApiErrorType.server,
          message: message,
          statusCode: status,
          errors: errors,
        );
    }
  }
}