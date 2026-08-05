import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../network/api_client.dart';
import 'notification_navigation_service.dart';

const AndroidNotificationChannel saloraNotificationChannel =
    AndroidNotificationChannel(
      'salora_high_importance',
      'إشعارات Salora المهمة',
      description: 'الحجوزات والدفعات والطلبات والتنبيهات المهمة.',
      importance: Importance.max,
      playSound: true,
      enableVibration: true,
      showBadge: true,
    );

final FlutterLocalNotificationsPlugin saloraLocalNotifications =
    FlutterLocalNotificationsPlugin();

bool _localNotificationsInitialized = false;

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  if (Firebase.apps.isEmpty) {
    await Firebase.initializeApp();
  }
}

class FirebasePushService {
  FirebasePushService(this._api);

  final ApiClient _api;

  StreamSubscription<RemoteMessage>? _foregroundSubscription;
  StreamSubscription<RemoteMessage>? _openedSubscription;
  StreamSubscription<String>? _tokenSubscription;

  bool _started = false;

  Future<void> start(
    FutureOr<void> Function(RemoteMessage message) onMessage,
  ) async {
    if (_started || !_api.isAuthenticated) {
      return;
    }

    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp();
      }

      await _initializeLocalNotifications();

      final messaging = FirebaseMessaging.instance;

      await messaging.setAutoInitEnabled(true);
      await messaging.requestPermission(alert: true, badge: true, sound: true);
      await messaging.setForegroundNotificationPresentationOptions(
        alert: true,
        badge: true,
        sound: true,
      );

      final token = await messaging.getToken();
      if (token != null && token.isNotEmpty) {
        await _register(token);
      }

      await _tokenSubscription?.cancel();
      _tokenSubscription = messaging.onTokenRefresh.listen((token) async {
        try {
          await _register(token);
        } catch (error) {
          stderr.writeln('Failed to register refreshed Firebase token: $error');
        }
      });

      await _foregroundSubscription?.cancel();
      _foregroundSubscription = FirebaseMessaging.onMessage.listen((
        message,
      ) async {
        if (Platform.isAndroid) {
          await _showForegroundNotification(message);
        }

        await onMessage(message);
      });

      await _openedSubscription?.cancel();
      _openedSubscription = FirebaseMessaging.onMessageOpenedApp.listen((
        message,
      ) async {
        NotificationNavigationService.openNotification(message.data);
        await onMessage(message);
      });

      final initialMessage = await messaging.getInitialMessage();
      if (initialMessage != null) {
        NotificationNavigationService.openNotification(initialMessage.data);
        await onMessage(initialMessage);
      }

      _started = true;
    } catch (error, stackTrace) {
      _started = false;
      stderr.writeln(
        'FirebasePushService failed to start: $error\n$stackTrace',
      );
    }
  }

  Future<void> _initializeLocalNotifications() async {
    if (_localNotificationsInitialized) {
      return;
    }

    const initializationSettings = InitializationSettings(
      android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      iOS: DarwinInitializationSettings(
        requestAlertPermission: true,
        requestBadgePermission: true,
        requestSoundPermission: true,
      ),
    );

    await saloraLocalNotifications.initialize(
      settings: initializationSettings,
      onDidReceiveNotificationResponse: (response) {
        _handleLocalPayload(response.payload);
      },
    );

    final androidPlugin = saloraLocalNotifications
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >();

    await androidPlugin?.createNotificationChannel(saloraNotificationChannel);
    await androidPlugin?.requestNotificationsPermission();

    final launchDetails = await saloraLocalNotifications
        .getNotificationAppLaunchDetails();

    if (launchDetails?.didNotificationLaunchApp == true) {
      _handleLocalPayload(launchDetails?.notificationResponse?.payload);
    }

    _localNotificationsInitialized = true;
  }

  Future<void> _showForegroundNotification(RemoteMessage message) async {
    final title =
        message.notification?.title ??
        message.data['title']?.toString() ??
        'Salora';
    final body =
        message.notification?.body ?? message.data['body']?.toString() ?? '';

    if (title.trim().isEmpty && body.trim().isEmpty) {
      return;
    }

    final payload = jsonEncode(message.data);
    final notificationId =
        (message.messageId ?? message.data['notification_id']?.toString() ?? '')
            .hashCode &
        0x7fffffff;

    const details = NotificationDetails(
      android: AndroidNotificationDetails(
        'salora_high_importance',
        'إشعارات Salora المهمة',
        channelDescription: 'الحجوزات والدفعات والطلبات والتنبيهات المهمة.',
        importance: Importance.max,
        priority: Priority.high,
        playSound: true,
        enableVibration: true,
        visibility: NotificationVisibility.public,
        category: AndroidNotificationCategory.message,
      ),
      iOS: DarwinNotificationDetails(
        presentAlert: true,
        presentBadge: true,
        presentSound: true,
      ),
    );

    await saloraLocalNotifications.show(
      id: notificationId,
      title: title,
      body: body,
      notificationDetails: details,
      payload: payload,
    );
  }

  void _handleLocalPayload(String? payload) {
    if (payload == null || payload.trim().isEmpty) {
      NotificationNavigationService.openNotification(const <String, dynamic>{});
      return;
    }

    try {
      final decoded = jsonDecode(payload);

      if (decoded is Map) {
        NotificationNavigationService.openNotification(
          Map<String, dynamic>.from(decoded),
        );
        return;
      }
    } catch (_) {
      // Open the notification center when payload parsing fails.
    }

    NotificationNavigationService.openNotification(<String, dynamic>{
      'payload': payload,
    });
  }

  Future<void> _register(String token) async {
    if (!_api.isAuthenticated || token.isEmpty) {
      return;
    }

    await _api.post('/device-tokens', {
      'token': token,
      'platform': Platform.isIOS ? 'ios' : 'android',
      'device_name': Platform.operatingSystemVersion,
    });
  }

  Future<void> dispose() async {
    await _foregroundSubscription?.cancel();
    await _openedSubscription?.cancel();
    await _tokenSubscription?.cancel();

    _foregroundSubscription = null;
    _openedSubscription = null;
    _tokenSubscription = null;
    _started = false;
  }
}
