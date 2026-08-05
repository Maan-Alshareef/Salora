import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../models/booking_model.dart';
import '../../models/service_model.dart';
import '../../providers/booking_provider.dart';

Future<bool?> showServiceBookingSheet(
  BuildContext context, {
  required ServiceModel service,
  String? preferredBookingId,
}) {
  return showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (_) => _ServiceBookingSheet(
      service: service,
      preferredBookingId: preferredBookingId,
    ),
  );
}

class _ServiceBookingSheet extends StatefulWidget {
  final ServiceModel service;
  final String? preferredBookingId;

  const _ServiceBookingSheet({required this.service, this.preferredBookingId});

  @override
  State<_ServiceBookingSheet> createState() => _ServiceBookingSheetState();
}

class _ServiceBookingSheetState extends State<_ServiceBookingSheet> {
  final notes = TextEditingController();
  String? selectedBookingId;
  bool submitting = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadAndSelect());
  }

  Future<void> _loadAndSelect() async {
    final provider = context.read<BookingProvider>();
    await provider.loadMyBookings();
    if (!mounted) return;
    _selectBestBooking(provider.activeForProviderServices);
  }

  void _selectBestBooking(List<BookingModel> bookings) {
    final eligible = bookings.where(_isEligible).toList();
    if (eligible.isEmpty) return;
    final preferred = eligible
        .where((booking) => booking.id == widget.preferredBookingId)
        .toList();
    final nextId = preferred.isNotEmpty
        ? preferred.first.id
        : eligible.first.id;
    if (selectedBookingId != nextId) {
      setState(() => selectedBookingId = nextId);
    }
  }

  bool _isEligible(BookingModel booking) =>
      !booking.hasProviderServiceRequest(widget.service.id) &&
      widget.service.supportsEvent(booking.eventType) &&
      booking.allowsProviderCategory(widget.service.category);

  @override
  void dispose() {
    notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bookingProvider = context.watch<BookingProvider>();
    final bookings = bookingProvider.activeForProviderServices;
    final eligible = bookings.where(_isEligible).toList();
    if (selectedBookingId == null && eligible.isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && selectedBookingId == null) _selectBestBooking(bookings);
      });
    }

    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 16,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'إضافة الخدمة إلى حجز',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 6),
            Text(
              widget.service.name,
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 14),
            if (bookingProvider.isLoading && bookings.isEmpty)
              const Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (bookings.isEmpty)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.primary.withOpacity(.08),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Text(
                  'لا يوجد حجز صالة مثبت. ارفع إيصال الدفع للحجز، وبعد أن يتحقق مالك الصالة منه ويتثبت الحجز ستتمكن من اختيار مقدم خدمة.',
                  textAlign: TextAlign.center,
                  style: TextStyle(height: 1.5),
                ),
              )
            else ...[
              const Text(
                'اختر الحجز المثبت:',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              ...bookings.map(_bookingTile),
              const SizedBox(height: 10),
              TextField(
                controller: notes,
                minLines: 2,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'ملاحظات لمقدم الخدمة (اختياري)',
                  hintText: 'مثال: نوع التغطية أو التجهيز المطلوب',
                ),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.accent.withOpacity(.08),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Text(
                  '${widget.service.paymentLabel}. لا تضاف هذه الخدمة إلى فاتورة الصالة في النسخة الجامعية المبسطة.',
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontSize: 12,
                    height: 1.4,
                  ),
                ),
              ),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: submitting || selectedBookingId == null
                    ? null
                    : _submit,
                child: Text(
                  submitting
                      ? 'جاري إرسال الطلب...'
                      : 'إرسال الطلب لمقدم الخدمة',
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _bookingTile(BookingModel booking) {
    final alreadyRequested = booking.hasProviderServiceRequest(
      widget.service.id,
    );
    final supportsEvent = widget.service.supportsEvent(booking.eventType);
    final allowedByVenue = booking.allowsProviderCategory(
      widget.service.category,
    );
    final enabled = !alreadyRequested && supportsEvent && allowedByVenue;
    final subtitleParts = [
      booking.eventTitle,
      booking.eventType,
      _date(booking.eventDate),
      '${booking.startTime} - ${booking.endTime}',
    ];

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: selectedBookingId == booking.id
              ? AppColors.primary
              : Colors.transparent,
        ),
      ),
      child: RadioListTile<String>(
        value: booking.id,
        groupValue: selectedBookingId,
        onChanged: enabled
            ? (value) => setState(() => selectedBookingId = value)
            : null,
        title: Text(
          booking.venueName,
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        subtitle: Text(
          alreadyRequested
              ? 'تم إرسال طلب لهذه الخدمة مسبقاً على هذا الحجز.'
              : !supportsEvent
              ? 'الخدمة لا تدعم نوع المناسبة: ${booking.eventType}'
              : !allowedByVenue
              ? 'سياسة الصالة لا تسمح بتصنيف الخدمة: ${widget.service.category}'
              : subtitleParts.join(' • '),
          style: TextStyle(
            color: enabled ? AppColors.textSecondary : Colors.orangeAccent,
            fontSize: 12,
          ),
        ),
      ),
    );
  }

  Future<void> _submit() async {
    final bookingId = selectedBookingId;
    if (bookingId == null) return;
    setState(() => submitting = true);
    try {
      await context.read<BookingProvider>().requestProviderServices(
        bookingId: bookingId,
        serviceIds: [widget.service.id],
        notes: notes.text,
      );
      if (!mounted) return;
      Navigator.pop(context, true);
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  String _date(DateTime value) =>
      '${value.year}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
}
