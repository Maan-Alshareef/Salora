import 'package:flutter/material.dart';
import '../screens/booking/booking_adjustment_payment_screen.dart';

import 'salora_booking_time_picker.dart';
import 'salora_booking_v2_api.dart';

class SaloraBookingActionsPanel extends StatefulWidget {
  const SaloraBookingActionsPanel({
    super.key,
    required this.bookingId,
    required this.venueId,
    required this.eventStartAt,
    required this.eventEndAt,
    required this.currentGuestCount,
    required this.api,
    this.onChanged,
  });

  final int bookingId;
  final int venueId;
  final DateTime eventStartAt;
  final DateTime eventEndAt;
  final int currentGuestCount;
  final SaloraBookingV2Api api;
  final VoidCallback? onChanged;

  @override
  State<SaloraBookingActionsPanel> createState() =>
      _SaloraBookingActionsPanelState();
}

class _SaloraBookingActionsPanelState extends State<SaloraBookingActionsPanel> {
  Map<String, dynamic>? _actionState;
  String? _loadError;
  bool _loading = true;

  bool get _localCanEdit =>
      widget.eventStartAt.difference(DateTime.now()).inHours > 120;

  bool get _canEdit => _actionState?['can_edit'] == true;

  String get _editMessage {
    final serverMessage = _actionState?['edit_message']?.toString().trim();
    if (serverMessage != null && serverMessage.isNotEmpty) {
      return serverMessage;
    }
    if (_actionState == null) {
      return 'تعذر التحقق من حالة تعديل الحجز من الخادم. أعد تحميل الحالة قبل المتابعة.';
    }
    return _localCanEdit
        ? 'يمكن إرسال طلب تعديل للتاريخ والوقت وعدد الضيوف، ويُطبق فقط بعد موافقة مالك الصالة.'
        : 'لا يمكن تعديل الحجز خلال آخر 120 ساعة قبل الموعد.';
  }

  Map<String, dynamic>? get _pendingChange {
    final value = _actionState?['pending_change_request'];
    return value is Map ? Map<String, dynamic>.from(value) : null;
  }

  String? get _cancellationStatus =>
      _actionState?['cancellation_status']?.toString();

  Map<String, dynamic>? get _paymentAdjustment {
    final value = _actionState?['payment_adjustment'];
    return value is Map ? Map<String, dynamic>.from(value) : null;
  }

  @override
  void initState() {
    super.initState();
    _loadActionState();
  }

