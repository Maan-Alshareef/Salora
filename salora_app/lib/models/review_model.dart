class ReviewModel {
  final String id;
  final String venueId;
  final String bookingId;
  final String userName;
  final int rating;
  final String comment;
  final DateTime createdAt;

  const ReviewModel({
    required this.id,
    required this.venueId,
    this.bookingId = '',
    required this.userName,
    required this.rating,
    required this.comment,
    required this.createdAt,
  });

  factory ReviewModel.fromJson(Map<String, dynamic> json) {
    final customer = json['customer'] is Map ? Map<String, dynamic>.from(json['customer'] as Map) : <String, dynamic>{};
    return ReviewModel(
      id: '${json['id'] ?? ''}',
      venueId: '${json['venue_id'] ?? ''}',
      bookingId: '${json['booking_id'] ?? ''}',
      userName: (customer['name'] ?? json['customer_name'] ?? 'عميل').toString(),
      rating: _toInt(json['rating']).clamp(1, 5).toInt(),
      comment: (json['comment'] ?? 'تقييم بدون تعليق').toString(),
      createdAt: DateTime.tryParse((json['created_at'] ?? DateTime.now().toIso8601String()).toString()) ?? DateTime.now(),
    );
  }
}

int _toInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}
