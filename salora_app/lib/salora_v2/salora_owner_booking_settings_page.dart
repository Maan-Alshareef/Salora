import 'package:flutter/material.dart';

import 'salora_booking_v2_api.dart';

class SaloraOwnerBookingSettingsPage extends StatefulWidget {
  const SaloraOwnerBookingSettingsPage({
    super.key,
    required this.venueId,
    required this.api,
  });

  final int venueId;
  final SaloraBookingV2Api api;

  @override
  State<SaloraOwnerBookingSettingsPage> createState() =>
      _SaloraOwnerBookingSettingsPageState();
}

class _SaloraOwnerBookingSettingsPageState
    extends State<SaloraOwnerBookingSettingsPage> {
  static const dayNames = [
    'الأحد',
    'الاثنين',
    'الثلاثاء',
    'الأربعاء',
    'الخميس',
    'الجمعة',
    'السبت',
  ];

  final hourlyPrice = TextEditingController();
  final maximumHours = TextEditingController(text: '5');
  final cleanupMinutes = TextEditingController(text: '60');
  late List<Map<String, dynamic>> days;
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> offers = [];

  @override
  void initState() {
    super.initState();
    days = List.generate(
      7,
      (index) => {
        'day_of_week': index,
        'is_closed': false,
        'open_time': '10:00',
        'close_time': '23:00',
      },
    );
    _load();
  }

  @override
  void dispose() {
    hourlyPrice.dispose();
    maximumHours.dispose();
    cleanupMinutes.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.api.get('/owner/venues/${widget.venueId}');
      final venue = data['venue'] as Map<String, dynamic>? ?? {};
      hourlyPrice.text = venue['hourly_price_syp']?.toString() ?? '';
      maximumHours.text =
          (((venue['maximum_booking_minutes'] as num? ?? 300) / 60)).toString();
      cleanupMinutes.text = (venue['cleanup_minutes'] as num? ?? 60).toString();
      offers = List<Map<String, dynamic>>.from(data['offers'] ?? const []);

      final receivedHours = List<Map<String, dynamic>>.from(
        data['working_hours'] ?? const [],
      );
      if (receivedHours.isNotEmpty) {
        for (final row in receivedHours) {
          final index = (row['day_of_week'] as num).toInt();
          days[index] = {
            'day_of_week': index,
            'is_closed': row['is_closed'] == true || row['is_closed'] == 1,
            'open_time': _shortTime(row['open_time']?.toString()) ?? '10:00',
            'close_time': _shortTime(row['close_time']?.toString()) ?? '23:00',
          };
        }
      }
    } catch (e) {
      error = e.toString();
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _savePricing() async {
    final maxHours = double.tryParse(maximumHours.text);
    if (maxHours == null || maxHours < 2 || (maxHours * 2) % 1 != 0) {
      _message('الحد الأقصى يجب أن يكون ساعتين أو أكثر وبخطوات نصف ساعة.');
      return;
    }
    final price = double.tryParse(hourlyPrice.text);
    if (price == null || price <= 0) {
      _message('أدخل سعر ساعة صحيحاً.');
      return;
    }

    setState(() => loading = true);
    try {
      await widget.api.put('/owner/venues/${widget.venueId}/pricing', {
        'hourly_price_syp': price,
        'maximum_booking_minutes': (maxHours * 60).round(),
        'cleanup_minutes': int.tryParse(cleanupMinutes.text) ?? 60,
      });
      _message('تم نشر السعر والإعدادات مباشرة في التطبيق.');
    } catch (e) {
      _message(e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _saveWorkingHours() async {
    setState(() => loading = true);
    try {
      await widget.api.put('/owner/venues/${widget.venueId}/working-hours', {
        'days': days,
      });
      _message('تم نشر أوقات عمل الصالة مباشرة.');
    } catch (e) {
      _message(e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _pickDayTime(int index, String key) async {
    final initial =
        _parseTime(days[index][key]?.toString()) ??
        const TimeOfDay(hour: 10, minute: 0);
    final result = await showTimePicker(context: context, initialTime: initial);
    if (result == null) return;
    setState(() => days[index][key] = _time(_halfHour(result)));
  }

  Future<void> _toggleOffer(Map<String, dynamic> offer, bool active) async {
    try {
      await widget.api.patch(
        '/owner/venues/${widget.venueId}/offers/${offer['id']}/toggle',
        {'is_active': active},
      );
      await _load();
      _message(active ? 'تم نشر العرض.' : 'تم إيقاف العرض.');
    } catch (e) {
      _message(e.toString());
    }
  }

  Future<void> _newOffer() async {
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => const _OfferDialog(),
    );
    if (result == null) return;
    try {
      await widget.api.post('/owner/venues/${widget.venueId}/offers', result);
      await _load();
      _message('تم نشر العرض مباشرة.');
    } catch (e) {
      _message(e.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('إعدادات حجز الصالة')),
      body: Directionality(
        textDirection: TextDirection.rtl,
        child: RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (loading) const LinearProgressIndicator(),
              if (error != null)
                Text(
                  error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              const Text(
                'التسعير والمدة',
                style: TextStyle(fontSize: 19, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: hourlyPrice,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'سعر الساعة بالليرة السورية',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: maximumHours,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'الحد الأقصى بالساعات',
                  helperText: 'مثال: 5 أو 5.5 — الحد الأدنى ثابت ساعتان',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: cleanupMinutes,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'مدة التنظيف والتجهيز بالدقائق',
                  helperText: '0 أو مضاعفات 30، مثال: 30 أو 60 أو 90',
                ),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: loading ? null : _savePricing,
                child: const Text('حفظ ونشر السعر'),
              ),
              const SizedBox(height: 28),
              ExpansionTile(
                initiallyExpanded: true,
                title: const Text(
                  'أوقات عمل الصالة',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                children: [
                  ...List.generate(7, (index) {
                    final day = days[index];
                    final closed = day['is_closed'] == true;
                    return Card(
                      child: Column(
                        children: [
                          SwitchListTile(
                            title: Text(dayNames[index]),
                            subtitle: Text(
                              closed
                                  ? 'مغلق'
                                  : '${day['open_time']} — ${day['close_time']}',
                            ),
                            value: !closed,
                            onChanged: (open) =>
                                setState(() => day['is_closed'] = !open),
                          ),
                          if (!closed)
                            Padding(
                              padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: OutlinedButton(
                                      onPressed: () =>
                                          _pickDayTime(index, 'open_time'),
                                      child: Text('يفتح ${day['open_time']}'),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: OutlinedButton(
                                      onPressed: () =>
                                          _pickDayTime(index, 'close_time'),
                                      child: Text('يغلق ${day['close_time']}'),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                        ],
                      ),
                    );
                  }),
                  const SizedBox(height: 8),
                  FilledButton(
                    onPressed: loading ? null : _saveWorkingHours,
                    child: const Text('حفظ ونشر أوقات العمل'),
                  ),
                  const SizedBox(height: 12),
                ],
              ),
              const SizedBox(height: 24),
              Row(
                children: [
                  FilledButton.icon(
                    onPressed: _newOffer,
                    icon: const Icon(Icons.add),
                    label: const Text('إضافة عرض'),
                  ),
                  const Spacer(),
                  const Text(
                    'العروض المنشورة',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              ...offers.map(
                (offer) => Card(
                  child: ListTile(
                    title: Text(offer['title']?.toString() ?? ''),
                    subtitle: Text(
                      offer['offer_type'] == 'percentage'
                          ? 'خصم ${offer['percentage']}%'
                          : offer['offer_type'] == 'fixed'
                          ? 'خصم ${offer['fixed_amount_syp']} ل.س'
                          : 'عرض حسب اليوم أو الوقت',
                    ),
                    trailing: Switch(
                      value:
                          offer['is_active'] == true || offer['is_active'] == 1,
                      onChanged: (active) => _toggleOffer(offer, active),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _message(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  static String? _shortTime(String? value) =>
      value == null || value.length < 5 ? null : value.substring(0, 5);
  static TimeOfDay? _parseTime(String? value) {
    if (value == null || !value.contains(':')) return null;
    final parts = value.split(':');
    return TimeOfDay(hour: int.parse(parts[0]), minute: int.parse(parts[1]));
  }

  static TimeOfDay _halfHour(TimeOfDay value) =>
      TimeOfDay(hour: value.hour, minute: value.minute < 30 ? 0 : 30);
  static String _time(TimeOfDay value) =>
      '${value.hour.toString().padLeft(2, '0')}:'
      '${value.minute.toString().padLeft(2, '0')}';
}

class _OfferDialog extends StatefulWidget {
  const _OfferDialog();
  @override
  State<_OfferDialog> createState() => _OfferDialogState();
}

class _OfferDialogState extends State<_OfferDialog> {
  static const dayNames = [
    'أحد',
    'اثنين',
    'ثلاثاء',
    'أربعاء',
    'خميس',
    'جمعة',
    'سبت',
  ];
  final title = TextEditingController();
  final value = TextEditingController();
  String type = 'percentage';
  String scheduledDiscountType = 'percentage';
  final selectedDays = <int>{};
  bool allDay = false;
  TimeOfDay? startTime;
  TimeOfDay? endTime;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('عرض جديد'),
      content: SingleChildScrollView(
        child: Column(
          children: [
            TextField(
              controller: title,
              decoration: const InputDecoration(labelText: 'اسم العرض'),
            ),
            DropdownButtonFormField<String>(
              value: type,
              decoration: const InputDecoration(labelText: 'نوع العرض'),
              items: const [
                DropdownMenuItem(
                  value: 'percentage',
                  child: Text('خصم بنسبة مئوية'),
                ),
                DropdownMenuItem(value: 'fixed', child: Text('خصم مبلغ ثابت')),
                DropdownMenuItem(
                  value: 'scheduled',
                  child: Text('عرض حسب يوم أو وقت'),
                ),
              ],
              onChanged: (newValue) => setState(() => type = newValue!),
            ),
            if (type == 'scheduled') ...[
              DropdownButtonFormField<String>(
                value: scheduledDiscountType,
                decoration: const InputDecoration(labelText: 'طريقة الخصم'),
                items: const [
                  DropdownMenuItem(
                    value: 'percentage',
                    child: Text('نسبة مئوية'),
                  ),
                  DropdownMenuItem(value: 'fixed', child: Text('مبلغ ثابت')),
                ],
                onChanged: (newValue) =>
                    setState(() => scheduledDiscountType = newValue!),
              ),
              const SizedBox(height: 12),
              const Align(
                alignment: Alignment.centerRight,
                child: Text('الأيام — اتركها فارغة لتطبيق العرض بكل الأيام'),
              ),
              Wrap(
                spacing: 6,
                children: List.generate(
                  7,
                  (index) => FilterChip(
                    label: Text(dayNames[index]),
                    selected: selectedDays.contains(index),
                    onSelected: (selected) => setState(() {
                      selected
                          ? selectedDays.add(index)
                          : selectedDays.remove(index);
                    }),
                  ),
                ),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('طوال اليوم'),
                value: allDay,
                onChanged: (value) => setState(() => allDay = value),
              ),
              if (!allDay) ...[
                ListTile(
                  title: Text(
                    startTime == null
                        ? 'اختيار وقت بداية العرض'
                        : 'البداية: ${startTime!.format(context)}',
                  ),
                  onTap: () async {
                    final result = await showTimePicker(
                      context: context,
                      initialTime:
                          startTime ?? const TimeOfDay(hour: 10, minute: 0),
                    );
                    if (result != null)
                      setState(() => startTime = _halfHour(result));
                  },
                ),
                ListTile(
                  title: Text(
                    endTime == null
                        ? 'اختيار وقت نهاية العرض'
                        : 'النهاية: ${endTime!.format(context)}',
                  ),
                  onTap: () async {
                    final result = await showTimePicker(
                      context: context,
                      initialTime:
                          endTime ?? const TimeOfDay(hour: 17, minute: 0),
                    );
                    if (result != null)
                      setState(() => endTime = _halfHour(result));
                  },
                ),
              ],
            ],
            TextField(
              controller: value,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText:
                    (type == 'percentage' ||
                        (type == 'scheduled' &&
                            scheduledDiscountType == 'percentage'))
                    ? 'النسبة، بحد أقصى 50%'
                    : 'مبلغ الخصم',
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('إلغاء'),
        ),
        FilledButton(
          onPressed: () {
            final numeric = double.tryParse(value.text);
            final discountType = type == 'scheduled'
                ? scheduledDiscountType
                : type;
            if (title.text.trim().isEmpty || numeric == null || numeric <= 0)
              return;
            if (discountType == 'percentage' && numeric > 50) return;
            if (type == 'scheduled' &&
                !allDay &&
                (startTime == null || endTime == null))
              return;
            if (type == 'scheduled' && allDay && selectedDays.isEmpty) return;
            Navigator.pop(context, {
              'title': title.text.trim(),
              'offer_type': type,
              'scheduled_discount_type': type == 'scheduled'
                  ? scheduledDiscountType
                  : null,
              'percentage': discountType == 'percentage' ? numeric : null,
              'fixed_amount_syp': discountType == 'fixed' ? numeric : null,
              'days_of_week': type == 'scheduled' && selectedDays.isNotEmpty
                  ? (selectedDays.toList()..sort())
                  : null,
              'start_time': type == 'scheduled' && !allDay
                  ? _time(startTime!)
                  : null,
              'end_time': type == 'scheduled' && !allDay
                  ? _time(endTime!)
                  : null,
            });
          },
          child: const Text('نشر العرض'),
        ),
      ],
    );
  }

  TimeOfDay _halfHour(TimeOfDay value) =>
      TimeOfDay(hour: value.hour, minute: value.minute < 30 ? 0 : 30);
  String _time(TimeOfDay value) =>
      '${value.hour.toString().padLeft(2, '0')}:'
      '${value.minute.toString().padLeft(2, '0')}';
}
