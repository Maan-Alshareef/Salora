import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../models/booking_model.dart';
import '../../models/venue_model.dart';
import '../../providers/booking_provider.dart';
import 'booking_success_screen.dart';

class BookingSummaryScreen extends StatelessWidget {
  final VenueModel venue;
  final String date;
  final int guests;
  final String totalAmount;
  final List<String> services;

  const BookingSummaryScreen({
    super.key,
    required this.venue,
    required this.date,
    required this.guests,
    required this.totalAmount,
    required this.services,
  });

  DateTime _parseDate() {
    try {
      return DateTime.parse(date);
    } catch (_) {
      return DateTime.now().add(const Duration(days: 7));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('ملخص الحجز')),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
                      child: Column(
                        children: [
                          ClipRRect(
                            borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                            child: venue.image.isEmpty
                                ? Container(
                                    height: 160,
                                    width: double.infinity,
                                    color: AppColors.primary.withOpacity(.15),
                                    child: const Icon(Icons.apartment_rounded, size: 64, color: AppColors.primary),
                                  )
                                : Image.asset(venue.image, height: 160, width: double.infinity, fit: BoxFit.cover),
                          ),
                          Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              children: [
                                _buildRow('الصالة', venue.name),
                                const SizedBox(height: 12),
                                _buildRow('الموقع', venue.city),
                                const SizedBox(height: 12),
                                _buildRow('عدد الضيوف', '$guests'),
                                const SizedBox(height: 12),
                                _buildRow('تاريخ المناسبة', date),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),
                    const Text('الخدمات المختارة', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
                      child: services.isEmpty
                          ? const ListTile(title: Text('لا توجد خدمات إضافية مختارة', style: TextStyle(color: AppColors.textSecondary)))
                          : Column(
                              children: services.map((service) {
                                IconData icon = Icons.check_circle_outline;
                                if (service == 'تصوير') icon = Icons.camera_alt_rounded;
                                if (service == 'ديكور') icon = Icons.auto_awesome_rounded;
                                if (service == 'خدمة دي جي') icon = Icons.music_note_rounded;
                                if (service == 'خدمة ضيافة') icon = Icons.restaurant_rounded;
                                return ListTile(leading: Icon(icon), title: Text(service));
                              }).toList(),
                            ),
                    ),
                    const SizedBox(height: 24),
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('المبلغ الإجمالي', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                          Text(totalAmount, style: const TextStyle(color: Colors.green, fontSize: 22, fontWeight: FontWeight.w900)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: ElevatedButton(
                onPressed: () async {
                  final booking = await context.read<BookingProvider>().createBooking(
                        venue: venue,
                        date: _parseDate(),
                        startTime: '18:00',
                        endTime: '23:00',
                        eventType: venue.eventTypes.isNotEmpty ? venue.eventTypes.first : 'زفاف',
                        guests: guests,
                        services: services,
                        paymentMethod: PaymentMethod.bankTransfer,
                      );
                  Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => BookingSuccessScreen(booking: booking)));
                },
                child: const Text('تأكيد الحجز'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: AppColors.textSecondary)),
        Text(value, style: const TextStyle(fontWeight: FontWeight.bold)),
      ],
    );
  }
}
