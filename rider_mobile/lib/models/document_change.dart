import 'rider.dart';

/// A document type the rider can request to replace.
class DocumentTypeOption {
  const DocumentTypeOption({
    required this.value,
    required this.label,
    this.hasCurrent = false,
    this.pending = false,
  });

  final String value;
  final String label;
  final bool hasCurrent;
  final bool pending;

  factory DocumentTypeOption.fromJson(Map<String, dynamic> json) {
    return DocumentTypeOption(
      value: json['value'] as String? ?? '',
      label: json['label'] as String? ?? '',
      hasCurrent: json['has_current'] as bool? ?? false,
      pending: json['pending'] as bool? ?? false,
    );
  }
}

/// A privately stored file attached to a change request.
class DocumentChangeFile {
  const DocumentChangeFile({
    required this.name,
    this.mimeType,
    this.isImage = false,
    this.size,
    this.exists = true,
    this.url,
  });

  final String name;
  final String? mimeType;
  final bool isImage;
  final String? size;
  final bool exists;
  final String? url;

  factory DocumentChangeFile.fromJson(Map<String, dynamic> json) {
    return DocumentChangeFile(
      name: json['name'] as String? ?? '',
      mimeType: json['mime_type'] as String?,
      isImage: json['is_image'] as bool? ?? false,
      size: json['size'] as String?,
      exists: json['exists'] as bool? ?? true,
      url: json['url'] as String?,
    );
  }
}

/// A rider's proposed document replacement and its review state.
class DocumentChangeRequest {
  const DocumentChangeRequest({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.status,
    required this.statusLabel,
    this.reason,
    this.rejectionReason,
    this.proposedExpiry,
    this.previousExpiry,
    this.submittedAt,
    this.reviewedAt,
    this.reviewerName,
    this.canResubmit = false,
    this.document,
    this.previousDocument,
  });

  final int id;
  final String type;
  final String typeLabel;
  final String status;
  final String statusLabel;
  final String? reason;
  final String? rejectionReason;
  final String? proposedExpiry;
  final String? previousExpiry;
  final String? submittedAt;
  final String? reviewedAt;
  final String? reviewerName;
  final bool canResubmit;
  final DocumentChangeFile? document;
  final DocumentChangeFile? previousDocument;

  bool get isPending => status == 'pending';
  bool get isApproved => status == 'approved';
  bool get isRejected => status == 'rejected';

  factory DocumentChangeRequest.fromJson(Map<String, dynamic> json) {
    return DocumentChangeRequest(
      id: (json['id'] as num?)?.toInt() ?? 0,
      type: json['type'] as String? ?? '',
      typeLabel: json['type_label'] as String? ?? '',
      status: json['status'] as String? ?? 'pending',
      statusLabel: json['status_label'] as String? ?? 'Pending Approval',
      reason: json['reason'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      proposedExpiry: json['proposed_expiry'] as String?,
      previousExpiry: json['previous_expiry'] as String?,
      submittedAt: json['submitted_at'] as String?,
      reviewedAt: json['reviewed_at'] as String?,
      reviewerName: json['reviewer_name'] as String?,
      canResubmit: json['can_resubmit'] as bool? ?? false,
      document: json['document'] is Map
          ? DocumentChangeFile.fromJson(
              Map<String, dynamic>.from(json['document'] as Map),
            )
          : null,
      previousDocument: json['previous_document'] is Map
          ? DocumentChangeFile.fromJson(
              Map<String, dynamic>.from(json['previous_document'] as Map),
            )
          : null,
    );
  }
}

/// License compliance summary returned with the My Documents overview.
class DocumentLicenseStatus {
  const DocumentLicenseStatus({
    this.expiresOn,
    this.status = 'missing',
    this.expired = false,
    this.expiringSoon = false,
    this.pendingChange = false,
    this.canGoOnline = true,
    this.blockReason,
  });

  final String? expiresOn;
  final String status;
  final bool expired;
  final bool expiringSoon;
  final bool pendingChange;
  final bool canGoOnline;
  final String? blockReason;

  factory DocumentLicenseStatus.fromJson(Map<String, dynamic> json) {
    return DocumentLicenseStatus(
      expiresOn: json['expires_on'] as String?,
      status: json['status'] as String? ?? 'missing',
      expired: json['expired'] as bool? ?? false,
      expiringSoon: json['expiring_soon'] as bool? ?? false,
      pendingChange: json['pending_change'] as bool? ?? false,
      canGoOnline: json['can_go_online'] as bool? ?? true,
      blockReason: json['block_reason'] as String?,
    );
  }
}

/// Everything the My Documents screen needs in one response.
class DocumentChangeOverview {
  const DocumentChangeOverview({
    required this.documents,
    required this.requests,
    required this.documentTypes,
    required this.license,
  });

  final List<RiderDocument> documents;
  final List<DocumentChangeRequest> requests;
  final List<DocumentTypeOption> documentTypes;
  final DocumentLicenseStatus license;

  bool get hasPendingRequests => requests.any((r) => r.isPending);

  factory DocumentChangeOverview.fromJson(Map<String, dynamic> json) {
    List<T> parseList<T>(Object? raw, T Function(Map<String, dynamic>) parse) {
      if (raw is! List) return const [];
      return raw
          .whereType<Map>()
          .map((e) => parse(Map<String, dynamic>.from(e)))
          .toList();
    }

    return DocumentChangeOverview(
      documents: parseList(json['documents'], RiderDocument.fromJson),
      requests: parseList(json['requests'], DocumentChangeRequest.fromJson),
      documentTypes: parseList(
        json['document_types'],
        DocumentTypeOption.fromJson,
      ),
      license: json['license'] is Map
          ? DocumentLicenseStatus.fromJson(
              Map<String, dynamic>.from(json['license'] as Map),
            )
          : const DocumentLicenseStatus(),
    );
  }
}
