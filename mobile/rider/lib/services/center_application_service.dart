import 'dart:typed_data';

import '../core/network/api_client.dart';
import '../core/network/api_exception.dart';
import '../core/utils/name_validation.dart';

/// Formats a center application id as CEN-YYYY-NNNN, e.g. CEN-2026-0001.
class CenterApplicationReference {
  CenterApplicationReference._();

  static String forId(int id) {
    final year = DateTime.now().year;
    return 'CEN-$year-${id.toString().padLeft(4, '0')}';
  }
}

/// A submitted supporting document echoed back by the status endpoint.
class CenterApplicationStatusDoc {
  const CenterApplicationStatusDoc({required this.type, required this.name});
  final String type;
  final String name;

  factory CenterApplicationStatusDoc.fromJson(Map<String, dynamic> j) =>
      CenterApplicationStatusDoc(
        type: j['type'] as String? ?? '',
        name: j['name'] as String? ?? '',
      );

  String get label => type.replaceAll('_', ' ').toUpperCase();
}

class CenterApplicationStatus {
  const CenterApplicationStatus({
    required this.id,
    required this.businessName,
    required this.ownerName,
    required this.email,
    required this.status,
    this.submittedVia,
    this.createdAt,
    this.reviewedAt,
    this.provisionedAt,
    this.notes,
    this.documents = const [],
  });
  final int id;
  final String businessName;
  final String ownerName;
  final String email;
  final String status;
  final String? submittedVia;
  final String? createdAt;
  final String? reviewedAt;
  final String? provisionedAt;
  final String? notes;
  final List<CenterApplicationStatusDoc> documents;

  String get referenceNumber => CenterApplicationReference.forId(id);

  factory CenterApplicationStatus.fromJson(Map<String, dynamic> j) {
    List<CenterApplicationStatusDoc> docs = [];
    if (j['documents'] is List) {
      docs = (j['documents'] as List)
          .whereType<Map<String, dynamic>>()
          .map(CenterApplicationStatusDoc.fromJson)
          .toList();
    }
    return CenterApplicationStatus(
      id: (j['id'] as num?)?.toInt() ?? 0,
      businessName: j['business_name'] as String? ?? '',
      ownerName: j['owner_name'] as String? ?? '',
      email: j['email'] as String? ?? '',
      status: j['status'] as String? ?? 'pending',
      submittedVia: j['submitted_via'] as String?,
      createdAt: j['created_at'] as String?,
      reviewedAt: j['reviewed_at'] as String?,
      provisionedAt: j['provisioned_at'] as String?,
      notes: j['notes'] as String?,
      documents: docs,
    );
  }
}

/// Result of a successful center application submission.
class CenterApplicationSubmitResult {
  const CenterApplicationSubmitResult({
    required this.id,
    required this.status,
    required this.submittedVia,
  });
  final int id;
  final String status;
  final String? submittedVia;

  String get referenceNumber => CenterApplicationReference.forId(id);
}

class CenterApplicationService {
  CenterApplicationService(this._api);
  final ApiClient _api;

  Future<CenterApplicationSubmitResult> submit({
    required String businessName,
    required String ownerFirstName,
    String ownerMiddleName = '',
    required String ownerLastName,
    required String secNumber,
    required String tin,
    required String postalCode,
    required String region,
    required String email,
    required String phone,
    required String address,
    required Map<String, ({Uint8List bytes, String filename})> documents,
    String? houseNumber,
    String? street,
    String? barangay,
    String? municipality,
    String? province,
  }) async {
    final fields = <String, String>{
      'business_name': NameValidation.normalize(businessName),
      'owner_first_name': NameValidation.normalize(ownerFirstName),
      if (ownerMiddleName.trim().isNotEmpty)
        'owner_middle_name': NameValidation.normalize(ownerMiddleName),
      'owner_last_name': NameValidation.normalize(ownerLastName),
      'sec_number': secNumber,
      'tin': tin,
      'postal_code': postalCode,
      'region': region,
      'email': email,
      'phone': phone,
      'address': address,
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
              '/center/apply',
              files: files,
              fields: fields,
            )
            as Map<String, dynamic>;
    final app = (data['application'] as Map?) ?? {};
    return CenterApplicationSubmitResult(
      id: (app['id'] as num?)?.toInt() ?? 0,
      status: app['status'] as String? ?? 'pending',
      submittedVia: app['submitted_via'] as String?,
    );
  }

  Future<CenterApplicationStatus?> getStatus(String email) async {
    try {
      final data = await _api.get(
        '/center/application-status',
        query: {'email': email},
      );
      final app = data['application'] as Map?;
      if (app == null) return null;
      return CenterApplicationStatus.fromJson(Map<String, dynamic>.from(app));
    } on ApiException catch (e) {
      if (e.type == ApiErrorType.notFound) return null;
      rethrow;
    }
  }
}
