import 'dart:convert';

import 'package:http/http.dart' as http;

/// National PSGC response shapes, served from the shared /api/psgc route.
http.Response? psgcFixture(http.BaseRequest request) {
  final data = switch (request.url.path) {
    '/api/psgc/regions' => [
      {'code': '120000000', 'name': 'SOCCSKSARGEN'},
    ],
    '/api/psgc/regions/120000000/provinces' => [
      {'code': '126300000', 'name': 'South Cotabato'},
    ],
    '/api/psgc/provinces/126300000/cities-municipalities' => [
      {'code': '126303000', 'name': 'General Santos City'},
    ],
    '/api/psgc/cities-municipalities/126303000/barangays' => [
      {'code': '126303001', 'name': 'Barangay Poblacion'},
    ],
    _ => null,
  };
  return data == null
      ? null
      : http.Response(
          jsonEncode(data),
          200,
          headers: {'content-type': 'application/json'},
        );
}
