class JoinRequestModel {
  const JoinRequestModel({
    required this.id,
    required this.requestType,
    required this.fullName,
    required this.email,
    required this.phone,
    required this.city,
    required this.status,
    required this.createdAt,
    this.serviceCategory = '',
    this.rejectionReason,
  });

  final String id;
  final String requestType;
  final String fullName;
  final String email;
  final String phone;
  final String city;
  final String status;
  final DateTime? createdAt;
  final String serviceCategory;
  final String? rejectionReason;

  String get statusLabel => switch (status) {
        'approved' => 'مقبول - تم إنشاء حساب العمل',
        'rejected' => 'مرفوض',
        _ => 'قيد المراجعة',
      };

  factory JoinRequestModel.fromJson(Map<String, dynamic> json) {
    final category = json['service_category'] is Map
        ? Map<String, dynamic>.from(json['service_category'] as Map)
        : const <String, dynamic>{};
    return JoinRequestModel(
      id: '${json['id'] ?? ''}',
      requestType: (json['request_type'] ?? 'owner').toString(),
      fullName: (json['full_name'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      phone: (json['phone'] ?? '').toString(),
      city: (json['city'] ?? '').toString(),
      status: (json['status'] ?? 'pending').toString(),
      createdAt: DateTime.tryParse((json['created_at'] ?? '').toString()),
      serviceCategory: (category['name_ar'] ?? category['name_en'] ?? json['service_category'] ?? '').toString(),
      rejectionReason: json['rejection_reason']?.toString(),
    );
  }
}
