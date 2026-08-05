enum NotificationType { booking, payment, offer, reminder, complaint, system }

class AppNotification {
  final String id;
  final NotificationType type;
  final String title;
  final String body;
  final DateTime date;
  final bool isRead;
  final Map<String, dynamic> data;

  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.date,
    this.isRead = false,
    this.data = const {},
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
        id: '${json['id'] ?? ''}',
        type: _typeFromApi((json['type'] ?? '').toString()),
        title: (json['title'] ?? 'إشعار').toString(),
        body: (json['body'] ?? '').toString(),
        date: DateTime.tryParse((json['created_at'] ?? DateTime.now().toIso8601String()).toString()) ?? DateTime.now(),
        isRead: json['is_read'] == true || json['is_read']?.toString() == '1',
        data: json['data_json'] is Map ? Map<String, dynamic>.from(json['data_json'] as Map) : const {},
      );

  AppNotification copyWith({bool? isRead}) => AppNotification(
        id: id,
        type: type,
        title: title,
        body: body,
        date: date,
        isRead: isRead ?? this.isRead,
        data: data,
      );
}

NotificationType _typeFromApi(String value) {
  final normalized = value.toLowerCase();
  if (normalized.contains('booking') || normalized.contains('provider_service')) return NotificationType.booking;
  if (normalized.contains('payment') || normalized.contains('invoice')) return NotificationType.payment;
  if (normalized.contains('offer')) return NotificationType.offer;
  if (normalized.contains('reminder')) return NotificationType.reminder;
  if (normalized.contains('complaint')) return NotificationType.complaint;
  return NotificationType.system;
}
