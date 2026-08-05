import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/constants/app_constants.dart';
import 'core/network/api_client.dart';
import 'core/notifications/firebase_push_service.dart';
import 'core/notifications/notification_navigation_service.dart';
import 'core/theme/app_theme.dart';
import 'data/repositories/venue_repository.dart';
import 'providers/app_settings_provider.dart';
import 'providers/auth_provider.dart';
import 'providers/booking_provider.dart';
import 'providers/compare_provider.dart';
import 'providers/complaint_provider.dart';
import 'providers/event_provider.dart';
import 'providers/favorite_provider.dart';
import 'providers/notification_provider.dart';
import 'providers/provider_account_provider.dart';
import 'providers/review_provider.dart';
import 'providers/service_provider.dart';
import 'providers/theme_provider.dart';
import 'providers/venue_provider.dart';
import 'screens/auth/splash_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await Firebase.initializeApp();
  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

  runApp(const SaloraApp());
}

class SaloraApp extends StatelessWidget {
  const SaloraApp({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = ApiClient();

    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
        ChangeNotifierProvider(create: (_) => AppSettingsProvider()),
        Provider<ApiClient>.value(value: apiClient),
        ChangeNotifierProvider(create: (_) => AuthProvider(apiClient)),
        ChangeNotifierProvider(
          create: (_) => VenueProvider(RemoteVenueRepository(apiClient)),
        ),
        ChangeNotifierProvider(create: (_) => FavoriteProvider()),
        ChangeNotifierProvider(create: (_) => CompareProvider()),
        ChangeNotifierProvider(create: (_) => BookingProvider(apiClient)),
        ChangeNotifierProvider(create: (_) => NotificationProvider(apiClient)),
        ChangeNotifierProvider(create: (_) => EventProvider(apiClient)),
        ChangeNotifierProvider(create: (_) => ServiceProviderState(apiClient)),
        ChangeNotifierProvider(
          create: (_) => ProviderAccountProvider(apiClient),
        ),
        ChangeNotifierProvider(create: (_) => ComplaintProvider(apiClient)),
        ChangeNotifierProvider(create: (_) => ReviewProvider(apiClient)),
      ],
      child: Consumer2<ThemeProvider, AppSettingsProvider>(
        builder: (context, themeProvider, settings, _) {
          return MaterialApp(
            navigatorKey: NotificationNavigationService.navigatorKey,
            title: AppConstants.appName,
            debugShowCheckedModeBanner: false,
            theme: AppTheme.light(),
            darkTheme: AppTheme.dark(),
            themeMode: themeProvider.themeMode,
            locale: const Locale('ar'),
            supportedLocales: const [Locale('ar')],
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            builder: (context, child) => Directionality(
              textDirection: TextDirection.rtl,
              child: child ?? const SizedBox.shrink(),
            ),
            home: const SplashScreen(),
          );
        },
      ),
    );
  }
}
