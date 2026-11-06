Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : const <String, dynamic>{};

String _text(dynamic value) => value == null ? '' : value.toString();

int? _id(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}

/// Resolved logistics identity returned by the backend after login.
///
/// The facility is authoritative: it is derived from the authenticated
/// account on the server, never from anything the app sends back.
class LogisticsAccount {
  const LogisticsAccount({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    required this.role,
    required this.scope,
    required this.canScan,
    this.companyId,
    this.companyName = '',
    this.facilityId,
    this.facilityName = '',
    this.facilityAddress = '',
    this.facilityCity = '',
  });

  final int id;
  final String name;
  final String email;
  final String phone;
  final String role;
  final String scope;
  final bool canScan;
  final int? companyId;
  final String companyName;
  final int? facilityId;
  final String facilityName;
  final String facilityAddress;
  final String facilityCity;

  bool get hasFacility => facilityId != null && facilityName.isNotEmpty;

  String get roleLabel => switch (role) {
    'platform_admin' => 'Platform Administrator',
    'logistics_admin' => 'Logistics Admin',
    'company_manager' => 'Company Manager',
    _ => 'Logistics Staff',
  };

  String get scopeLabel => switch (scope) {
    'global' => 'All hubs',
    'company' => 'Company-wide',
    _ => 'Assigned hub',
  };

  factory LogisticsAccount.fromJson(dynamic raw) {
    final root = _map(raw);
    final user = root['user'] is Map ? _map(root['user']) : root;
    final logistics = _map(root['logistics'] ?? user['logistics']);
    final facility = _map(logistics['facility'] ?? root['facility']);
    final company = _map(logistics['company'] ?? root['company']);

    return LogisticsAccount(
      id: _id(user['id']) ?? 0,
      name: _text(user['name']),
      email: _text(user['email']),
      phone: _text(user['phone']),
      role: _text(root['account_type'] ?? root['role'] ?? user['role']),
      scope: _text(logistics['scope'] ?? 'hub'),
      canScan: logistics['can_scan'] == true,
      companyId: _id(company['id']),
      companyName: _text(company['name']),
      facilityId: _id(facility['id']),
      facilityName: _text(facility['name']),
      facilityAddress: _text(facility['address']),
      facilityCity: _text(facility['city']),
    );
  }

  Map<String, dynamic> toJson() => {
    'account_type': role,
    'user': {
      'id': id,
      'name': name,
      'email': email,
      'phone': phone,
      'role': role,
      'account_type': role,
      'logistics': {
        'role': role,
        'scope': scope,
        'can_scan': canScan,
        'company': companyId == null
            ? null
            : {'id': companyId, 'name': companyName},
        'facility': facilityId == null
            ? null
            : {
                'id': facilityId,
                'name': facilityName,
                'address': facilityAddress,
                'city': facilityCity,
              },
      },
    },
  };
}
