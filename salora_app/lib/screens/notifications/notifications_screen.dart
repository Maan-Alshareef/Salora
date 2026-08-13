import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../models/app_notification.dart';
import '../../providers/booking_provider.dart';
import '../../providers/notification_provider.dart';
import '../../providers/venue_provider.dart';
import '../../screens/venue/venue_details_screen.dart';
import '../../screens/booking/booking_details_screen.dart';
import '../../screens/provider/business_finance_screen.dart';
import '../../screens/provider/provider_requests_screen.dart';
import '../../widgets/empty_state.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  bool _handledRouteArguments = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await context.read<NotificationProvider>().loadNotifications();
      if (mounted) _handleRouteArguments();
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => _handleRouteArguments(),
    );
  }

  Future<void> _handleRouteArguments() async {
    if (_handledRouteArguments || !mounted) return;

    final arguments = ModalRoute.of(context)?.settings.arguments;
    if (arguments is! Map) return;

    _handledRouteArguments = true;
    await _openTarget(Map<String, dynamic>.from(arguments));
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<NotificationProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('الإشعارات'),
        actions: [
          TextButton(
            onPressed: provider.unreadCount == 0
                ? null
                : provider.markAllAsRead,
            child: const Text('قراءة الكل'),
          ),
        ],
      ),
      body: provider.isLoading && provider.notifications.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : provider.error != null && provider.notifications.isEmpty
          ? EmptyState(
              icon: Icons.cloud_off_rounded,
              title: 'تعذر تحميل الإشعارات',
              subtitle: provider.error!,
              buttonText: 'إعادة المحاولة',
              onPressed: provider.loadNotifications,
            )
          : provider.notifications.isEmpty
          ? const EmptyState(
              icon: Icons.notifications_off_outlined,
              title: 'لا توجد إشعارات',
              subtitle: 'ستظهر هنا تحديثات الحجز والدفع وطلبات الخدمات.',
            )
          : RefreshIndicator(
              onRefresh: provider.loadNotifications,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: provider.notifications.length,
                itemBuilder: (context, index) {
                  final item = provider.notifications[index];

                  return InkWell(
                    onTap: () async {
                      await provider.markAsRead(item.id);
                      if (context.mounted) {
                        await _openTarget(item.data);
                      }
                    },
                    borderRadius: BorderRadius.circular(18),
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: item.isRead
                            ? AppColors.surface
                            : AppColors.primary.withValues(alpha: .15),
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CircleAvatar(
                            backgroundColor: AppColors.surface2,
                            child: Icon(
                              _icon(item.type),
                              color: AppColors.primary,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.title,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(height: 5),
                                Text(
                                  item.body,
                                  style: const TextStyle(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  '${item.date.year}-${item.date.month}-${item.date.day}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }

  Future<void> _openTarget(Map<String, dynamic> rawData) async {
    if (!mounted || rawData.isEmpty) return;

    final data = rawData['data'] is Map
        ? {...Map<String, dynamic>.from(rawData['data'] as Map), ...rawData}
        : Map<String, dynamic>.from(rawData);
    final route = (data['target_route'] ?? '').toString();

    if (route == 'provider_requests') {
      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const ProviderRequestsScreen()),
      );
      return;
    }

    if (route == 'business_payments') {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => const BusinessFinanceScreen(initialTab: 1),
        ),
      );
      return;
    }

    if (route == 'offer_details') {
      final venueId = (data['venue_id'] ?? '').toString();
      final venues = context.read<VenueProvider>();
      // Always refresh here so a just-published offer is visible immediately
      // even when the app already had an older cached venue list.
      await venues.loadVenues();
      if (!mounted) return;
      for (final venue in venues.venues) {
        if (venue.id == venueId) {
          await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => VenueDetailsScreen(venue: venue)),
          );
          return;
        }
      }
      _message('تعذر إيجاد الصالة المرتبطة بالعرض.');
      return;
    }

    final bookingId = (data['booking_id'] ?? '').toString();
    if (route == 'booking_details' || bookingId.isNotEmpty) {
      final bookings = context.read<BookingProvider>();
      await bookings.loadMyBookings();
      if (!mounted) return;

      for (final booking in bookings.bookings) {
        if (booking.id == bookingId) {
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => BookingDetailsScreen(booking: booking),
            ),
          );
          return;
        }
      }

      _message('تعذر إيجاد الحجز المرتبط بهذا الإشعار.');
    }
  }

  void _message(String value) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }

  IconData _icon(NotificationType type) {
    switch (type) {
      case NotificationType.booking:
        return Icons.event_available_outlined;
      case NotificationType.payment:
        return Icons.payments_outlined;
      case NotificationType.offer:
        return Icons.local_offer_outlined;
      case NotificationType.reminder:
        return Icons.alarm_outlined;
      case NotificationType.complaint:
        return Icons.support_agent_outlined;
      case NotificationType.system:
        return Icons.info_outline;
    }
  }
}
