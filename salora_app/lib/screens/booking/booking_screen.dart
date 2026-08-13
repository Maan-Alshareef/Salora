import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_colors.dart';
import '../../models/booking_model.dart';
import '../../models/event_model.dart';
import '../../models/invoice_item.dart';
import '../../models/venue_availability_model.dart';
import '../../models/venue_model.dart';
import '../../providers/app_settings_provider.dart';
import '../../providers/booking_provider.dart';
import '../../providers/event_provider.dart';
import '../../salora_v2/salora_booking_time_picker.dart';
import '../../salora_v2/salora_booking_v2_api.dart';
import 'booking_success_screen.dart';

class BookingScreen extends StatefulWidget {
  final VenueModel venue;
  const BookingScreen({super.key, required this.venue});

  @override
  State<BookingScreen> createState() => _BookingScreenState();
}

class _BookingScreenState extends State<BookingScreen> {
  int currentStep = 0;
  DateTime? date;
  String startTime = '18:00';
  String endTime = '23:00';
  late String eventType;
  final eventTitleCtrl = TextEditingController();
  final hostCtrl = TextEditingController();
  final guestsCtrl = TextEditingController();
  final notesCtrl = TextEditingController();
  final selectedHallExtraIds = <String>{};
  PaymentMethod method = PaymentMethod.bankTransfer;
  bool submitting = false;
  Map<String, dynamic>? _v2Quote;

  @override
  void initState() {
    super.initState();
    eventType = widget.venue.eventTypes.isNotEmpty
        ? widget.venue.eventTypes.first
        : '';
    eventTitleCtrl.text = _defaultEventTitle(eventType);
  }

  @override
  void dispose() {
    eventTitleCtrl.dispose();
    hostCtrl.dispose();
    guestsCtrl.dispose();
    notesCtrl.dispose();
    super.dispose();
  }

  List<HallServiceOption> get _availableHallExtras =>
      widget.venue.hallExtrasFor(eventType);

  List<HallServiceOption> get _selectedHallExtras => _availableHallExtras
      .where((item) => selectedHallExtraIds.contains(item.id))
      .toList();

