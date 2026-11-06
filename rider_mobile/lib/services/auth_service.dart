import '../core/network/api_client.dart';
import '../models/hub_courier.dart';
import '../models/logistics_account.dart';
import '../models/rider.dart';

typedef AuthResult = ({
  String token,
  String accountType,
  Rider? rider,
  HubCourier? courier,
  LogisticsAccount? logistics,
});

const Set<String> _logisticsAccountTypes = {
  'logistics_staff',
  'logistics_admin',
  'company_manager',
  'platform_admin',
};

/// Authentication service (login / logout / session).
class AuthService {
  AuthService(this._api);

  final ApiClient _api;

  Future<AuthResult> login({
    required String email,
    required String password,
  }) async {
    final data = await _api.post('/login', body: {
      'email': email,
      'password': password,
    });

    final token = data['token'] as String? ?? '';
    if (token.isEmpty) {
      throw Exception('Login failed: no token returned.');
    }

    final accountType =
        data['account_type'] as String? ??
        (data['courier'] != null
            ? 'hub_transport_driver'
            : data['logistics'] != null
            ? 'logistics_staff'
            : 'delivery_rider');

    Rider? rider;
    HubCourier? courier;
    LogisticsAccount? logistics;

    if (_logisticsAccountTypes.contains(accountType) ||
        data['logistics'] != null) {
      logistics = LogisticsAccount.fromJson(data);
    } else if (accountType == 'hub_transport_driver' ||
        data['courier'] != null) {
      final courierMap = Map<String, dynamic>.from(
        data['courier'] as Map? ?? {},
      );
      courier = HubCourier.fromJson(courierMap);
    } else {
      final riderMap = Map<String, dynamic>.from(
        (data['user'] as Map?)?['rider'] as Map? ??
            (data['rider'] as Map? ?? {}),
      );
      rider = Rider.fromJson(riderMap);
    }

    return (
      token: token,
      accountType: accountType,
      rider: rider,
      courier: courier,
      logistics: logistics,
    );
  }

  Future<
    ({
      String accountType,
      Rider? rider,
      HubCourier? courier,
      LogisticsAccount? logistics,
    })
  >
  currentUser() async {
    final data = await _api.get('/user');
    final accountType =
        data['account_type'] as String? ??
        (data['courier'] != null
            ? 'hub_transport_driver'
            : data['logistics'] != null
            ? 'logistics_staff'
            : 'delivery_rider');

    Rider? rider;
    HubCourier? courier;
    LogisticsAccount? logistics;

    if (_logisticsAccountTypes.contains(accountType) ||
        data['logistics'] != null) {
      logistics = LogisticsAccount.fromJson(data);
    } else if (accountType == 'hub_transport_driver' ||
        data['courier'] != null) {
      final courierMap = Map<String, dynamic>.from(
        data['courier'] as Map? ?? {},
      );
      courier = HubCourier.fromJson(courierMap);
    } else {
      final riderMap = Map<String, dynamic>.from(
        (data['user'] as Map?)?['rider'] as Map? ??
            (data['rider'] as Map? ?? {}),
      );
      rider = Rider.fromJson(riderMap);
    }

    return (
      accountType: accountType,
      rider: rider,
      courier: courier,
      logistics: logistics,
    );
  }

  Future<void> logout() async {
    await _api.post('/logout');
  }

  /// Request a password reset email for a rider address.
  ///
  /// The backend always answers with the same generic message (even for
  /// unknown addresses), so the returned text is safe to display as-is.
  Future<String> requestPasswordReset({required String email}) async {
    final data = await _api.post('/rider/password/forgot', body: {
      'email': email,
    });
    final message = (data is Map ? data['message'] : null) as String?;
    return message ??
        'If an active rider account exists for that email address, a password reset link has been sent.';
  }

  /// Consume a reset token and set a new password. Throws [ApiException]
  /// with the backend's generic message for invalid/expired/used tokens —
  /// never revealing which account (if any) was involved.
  Future<String> resetPassword({
    required String email,
    required String token,
    required String newPassword,
  }) async {
    final data = await _api.post('/rider/password/reset', body: {
      'email': email,
      'token': token,
      'password': newPassword,
      'password_confirmation': newPassword,
    });
    final message = (data is Map ? data['message'] : null) as String?;
    return message ?? 'Password reset successfully.';
  }
}