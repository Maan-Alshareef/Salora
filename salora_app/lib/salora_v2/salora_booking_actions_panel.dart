import 'package:flutter/material.dart';

import 'salora_booking_time_picker.dart';
import 'salora_booking_v2_api.dart';

class SaloraBookingActionsPanel extends StatefulWidget {
  const SaloraBookingActionsPanel({
    super.key,
    required this.bookingId,
    required this.venueId,
    required this.eventStartAt,
    required this.api,
    this.onChanged,
  });

  final int bookingId;
  final int venueId;
  final DateTime eventStartAt;
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

  bool get _canEdit =>
      _actionState?['can_edit'] == true ||
      (_actionState == null && _localCanEdit);

  String get _editMode => _actionState?['edit_mode']?.toString() ?? 'request';

  bool get _isDirectEdit => _editMode == 'direct';

  String get _editMessage {
    final serverMessage = _actionState?['edit_message']?.toString().trim();
    if (serverMessage != null && serverMessage.isNotEmpty) {
      return serverMessage;
    }
    return _localCanEdit
        ? 'يمكن تعديل الموعد. سيعيد النظام فحص التوفر والسعر قبل الحفظ.'
        : 'لا يمكن تعديل الحجز خلال آخر 120 ساعة قبل الموعد.';
  }

  Map<String, dynamic>? get _pendingChange {
    final value = _actionState?['pending_change_request'];
    return value is Map ? Map<String, dynamic>.from(value) : null;
  }

  String? get _cancellationStatus =>
      _actionState?['cancellation_status']?.toString();

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
                      : _isDirectEdit
                      ? 'تعديل الحجز'
                      : 'طلب تعديل الحجز',
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
        initialDate: widget.eventStartAt,
        editMode: _editMode,
      ),
    );

    if (result == null || !mounted) return;

    try {
      final response = await widget.api
          .post('/bookings/${widget.bookingId}/change-requests', {
            'venue_id': widget.venueId,
            'start_at': result['start_at'],
            'end_at': result['end_at'],
            if ((result['reason']?.toString().trim() ?? '').isNotEmpty)
              'reason': result['reason'].toString().trim(),
          });
      if (!mounted) return;
      _message(
        response['message']?.toString() ??
            (_isDirectEdit
                ? 'تم تعديل الحجز وإعادة حساب السعر والتوفر.'
                : 'تم إرسال طلب التعديل إلى مالك الصالة.'),
      );
      await _loadActionState();
      widget.onChanged?.call();
    } catch (error) {
      if (mounted) _message(error.toString());
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
    required this.initialDate,
    required this.editMode,
  });

  final int venueId;
  final int bookingId;
  final SaloraBookingV2Api api;
  final DateTime initialDate;
  final String editMode;

  @override
  State<_BookingChangeDialog> createState() => _BookingChangeDialogState();
}

class _BookingChangeDialogState extends State<_BookingChangeDialog> {
  final TextEditingController _reason = TextEditingController();
  Map<String, dynamic>? _quote;

  @override
  void dispose() {
    _reason.dispose();
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
    final initialDate = widget.initialDate.isBefore(firstDate)
        ? firstDate
        : widget.initialDate;

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
                    child: Text(
                      widget.editMode == 'direct'
                          ? 'تعديل موعد الحجز'
                          : 'طلب تعديل موعد الحجز',
                      style: const TextStyle(
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
                      firstDate: firstDate,
                      minimumStartAt: minimumStartAt,
                      excludeBookingId: widget.bookingId,
                      onQuoteChanged: (quote) {
                        if (mounted) setState(() => _quote = quote);
                      },
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
                    label: Text(
                      widget.editMode == 'direct'
                          ? 'حفظ التعديل'
                          : 'إرسال طلب التعديل',
                    ),
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

    // Use the exact timestamps returned by the server quote. Rebuilding the
    // time locally can shift the selected hour when the device and Laravel
    // use different time-zone offsets, especially for overnight bookings.
    final quotedStart = quote['start_at']?.toString().trim();
    final quotedEnd = quote['end_at']?.toString().trim();
    if (quotedStart == null ||
        quotedStart.isEmpty ||
        quotedEnd == null ||
        quotedEnd.isEmpty ||
        DateTime.tryParse(quotedStart) == null ||
        DateTime.tryParse(quotedEnd) == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تعذر قراءة الموعد المحسوب من الخادم. أعد اختياره.'),
        ),
      );
      return;
    }

    Navigator.pop(context, <String, dynamic>{
      'start_at': quotedStart,
      'end_at': quotedEnd,
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
