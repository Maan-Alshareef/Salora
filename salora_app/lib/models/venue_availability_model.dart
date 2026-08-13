class VenueUnavailableInterval {
  const VenueUnavailableInterval({
    required this.startTime,
    required this.endTime,
    required this.status,
  });

  final String startTime;
  final String endTime;
  final String status;

  factory VenueUnavailableInterval.fromJson(Map<String, dynamic> json) => VenueUnavailableInterval(
        startTime: _shortTime(json['start_time']),
        endTime: _shortTime(json['end_time']),
        status: (json['status'] ?? '').toString(),
      );

  bool overlaps(String start, String end) {
    return _minutes(startTime) < _minutes(end) && _minutes(endTime) > _minutes(start);
  }
}

class VenueDayAvailability {
  const VenueDayAvailability({
    required this.venueId,
    required this.date,
    required this.isClosed,
    required this.openTime,
    required this.closeTime,
    required this.unavailableIntervals,
  });

  final String venueId;
  final DateTime date;
  final bool isClosed;
  final String openTime;
  final String closeTime;
  final List<VenueUnavailableInterval> unavailableIntervals;

  factory VenueDayAvailability.fromJson(Map<String, dynamic> json) {
    final opening = json['opening_hours'] is Map
        ? Map<String, dynamic>.from(json['opening_hours'] as Map)
        : const <String, dynamic>{};
    final intervals = (json['unavailable_intervals'] is List ? json['unavailable_intervals'] as List : const [])
        .whereType<Map>()
        .map((item) => VenueUnavailableInterval.fromJson(Map<String, dynamic>.from(item)))
        .toList();
    return VenueDayAvailability(
      venueId: '${json['venue_id'] ?? ''}',
      date: DateTime.tryParse((json['date'] ?? '').toString()) ?? DateTime.now(),
      isClosed: json['is_closed'] == true || opening['enabled'] == false || opening['enabled']?.toString() == '0',
      openTime: _shortTime(opening['open']),
      closeTime: _shortTime(opening['close']),
      unavailableIntervals: intervals,
    );
  }

  bool isTimeAvailable(String start, String end) {
    if (isClosed || _minutes(end) <= _minutes(start)) return false;
    if (openTime.isNotEmpty && _minutes(start) < _minutes(openTime)) return false;
    if (closeTime.isNotEmpty && _minutes(end) > _minutes(closeTime)) return false;
    return unavailableIntervals.every((interval) => !interval.overlaps(start, end));
  }

  String? unavailabilityReason(String start, String end) {
    if (isClosed) return 'الصالة مغلقة في هذا اليوم.';
    if (_minutes(end) <= _minutes(start)) return 'وقت النهاية يجب أن يكون بعد وقت البداية.';
    if (openTime.isNotEmpty && _minutes(start) < _minutes(openTime)) {
      return 'وقت البداية قبل موعد فتح الصالة ($openTime).';
    }
    if (closeTime.isNotEmpty && _minutes(end) > _minutes(closeTime)) {
      return 'وقت النهاية بعد موعد إغلاق الصالة ($closeTime).';
    }
    for (final interval in unavailableIntervals) {
      if (interval.overlaps(start, end)) {
        return 'الفترة ${interval.startTime} - ${interval.endTime} محجوزة أو معلقة. اختر وقتاً آخر.';
      }
    }
    return null;
  }
}

String _shortTime(dynamic value) {
  final text = value?.toString().trim() ?? '';
  if (text.length >= 5 && text.contains(':')) return text.substring(0, 5);
  return text;
}

int _minutes(String value) {
  final parts = value.split(':');
  if (parts.length < 2) return -1;
  final hour = int.tryParse(parts[0]) ?? 0;
  final minute = int.tryParse(parts[1]) ?? 0;
  return hour * 60 + minute;
}
