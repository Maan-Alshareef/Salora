import 'dart:io';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../core/network/api_client.dart';
import '../../salora_v2/salora_booking_actions_panel.dart';
import '../../salora_v2/salora_booking_v2_api.dart';
import '../../models/booking_model.dart';
import '../../providers/app_settings_provider.dart';
import '../../providers/booking_provider.dart';
import '../../providers/event_provider.dart';
import '../../models/event_model.dart';
import 'invoice_document_screen.dart';
import 'payment_proof_screen.dart';
import 'payment_receipt_screen.dart';
import 'provider_service_payment_screen.dart';
import '../services/services_screen.dart';
import '../events/event_todo_screen.dart';
import '../invitations/invitation_screen.dart';

class BookingDetailsScreen extends StatelessWidget {
  final BookingModel booking;
  const BookingDetailsScreen({super.key, required this.booking});

  static List<int> _clockParts(String value) {
    final match = RegExp(r'(\d{1,2}):(\d{2})').firstMatch(value);
    return <int>[
      int.tryParse(match?.group(1) ?? '') ?? 0,
      int.tryParse(match?.group(2) ?? '') ?? 0,
    ];
  }

  static bool _isRemoteReceipt(String value) {
    final uri = Uri.tryParse(value.trim());
    return uri != null && (uri.scheme == 'http' || uri.scheme == 'https');
  }

