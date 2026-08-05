enum EventType { wedding, engagement, graduation, conference, condolence, meeting, birthday }

enum EventTaskStatus { pending, inProgress, done }

enum EventStatus { draft, confirmed }

extension EventTypeLabel on EventType {
  String get label {
    switch (this) {
      case EventType.wedding:
        return 'زفاف';
      case EventType.engagement:
        return 'خطوبة';
      case EventType.graduation:
        return 'تخرج';
      case EventType.conference:
        return 'مؤتمر';
      case EventType.condolence:
        return 'عزاء';
      case EventType.meeting:
        return 'اجتماع';
      case EventType.birthday:
        return 'عيد ميلاد';
    }
  }

  String get arabicLabel => label;

  String get iconEmoji {
    switch (this) {
      case EventType.wedding:
        return '💍';
      case EventType.engagement:
        return '💞';
      case EventType.graduation:
        return '🎓';
      case EventType.conference:
        return '🎤';
      case EventType.condolence:
        return '🕊️';
      case EventType.meeting:
        return '🤝';
      case EventType.birthday:
        return '🎂';
    }
  }
}

extension EventStatusLabel on EventStatus {
  String get label => this == EventStatus.confirmed ? 'نشطة' : 'مسودة';
  String get arabicLabel => label;
}

class EventTask {
  final String id;
  final String title;
  final String subtitle;
  final EventTaskStatus status;

  const EventTask({
    required this.id,
    required this.title,
    required this.subtitle,
    this.status = EventTaskStatus.pending,
  });

  factory EventTask.fromJson(Map<String, dynamic> json) {
    final completed = json['is_completed'] == true || json['is_completed']?.toString() == '1';
    return EventTask(
      id: '${json['id'] ?? ''}',
      title: (json['title'] ?? 'مهمة').toString(),
      subtitle: json['todo_template_id'] == null ? 'مهمة مخصصة' : 'مهمة مولدة من نوع المناسبة',
      status: completed ? EventTaskStatus.done : EventTaskStatus.pending,
    );
  }

  EventTask copyWith({String? title, EventTaskStatus? status}) => EventTask(
        id: id,
        title: title ?? this.title,
        subtitle: subtitle,
        status: status ?? this.status,
      );
}

class EventModel {
  final String id;
  final String eventTypeId;
  final String eventTypeName;
  final String title;
  final EventType type;
  final DateTime date;
  final String city;
  final int guests;
  final int budget;
  final int totalAmount;
  final String? venueId;
  final String? venueName;
  final String? venueAddress;
  final String? startTime;
  final String? endTime;
  final String? bookingId;
  final List<String> neededServices;
  final List<EventTask> tasks;
  final EventStatus status;
  final DateTime createdAt;

  const EventModel({
    required this.id,
    this.eventTypeId = '',
    this.eventTypeName = '',
    required this.title,
    required this.type,
    required this.date,
    required this.city,
    required this.guests,
    required this.budget,
    int? totalAmount,
    this.venueId,
    this.venueName,
    this.venueAddress,
    this.startTime,
    this.endTime,
    this.bookingId,
    required this.neededServices,
    required this.tasks,
    this.status = EventStatus.confirmed,
    required this.createdAt,
  }) : totalAmount = totalAmount ?? budget;

  factory EventModel.fromJson(Map<String, dynamic> json) {
    final eventTypeMap = json['event_type'] is Map ? Map<String, dynamic>.from(json['event_type'] as Map) : <String, dynamic>{};
    final eventTypeLabel = (eventTypeMap['name_ar'] ?? eventTypeMap['name_en'] ?? '').toString();
    final bookings = json['bookings'] is List ? json['bookings'] as List : const [];
    final firstBooking = bookings.whereType<Map>().isEmpty ? <String, dynamic>{} : Map<String, dynamic>.from(bookings.whereType<Map>().first);
    final venue = firstBooking['venue'] is Map ? Map<String, dynamic>.from(firstBooking['venue'] as Map) : <String, dynamic>{};
    final tasks = json['todo_items'] is List ? json['todo_items'] as List : const [];
    final notes = (json['notes'] ?? '').toString();

    return EventModel(
      id: '${json['id'] ?? ''}',
      eventTypeId: '${json['event_type_id'] ?? eventTypeMap['id'] ?? ''}',
      eventTypeName: eventTypeLabel,
      title: (json['name'] ?? 'مناسبة').toString(),
      type: eventTypeFromLabel(eventTypeLabel),
      date: DateTime.tryParse((json['event_date'] ?? DateTime.now().toIso8601String()).toString()) ?? DateTime.now(),
      city: (json['city'] ?? venue['city'] ?? '').toString(),
      guests: _toInt(json['guests_count']),
      budget: _toInt(json['budget_syp']),
      totalAmount: _toInt(firstBooking['total_syp'] ?? json['budget_syp']),
      venueId: firstBooking['venue_id']?.toString() ?? venue['id']?.toString(),
      venueName: (venue['name_ar'] ?? venue['name_en'])?.toString(),
      venueAddress: venue['address']?.toString(),
      startTime: _shortTime(json['start_time']?.toString()),
      endTime: _shortTime(json['end_time']?.toString()),
      bookingId: firstBooking['id']?.toString(),
      neededServices: _servicesFromNotes(notes),
      tasks: tasks.whereType<Map>().map((item) => EventTask.fromJson(Map<String, dynamic>.from(item))).toList(),
      status: (json['status'] ?? 'active').toString() == 'active' ? EventStatus.confirmed : EventStatus.draft,
      createdAt: DateTime.tryParse((json['created_at'] ?? DateTime.now().toIso8601String()).toString()) ?? DateTime.now(),
    );
  }

