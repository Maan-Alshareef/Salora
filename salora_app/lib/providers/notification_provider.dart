import 'dart:async';

import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../core/notifications/firebase_push_service.dart';
import '../models/app_notification.dart';

class NotificationProvider extends ChangeNotifier {
  NotificationProvider(this._api) : _push = FirebasePushService(_api);

  final ApiClient _api;
  final FirebasePushService _push;

  final List<AppNotification> _notifications = [];

  Timer? _pollTimer;

  bool isLoading = false;
  String? error;

  List<AppNotification> get notifications => List.unmodifiable(_notifications);

  int get unreadCount => _notifications.where((item) => !item.isRead).length;

  Future<void> loadNotifications({bool silent = false}) async {
    if (!_api.isAuthenticated) {
      _notifications.clear();

      _pollTimer?.cancel();
      _pollTimer = null;

      await _push.dispose();

      notifyListeners();
      return;
    }

    if (!silent) {
      isLoading = true;
      error = null;
      notifyListeners();
    }

    try {
      // تشغيل Firebase وتسجيل رمز الجهاز أولاً.
      await _push.start((_) async {
        await loadNotifications(silent: true);
      });

      final data = await _api.get('/notifications', query: {'per_page': 100});

      final List<dynamic> list;

      if (data is Map && data['data'] is List) {
        list = data['data'] as List;
      } else if (data is List) {
        list = data;
      } else {
        list = const [];
      }

      _notifications
        ..clear()
        ..addAll(
          list.whereType<Map>().map(
            (item) => AppNotification.fromJson(Map<String, dynamic>.from(item)),
          ),
        );

      error = null;
      _ensurePolling();
    } catch (exception) {
      if (!silent) {
        error = exception.toString();
      }
    } finally {
      if (!silent) {
        isLoading = false;
      }

      notifyListeners();
    }
  }

  void _ensurePolling() {
    _pollTimer ??= Timer.periodic(const Duration(seconds: 20), (_) {
      loadNotifications(silent: true);
    });
  }

  Future<void> markAsRead(String id) async {
    final index = _notifications.indexWhere((item) => item.id == id);

    if (index == -1 || _notifications[index].isRead) {
      return;
    }

    final data = await _api.post('/notifications/$id/read', {});

    if (data is Map) {
      _notifications[index] = AppNotification.fromJson(
        Map<String, dynamic>.from(data),
      );
    } else {
      _notifications[index] = _notifications[index].copyWith(isRead: true);
    }

    notifyListeners();
  }

  Future<void> markAllAsRead() async {
    if (!_api.isAuthenticated || unreadCount == 0) {
      return;
    }

    await _api.post('/notifications/read-all', {});

    for (var index = 0; index < _notifications.length; index++) {
      _notifications[index] = _notifications[index].copyWith(isRead: true);
    }

    notifyListeners();
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _push.dispose();
    super.dispose();
  }
}
