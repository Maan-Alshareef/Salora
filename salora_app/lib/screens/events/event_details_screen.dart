import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../models/booking_model.dart';
import '../../models/event_model.dart';
import '../../providers/app_settings_provider.dart';
import '../../providers/booking_provider.dart';
import '../../providers/event_provider.dart';
import '../booking/booking_details_screen.dart';
import '../invitations/invitation_screen.dart';
import '../services/services_screen.dart';
import 'create_event_screen.dart';
import 'event_todo_screen.dart';

class EventDetailsScreen extends StatelessWidget {
  final EventModel event;
  const EventDetailsScreen({super.key, required this.event});

  @override
  Widget build(BuildContext context) {
    final freshEvent = context.watch<EventProvider>().events.firstWhere((item) => item.id == event.id, orElse: () => event);
    final bookings = context.watch<BookingProvider>().bookings;
    BookingModel? booking;
    for (final item in bookings) {
      if (item.eventId == freshEvent.id) {
        booking = item;
        break;
      }
    }
    final settings = context.watch<AppSettingsProvider>();
    final date = '${freshEvent.date.day}/${freshEvent.date.month}/${freshEvent.date.year}';
    final total = booking?.totalAmount ?? freshEvent.totalAmount;

    return Scaffold(
      appBar: AppBar(
        title: const Text('تفاصيل المناسبة'),
        actions: [
          PopupMenuButton<String>(
            onSelected: (value) async {
              if (value == 'edit') {
                await Navigator.push(context, MaterialPageRoute(builder: (_) => CreateEventScreen(event: freshEvent)));
                return;
              }
              if (value == 'delete') {
                final confirmed = await showDialog<bool>(
                  context: context,
                  builder: (dialogContext) => AlertDialog(
                    title: const Text('حذف المناسبة'),
                    content: const Text('سيتم أرشفة المناسبة. لا يمكن حذف مناسبة مرتبطة بحجز مدفوع أو نشط.'),
                    actions: [
                      TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('تراجع')),
                      FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('حذف')),
                    ],
                  ),
                );
                if (confirmed != true || !context.mounted) return;
                try {
                  await context.read<EventProvider>().deleteEvent(freshEvent.id);
                  if (context.mounted) Navigator.pop(context);
                } catch (error) {
                  if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
                }
              }
            },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'edit', child: ListTile(leading: Icon(Icons.edit_outlined), title: Text('تعديل'))),
              PopupMenuItem(value: 'delete', child: ListTile(leading: Icon(Icons.delete_outline_rounded), title: Text('حذف'))),
            ],
          ),
        ],
      ),
      body: ListView(padding: const EdgeInsets.all(18), children: [
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(28), gradient: const LinearGradient(colors: [AppColors.primary, AppColors.secondary])),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Text(freshEvent.type.iconEmoji, style: const TextStyle(fontSize: 34)),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: .16), borderRadius: BorderRadius.circular(30)),
                child: Text(freshEvent.status.arabicLabel, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900)),
              ),
            ]),
            const SizedBox(height: 8),
            Text(freshEvent.displayEventType, style: const TextStyle(color: Colors.white70, fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            Text(freshEvent.title, style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w900, color: Colors.white)),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [
              _chip(Icons.calendar_month_rounded, date),
              _chip(Icons.location_on_outlined, freshEvent.city),
              _chip(Icons.groups_rounded, '${freshEvent.guests} ضيف'),
              _chip(Icons.payments_outlined, settings.formatPrice(total)),
            ]),
          ]),
        ),
        const SizedBox(height: 16),
        if (freshEvent.venueName != null) _hallSummary(freshEvent, booking),
        const SizedBox(height: 16),
        _progressCard(freshEvent),
        const SizedBox(height: 18),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.17,
          children: [
            _ActionCard(icon: Icons.receipt_long_rounded, title: 'الفاتورة', subtitle: 'الإجمالي والدفع', onTap: booking == null ? null : () => Navigator.push(context, MaterialPageRoute(builder: (_) => BookingDetailsScreen(booking: booking!)))),
            _ActionCard(icon: Icons.room_service_rounded, title: 'الخدمات', subtitle: 'إضافة مقدمي خدمات', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ServicesScreen(event: freshEvent)))),
            _ActionCard(icon: Icons.checklist_rounded, title: 'قائمة المهام', subtitle: 'إدارة المهام', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EventTodoScreen(event: freshEvent)))),
            _ActionCard(icon: Icons.card_giftcard_rounded, title: freshEvent.type == EventType.condolence ? 'دعوة عزاء إلكترونية' : 'الدعوة', subtitle: 'إنشاء ومشاركة', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => InvitationScreen(event: freshEvent)))),
          ],
        ),
        const SizedBox(height: 16),
        Text('الخدمات المدفوعة المختارة', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
        const SizedBox(height: 10),
        if (freshEvent.neededServices.isEmpty)
          const Text('لا توجد خدمات مدفوعة مختارة.', style: TextStyle(color: AppColors.textSecondary))
        else
          Wrap(spacing: 8, runSpacing: 8, children: freshEvent.neededServices.map((service) => Chip(label: Text(service), avatar: const Icon(Icons.check_circle_outline, size: 18))).toList()),
      ]),
    );
  }

  Widget _hallSummary(EventModel event, BookingModel? booking) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(22)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('حجز الصالة', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
        const SizedBox(height: 10),
        _info(Icons.location_city_rounded, event.venueName ?? '-'),
        _info(Icons.place_outlined, event.venueAddress ?? event.city),
        if (event.timeRange.isNotEmpty) _info(Icons.schedule_rounded, event.timeRange),
        if (booking != null) _info(Icons.verified_outlined, booking.status.label),
      ]),
    );
  }

  Widget _progressCard(EventModel event) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(22)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const Expanded(child: Text('تقدم التخطيط', style: TextStyle(fontWeight: FontWeight.w900))),
          Text('${(event.progress * 100).round()}%', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w900)),
        ]),
        const SizedBox(height: 10),
        ClipRRect(borderRadius: BorderRadius.circular(20), child: LinearProgressIndicator(value: event.progress, minHeight: 9, backgroundColor: AppColors.surface2)),
        const SizedBox(height: 8),
        Text('${event.completedTasks}/${event.tasks.length} مهام مكتملة', style: const TextStyle(color: AppColors.textSecondary)),
      ]),
    );
  }

  Widget _chip(IconData icon, String text) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(color: Colors.white.withValues(alpha: .14), borderRadius: BorderRadius.circular(30)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, color: Colors.white, size: 16), const SizedBox(width: 6), Text(text, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700))]),
      );

  Widget _info(IconData icon, String text) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, size: 18, color: AppColors.primary), const SizedBox(width: 8), Expanded(child: Text(text, style: const TextStyle(color: AppColors.textSecondary, height: 1.35)))]),
      );
}

class _ActionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;
  const _ActionCard({required this.icon, required this.title, required this.subtitle, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(24),
      onTap: onTap,
      child: Opacity(
        opacity: onTap == null ? .5 : 1,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(24), border: Border.all(color: Colors.white10)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            CircleAvatar(backgroundColor: AppColors.surface2, child: Icon(icon, color: AppColors.primary)),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
              const SizedBox(height: 4),
              Text(subtitle, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
            ]),
          ]),
        ),
      ),
    );
  }
}