  Future<void> _loadActionState() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _loadError = null;
      });
    }

    try {
      final result = await widget.api.get(
        '/bookings/${widget.bookingId}/action-state',
      );
      if (!mounted) return;
      setState(() => _actionState = _unwrapData(result));
    } catch (error) {
      if (!mounted) return;
      setState(() => _loadError = error.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Map<String, dynamic> _unwrapData(Map<String, dynamic> response) {
    var current = response;
    for (var depth = 0; depth < 3; depth++) {
      final nested = current['data'];
      if (nested is! Map) break;
      current = Map<String, dynamic>.from(nested);
    }
    return current;
  }

  @override
  Widget build(BuildContext context) {
    final hasPendingChange = _pendingChange != null;
    final cancellationClosed =
        _cancellationStatus == 'waiting_refund' ||
            _cancellationStatus == 'cancelled';
    final paymentAdjustment = _paymentAdjustment;

    return Directionality(
      textDirection: TextDirection.rtl,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_loading) const LinearProgressIndicator(),
          if (_loadError != null) ...[
            Text(
              _loadError!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _loadActionState,
              icon: const Icon(Icons.refresh),
              label: const Text('إعادة تحميل حالة الحجز'),
            ),
            const SizedBox(height: 8),
          ],
          if (!_loading && _editMessage.isNotEmpty) ...[
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    Icon(
                      _canEdit ? Icons.info_outline : Icons.lock_clock_outlined,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _editMessage,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 8),
          ],
          if (hasPendingChange) ...[
            const Card(
              child: Padding(
                padding: EdgeInsets.all(14),
                child: Row(
                  children: [
                    Icon(Icons.hourglass_top),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'يوجد طلب تعديل قيد مراجعة مالك الصالة. لا يمكن إرسال طلب جديد قبل اتخاذ القرار.',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 8),
          ],
          if (paymentAdjustment != null &&
              ['pending_payment', 'proof_uploaded', 'pending_refund', 'pending']
                  .contains(paymentAdjustment['status']?.toString())) ...[
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    const Icon(Icons.account_balance_wallet_outlined),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        paymentAdjustment['type'] == 'additional_payment'
                            ? 'بعد التعديل يوجد فرق دفع مطلوب: ${_money(paymentAdjustment['amount_syp'])} ل.س.'
                            : 'بعد التعديل يوجد مبلغ مستحق لك: ${_money(paymentAdjustment['amount_syp'])} ل.س.',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            if (paymentAdjustment['type'] == 'additional_payment' &&
                paymentAdjustment['status'] == 'pending_payment') ...[
              FilledButton.icon(
                onPressed: () => _payAdjustment(paymentAdjustment),
                icon: const Icon(Icons.payments_outlined),
                label: const Text('دفع فرق التعديل ورفع الإثبات'),
              ),
              const SizedBox(height: 8),
            ],
            if (paymentAdjustment['type'] == 'additional_payment' &&
                paymentAdjustment['status'] == 'proof_uploaded') ...[
              const Text(
                'تم رفع إثبات فرق الدفع وهو بانتظار مراجعة مالك الصالة.',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
            ],
          ],
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              OutlinedButton.icon(
                onPressed: !_loading && _canEdit && !hasPendingChange
                    ? _requestChange
                    : null,
                icon: const Icon(Icons.edit_calendar),
                label: Text(
                  hasPendingChange
                      ? 'طلب تعديل قيد المراجعة'
                      : !_canEdit
                      ? 'التعديل غير متاح'
                      : 'تعديل الحجز',
                ),
              ),
              if (!cancellationClosed)
                OutlinedButton.icon(
                  onPressed: _loading ? null : _cancel,
                  icon: const Icon(Icons.cancel_outlined),
                  label: const Text('إلغاء الحجز'),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _requestChange() async {
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _BookingChangeDialog(
        venueId: widget.venueId,
        bookingId: widget.bookingId,
        api: widget.api,
        initialStartAt: widget.eventStartAt,
        initialEndAt: widget.eventEndAt,
        currentGuestCount: widget.currentGuestCount,
      ),
    );

    if (result == null || !mounted) return;

    try {
      final response = await widget.api
          .post('/bookings/${widget.bookingId}/change-requests', {
        'venue_id': widget.venueId,
        'start_at': result['start_at'],
        'end_at': result['end_at'],
        'guests_count': result['guests_count'],
        if ((result['reason']?.toString().trim() ?? '').isNotEmpty)
          'reason': result['reason'].toString().trim(),
      });
      if (!mounted) return;
      _message(
        response['message']?.toString() ??
            'تم إرسال طلب التعديل إلى مالك الصالة.',
      );
      await _loadActionState();
      widget.onChanged?.call();
    } catch (error) {
      if (mounted) _message(error.toString());
    }
  }

  Future<void> _payAdjustment(Map<String, dynamic> adjustment) async {
    final id = int.tryParse(adjustment['id']?.toString() ?? '');
    final amount = double.tryParse(adjustment['amount_syp']?.toString() ?? '') ?? 0;
    if (id == null) return;
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => BookingAdjustmentPaymentScreen(
          bookingId: widget.bookingId,
          adjustmentId: id,
          amountSyp: amount,
        ),
      ),
    );
    if (changed == true && mounted) {
      await _loadActionState();
      widget.onChanged?.call();
    }
  }

  Future<void> _cancel() async {
    final reason = TextEditingController();
    try {
      final preview = await widget.api.get(
        '/bookings/${widget.bookingId}/cancellation-preview',
      );
      if (!mounted) return;

      final accepted = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('تأكيد إلغاء الحجز'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _line('المبلغ المدفوع/النهائي', preview['final_price_syp']),
                _line('نسبة الخصم', '${preview['deduction_percentage']}%'),
                _line('المبلغ المسترد', preview['refunded_syp']),
                _line('المبلغ المتبقي للمالك', preview['owner_retained_syp']),
                const SizedBox(height: 10),
                TextField(
                  controller: reason,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'سبب الإلغاء اختياري',
                  ),
                ),
                const SizedBox(height: 10),
                const Text(
                  'بالمتابعة أنت تقر أنك قرأت سياسة الإلغاء ووافقت على النسبة والمبلغ الظاهرين.',
                ),
              ],
            ),
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
      if (accepted != true) return;

      final response = await widget.api
          .post('/bookings/${widget.bookingId}/cancel', {
        'accepted_policy': true,
        if (reason.text.trim().isNotEmpty) 'reason': reason.text.trim(),
      });
      if (!mounted) return;
      final status = response['status'] == 'waiting_refund'
          ? 'تم الإلغاء وهو الآن بانتظار تأكيد استرداد المبلغ.'
          : 'تم إلغاء الحجز.';
      _message(status);
      await _loadActionState();
      widget.onChanged?.call();
    } catch (error) {
      if (mounted) _message(error.toString());
    } finally {
      reason.dispose();
    }
  }

  void _message(String text) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(text)));
  }

  static String _money(dynamic value) {
    final number = (value as num?)?.round() ?? int.tryParse(value?.toString() ?? '') ?? 0;
    return number.toString().replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
          (_) => ',',
    );
  }

  static Widget _line(String label, dynamic value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(
      children: [Text(value?.toString() ?? '-'), const Spacer(), Text(label)],
    ),
  );
}

class _BookingChangeDialog extends StatefulWidget {
  const _BookingChangeDialog({
    required this.venueId,
    required this.bookingId,
    required this.api,
    required this.initialStartAt,
    required this.initialEndAt,
    required this.currentGuestCount,
  });

