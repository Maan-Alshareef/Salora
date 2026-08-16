import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/app_settings_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../models/booking_model.dart';
import '../../providers/booking_provider.dart';
import '../../widgets/empty_state.dart';
import 'booking_details_screen.dart';

class MyBookingsScreen extends StatefulWidget {
  const MyBookingsScreen({super.key});

  @override
  State<MyBookingsScreen> createState() => _MyBookingsScreenState();
}

class _MyBookingsScreenState extends State<MyBookingsScreen> {
  String filter = 'الكل';

  final filters = const [
    'الكل',
    'بانتظار الدفع',
    'بانتظار مراجعة الدفع',
    'مؤكد',
    'بانتظار الاسترداد',
    'طلب تعديل قيد المراجعة',
    'طلب إلغاء قيد المراجعة',
    'مرفوض',
    'ملغى',
    'مكتمل',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<BookingProvider>().loadMyBookings();
    });
  }

  bool _matchesFilter(BookingModel booking) {
    if (filter == 'الكل') return true;
    return booking.effectiveStatusLabel == filter;
  }

  @override
  Widget build(BuildContext context) {
    final bookings = context.watch<BookingProvider>().bookings.where(_matchesFilter).toList();

    return Scaffold(
      appBar: AppBar(title: const Text('حجوزاتي')),
      body: Column(
        children: [
          SizedBox(
            height: 50,
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              scrollDirection: Axis.horizontal,
              itemBuilder: (_, i) => ChoiceChip(
                label: Text(filters[i]),
                selected: filter == filters[i],
                onSelected: (_) => setState(() => filter = filters[i]),
              ),
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemCount: filters.length,
            ),
          ),
          Expanded(
            child: bookings.isEmpty
                ? const EmptyState(
                    icon: Icons.event_busy_rounded,
                    title: 'لا توجد حجوزات بعد',
                    subtitle: 'احجز صالة وتابع حالة الحجز من هنا.',
                  )
                : ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: bookings.length,
                    itemBuilder: (context, index) => _BookingCard(booking: bookings[index]),
                  ),
          ),
        ],
      ),
    );
  }
}

class _BookingCard extends StatelessWidget {
  const _BookingCard({required this.booking});

  final BookingModel booking;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  booking.venueName,
                  style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
                ),
              ),
              _status(booking),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            '${booking.eventDate.year}-${booking.eventDate.month}-${booking.eventDate.day} • ${booking.guests} ضيف',
            style: const TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 8),
          Text(
            context.watch<AppSettingsProvider>().formatPrice(booking.totalAmount),
            style: const TextStyle(color: AppColors.success, fontWeight: FontWeight.bold),
          ),
          if (booking.isAwaitingRefund) ...[
            const SizedBox(height: 8),
            Text(
              booking.refundAmount > 0
                  ? 'الاسترداد المتوقع: ${context.watch<AppSettingsProvider>().formatPrice(booking.refundAmount)}'
                  : 'تم إلغاء الحجز وهو بانتظار تأكيد الاسترداد من المالك.',
              style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
            ),
          ],
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => BookingDetailsScreen(booking: booking)),
            ),
            child: const Text('عرض التفاصيل'),
          ),
        ],
      ),
    );
  }

  Widget _status(BookingModel booking) {
    final status = booking.status;
    Color color;
    switch (status) {
      case BookingStatus.pending:
        color = AppColors.warning;
        break;
      case BookingStatus.approved:
        color = AppColors.primary;
        break;
      case BookingStatus.paymentUploaded:
        color = AppColors.secondary;
        break;
      case BookingStatus.paid:
        color = AppColors.success;
        break;
      case BookingStatus.modificationRequested:
        color = AppColors.secondary;
        break;
      case BookingStatus.cancellationRequested:
        color = AppColors.warning;
        break;
      case BookingStatus.rejected:
        color = AppColors.danger;
        break;
      case BookingStatus.cancelled:
        color = booking.isAwaitingRefund ? AppColors.warning : AppColors.danger;
        break;
      case BookingStatus.completed:
        color = AppColors.textSecondary;
        break;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withOpacity(.15),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        booking.effectiveStatusLabel,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w800,
          fontSize: 12,
        ),
      ),
    );
  }
}
