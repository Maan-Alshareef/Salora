import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_constants.dart';
import '../../core/theme/app_colors.dart';
import '../../models/user_role.dart';
import '../../providers/auth_provider.dart';
import '../../providers/booking_provider.dart';
import '../../providers/event_provider.dart';
import '../../providers/notification_provider.dart';
import '../../providers/service_provider.dart';
import '../../providers/venue_provider.dart';
import '../home/main_navigation_screen.dart';
import '../provider/provider_navigation_screen.dart';
import 'login_screen.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  Future<void> _bootstrap() async {
    final auth = context.read<AuthProvider>();
    await Future.wait([
      auth.restoreSession(),
      Future<void>.delayed(const Duration(milliseconds: 700)),
    ]);
    if (!mounted) return;

    Widget destination = const LoginScreen();
    if (auth.isLoggedIn && auth.role == UserRole.customer) {
      await Future.wait([
        context.read<EventProvider>().loadTemplates(),
        context.read<EventProvider>().loadEvents(),
        context.read<BookingProvider>().loadMyBookings(),
        context.read<NotificationProvider>().loadNotifications(),
        context.read<VenueProvider>().loadVenues(),
        context.read<ServiceProviderState>().loadDirectory(),
      ]);
      destination = const MainNavigationScreen();
    } else if (auth.isLoggedIn && auth.role == UserRole.provider) {
      if (!auth.mustChangePassword) {
        await context.read<NotificationProvider>().loadNotifications();
      }
      destination = const ProviderNavigationScreen();
    } else if (auth.isLoggedIn) {
      await auth.logout();
    }

    if (!mounted) return;
    Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => destination));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [AppColors.background, AppColors.primary],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 96,
              height: 96,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(.12),
                borderRadius: BorderRadius.circular(28),
                border: Border.all(color: Colors.white24),
              ),
              child: const Icon(Icons.celebration_rounded, size: 52, color: Colors.white),
            ),
            const SizedBox(height: 22),
            const Text(AppConstants.appName, style: TextStyle(fontSize: 34, fontWeight: FontWeight.w900, letterSpacing: .5)),
            const SizedBox(height: 8),
            const Text('احجز الصالات وتابع مناسباتك', style: TextStyle(color: AppColors.textSecondary)),
            const SizedBox(height: 30),
            const SizedBox(width: 34, height: 34, child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white)),
          ]),
        ),
      ),
    );
  }
}
