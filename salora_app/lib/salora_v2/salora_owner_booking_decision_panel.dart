import 'package:flutter/material.dart';

import 'salora_booking_v2_api.dart';

class SaloraOwnerBookingDecisionPanel extends StatelessWidget {
  const SaloraOwnerBookingDecisionPanel({
    super.key,
    required this.bookingId,
    required this.api,
    this.pendingChangeRequestId,
    this.cancellationStatus,
    this.canCancelBooking = true,
    this.onChanged,
  });

  final int bookingId;
  final SaloraBookingV2Api api;
  final int? pendingChangeRequestId;
  final String? cancellationStatus;
  final bool canCancelBooking;
  final VoidCallback? onChanged;

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (pendingChangeRequestId != null) ...[
            const Text(
              'طلب تعديل بانتظار القرار',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () => _approve(context),
                    icon: const Icon(Icons.check),
                    label: const Text('قبول التعديل'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _reject(context),
                    icon: const Icon(Icons.close),
                    label: const Text('رفض التعديل'),
                  ),
                ),
              ],
            ),
          ],
          if (canCancelBooking &&
              cancellationStatus != 'waiting_refund' &&
              cancellationStatus != 'cancelled') ...[
            if (pendingChangeRequestId != null) const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: () => _ownerCancel(context),
              icon: const Icon(Icons.event_busy),
              label: const Text('إلغاء الحجز من جهة الصالة'),
            ),
          ],
          if (cancellationStatus == 'waiting_refund') ...[
            if (pendingChangeRequestId != null) const SizedBox(height: 16),
            const Text(
              'الإلغاء بانتظار تنفيذ الاسترداد',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            FilledButton.icon(
              onPressed: () => _confirmRefund(context),
              icon: const Icon(Icons.payments_outlined),
              label: const Text('تأكيد رد المبلغ'),
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _approve(BuildContext context) async {
    try {
      final result = await api.post(
        '/bookings/$bookingId/change-requests/$pendingChangeRequestId/approve',
        {},
      );
      if (!context.mounted) return;
      _message(context, result['message']?.toString() ?? 'تم قبول التعديل.');
      onChanged?.call();
    } catch (e) {
      if (context.mounted) _message(context, e.toString());
    }
  }

  Future<void> _reject(BuildContext context) async {
    final controller = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('رفض طلب التعديل'),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'سبب الرفض'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('رجوع'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('رفض الطلب'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      final result = await api.post(
        '/bookings/$bookingId/change-requests/$pendingChangeRequestId/reject',
        {'reason': controller.text.trim()},
      );
      if (!context.mounted) return;
      _message(context, result['message']?.toString() ?? 'تم رفض التعديل.');
      onChanged?.call();
    } catch (e) {
      if (context.mounted) _message(context, e.toString());
    }
  }

  Future<void> _ownerCancel(BuildContext context) async {
    final controller = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('إلغاء الحجز من جهة الصالة'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'إلغاء الصالة يعني استرداد 100% للعميل وإلغاء عمولة Salora.',
            ),
            TextField(
              controller: controller,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'سبب الإلغاء الإلزامي',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('رجوع'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('تأكيد الإلغاء'),
          ),
        ],
      ),
    );
    if (confirmed != true || controller.text.trim().isEmpty) return;
    try {
      final result = await api.post('/owner/bookings/$bookingId/cancel', {
        'reason': controller.text.trim(),
      });
      if (!context.mounted) return;
      final message = result['status'] == 'waiting_refund'
          ? 'تم الإلغاء وهو بانتظار رد المبلغ كاملاً للعميل.'
          : 'تم إلغاء الحجز واستحق العميل استرداداً كاملاً.';
      _message(context, message);
      onChanged?.call();
    } catch (e) {
      if (context.mounted) _message(context, e.toString());
    }
  }

  Future<void> _confirmRefund(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('تأكيد رد المبلغ'),
        content: const Text(
          'أكد فقط بعد تنفيذ رد المبلغ فعلياً للعميل. بعدها يصبح الحجز ملغى نهائياً وتتحدث العمولة والتسوية.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('رجوع'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('تم رد المبلغ'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await api.post('/bookings/$bookingId/confirm-refund', {});
      if (!context.mounted) return;
      _message(context, 'تم تأكيد الاسترداد وإلغاء الحجز نهائياً.');
      onChanged?.call();
    } catch (e) {
      if (context.mounted) _message(context, e.toString());
    }
  }

  static void _message(BuildContext context, String text) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }
}
