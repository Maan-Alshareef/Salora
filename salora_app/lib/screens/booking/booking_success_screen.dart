import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../models/booking_model.dart';
import '../../models/event_model.dart';
import '../events/event_details_screen.dart';
import 'booking_details_screen.dart';
import 'payment_proof_screen.dart';

class BookingSuccessScreen extends StatelessWidget {
  final BookingModel booking;
  final EventModel? event;

  const BookingSuccessScreen({super.key, required this.booking, this.event});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              const SizedBox(height: 36),
              const CircleAvatar(
                radius: 52,
                backgroundColor: AppColors.success,
                child: Icon(Icons.check_rounded, size: 58, color: Colors.white),
              ),
              const SizedBox(height: 24),
              const Text(
                'تم إنشاء الحجز',
                style: TextStyle(fontSize: 27, fontWeight: FontWeight.w900),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 10),
              Text(
                'تم حجز الموعد مؤقتًا في ${booking.venueName}. بعد الدفع ارفع الإيصال في أي وقت بدون مهلة زمنية، وبعد أن يتحقق مالك الصالة منه يتثبت الحجز نهائيًا.',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  height: 1.6,
                ),
              ),
              const SizedBox(height: 28),
              ElevatedButton.icon(
                onPressed: booking.invoiceId == null
                    ? null
                    : () => Navigator.pushReplacement(
                        context,
                        MaterialPageRoute(
                          builder: (_) => PaymentProofScreen(booking: booking),
                        ),
                      ),
                icon: const Icon(Icons.upload_file_rounded),
                label: Text(
                  booking.invoiceId == null
                      ? 'جاري تجهيز فاتورة الدفع'
                      : 'رفع إيصال الدفع الآن',
                ),
              ),
              const SizedBox(height: 10),
              OutlinedButton(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => BookingDetailsScreen(booking: booking),
                  ),
                ),
                child: const Text('عرض تفاصيل الحجز'),
              ),
              if (event != null) ...[
                const SizedBox(height: 8),
                TextButton(
                  onPressed: () => Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(
                      builder: (_) => EventDetailsScreen(event: event!),
                    ),
                  ),
                  child: const Text('عرض تفاصيل المناسبة'),
                ),
              ],
              TextButton(
                onPressed: () =>
                    Navigator.popUntil(context, (route) => route.isFirst),
                child: const Text('العودة للرئيسية'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
