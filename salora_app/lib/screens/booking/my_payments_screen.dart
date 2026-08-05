import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../providers/app_settings_provider.dart';
import '../../providers/booking_provider.dart';
import 'booking_details_screen.dart';

class MyPaymentsScreen extends StatefulWidget {
  const MyPaymentsScreen({super.key});

  @override
  State<MyPaymentsScreen> createState() => _MyPaymentsScreenState();
}

class _MyPaymentsScreenState extends State<MyPaymentsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => context.read<BookingProvider>().loadMyBookings(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bookings = context.watch<BookingProvider>().bookings;
    final settings = context.watch<AppSettingsProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('مدفوعاتي')),
      body: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: bookings.length,
        itemBuilder: (_, i) {
          final booking = bookings[i];
          return Container(
            margin: const EdgeInsets.only(bottom: 12),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18),
            ),
            child: InkWell(
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => BookingDetailsScreen(booking: booking),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    booking.venueName,
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 17,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    '${booking.eventDate.year}-${booking.eventDate.month}-${booking.eventDate.day} • ${booking.startTime} - ${booking.endTime}',
                    style: const TextStyle(color: AppColors.textSecondary),
                  ),
                  const Divider(height: 24),
                  _row(
                    'المدفوع للمالك',
                    settings.formatPrice(booking.totalAmount),
                  ),
                  _row('حالة الحجز/الدفع', _bookingStatusLabel(booking.status)),
                  _row(
                    'إيصال الدفع',
                    booking.receiptPath == null ? 'لم يتم الرفع' : 'مرفوع',
                  ),
                  if (booking.providerRequests.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    const Text(
                      'خدمات خارجية لا تدخل ضمن دفعة الصالة:',
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                    ...booking.providerRequests.map(
                      (r) => Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text(
                          '${r.serviceName}: ${settings.formatPrice(r.priceSyp)} دفعة مستقلة • ${r.statusLabel}',
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _row(String a, String b) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      children: [
        Text(a, style: const TextStyle(color: AppColors.textSecondary)),
        const Spacer(),
        Flexible(
          child: Text(
            b,
            textAlign: TextAlign.end,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ),
      ],
    ),
  );
}

String _bookingStatusLabel(dynamic status) {
  final value = status.toString().split('.').last;
  switch (value) {
    case 'pending':
      return 'قيد الانتظار';
    case 'approved':
      return 'بانتظار الدفع';
    case 'paymentUploaded':
      return 'تم رفع إيصال الدفع';
    case 'paid':
      return 'الدفع مقبول';
    case 'cancelled':
      return 'ملغى';
    case 'completed':
      return 'مكتمل';
    default:
      return 'غير معروف';
  }
}
