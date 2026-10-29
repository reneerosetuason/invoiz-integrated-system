import 'dart:typed_data';

import '../core/network/api_client.dart';
import '../core/network/api_exception.dart';
import '../core/utils/name_validation.dart';

/// Reference-number helpers shared by the apply + status screens.
class ApplicationReference {
  ApplicationReference._();

  /// Formats an application id as RID-YYYY-NNNN, e.g. RID-2026-0007.
  static String forId(int id) {
    final year = DateTime.now().year;
    return 'RID-$year-${id.toString().padLeft(4, '0')}';
  }
}

/// A submitted supporting document echoed back by the status endpoint.
class ApplicationStatusDoc {
  const ApplicationStatusDoc({required this.type, required this.name});
  final String type;
  final String name;

  factory ApplicationStatusDoc.fromJson(Map<String, dynamic> j) =>
      ApplicationStatusDoc(
        type: j['type'] as String? ?? '',
        name: j['name'] as String? ?? '',
      );

  String get label => type.replaceAll('_', ' ').toUpperCase();
}

class RiderApplicationStatus {
  const RiderApplicationStatus({
    required this.id,
    required this.name,
    required this.email,
    required this.status,
    this.submittedVia,
    this.riderType,
    this.vehicleOwnership,
    this.createdAt,
    this.reviewedAt,
    this.notes,
    this.documents = const [],
  });
  final int id;
  final String name;
  final String email;
  final String status;
  final String? submittedVia;
  final String? riderType;
  final String? vehicleOwnership;
  final String? createdAt;
  final String? reviewedAt;
  final String? notes;
  final List<ApplicationStatusDoc> documents;

  String get referenceNumber => ApplicationReference.forId(id);

  factory RiderApplicationStatus.fromJson(Map<String, dynamic> j) {
    List<ApplicationStatusDoc> docs = [];
    if (j['documents'] is List) {
      docs = (j['documents'] as List)
          .whereType<Map<String, dynamic>>()
          .map(ApplicationStatusDoc.fromJson)
          .toList();
    }
    return RiderApplicationStatus(
      id: (j['id'] as num?)?.toInt() ?? 0,
      name: j['name'] as String? ?? '',
      email: j['email'] as String? ?? '',
      status: j['status'] as String? ?? 'pending',
      submittedVia: j['submitted_via'] as String?,
      riderType: j['rider_type'] as String?,
      vehicleOwnership: j['vehicle_ownership'] as String?,
      createdAt: j['created_at'] as String?,
      reviewedAt: j['reviewed_at'] as String?,
      notes: j['notes'] as String?,
      documents: docs,
    );
  }
}

/// Result of a successful application submission.
class RiderApplicationSubmitResult {
  const RiderApplicationSubmitResult({
    required this.id,
    required this.status,
    required this.submittedVia,
  });
  final int id;
  final String status;
  final String? submittedVia;

  String get referenceNumber => ApplicationReference.forId(id);
}

class ApplicationService {
  ApplicationService(this._api);
  final ApiClient _api;

  Future<RiderApplicationSubmitResult> submit({
    required String name,
    required String email,
    required String phone,
    required String address,
    required String vehicleType,
    required String licensePlate,
    required String licenseNumber,
    required String licenseExpiry,
    required String vehicleRegistration,
    required String riderType,
    required String vehicleOwnership,
    required bool acceptedTerms,
    required Map<String, ({Uint8List bytes, String filename})> documents,
    String? middleInitial,
    String? sex,
    String? birthday,
    String? houseNumber,
    String? street,
    String? barangay,
    String? municipality,
    String? province,
  }) async {
    final fields = <String, String>{
      'name': NameValidation.normalize(name),
      'email': email,
      'phone': phone,
      'address': address,
      'vehicle_type': vehicleType,
      'license_plate': licensePlate,
      'license_number': licenseNumber,
      'license_expiry': licenseExpiry,
      'vehicle_registration': vehicleRegistration,
      'rider_type': riderType,
      'vehicle_ownership': vehicleOwnership,
      'accepted_terms': acceptedTerms ? '1' : '0',
      if (middleInitial != null && middleInitial.trim().isNotEmpty)
        'middle_initial': NameValidation.normalize(middleInitial),
      if (sex != null && sex.trim().isNotEmpty) 'sex': sex.trim(),
      if (birthday != null && birthday.trim().isNotEmpty)
        'birthday': birthday.trim(),
      if (houseNumber != null && houseNumber.trim().isNotEmpty)
        'house_number': houseNumber.trim(),
      if (street != null && street.trim().isNotEmpty) 'street': street.trim(),
      if (barangay != null && barangay.trim().isNotEmpty)
        'barangay': barangay.trim(),
      if (municipality != null && municipality.trim().isNotEmpty)
        'municipality': municipality.trim(),
      if (province != null && province.trim().isNotEmpty)
        'province': province.trim(),
    };
    final files = <String, ({Uint8List bytes, String filename})>{};
    documents.forEach((k, v) => files['documents[$k]'] = v);
    final data =
        await _api.postMultipartMany(
              '/rider/apply',
              files: files,
              fields: fields,
            )
            as Map<String, dynamic>;
    final app = (data['application'] as Map?) ?? {};
    return RiderApplicationSubmitResult(
      id: (app['id'] as num?)?.toInt() ?? 0,
      status: app['status'] as String? ?? 'pending',
      submittedVia: app['submitted_via'] as String?,
    );
  }

  /// Request a 6-digit email verification code (60s resend cooldown).
  /// Returns the backend message. Throws [ApiException] on failure.
  Future<String> requestEmailCode({required String email, String? name}) async {
    final data =
        await _api.post(
              '/rider/email/request-code',
              body: {
                'email': email,
                if (name != null && name.isNotEmpty)
                  'name': NameValidation.normalize(name),
              },
            )
            as Map<String, dynamic>;
    return data['message'] as String? ?? 'Verification code sent.';
  }

  /// Resend the code (invalidates the previous one). Same contract.
  Future<String> resendEmailCode({required String email}) async {
    final data =
        await _api.post('/rider/email/resend', body: {'email': email})
            as Map<String, dynamic>;
    return data['message'] as String? ?? 'Verification code sent.';
  }

  /// Verify the code. Returns the verified email address.
  /// Throws [ApiException] for invalid/expired/over-attempted codes.
  Future<String> verifyEmailCode({
    required String email,
    required String code,
  }) async {
    final data =
        await _api.post(
              '/rider/email/verify',
              body: {'email': email, 'code': code},
            )
            as Map<String, dynamic>;
    return data['email'] as String? ?? email;
  }

  Future<RiderApplicationStatus?> getStatus(String email) async {
    try {
      final data = await _api.get(
        '/rider/application-status',
        query: {'email': email},
      );
      final app = data['application'] as Map?;
      if (app == null) return null;
      return RiderApplicationStatus.fromJson(Map<String, dynamic>.from(app));
    } on ApiException catch (e) {
      if (e.type == ApiErrorType.notFound) return null;
      rethrow;
    }
  }
}
