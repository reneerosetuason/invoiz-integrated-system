class Seller {
  final int id;
  final int userId;
  final String businessName;
  final String lineOfBusiness;
  final String? idImage;
  final String? businessPermit;
  final String approvalStatus;
  final String status;

  Seller({
    required this.id,
    required this.userId,
    required this.businessName,
    required this.lineOfBusiness,
    this.idImage,
    this.businessPermit,
    required this.approvalStatus,
    required this.status,
  });

  bool get isApproved => approvalStatus == 'approved' && status == 'active';
  bool get isPending => approvalStatus == 'pending';

  factory Seller.fromJson(Map<String, dynamic> json) => Seller(
    id: json['id'] as int,
    userId: json['user_id'] as int? ?? 0,
    businessName: json['business_name'] as String? ?? '',
    lineOfBusiness: json['line_of_business'] as String? ?? '',
    idImage: json['id_image'] as String?,
    businessPermit: json['business_permit'] as String?,
    approvalStatus: json['approval_status'] as String? ?? 'pending',
    status: json['status'] as String? ?? 'active',
  );
}