  final int venueId;
  final int bookingId;
  final SaloraBookingV2Api api;
  final DateTime initialStartAt;
  final DateTime initialEndAt;
  final int currentGuestCount;

  @override
  State<_BookingChangeDialog> createState() => _BookingChangeDialogState();
}

class _BookingChangeDialogState extends State<_BookingChangeDialog> {
  final TextEditingController _reason = TextEditingController();
  late final TextEditingController _guests;
  Map<String, dynamic>? _quote;

  @override
  void initState() {
    super.initState();
    _guests = TextEditingController(
      text: (widget.currentGuestCount < 1 ? 1 : widget.currentGuestCount)
          .toString(),
    );
  }

  @override
  void dispose() {
    _reason.dispose();
    _guests.dispose();
    super.dispose();
  }

  Map<String, dynamic> _unwrapData(Map<String, dynamic> response) {
    var current = response;
    for (var depth = 0; depth < 3; depth++) {
      final nested = current['data'];
      if (nested is! Map) break;
      current = Map<String, dynamic>.from(nested);
    }
    return current;
  }

  @override
  Widget build(BuildContext context) {
    final minimumStartAt = DateTime.now().add(const Duration(hours: 120));
    final firstDate = DateTime(
      minimumStartAt.year,
      minimumStartAt.month,
      minimumStartAt.day,
    );
    final initialDate = widget.initialStartAt.isBefore(firstDate)
        ? firstDate
        : widget.initialStartAt;

    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 620, maxHeight: 760),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
              child: Row(
                children: [
                  const Icon(Icons.edit_calendar),
                  const SizedBox(width: 8),
                  Expanded(
                    child: const Text(
                      'تعديل الصالة',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'وقت البداية يظهر على رأس الساعة فقط، ووقت النهاية يظهر كل نصف ساعة حسب دوام الصالة والحد الأدنى والأقصى للحجز.',
                      style: TextStyle(height: 1.5),
                    ),
                    const SizedBox(height: 12),
                    SaloraBookingTimePicker(
                      venueId: widget.venueId,
                      api: widget.api,
                      initialDate: initialDate,
                      initialStartAt: widget.initialStartAt,
                      initialEndAt: widget.initialEndAt,
                      firstDate: firstDate,
                      minimumStartAt: minimumStartAt,
                      excludeBookingId: widget.bookingId,
                      onQuoteChanged: (quote) {
                        if (mounted) setState(() => _quote = quote);
                      },
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _guests,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'عدد الضيوف',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _reason,
                      maxLines: 3,
                      decoration: const InputDecoration(
                        labelText: 'سبب التعديل اختياري',
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('رجوع'),
                  ),
                  const Spacer(),
                  FilledButton.icon(
                    onPressed: _quote == null ? null : _submit,
                    icon: const Icon(Icons.send),
                    label: const Text('إرسال طلب التعديل'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _submit() {
    final quote = _quote;
    if (quote == null) return;

    final guests = int.tryParse(_guests.text.trim());
    if (guests == null || guests < 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('أدخل عدداً صحيحاً للضيوف.')),
      );
      return;
    }

    DateTime? startAt = DateTime.tryParse(quote['start_at']?.toString() ?? '');
    DateTime? endAt = DateTime.tryParse(quote['end_at']?.toString() ?? '');

    // استخدم التاريخ والوقت اللذين تحقّق منهما الخادم نفسه.
    // أبقِ إعادة التركيب القديمة فقط كحل توافق احتياطي.
    if (startAt == null || endAt == null) {
      final selectedDate = DateTime.tryParse(
        quote['selected_date']?.toString() ?? '',
      );
      final startParts = _clockParts(quote['selected_start']);
      final endParts = _clockParts(quote['selected_end']);

      if (selectedDate != null && startParts != null && endParts != null) {
        startAt = DateTime(
          selectedDate.year,
          selectedDate.month,
          selectedDate.day,
          startParts[0],
          startParts[1],
        );
        endAt = DateTime(
          selectedDate.year,
          selectedDate.month,
          selectedDate.day,
          endParts[0],
          endParts[1],
        );

        if (!endAt.isAfter(startAt)) {
          endAt = endAt.add(const Duration(days: 1));
        }
      }
    }

    if (startAt == null || endAt == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تعذر قراءة الموعد المختار. أعد اختياره.'),
        ),
      );
      return;
    }

    if (!endAt.isAfter(startAt)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('وقت النهاية يجب أن يكون بعد وقت البداية.'),
        ),
      );
      return;
    }

    Navigator.pop(context, <String, dynamic>{
      'start_at': startAt.toIso8601String(),
      'end_at': endAt.toIso8601String(),
      'guests_count': guests,
      'reason': _reason.text.trim(),
    });
  }

  static List<int>? _clockParts(dynamic value) {
    final match = RegExp(
      r'(\d{1,2}):(\d{2})',
    ).firstMatch(value?.toString() ?? '');
    if (match == null) return null;
    final hour = int.tryParse(match.group(1)!);
    final minute = int.tryParse(match.group(2)!);
    if (hour == null || minute == null) return null;
    return <int>[hour, minute];
  }
}
