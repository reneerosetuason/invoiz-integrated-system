import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config.dart';

class ApiService {
  final http.Client _client;
  ApiService({http.Client? client}) : _client = client ?? http.Client();
  static const _timeout = Duration(seconds: 25);
  static String? _token;

  static void setToken(String? token) => _token = token;
  static String? get token => _token;

  Map<String, String> _headers({bool json = true, String? contentType}) {
    final h = <String, String>{'Accept': 'application/json'};
    if (_token != null) h['Authorization'] = 'Bearer $_token';
    if (json) h['Content-Type'] = 'application/json';
    if (contentType != null) h['Content-Type'] = contentType;
    return h;
  }

  Uri _uri(String path, [Map<String, String>? query]) {
    final base = Uri.parse(AppConfig.apiBaseUrl);
    return base.replace(
      path: base.path.endsWith('/')
          ? '${base.path}$path'
          : '${base.path}/$path',
      queryParameters: query,
    );
  }

  // ---- Generic helpers ----

  Future<dynamic> get(String path, {Map<String, String>? query}) async {
    final res = await _client
        .get(_uri(path, query), headers: _headers())
        .timeout(_timeout);
    return _decode(res);
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    final res = await _client
        .post(_uri(path), headers: _headers(), body: jsonEncode(body))
        .timeout(_timeout);
    return _decode(res);
  }

  Future<dynamic> postForm(
    String path,
    Map<String, String> fields, {
    List<http.MultipartFile>? files,
  }) async {
    final req = http.MultipartRequest('POST', _uri(path));
    req.headers.addAll(_headers(json: false));
    fields.forEach((k, v) => req.fields[k] = v);
    if (files != null) req.files.addAll(files);
    final streamed = await _client.send(req).timeout(_timeout);
    final res = await http.Response.fromStream(streamed);
    return _decode(res);
  }

  Future<dynamic> put(String path, Map<String, dynamic> body) async {
    final res = await _client
        .put(_uri(path), headers: _headers(), body: jsonEncode(body))
        .timeout(_timeout);
    return _decode(res);
  }

  Future<dynamic> delete(String path) async {
    final res = await _client
        .delete(_uri(path), headers: _headers())
        .timeout(_timeout);
    return _decode(res);
  }

  Future<dynamic> request(
    String path, {
    required String method,
    Map<String, dynamic>? body,
  }) async {
    final uri = _uri(path);
    final headers = _headers();
    http.Response res;
    switch (method.toUpperCase()) {
      case 'GET':
        res = await _client.get(uri, headers: headers).timeout(_timeout);
        break;
      case 'POST':
        res = await _client
            .post(uri, headers: headers, body: jsonEncode(body ?? {}))
            .timeout(_timeout);
        break;
      case 'PUT':
        res = await _client
            .put(uri, headers: headers, body: jsonEncode(body ?? {}))
            .timeout(_timeout);
        break;
      case 'DELETE':
        res = await _client.delete(uri, headers: headers).timeout(_timeout);
        break;
      default:
        throw ArgumentError('Unsupported method: $method');
    }
    return _decode(res);
  }

  dynamic _decode(http.Response res) {
    dynamic body;
    try {
      body = res.body.isEmpty ? null : jsonDecode(utf8.decode(res.bodyBytes));
    } on FormatException {
      throw ApiException(
        statusCode: res.statusCode,
        message:
            'The server returned an unexpected response. Please try again.',
      );
    }
    if (res.statusCode >= 200 && res.statusCode < 300) {
      return body;
    }
    throw ApiException(
      statusCode: res.statusCode,
      message: (body is Map && body['message'] != null)
          ? body['message'] as String
          : 'Request failed (${res.statusCode})',
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
