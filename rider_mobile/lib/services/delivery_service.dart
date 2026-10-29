import 'dart:typed_data';

import '../core/network/api_client.dart';
import '../models/delivery.dart';

/// Result of the delivery list (items + pagination).
class DeliveryPage {
  const DeliveryPage({required this.deliveries, required this.total});

  final List<Delivery> deliveries;
  final int total;
}

/// Delivery workflow service. All calls are authenticated as the rider and
/// the backend enforces ownership + the state machine.
class DeliveryService {
  DeliveryService(this._api);

  final ApiClient _api;

  /// List the rider's deliveries. [filter] can be:
  /// all | new | accepted | pickup | in_transit | delivered | failed | cancelled
  Future<DeliveryPage> list({String filter = 'all', int page = 1}) async {
    final data = await _api.get(
      '/rider/deliveries',
      query: {'status': filter, 'page': page},
    );

    List<Delivery> list = [];
    if (data['deliveries'] is List) {
      list = (data['deliveries'] as List)
          .whereType<Map<String, dynamic>>()
          .map(Delivery.fromJson)
          .toList();
    }

    final total =
        (data['pagination']?['total'] as num?)?.toInt() ?? list.length;

    return DeliveryPage(deliveries: list, total: total);
  }

  Future<Delivery> detail(int id) async {
    final data = await _api.get('/rider/deliveries/$id');
    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }

  /// Find the rider's delivery by the tracking number scanned from an INVOIZ
  /// shipping label (QR payload). The tracking number is the existing
  /// delivery identifier — scanning never creates one.
  ///
  /// The Sanctum-authenticated backend returns the same delivery payload as
  /// [detail] and enforces the same rider-ownership rules.
  Future<Delivery> lookupByTracking(String trackingNumber) async {
    final data = await _api.get(
      '/rider/deliveries/lookup',
      query: {'tracking_number': trackingNumber},
    );
    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }

  Future<Delivery> accept(int id) async {
    final data = await _api.post('/rider/deliveries/$id/accept');
    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }

  Future<Delivery> updateStatus(int id, String status) async {
    final data = await _api.patch(
      '/rider/deliveries/$id/status',
      body: {'status': status},
    );
    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }

  Future<Delivery> pickup(int id, {String? pickupPin}) async {
    final data = await _api.post(
      '/rider/deliveries/$id/pickup',
      body: {'pickup_pin': ?pickupPin},
    );
    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }

  /// Complete a delivery. Buyer-backed deliveries require the 6-digit [otp]
  /// issued by the backend; photo proof and COD settlement remain unchanged.
  Future<Delivery> complete(
    int id, {
    String proofType = 'signature',
    Uint8List? photoBytes,
    String? photoFilename,
    String? signatureName,
    String? otp,
    double? amountReceived,
    double? latitude,
    double? longitude,
  }) async {
    final fields = <String, String>{
      'proof_type': proofType,
      'signature_name': ?signatureName,
      'otp': ?otp,
      if (amountReceived != null)
        'amount_received': amountReceived.toStringAsFixed(2),
      if (latitude != null) 'latitude': latitude.toString(),
      if (longitude != null) 'longitude': longitude.toString(),
    };

    final dynamic data;
    if (photoBytes != null) {
      data = await _api.postMultipart(
        '/rider/deliveries/$id/complete',
        fileField: 'photo',
        fileBytes: photoBytes,
        filename: photoFilename ?? 'proof.jpg',
        fields: fields,
      );
    } else {
      data = await _api.post('/rider/deliveries/$id/complete', body: fields);
    }

    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }

  Future<Delivery> failed(
    int id, {
    required String reason,
    String? notes,
  }) async {
    final data = await _api.post(
      '/rider/deliveries/$id/failed',
      body: {
        'reason': reason,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      },
    );
    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }

  /// Request a re-attempt of a failed delivery. Status stays failed;
  /// Logistics reassigns through the existing flow.
  Future<Delivery> requestReattempt(int id) async {
    final data = await _api.post('/rider/deliveries/$id/reattempt');
    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }

  /// Return a failed parcel to the sorting center.
  Future<Delivery> returnToCenter(int id) async {
    final data = await _api.post('/rider/deliveries/$id/return-to-center');
    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }

  Future<Delivery> sortingCenterHandoff(int id) async {
    final data = await _api.post(
      '/rider/deliveries/$id/sorting-center-handoff',
    );
    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }

  Future<Delivery> sortingCenterPickup(int id) async {
    final data = await _api.post('/rider/deliveries/$id/sorting-center-pickup');
    return Delivery.fromJson(
      Map<String, dynamic>.from(data['delivery'] as Map? ?? {}),
    );
  }
}
