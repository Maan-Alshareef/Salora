import 'dart:async';

import 'package:flutter/material.dart';

import '../../screens/notifications/notifications_screen.dart';

class NotificationNavigationService {
  NotificationNavigationService._();

  static final GlobalKey<NavigatorState> navigatorKey =
      GlobalKey<NavigatorState>();

  static Map<String, dynamic>? _pendingData;
  static String? _lastNotificationId;
  static DateTime? _lastOpenedAt;
  static bool _scheduled = false;

  static void openNotification(Map<String, dynamic> data) {
    final notificationId = data['notification_id']?.toString();
    final now = DateTime.now();

    if (notificationId != null &&
        notificationId.isNotEmpty &&
        notificationId == _lastNotificationId &&
        _lastOpenedAt != null &&
        now.difference(_lastOpenedAt!) < const Duration(seconds: 3)) {
      return;
    }

    _lastNotificationId = notificationId;
    _lastOpenedAt = now;
    _pendingData = Map<String, dynamic>.from(data);

    _scheduleOpen();
  }

  static void _scheduleOpen([int attempt = 0]) {
    if (_scheduled) {
      return;
    }

    _scheduled = true;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _scheduled = false;

      final navigator = navigatorKey.currentState;
      final data = _pendingData;

      if (navigator == null) {
        if (attempt < 20) {
          Timer(
            const Duration(milliseconds: 250),
            () => _scheduleOpen(attempt + 1),
          );
        }

        return;
      }

      if (data == null) {
        return;
      }

      _pendingData = null;

      navigator.push(
        MaterialPageRoute<void>(
          builder: (_) => NotificationsScreen(),
          settings: RouteSettings(name: '/notifications', arguments: data),
        ),
      );
    });
  }
}
