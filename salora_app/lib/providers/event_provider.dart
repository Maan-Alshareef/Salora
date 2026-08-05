import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../core/utils/arabic_text.dart';
import '../models/event_model.dart';

class EventProvider extends ChangeNotifier {
  EventProvider(this._api);

  final ApiClient _api;
  final List<EventModel> _events = [];
  final Map<String, List<String>> _remoteTemplates = {};
  final Map<String, String> _eventTypeIds = {};

  bool isLoading = false;
  String? error;

  List<EventModel> get events => List.unmodifiable(_events);

  String? idForType(EventType type) => _eventTypeIds[type.label];

  Future<void> loadTemplates() async {
    try {
      final data = await _api.get('/event-types');
      final list = data is List ? data : const [];
      _remoteTemplates.clear();
      _eventTypeIds.clear();
      for (final raw in list.whereType<Map>()) {
        final item = Map<String, dynamic>.from(raw);
        final name = ArabicText.tr((item['name_ar'] ?? item['name_en'] ?? '').toString());
        final id = '${item['id'] ?? ''}';
        final tasks = (item['todo_templates'] is List ? item['todo_templates'] as List : const [])
            .whereType<Map>()
            .map((task) => Map<String, dynamic>.from(task))
            .map((task) => ArabicText.tr((task['task_ar'] ?? task['task_en'] ?? '').toString()))
            .where((task) => task.trim().isNotEmpty)
            .toList();
        if (name.isNotEmpty && id.isNotEmpty) {
          _eventTypeIds[name] = id;
          _eventTypeIds[eventTypeFromLabel(name).label] = id;
          _remoteTemplates[name] = tasks;
        }
      }
      notifyListeners();
    } catch (e) {
      error = e.toString();
      notifyListeners();
    }
  }

  Future<void> loadEvents() async {
    if (!_api.isAuthenticated) {
      _events.clear();
      notifyListeners();
      return;
    }
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      if (_eventTypeIds.isEmpty) await loadTemplates();
      final data = await _api.get('/customer/events');
      final list = data is List ? data : const [];
      _events
        ..clear()
        ..addAll(list.whereType<Map>().map((item) => EventModel.fromJson(Map<String, dynamic>.from(item))));
    } catch (e) {
      error = e.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<EventModel> createEvent({
    required String title,
    required EventType type,
    String? explicitEventTypeId,
    required DateTime date,
    required String city,
    required int guests,
    required int budget,
    int? totalAmount,
    String? venueId,
    String? venueName,
    String? venueAddress,
    String? startTime,
    String? endTime,
    String? bookingId,
    required List<String> neededServices,
    bool hallBooked = false,
  }) async {
    if (!_api.isAuthenticated) throw const ApiException('الرجاء تسجيل الدخول قبل إنشاء المناسبة.');
    if (_eventTypeIds.isEmpty) await loadTemplates();
    final eventTypeId = int.tryParse(explicitEventTypeId ?? '') ?? int.tryParse(idForType(type) ?? '');
    if (eventTypeId == null) {
      throw ApiException('نوع المناسبة ${type.label} غير معرف في قاعدة البيانات.');
    }

    final notes = neededServices.isEmpty ? null : 'الخدمات المطلوبة: ${neededServices.join(', ')}';
    final data = await _api.post('/customer/events', {
      'event_type_id': eventTypeId,
      'name': title.trim(),
      'event_date': date.toIso8601String().split('T').first,
      if (startTime != null && startTime.isNotEmpty) 'start_time': startTime,
      if (endTime != null && endTime.isNotEmpty) 'end_time': endTime,
      'guests_count': guests,
      'budget_syp': budget,
      'city': city.trim(),
      if (notes != null) 'notes': notes,
    });

    final event = EventModel.fromJson(Map<String, dynamic>.from(data as Map));
    _events.insert(0, event);
    error = null;
    notifyListeners();
    return event;
  }

  Future<EventModel> updateEvent({
    required String eventId,
    required String existingEventTypeId,
    required String title,
    required EventType type,
    required DateTime date,
    required String city,
    required int guests,
    required int budget,
    required List<String> neededServices,
  }) async {
    if (!_api.isAuthenticated) throw const ApiException('الرجاء تسجيل الدخول قبل تعديل المناسبة.');
    if (_eventTypeIds.isEmpty) await loadTemplates();
    final mappedId = idForType(type);
    final eventTypeId = int.tryParse(mappedId ?? '') ?? int.tryParse(existingEventTypeId);
    if (eventTypeId == null) {
      throw ApiException('نوع المناسبة ${type.label} غير معرف في قاعدة البيانات.');
    }

    final notes = neededServices.isEmpty ? null : 'الخدمات المطلوبة: ${neededServices.join(', ')}';
    final data = await _api.put('/customer/events/$eventId', {
      'event_type_id': eventTypeId,
      'name': title.trim(),
      'event_date': date.toIso8601String().split('T').first,
      'guests_count': guests,
      'budget_syp': budget,
      'city': city.trim(),
      'notes': notes,
    });

    final updated = EventModel.fromJson(Map<String, dynamic>.from(data as Map));
    final index = _events.indexWhere((event) => event.id == eventId);
    if (index == -1) {
      _events.insert(0, updated);
    } else {
      _events[index] = updated;
    }
    error = null;
    notifyListeners();
    return updated;
  }

  Future<void> toggleTask(String eventId, String taskId) async {
    final eventIndex = _events.indexWhere((event) => event.id == eventId);
    if (eventIndex == -1) return;
    final event = _events[eventIndex];
    EventTask? task;
    for (final item in event.tasks) {
      if (item.id == taskId) {
        task = item;
        break;
      }
    }
    if (task == null) return;

    final data = await _api.put('/customer/events/$eventId/todos/$taskId', {
      'is_completed': task.status != EventTaskStatus.done,
    });
    final updatedTask = EventTask.fromJson(Map<String, dynamic>.from(data as Map));
    final tasks = event.tasks.map((item) => item.id == taskId ? updatedTask : item).toList();
    _events[eventIndex] = event.copyWith(tasks: tasks);
    notifyListeners();
  }

  Future<void> addCustomTask(String eventId, String title) async {
    final cleanTitle = title.trim();
    if (cleanTitle.isEmpty) throw const ApiException('اكتب اسم المهمة قبل الإضافة.');
    final eventIndex = _events.indexWhere((event) => event.id == eventId);
    if (eventIndex == -1) throw const ApiException('تعذر العثور على المناسبة.');

    final data = await _api.post('/customer/events/$eventId/todos', {'title': cleanTitle});
    final task = EventTask.fromJson(Map<String, dynamic>.from(data as Map));
    _events[eventIndex] = _events[eventIndex].copyWith(tasks: [..._events[eventIndex].tasks, task]);
    notifyListeners();
  }

  Future<void> deleteTask(String eventId, String taskId) async {
    final eventIndex = _events.indexWhere((event) => event.id == eventId);
    if (eventIndex == -1) return;
    await _api.delete('/customer/events/$eventId/todos/$taskId');
    _events[eventIndex] = _events[eventIndex].copyWith(
      tasks: _events[eventIndex].tasks.where((task) => task.id != taskId).toList(),
    );
    notifyListeners();
  }

  Future<void> deleteEvent(String eventId) async {
    await _api.delete('/customer/events/$eventId');
    _events.removeWhere((event) => event.id == eventId);
    notifyListeners();
  }

  Future<void> attachBooking(String eventId, String bookingId) async {
    await loadEvents();
  }
}