  int get completedTasks => tasks.where((task) => task.status == EventTaskStatus.done).length;
  double get progress => tasks.isEmpty ? 0 : completedTasks / tasks.length;
  String get displayEventType {
    final value = eventTypeName.trim();
    return value.isEmpty ? type.label : value;
  }
  String get timeRange => startTime == null || endTime == null ? '' : '$startTime - $endTime';
  String get displayLocation {
    final hall = venueName?.trim() ?? '';
    final address = venueAddress?.trim() ?? '';
    if (hall.isEmpty && address.isEmpty) return city;
    if (hall.isEmpty) return address;
    if (address.isEmpty) return hall;
    return '$hall، $address';
  }

  EventModel copyWith({
    String? eventTypeName,
    String? title,
    EventType? type,
    DateTime? date,
    String? city,
    int? guests,
    int? budget,
    int? totalAmount,
    String? venueId,
    String? venueName,
    String? venueAddress,
    String? startTime,
    String? endTime,
    String? bookingId,
    List<String>? neededServices,
    List<EventTask>? tasks,
    EventStatus? status,
    DateTime? createdAt,
  }) =>
      EventModel(
        id: id,
        eventTypeId: eventTypeId,
        eventTypeName: eventTypeName ?? this.eventTypeName,
        title: title ?? this.title,
        type: type ?? this.type,
        date: date ?? this.date,
        city: city ?? this.city,
        guests: guests ?? this.guests,
        budget: budget ?? this.budget,
        totalAmount: totalAmount ?? this.totalAmount,
        venueId: venueId ?? this.venueId,
        venueName: venueName ?? this.venueName,
        venueAddress: venueAddress ?? this.venueAddress,
        startTime: startTime ?? this.startTime,
        endTime: endTime ?? this.endTime,
        bookingId: bookingId ?? this.bookingId,
        neededServices: neededServices ?? this.neededServices,
        tasks: tasks ?? this.tasks,
        status: status ?? this.status,
        createdAt: createdAt ?? this.createdAt,
      );
}

EventType eventTypeFromLabel(String value) {
  final normalized = value.trim().toLowerCase();
  if (normalized.contains('خطوب') || normalized.contains('engagement')) return EventType.engagement;
  if (normalized.contains('تخرج') || normalized.contains('graduation')) return EventType.graduation;
  if (normalized.contains('مؤتمر') || normalized.contains('conference')) return EventType.conference;
  if (normalized.contains('عزاء') || normalized.contains('condolence')) return EventType.condolence;
  if (normalized.contains('اجتماع') || normalized.contains('meeting')) return EventType.meeting;
  if (normalized.contains('ميلاد') || normalized.contains('birthday')) return EventType.birthday;
  return EventType.wedding;
}

String? _shortTime(String? value) {
  if (value == null || value.isEmpty) return null;
  return value.length >= 5 ? value.substring(0, 5) : value;
}

List<String> _servicesFromNotes(String notes) {
  const prefix = 'الخدمات المطلوبة:';
  final index = notes.indexOf(prefix);
  if (index == -1) return const [];
  return notes
      .substring(index + prefix.length)
      .split(',')
      .map((value) => value.trim())
      .where((value) => value.isNotEmpty)
      .toList();
}

int _toInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.round();
  return double.tryParse(value?.toString().replaceAll(',', '') ?? '')?.round() ?? 0;
}
