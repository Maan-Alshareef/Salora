enum ComplaintStatus { open, inReview, resolved }

extension ComplaintStatusLabel on ComplaintStatus {
  String get label {
    switch (this) {
      case ComplaintStatus.open:
        return 'مفتوحة';
      case ComplaintStatus.inReview:
        return 'قيد المراجعة';
      case ComplaintStatus.resolved:
        return 'مغلقة';
    }
  }
}

class ComplaintModel {
  final String id;
  final String referenceNumber;
  final String subject;
  final String type;
  final String description;
  final String bookingId;
  final DateTime createdAt;
  final ComplaintStatus status;

  const ComplaintModel({
    required this.id,
    required this.referenceNumber,
    required this.subject,
    required this.type,
    required this.description,
    this.bookingId = '',
    required this.createdAt,
    this.status = ComplaintStatus.open,
  });

  factory ComplaintModel.fromJson(Map<String, dynamic> json) {
    final adminReply = (json['admin_reply'] ?? '').toString();
    final ownerReply = (json['owner_reply'] ?? '').toString();
    final replies = [
      if (adminReply.isNotEmpty) 'رد الإدارة: $adminReply',
      if (ownerReply.isNotEmpty) 'رد مالك الصالة: $ownerReply',
    ].join('\n');
    final message = (json['message'] ?? '').toString();
    return ComplaintModel(
      id: '${json['id'] ?? ''}',
      referenceNumber: (json['reference_number'] ?? 'CMP-${json['id'] ?? ''}').toString(),
      subject: (json['subject'] ?? 'شكوى').toString(),
      type: complaintCategoryLabel((json['category'] ?? 'general').toString()),
      description: replies.isEmpty ? message : '$message\n\n$replies',
      bookingId: '${json['booking_id'] ?? ''}',
      createdAt: DateTime.tryParse((json['created_at'] ?? DateTime.now().toIso8601String()).toString()) ?? DateTime.now(),
      status: _statusFromApi((json['status'] ?? '').toString()),
    );
  }
}

String complaintCategoryLabel(String value) {
  switch (value) {
    case 'technical':
      return 'مشكلة تقنية';
    case 'financial':
      return 'مشكلة مالية';
    case 'venue':
      return 'شكوى على صالة';
    case 'provider':
      return 'شكوى على مقدم خدمة';
    default:
      return 'استفسار عام';
  }
}

ComplaintStatus _statusFromApi(String status) {
  final value = status.toLowerCase();
  if (value.contains('closed') || value.contains('resolved')) return ComplaintStatus.resolved;
  if (value.contains('progress') || value.contains('answered')) return ComplaintStatus.inReview;
  return ComplaintStatus.open;
}
