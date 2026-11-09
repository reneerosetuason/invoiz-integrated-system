import 'dart:typed_data';

import '../core/network/api_client.dart';
import '../models/hub_courier.dart';

class HubCourierService {
  HubCourierService(this.api);
  final ApiClient api;

  Future<({String token, HubCourier courier})> login(
    String email,
    String password,
  ) async {
    final data = await api.post(
      '/courier/login',
      body: {'email': email, 'password': password},
    );
    return (
      token: data['token'] as String,
      courier: HubCourier.fromJson(
        Map<String, dynamic>.from(data['courier'] as Map),
      ),
    );
  }

  Future<HubCourier> profile() async {
    final d = await api.get('/courier/profile');
    return HubCourier.fromJson(Map<String, dynamic>.from(d['courier'] as Map));
  }

  Future<HubCourier> updateProfile({String? phone}) async {
    final d = await api.patch(
      '/courier/profile',
      body: {if (phone != null) 'phone': phone},
    );
    return HubCourier.fromJson(Map<String, dynamic>.from(d['courier'] as Map));
  }

  Future<HubCourier> uploadPhoto({
    required Uint8List fileBytes,
    required String filename,
  }) async {
    final d = await api.postMultipart(
      '/courier/profile/photo',
      fileField: 'photo',
      fileBytes: fileBytes,
      filename: filename,
    );
    return HubCourier.fromJson(Map<String, dynamic>.from(d['courier'] as Map));
  }

  Future<Map<String, dynamic>> dashboard() async {
    final data = await api.get('/courier/dashboard');
    if (data is! Map) {
      throw const FormatException(
        'The dashboard response was not a JSON object. Please retry.',
      );
    }
    return Map<String, dynamic>.from(data);
  }

  Future<void> setStatus(bool online) async {
    await api.patch(
      '/courier/status',
      body: {'status': online ? 'online' : 'offline'},
    );
  }

  Future<Map<String, dynamic>> earnings() async {
    final d = await api.get('/courier/earnings');
    return Map<String, dynamic>.from(d as Map);
  }

  Future<Map<String, dynamic>> remittances({int page = 1}) async {
    final data = await api.getSharedAuthenticated(
      'remittances/incoming?page=$page',
    );
    return Map<String, dynamic>.from(data as Map);
  }

  Future<void> acceptRemittance(int id, {String source = 'order'}) async {
    await api.postSharedAuthenticated(
      'remittances/incoming/$source/$id/accept',
      {'confirm_received': 1},
    );
  }

  Future<Map<String, dynamic>> reportIncident({
    required String incidentType,
    required String severity,
    required String description,
    String? location,
    String? damageDetails,
    int? hubTransferId,
  }) async {
    final d = await api.post(
      '/courier/incidents',
      body: {
        'incident_type': incidentType,
        'severity': severity,
        'description': description,
        if (location != null && location.isNotEmpty) 'location': location,
        if (damageDetails != null && damageDetails.isNotEmpty)
          'damage_details': damageDetails,
        if (hubTransferId != null) 'hub_transfer_id': hubTransferId,
      },
    );
    return Map<String, dynamic>.from(d as Map);
  }

  Future<Map<String, dynamic>> incidents() async {
    final d = await api.get('/courier/incidents');
    return Map<String, dynamic>.from(d as Map);
  }

  Future<HubTransferJob> transfer(int id) async {
    final d = await api.get('/courier/transfers/$id');
    return HubTransferJob(Map<String, dynamic>.from(d['transfer'] as Map));
  }

  Future<List<HubTransferJob>> transfers({String filter = 'all'}) async {
    final d = await api.get('/courier/transfers', query: {'filter': filter});
    final list = d['transfers'] as List? ?? [];
    return list
        .map((e) => HubTransferJob(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<Map<String, dynamic>> notifications() async {
    final d = await api.get('/courier/notifications');
    return Map<String, dynamic>.from(d as Map);
  }

  Future<void> markNotificationRead(int id) async {
    await api.patch('/courier/notifications/$id/read');
  }

  Future<void> markAllNotificationsRead() async {
    await api.patch('/courier/notifications/read-all');
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmPassword,
  }) async {
    await api.patch(
      '/courier/password',
      body: {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': confirmPassword,
      },
    );
  }

  Future<HubTransferJob> action(
    int id,
    String action, {
    Map<String, dynamic>? body,
  }) async {
    final d = await api.post('/courier/transfers/$id/$action', body: body);
    return HubTransferJob(Map<String, dynamic>.from(d['transfer'] as Map));
  }
}