  Widget _receiptPreview(BuildContext context, String source) {
    if (_isRemoteReceipt(source)) {
      final token = context.read<ApiClient>().token?.trim() ?? '';
      return Image.network(
        source,
        headers: token.isEmpty
            ? const <String, String>{}
            : <String, String>{'Authorization': 'Bearer $token'},
        height: 190,
        width: double.infinity,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => Container(
          height: 150,
          color: AppColors.surface,
          alignment: Alignment.center,
          child: const Text(
            'تم رفع الإيصال، لكن تعذر تحميل المعاينة الآن.',
            textAlign: TextAlign.center,
            style: TextStyle(color: AppColors.textSecondary),
          ),
        ),
      );
    }

    return Image.file(
      File(source),
      height: 190,
      width: double.infinity,
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) => Container(
        height: 150,
        color: AppColors.surface,
        alignment: Alignment.center,
        child: const Text(
          'تم رفع الإيصال، لكن تعذر تحميل المعاينة الآن.',
          textAlign: TextAlign.center,
          style: TextStyle(color: AppColors.textSecondary),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bookings = context.watch<BookingProvider>().bookings;
    BookingModel fresh = booking;
    for (final item in bookings) {
      if (item.id == booking.id) {
        fresh = item;
        break;
      }
    }
    final settings = context.watch<AppSettingsProvider>();
    final bookingId = int.tryParse(fresh.id);
    final venueId = int.tryParse(fresh.venueId);
    final startParts = _clockParts(fresh.startTime);
    final endParts = _clockParts(fresh.endTime);
    final eventStartAt = DateTime(
      fresh.eventDate.year,
      fresh.eventDate.month,
      fresh.eventDate.day,
      startParts[0],
      startParts[1],
    );
    var eventEndAt = DateTime(
      fresh.eventDate.year,
      fresh.eventDate.month,
      fresh.eventDate.day,
      endParts[0],
      endParts[1],
    );
    if (!eventEndAt.isAfter(eventStartAt)) {
      eventEndAt = eventEndAt.add(const Duration(days: 1));
    }
    return Scaffold(
      appBar: AppBar(title: const Text('تفاصيل الحجز')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(20),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  fresh.eventTitle,
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  fresh.venueName,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                Text(
                  fresh.id,
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
                const Divider(height: 28),
                _row('الحالة', fresh.effectiveStatusLabel),
                if (fresh.isAwaitingRefund) ...[
                  _row('الاسترداد', fresh.refundAmount > 0 ? '${settings.formatPrice(fresh.refundAmount)} (${fresh.refundPercentage.toStringAsFixed(0)}%)' : 'بانتظار تنفيذ الاسترداد'),
                  if ((fresh.cancellationReason ?? '').isNotEmpty) _row('سبب الإلغاء', fresh.cancellationReason!),
                ],
                _row('المدينة', fresh.city),
                _row(
                  'تاريخ المناسبة',
                  '${fresh.eventDate.year}-${fresh.eventDate.month}-${fresh.eventDate.day}',
                ),
                _row('الوقت', '${fresh.startTime} - ${fresh.endTime}'),
                _row('نوع المناسبة', fresh.eventType),
                _row('عدد الضيوف', '${fresh.guests}'),
                _row('الدفع', 'دفع إلكتروني'),
                _row(
                  'الإيصال',
                  fresh.receiptPath == null ? 'لم يتم الرفع' : 'تم الرفع',
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          _eventHub(context, fresh),
          if (fresh.receiptPath != null) ...[
            const SizedBox(height: 16),
            const Text(
              'معاينة إيصال الدفع',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: _receiptPreview(context, fresh.receiptPath!),
            ),
          ],
          const SizedBox(height: 18),
          const Text(
            'فاتورة الصالة فقط',
            style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Column(
              children: [
                ...fresh.invoiceItems.map(
                      (item) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 7),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.title,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              Text(
                                item.category,
                                style: const TextStyle(
                                  color: AppColors.textSecondary,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          item.isIncluded
                              ? 'مشمول'
                              : settings.formatPrice(item.amount),
                          style: TextStyle(
                            color: item.isIncluded ? AppColors.success : null,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const Divider(height: 26),
                _row(
                  'إجمالي الدفع للمالك',
                  settings.formatPrice(fresh.totalAmount),
                  bold: true,
                ),
              ],
            ),
          ),
          if (fresh.providerRequests.isNotEmpty) ...[
            const SizedBox(height: 18),
            const Text(
              'خدمات خارجية - دفعات منفصلة',
              style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Column(
                children: fresh.providerRequests
                    .map(
                      (request) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 7),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                request.serviceName,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              Text(
                                '${request.providerName} • ${request.statusLabel} • ${request.paymentStatusLabel}',
                                style: const TextStyle(
                                  color: AppColors.textSecondary,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              settings.formatPrice(request.priceSyp),
                              style: const TextStyle(
                                color: AppColors.primary,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            if (request.canUploadPayment) ...[
                              const SizedBox(height: 6),
                              FilledButton.tonal(
                                onPressed: () async {
                                  await Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) =>
                                          ProviderServicePaymentScreen(
                                            request: request,
                                          ),
                                    ),
                                  );
                                  if (context.mounted) {
                                    await context
                                        .read<BookingProvider>()
                                        .loadMyBookings();
                                  }
                                },
                                child: const Text('دفع الخدمة'),
                              ),
                            ],
                            if (request.canViewPaymentDocument) ...[
                              const SizedBox(height: 6),
                              OutlinedButton(
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        ProviderServicePaymentScreen(
                                          request: request,
                                          showDocument: true,
                                        ),
                                  ),
                                ),
                                child: const Text('وثيقة الدفع'),
                              ),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),
                )
                    .toList(),
              ),
            ),
          ],
          const SizedBox(height: 18),
          if (!fresh.status.isFinal && bookingId != null && venueId != null)
            SaloraBookingActionsPanel(
              bookingId: bookingId,
              venueId: venueId,
              currentGuestCount: fresh.guests,
              eventStartAt: eventStartAt,
              eventEndAt: eventEndAt,
              api: SaloraBookingV2Api(
                baseUrl: context.read<ApiClient>().baseUrl,
                tokenProvider: () async => context.read<ApiClient>().token,
              ),
              onChanged: () => context.read<BookingProvider>().loadMyBookings(),
            ),
          const SizedBox(height: 18),
          const Text(
            'مسار الحجز',
            style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          _timeline(fresh),
          const SizedBox(height: 18),
          if (fresh.invoiceId != null &&
              fresh.invoiceId!.isNotEmpty &&
              fresh.receiptPath == null &&
              fresh.status.canUploadPaymentProof)
            ElevatedButton.icon(
              onPressed: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => PaymentProofScreen(booking: fresh),
                  ),
                );
                if (context.mounted) {
                  await context.read<BookingProvider>().loadMyBookings();
                }
              },
              icon: const Icon(Icons.upload_file),
              label: const Text('اختيار وسيلة الدفع ورفع إيصال الدفع'),
            ),
          if (fresh.status == BookingStatus.paymentUploaded) ...[
            const Text(
              'تم رفع إيصال الدفع وهو بانتظار مراجعة صاحب المبلغ.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w700,
              ),
            ),
            if (fresh.invoiceId != null)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: OutlinedButton.icon(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => InvoiceDocumentScreen(
                        invoiceId: fresh.invoiceId!,
                        sourceTitle: fresh.venueName,
                        sourceSubtitle:
                        '${fresh.eventDate.year}-${fresh.eventDate.month}-${fresh.eventDate.day} • ${fresh.startTime} - ${fresh.endTime}',
                      ),
                    ),
                  ),
                  icon: const Icon(Icons.receipt_long_outlined),
                  label: const Text('عرض إيصال الدفع المرفوع'),
                ),
              ),
          ],
          if (fresh.status == BookingStatus.paid)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: OutlinedButton.icon(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => ServicesScreen(
                      preferredBookingId: fresh.id,
                      preferredEventType: fresh.eventType,
                    ),
                  ),
                ),
                icon: const Icon(Icons.storefront_outlined),
                label: const Text('اختيار مقدم خدمة لهذا الحجز'),
              ),
            ),
          if ((fresh.status == BookingStatus.paid ||
              fresh.status == BookingStatus.completed) &&
              fresh.invoiceId != null)
            ElevatedButton.icon(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => PaymentReceiptScreen(booking: fresh),
                ),
              ),
              icon: const Icon(Icons.receipt_long_outlined),
              label: const Text('عرض وحفظ إيصال الدفع'),
            ),
        ],
      ),
    );
  }

  EventModel _eventForBooking(BuildContext context, BookingModel booking) {
    final events = context.read<EventProvider>().events;
    for (final event in events) {
      if ((booking.eventId != null && booking.eventId!.isNotEmpty && event.id == booking.eventId) ||
          event.bookingId == booking.id) {
        return event;
      }
    }

    return EventModel(
      id: booking.eventId?.isNotEmpty == true ? booking.eventId! : 'booking-${booking.id}',
      title: booking.eventTitle,
      type: eventTypeFromLabel(booking.eventType),
      date: booking.eventDate,
      city: booking.city,
      guests: booking.guests,
      budget: booking.totalAmount,
      totalAmount: booking.totalAmount,
      venueId: booking.venueId,
      venueName: booking.venueName,
      startTime: booking.startTime,
      endTime: booking.endTime,
      bookingId: booking.id,
      neededServices: const [],
      tasks: const [],
      createdAt: booking.eventDate,
    );
  }

  Widget _eventHub(BuildContext context, BookingModel booking) {
    final event = _eventForBooking(context, booking);
    final settings = context.watch<AppSettingsProvider>();
    final canAccessEventTools = booking.status == BookingStatus.paid ||
        booking.status == BookingStatus.completed;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'تفاصيل المناسبة',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          _row('العنوان', event.title, bold: true),
          _row('الصالة', booking.venueName),
          _row('الموقع', booking.city),
          _row('الوقت', '${booking.startTime} - ${booking.endTime}'),
          _row('إجمالي الحجز', settings.formatPrice(booking.totalAmount), bold: true),
          const SizedBox(height: 12),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.12,
            children: [
              _eventActionCard(
                icon: Icons.room_service_rounded,
                title: 'الخدمات',
                subtitle: 'إضافة مقدمي خدمات',
                onTap: canAccessEventTools
                    ? () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => ServicesScreen(
                              event: event,
                              preferredBookingId: booking.id,
                              preferredEventType: booking.eventType,
                            ),
                          ),
                        )
                    : null,
              ),
              _eventActionCard(
                icon: Icons.receipt_long_outlined,
                title: 'الفاتورة',
                subtitle: 'الإجمالي والدفع',
                onTap: (booking.status == BookingStatus.paid || booking.status == BookingStatus.completed) && booking.invoiceId != null
                    ? () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => PaymentReceiptScreen(booking: booking)),
                        )
                    : booking.invoiceId != null
                        ? () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => InvoiceDocumentScreen(
                                  invoiceId: booking.invoiceId!,
                                  sourceTitle: booking.venueName,
                                  sourceSubtitle:
                                      '${booking.eventDate.year}-${booking.eventDate.month}-${booking.eventDate.day} • ${booking.startTime} - ${booking.endTime}',
                                ),
                              ),
                            )
                        : null,
              ),
              _eventActionCard(
                icon: Icons.card_giftcard_rounded,
                title: 'الدعوة',
                subtitle: 'إنشاء ومشاركة',
                onTap: canAccessEventTools
                    ? () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => InvitationScreen(event: event)),
                        )
                    : null,
              ),
              _eventActionCard(
                icon: Icons.checklist_rounded,
                title: 'قائمة المهام',
                subtitle: 'إدارة المهام',
                onTap: canAccessEventTools
                    ? () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => EventTodoScreen(event: event)),
                        )
                    : null,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            booking.providerRequests.isEmpty
                ? 'الخدمات المدفوعة المختارة\nلا توجد خدمات مدفوعة مختارة.'
                : 'الخدمات المدفوعة المختارة\nتم اختيار ${booking.providerRequests.length} خدمة مرتبطة بهذه المناسبة.',
            style: const TextStyle(color: AppColors.textSecondary, height: 1.5),
          ),
        ],
      ),
    );
  }

  Widget _eventActionCard({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback? onTap,
  }) {
    final enabled = onTap != null;
    return InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.surface2,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.textSecondary.withOpacity(0.18)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: AppColors.primary.withOpacity(0.12),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: enabled ? AppColors.primary : AppColors.textSecondary),
            ),
            const Spacer(),
            Text(title, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
            const SizedBox(height: 4),
            Text(
              enabled ? subtitle : 'يتاح بعد تأكيد الحجز',
              style: const TextStyle(color: AppColors.textSecondary),
            ),
          ],
        ),
      ),
    );
  }

  Widget _row(String a, String b, {bool bold = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(a, style: const TextStyle(color: AppColors.textSecondary)),
        const Spacer(),
        Flexible(
          child: Text(
            b,
            textAlign: TextAlign.end,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
            ),
          ),
        ),
      ],
    ),
  );

  Widget _timeline(BookingModel booking) {
    final paymentUploaded =
        booking.status == BookingStatus.paymentUploaded ||
            booking.status == BookingStatus.paid ||
            booking.status == BookingStatus.completed;
    final confirmed =
        booking.status == BookingStatus.paid ||
            booking.status == BookingStatus.completed;
    final steps = <_TimelineItem>[
      const _TimelineItem('تم إنشاء الحجز وحجز الموعد مؤقتًا', true),
      _TimelineItem('رفع إيصال الدفع', paymentUploaded),
      _TimelineItem('مراجعة مالك الصالة لإيصال الدفع', confirmed),
      _TimelineItem('تأكيد الحجز نهائيًا', confirmed),
    ];
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: steps
            .map(
              (s) => ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(
              s.done ? Icons.check_circle : Icons.radio_button_unchecked,
              color: s.done ? AppColors.success : AppColors.textSecondary,
            ),
            title: Text(s.title),
          ),
        )
            .toList(),
      ),
    );
  }
}

class _TimelineItem {
  final String title;
  final bool done;
  const _TimelineItem(this.title, this.done);
}
