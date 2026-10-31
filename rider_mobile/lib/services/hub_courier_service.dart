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

  Future<Map<String, dynamic>> dashboard() async =>
      Map<String, dynamic>.from(await api.get('/courier/dashboard') as Map);
  Future<void> setStatus(bool online) async {
    await api.patch(
      '/courier/status',
      body: {'status': online ? 'online' : 'offline'},
    );
  }

  Future<HubTransferJob> transfer(int id) async {
    final d = await api.get('/courier/transfers/$id');
    return HubTransferJob(Map<String, dynamic>.from(d['transfer'] as Map));
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