  int _total(BuildContext context) {
    final hallExtras = _selectedHallExtras.fold<int>(
      0,
      (sum, item) => sum + item.price,
    );
    final venuePrice =
        (_v2Quote?['final_price_syp'] as num?)?.round() ??
        widget.venue.finalPrice;
    return venuePrice + hallExtras;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('حجز الصالة وبيانات المناسبة')),
      body: Stepper(
        currentStep: currentStep,
        onStepTapped: (i) => setState(() => currentStep = i),
        controlsBuilder: (context, details) => Padding(
          padding: const EdgeInsets.only(top: 16),
          child: Row(
            children: [
              Expanded(
                child: ElevatedButton(
                  style: currentStep == 4
                      ? ElevatedButton.styleFrom(
                          backgroundColor: AppColors.success,
                          foregroundColor: Colors.white,
                        )
                      : null,
                  onPressed: submitting ? null : _continue,
                  child: submitting
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(currentStep == 4 ? 'إنشاء حجز' : 'متابعة'),
                ),
              ),
              if (currentStep > 0) ...[
                const SizedBox(width: 10),
                TextButton(
                  onPressed: () => setState(() => currentStep--),
                  child: const Text('رجوع'),
                ),
              ],
            ],
          ),
        ),
        steps: [
          Step(
            title: Text('${_emojiFor(eventType)} معلومات المناسبة'),
            isActive: currentStep >= 0,
            content: _eventStep(),
          ),
          Step(
            title: const Text('🏛️ خدمات الصالة'),
            isActive: currentStep >= 1,
            content: _hallServicesStep(),
          ),
          Step(
            title: const Text('💳 طريقة الدفع'),
            isActive: currentStep >= 2,
            content: _paymentStep(),
          ),
          Step(
            title: const Text('🎁 الدعوة'),
            isActive: currentStep >= 3,
            content: _invitationStep(),
          ),
          Step(
            title: const Text('🧾 المراجعة والتأكيد'),
            isActive: currentStep >= 4,
            content: _reviewStep(),
          ),
        ],
      ),
    );
  }

  Widget _eventStep() {
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (widget.venue.eventTypes.isEmpty)
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.redAccent.withOpacity(.10),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: Colors.redAccent.withOpacity(.35)),
              ),
              child: const Text(
                'لا يمكن حجز هذه الصالة حالياً لأن مالكها لم يحدد أنواع المناسبات المدعومة. اطلب من مالك الصالة تعديل بياناتها.',
                style: TextStyle(color: Colors.redAccent, height: 1.45),
              ),
            )
          else
            DropdownButtonFormField<String>(
              isExpanded: true,
              initialValue: eventType,
              items: widget.venue.eventTypes
                  .map(
                    (e) => DropdownMenuItem(
                      value: e,
                      child: Text('${_emojiFor(e)}  $e'),
                    ),
                  )
                  .toList(),
              onChanged: (v) {
                if (v == null) return;
                setState(() {
                  eventType = v;
                  eventTitleCtrl.text = _defaultEventTitle(v);
                  selectedHallExtraIds.clear();
                });
              },
              decoration: const InputDecoration(
                labelText: 'نوع المناسبة',
                hintText: 'اختر نوع المناسبة',
                prefixIcon: Icon(Icons.category_outlined),
                contentPadding: EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 20,
                ),
              ),
            ),
          const SizedBox(height: 12),
          TextField(
            controller: eventTitleCtrl,
            decoration: const InputDecoration(
              labelText: 'اسم المناسبة',
              prefixIcon: Icon(Icons.event_outlined),
              contentPadding: EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 18,
              ),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: hostCtrl,
            decoration: const InputDecoration(
              labelText: 'اسم المضيف أو العائلة اختياري',
              hintText: 'أدخل اسم العائلة أو المضيف',
              prefixIcon: Icon(Icons.person_outline),
              contentPadding: EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 18,
              ),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: guestsCtrl,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(
              labelText: 'عدد الضيوف - الحد الأقصى ${widget.venue.capacity}',
              prefixIcon: const Icon(Icons.people_outline),
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 18,
              ),
            ),
          ),
          const SizedBox(height: 12),
          SaloraBookingTimePicker(
            venueId: int.parse(widget.venue.id),
            api: SaloraBookingV2Api(
              baseUrl: context.read<ApiClient>().baseUrl,
              tokenProvider: () async => context.read<ApiClient>().token,
            ),
            initialDate: date,
            onQuoteChanged: (quote) {
              setState(() {
                _v2Quote = quote;
                if (quote != null) {
                  date = DateTime.tryParse(
                    quote['selected_date']?.toString() ?? '',
                  );
                  startTime = quote['selected_start']?.toString() ?? startTime;
                  endTime = quote['selected_end']?.toString() ?? endTime;
                }
              });
            },
          ),
          const SizedBox(height: 12),
          TextField(
            controller: notesCtrl,
            maxLines: 2,
            decoration: const InputDecoration(
              labelText: 'ملاحظات إضافية اختيارية',
              prefixIcon: Icon(Icons.notes_outlined),
              contentPadding: EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 18,
              ),
            ),
          ),
        ],
      ),
    );
  }

  static const _startTimes = [
    '08:00',
    '10:00',
    '12:00',
    '14:00',
    '16:00',
    '18:00',
    '20:00',
    '22:00',
  ];
  static const _allEndTimes = [
    '10:00',
    '12:00',
    '14:00',
    '16:00',
    '18:00',
    '20:00',
    '22:00',
    '23:00',
  ];

  List<String> _endTimesFor(String start) {
    final startMinutes = _timeToMinutes(start);
    return _allEndTimes
        .where((time) => _timeToMinutes(time) > startMinutes)
        .toList();
  }

  Widget _timeDropdown(
    String label,
    String value,
    List<String> times,
    ValueChanged<String?> onChanged,
  ) {
    final safeValue = times.contains(value) ? value : times.first;
    return DropdownButtonFormField<String>(
      initialValue: safeValue,
      items: times
          .map((e) => DropdownMenuItem(value: e, child: Text(e)))
          .toList(),
      onChanged: onChanged,
      decoration: InputDecoration(labelText: label),
    );
  }

  Widget _availabilityPanel() {
    if (date == null) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.primary.withOpacity(.15)),
        ),
        child: const Text(
          'اختر التاريخ حتى يعرض النظام أوقات الصالة المحجوزة ويتحقق من الفترة المختارة.',
          style: TextStyle(color: AppColors.textSecondary, height: 1.45),
        ),
      );
    }

    final provider = context.watch<BookingProvider>();
    final loading = provider.isLoadingAvailability(widget.venue.id, date!);
    final availability = provider.availabilityFor(widget.venue.id, date!);

    if (loading && availability == null) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(14),
          child: CircularProgressIndicator(),
        ),
      );
    }

    final reason = availability?.unavailabilityReason(startTime, endTime);
    final isAvailable = availability != null && reason == null;
    final statusColor = isAvailable ? AppColors.success : AppColors.danger;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: statusColor.withOpacity(.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: statusColor.withOpacity(.30)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                isAvailable
                    ? Icons.event_available_rounded
                    : Icons.event_busy_rounded,
                color: statusColor,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  availability == null
                      ? 'تعذر تحميل توفر هذا اليوم بعد.'
                      : isAvailable
                      ? 'الفترة $startTime - $endTime متاحة حالياً.'
                      : reason ?? 'الفترة المختارة غير متاحة.',
                  style: TextStyle(
                    color: statusColor,
                    fontWeight: FontWeight.w900,
                    height: 1.35,
                  ),
                ),
              ),
              IconButton(
                tooltip: 'تحديث التوفر',
                onPressed: loading
                    ? null
                    : () => _loadAvailability(force: true),
                icon: loading
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.refresh_rounded),
              ),
            ],
          ),
          if (availability != null) ...[
            const SizedBox(height: 8),
            Text(
              availability.isClosed
                  ? 'الصالة مغلقة في هذا اليوم.'
                  : availability.openTime.isNotEmpty &&
                        availability.closeTime.isNotEmpty
                  ? 'ساعات العمل: ${availability.openTime} - ${availability.closeTime}'
                  : 'لم يحدد المالك ساعات عمل خاصة لهذا اليوم.',
              style: const TextStyle(color: AppColors.textSecondary),
            ),
            if (availability.unavailableIntervals.isNotEmpty) ...[
              const SizedBox(height: 10),
              const Text(
                'الفترات غير المتاحة:',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 6),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: availability.unavailableIntervals
                    .map(
                      (interval) => Chip(
                        visualDensity: VisualDensity.compact,
                        avatar: const Icon(Icons.lock_clock_outlined, size: 17),
                        label: Text(
                          '${interval.startTime} - ${interval.endTime}',
                        ),
                      ),
                    )
                    .toList(),
              ),
            ],
            const SizedBox(height: 8),
            Text(
              'الحجز غير المكتمل يبقى بانتظار الدفع دون مهلة زمنية، والخادم يعيد التحقق من التوفر عند التأكيد لمنع الحجز المزدوج.',
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 12,
                height: 1.4,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Future<VenueDayAvailability?> _loadAvailability({bool force = false}) async {
    if (date == null) return null;
    try {
      return await context.read<BookingProvider>().loadVenueAvailability(
        venueId: widget.venue.id,
        date: date!,
        force: force,
      );
    } catch (error) {
      if (mounted) _error(error.toString().replaceFirst('Exception: ', ''));
      return null;
    }
  }

  Future<bool> _validateAvailability({bool force = false}) async {
    final availability = await _loadAvailability(force: force);
    if (availability == null) return false;
    final reason = availability.unavailabilityReason(startTime, endTime);
    if (reason != null) {
      if (mounted) _error(reason);
      return false;
    }
    return true;
  }

  Widget _hallServicesStep() {
    final settings = context.watch<AppSettingsProvider>();
    final extras = _availableHallExtras;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'مجانية ضمن سعر الصالة',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        ...widget.venue.includedServices.map(
          (service) => ListTile(
            dense: true,
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.check_circle, color: AppColors.success),
            title: Text('${_serviceEmoji(service)} $service'),
            trailing: const Text(
              'مشمول',
              style: TextStyle(
                color: AppColors.success,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        const Divider(),
        const Text(
          'خدمات مدفوعة إضافية من الصالة',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        if (extras.isEmpty)
          const Text(
            'لا توجد خدمات مدفوعة لهذا النوع من المناسبات.',
            style: TextStyle(color: AppColors.textSecondary),
          )
        else
          ...extras.map(
            (service) => CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: selectedHallExtraIds.contains(service.id),
              title: Text(
                '${_serviceEmoji(service.name, service.category)} ${service.name}',
              ),
              subtitle: Text(
                '${_serviceEmoji(service.category)} ${service.category}',
              ),
              secondary: Text(
                '+${settings.formatPrice(service.price)}',
                style: const TextStyle(
                  fontWeight: FontWeight.w900,
                  color: AppColors.primary,
                ),
              ),
              onChanged: (v) => setState(
                () => v == true
                    ? selectedHallExtraIds.add(service.id)
                    : selectedHallExtraIds.remove(service.id),
              ),
            ),
          ),
      ],
    );
  }

  Widget _paymentStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        RadioListTile<PaymentMethod>(
          value: PaymentMethod.bankTransfer,
          groupValue: method,
          onChanged: (v) => setState(() => method = v!),
          title: const Text('دفع إلكتروني'),
          subtitle: const Text(
            'بعد إنشاء الحجز مباشرة تختار حساب الاستلام وترفع صورة إيصال التحويل',
          ),
        ),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(16),
          ),
          child: const Text(
            'عند الضغط على «إنشاء حجز» يُحجز الموعد مؤقتًا وتظهر لك وسائل الدفع وحسابات مالك الصالة فورًا. يمكنك رفع الإيصال بعد الدفع في أي وقت بدون مهلة زمنية، ثم يتحقق المالك منه ليصبح الحجز مؤكدًا نهائيًا.',
          ),
        ),
      ],
    );
  }

  Widget _invitationStep() {
    final dateText = date == null
        ? '-'
        : '${date!.day}/${date!.month}/${date!.year}';
    final isCondolence = eventType == 'عزاء';
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          colors: isCondolence
              ? [const Color(0xFF111827), const Color(0xFF374151)]
              : [AppColors.secondary, AppColors.primary],
        ),
      ),
      child: Column(
        children: [
          Text(
            isCondolence ? '🕊️' : _emojiFor(eventType),
            style: const TextStyle(fontSize: 36),
          ),
          const SizedBox(height: 10),
          Text(
            _invitationTitle(eventType),
            style: const TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w800,
              fontSize: 18,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            eventTitleCtrl.text.trim().isEmpty
                ? _defaultEventTitle(eventType)
                : eventTitleCtrl.text.trim(),
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w900,
              fontSize: 24,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            _defaultMessage(eventType),
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white, height: 1.5),
          ),
          const SizedBox(height: 16),
          Text(
            '$dateText • $startTime - $endTime',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            '${widget.venue.name} • ${widget.venue.address}',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 10),
          const Text(
            'يمكنك حفظ ومشاركة الدعوة النهائية من تفاصيل المناسبة بعد التأكيد.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.white70, fontSize: 12),
          ),
        ],
      ),
    );
  }

  Widget _reviewStep() {
    final guests = int.tryParse(guestsCtrl.text) ?? 0;
    final settings = context.watch<AppSettingsProvider>();

    final startMinutes = _timeToMinutes(startTime);
    var endMinutes = _timeToMinutes(endTime);
    if (endMinutes <= startMinutes) {
      endMinutes += 24 * 60;
    }

    final durationMinutes = endMinutes - startMinutes;
    final durationHours = durationMinutes ~/ 60;
    final remainingMinutes = durationMinutes % 60;

    late final String durationText;
    if (durationMinutes <= 0) {
      durationText = '-';
    } else if (durationHours == 0) {
      durationText = '$remainingMinutes دقيقة';
    } else if (remainingMinutes == 0) {
      durationText = '$durationHours ساعة';
    } else {
      durationText = '$durationHours ساعة و$remainingMinutes دقيقة';
    }

    final quotedHallPrice =
        (_v2Quote?['final_price_syp'] as num?)?.round() ??
        widget.venue.finalPrice;

    final hallExtras = _selectedHallExtras
        .map((item) => item.toInvoiceItem())
        .toList();
    final invoice = <InvoiceItem>[
      InvoiceItem(
        id: 'hall-${widget.venue.id}',
        title: widget.venue.name,
        category: 'حجز صالة',
        amount: quotedHallPrice,
        type: InvoiceItemType.hallPrice,
      ),
      ...widget.venue.includedServices.map(
        (service) => InvoiceItem(
          id: 'included-${service.hashCode}',
          title: service,
          category: 'مجانية ضمن سعر الصالة',
          amount: 0,
          type: InvoiceItemType.includedService,
        ),
      ),
      ...hallExtras,
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(18),
          ),
          child: Column(
            children: [
              _row(
                'المناسبة',
                eventTitleCtrl.text.trim().isEmpty
                    ? _defaultEventTitle(eventType)
                    : eventTitleCtrl.text.trim(),
              ),
              _row('الصالة', widget.venue.name),
              _row('النوع', eventType),
              _row(
                'التاريخ',
                date == null
                    ? '-'
                    : '${date!.year}-${date!.month}-${date!.day}',
              ),
              _row('الوقت', '$startTime - $endTime'),
              if (durationMinutes > 0) _row('مدة الحجز', durationText),
              _row('عدد الضيوف', '$guests / ${widget.venue.capacity}'),
              _row('الدفع', 'دفع إلكتروني'),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const Text(
          'فاتورة الصالة فقط',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(18),
          ),
          child: Column(
            children: [
              ...invoice.map(
                (item) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
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
              const Divider(height: 24),
              _row(
                'إجمالي الدفع للمالك',
                settings.formatPrice(_total(context)),
                bold: true,
              ),
            ],
          ),
        ),
      ],
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
              fontWeight: bold ? FontWeight.w900 : FontWeight.normal,
            ),
          ),
        ),
      ],
    ),
  );

  Future<void> _continue() async {
    if (currentStep == 0) {
      if (widget.venue.eventTypes.isEmpty ||
          eventType.trim().isEmpty ||
          widget.venue.eventTypeIdFor(eventType) == null) {
        return _error(
          'لا يمكن إنشاء الحجز قبل أن يحدد مالك الصالة أنواع المناسبات المدعومة.',
        );
      }
      final guests = int.tryParse(guestsCtrl.text) ?? 0;
      if (eventTitleCtrl.text.trim().isEmpty)
        return _error('يرجى إدخال اسم المناسبة');
      if (date == null) return _error('يرجى اختيار تاريخ المناسبة');
      if (guests <= 0) return _error('عدد الضيوف يجب أن يكون أكبر من صفر');
      if (guests > widget.venue.capacity)
        return _error('عدد الضيوف يتجاوز سعة الصالة');
      if (_timeToMinutes(endTime) <= _timeToMinutes(startTime))
        return _error('وقت النهاية يجب أن يكون بعد وقت البداية');
      final now = DateTime.now();
      final selectedStart = DateTime(
        date!.year,
        date!.month,
        date!.day,
      ).add(Duration(minutes: _timeToMinutes(startTime)));
      if (!selectedStart.isAfter(now))
        return _error('لا يمكن اختيار وقت حجز سابق. اختر وقتًا قادمًا.');
      if (_v2Quote == null)
        return _error('اختر وقت بداية ونهاية متاحين حتى يظهر السعر النهائي.');
    }
    if (currentStep < 4) {
      setState(() => currentStep++);
      return;
    }

    // The availability screen is advisory; force one last refresh before
    // submission. The backend performs the authoritative transactional check.
    if (_v2Quote == null)
      return _error('أعد اختيار الموعد للتأكد من التوفر والسعر النهائي.');

    setState(() => submitting = true);
    try {
      final eventProvider = context.read<EventProvider>();
      final booking = await context.read<BookingProvider>().createBooking(
        venue: widget.venue,
        eventTypeId: widget.venue.eventTypeIdFor(eventType),
        eventTitle: eventTitleCtrl.text.trim(),
        hostName: hostCtrl.text.trim(),
        notes: notesCtrl.text.trim(),
        date: date!,
        startTime: startTime,
        endTime: endTime,
        eventType: eventType,
        guests: int.parse(guestsCtrl.text),
        hallExtraServices: _selectedHallExtras
            .map((item) => item.toInvoiceItem())
            .toList(),
        paymentMethod: method,
      );

      await eventProvider.loadEvents();
      EventModel? refreshedEvent;
      for (final item in eventProvider.events) {
        if (item.id == booking.eventId) {
          refreshedEvent = item;
          break;
        }
      }
      if (refreshedEvent == null) {
        throw const ApiException(
          'تم إنشاء الحجز، لكن تعذر تحميل المناسبة المرتبطة به. افتح قائمة مناسباتي لتحديثها.',
        );
      }
      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) =>
              BookingSuccessScreen(booking: booking, event: refreshedEvent!),
        ),
      );
    } on ApiException catch (error) {
      if (error.code == 'venue_time_conflict' ||
          error.code == 'outside_opening_hours') {
        await _loadAvailability(force: true);
      }
      if (!mounted) return;
      _error(error.message);
    } catch (error) {
      if (!mounted) return;
      _error(error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  EventType _eventTypeFromLabel(String label) {
    return EventType.values.firstWhere(
      (item) => item.label == label,
      orElse: () => EventType.wedding,
    );
  }

  String _defaultEventTitle(String type) {
    switch (type) {
      case 'زفاف':
        return 'مناسبة زفاف';
      case 'خطوبة':
        return 'مناسبة خطوبة';
      case 'تخرج':
        return 'مناسبة تخرج';
      case 'عزاء':
        return 'مناسبة عزاء';
      case 'عيد ميلاد':
        return 'مناسبة عيد ميلاد';
      case 'مناسبة عائلية':
        return 'مناسبة عائلية';
      default:
        return 'مناسبة $type';
    }
  }

  String _invitationTitle(String type) {
    switch (type) {
      case 'زفاف':
        return 'دعوة زفاف';
      case 'خطوبة':
        return 'دعوة خطوبة';
      case 'تخرج':
        return 'دعوة تخرج';
      case 'عزاء':
        return 'نعوة / دعوة عزاء';
      case 'عيد ميلاد':
        return 'دعوة عيد ميلاد';
      case 'مناسبة عائلية':
        return 'دعوة مناسبة عائلية';
      default:
        return 'دعوة مناسبة';
    }
  }

  String _defaultMessage(String type) {
    switch (type) {
      case 'زفاف':
        return 'ندعوكم بكل حب وسرور لحضور حفل الزفاف ومشاركتنا أجمل اللحظات.';
      case 'خطوبة':
        return 'ندعوكم لمشاركتنا فرحة الخطوبة، حضوركم يزيد فرحتنا.';
      case 'تخرج':
        return 'ندعوكم لحضور حفل التخرج ومشاركتنا فرحة النجاح.';
      case 'عزاء':
        return 'ببالغ الحزن والأسى وبقلوب مؤمنة بقضاء الله وقدره، ندعوكم لحضور مجلس العزاء.';
      case 'عيد ميلاد':
        return 'ندعوكم لمشاركتنا الاحتفال بعيد الميلاد وقضاء وقت جميل.';
      case 'مناسبة عائلية':
        return 'ندعوكم لمشاركتنا هذه المناسبة العائلية وقضاء وقت جميل.';
      default:
        return 'يسرنا دعوتكم لحضور مناسبتنا ومشاركتنا هذه اللحظات.';
    }
  }

  String _emojiFor(String type) {
    switch (type) {
      case 'زفاف':
        return '💍';
      case 'خطوبة':
        return '💞';
      case 'تخرج':
        return '🎓';
      case 'عزاء':
        return '🕊️';
      case 'عيد ميلاد':
        return '🎂';
      case 'مناسبة عائلية':
        return '👨‍👩‍👧';
      case 'مؤتمر':
        return '🎤';
      case 'اجتماع':
        return '🤝';
      default:
        return '🎉';
    }
  }

  String _serviceEmoji(String text, [String category = '']) {
    final value = '${text.toLowerCase()} ${category.toLowerCase()}';
    if (value.contains('photography') ||
        value.contains('photo') ||
        value.contains('تصوير'))
      return '📸';
    if (value.contains('food') ||
        value.contains('drinks') ||
        value.contains('مأكولات') ||
        value.contains('مشروبات') ||
        value.contains('ضيافة'))
      return '🍽️';
    if (value.contains('cake') || value.contains('كيك')) return '🎂';
    if (value.contains('decoration') ||
        value.contains('stage') ||
        value.contains('ديكور') ||
        value.contains('منصة'))
      return '🌸';
    if (value.contains('lighting') ||
        value.contains('sound') ||
        value.contains('إضاءة') ||
        value.contains('صوت'))
      return '💡';
    if (value.contains('hospitality') || value.contains('ضيافة')) return '☕';
    if (value.contains('reader') ||
        value.contains('sheikh') ||
        value.contains('قارئ') ||
        value.contains('شيخ'))
      return '📖';
    if (value.contains('water') || value.contains('مياه')) return '💧';
    if (value.contains('clean') || value.contains('تنظيف')) return '🧹';
    if (value.contains('tables') ||
        value.contains('chairs') ||
        value.contains('طاولات') ||
        value.contains('كراسي'))
      return '🪑';
    if (value.contains('time') ||
        value.contains('hour') ||
        value.contains('ساعة'))
      return '⏰';
    if (value.contains('printing') ||
        value.contains('notice') ||
        value.contains('طباعة'))
      return '🖨️';
    return '✅';
  }

  void _error(String message) => ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text(message), backgroundColor: AppColors.danger),
  );
}

int _timeToMinutes(String value) {
  final parts = value.split(':');
  final hour = int.tryParse(parts.first) ?? 0;
  final minute = parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0;
  return hour * 60 + minute;
}
