import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../models/event_model.dart';
import '../../providers/app_settings_provider.dart';
import '../../providers/event_provider.dart';
import 'event_details_screen.dart';

class CreateEventScreen extends StatefulWidget {
  final EventModel? event;
  const CreateEventScreen({super.key, this.event});

  @override
  State<CreateEventScreen> createState() => _CreateEventScreenState();
}

class _CreateEventScreenState extends State<CreateEventScreen> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _city = TextEditingController();
  final _guests = TextEditingController();
  final _budget = TextEditingController();

  late DateTime _date;
  late EventType _type;
  late Set<String> _services;
  int _step = 0;
  bool _submitting = false;

  bool get _editing => widget.event != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.event;
    _type = existing?.type ?? EventType.wedding;
    _date = existing?.date ?? DateTime.now().add(const Duration(days: 14));
    _services = {...?existing?.neededServices};
    if (existing == null) _services.addAll({'تصوير', 'ديكور'});
    _title.text = existing?.title ?? 'مناسبة ${_type.label}';
    _city.text = existing?.city ?? 'دمشق';
    _guests.text = '${existing?.guests ?? 200}';
    _budget.text = '${existing?.budget ?? 10000000}';
  }

  @override
  void dispose() {
    _title.dispose();
    _city.dispose();
    _guests.dispose();
    _budget.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_editing ? 'تعديل المناسبة' : 'إنشاء مناسبة')),
      body: Form(
        key: _formKey,
        child: Stepper(
          currentStep: _step,
          onStepTapped: (value) => setState(() => _step = value),
          controlsBuilder: (context, details) => Padding(
            padding: const EdgeInsets.only(top: 16),
            child: Row(children: [
              Expanded(
                child: ElevatedButton(
                  onPressed: _submitting ? null : _next,
                  child: _submitting
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : Text(_step == 2 ? (_editing ? 'حفظ التعديلات' : 'تأكيد المناسبة') : 'متابعة'),
                ),
              ),
              if (_step > 0) ...[
                const SizedBox(width: 10),
                TextButton(onPressed: () => setState(() => _step--), child: const Text('رجوع')),
              ],
            ]),
          ),
          steps: [
            Step(title: const Text('معلومات أساسية'), isActive: _step >= 0, content: _basicStep()),
            Step(title: const Text('الخدمات'), isActive: _step >= 1, content: _servicesStep()),
            Step(title: const Text('مراجعة'), isActive: _step >= 2, content: _reviewStep()),
          ],
        ),
      ),
    );
  }

  Widget _basicStep() {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(
        width: double.infinity,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(26),
          gradient: const LinearGradient(colors: [AppColors.primary, AppColors.secondary]),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(_type.iconEmoji, style: const TextStyle(fontSize: 34)),
          const SizedBox(height: 10),
          const Text('خطط مناسبتك من مكان واحد', style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          const Text('اختر نوع المناسبة والتاريخ وعدد الضيوف والخدمات ثم أكّد الطلب.', style: TextStyle(color: Colors.white70, height: 1.35)),
        ]),
      ),
      const SizedBox(height: 16),
      TextFormField(
        controller: _title,
        decoration: const InputDecoration(labelText: 'عنوان المناسبة', hintText: 'مثال: زفاف أحمد', prefixIcon: Icon(Icons.title_rounded)),
        validator: (value) => value == null || value.trim().length < 3 ? 'أدخل عنوان المناسبة' : null,
      ),
      const SizedBox(height: 12),
      DropdownButtonFormField<EventType>(
        value: _type,
        decoration: const InputDecoration(labelText: 'نوع المناسبة', prefixIcon: Icon(Icons.category_outlined)),
        items: EventType.values.map((type) => DropdownMenuItem(value: type, child: Text('${type.iconEmoji}  ${type.label}'))).toList(),
        onChanged: (value) {
          if (value == null) return;
          setState(() {
            _type = value;
            if (_title.text.trim().isEmpty || _title.text.endsWith('المناسبة')) {
              _title.text = 'مناسبة ${_type.label}';
            }
          });
        },
      ),
      const SizedBox(height: 12),
      InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: _pickDate,
        child: InputDecorator(
          decoration: const InputDecoration(labelText: 'تاريخ المناسبة', prefixIcon: Icon(Icons.calendar_month_rounded)),
          child: Text('${_date.day}/${_date.month}/${_date.year}'),
        ),
      ),
      const SizedBox(height: 12),
      TextFormField(
        controller: _city,
        decoration: const InputDecoration(labelText: 'المدينة', prefixIcon: Icon(Icons.location_on_outlined)),
        validator: (v) => v == null || v.trim().isEmpty ? 'أدخل المدينة' : null,
      ),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: TextFormField(controller: _guests, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'عدد الضيوف'), validator: _numberValidator)),
        const SizedBox(width: 12),
        Expanded(child: TextFormField(controller: _budget, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'التكلفة'), validator: _numberValidator)),
      ]),
    ]);
  }

  Widget _servicesStep() {
    const items = ['تصوير', 'ديكور', 'ضيافة', 'إضاءة', 'صوت', 'قارئ / شيخ', 'مأكولات ومشروبات'];
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('اختر الخدمات التي قد تحتاجها للمناسبة.', style: TextStyle(color: AppColors.textSecondary)),
      const SizedBox(height: 12),
      Wrap(
        spacing: 8,
        runSpacing: 8,
        children: items.map((service) {
          final selected = _services.contains(service);
          return FilterChip(
            label: Text(service),
            avatar: Icon(selected ? Icons.check_circle : Icons.add_circle_outline, size: 18),
            selected: selected,
            onSelected: (value) => setState(() => value ? _services.add(service) : _services.remove(service)),
          );
        }).toList(),
      ),
      const SizedBox(height: 14),
      Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(16)),
        child: const Text('يمكنك إضافة أو حجز مقدمي خدمات لاحقًا من شاشة الخدمات.', style: TextStyle(color: AppColors.textSecondary)),
      ),
    ]);
  }

  Widget _reviewStep() {
    final settings = context.watch<AppSettingsProvider>();
    final guests = int.tryParse(_guests.text) ?? 0;
    final budget = int.tryParse(_budget.text) ?? 0;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(22), border: Border.all(color: Colors.white10)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Text(_type.iconEmoji, style: const TextStyle(fontSize: 34)),
          const SizedBox(width: 12),
          Expanded(child: Text(_title.text.trim().isEmpty ? 'مناسبة ${_type.label}' : _title.text.trim(), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900))),
        ]),
        const Divider(height: 26),
        _row('النوع', _type.label),
        _row('التاريخ', '${_date.day}/${_date.month}/${_date.year}'),
        _row('المدينة', _city.text.trim()),
        _row('عدد الضيوف', '$guests'),
        _row('التكلفة', settings.formatPrice(budget), bold: true),
        _row('الخدمات', _services.isEmpty ? 'لم يتم اختيار خدمات' : _services.join(', ')),
        const SizedBox(height: 12),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: AppColors.primary.withOpacity(.12), borderRadius: BorderRadius.circular(16)),
          child: const Text('بعد التأكيد ستظهر المناسبة في مناسباتي مع قائمة مهام جاهزة.', style: TextStyle(color: AppColors.textSecondary)),
        ),
      ]),
    );
  }

  Widget _row(String a, String b, {bool bold = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(a, style: const TextStyle(color: AppColors.textSecondary)),
          const Spacer(),
          Flexible(child: Text(b, textAlign: TextAlign.end, style: TextStyle(fontWeight: bold ? FontWeight.w900 : FontWeight.w600))),
        ]),
      );

  String? _numberValidator(String? value) {
    final number = int.tryParse(value ?? '');
    return number == null || number <= 0 ? 'مطلوب' : null;
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _next() async {
    if (!_formKey.currentState!.validate()) return;
    if (_step < 2) {
      setState(() => _step++);
      return;
    }
    await _confirm();
  }

  Future<void> _confirm() async {
    setState(() => _submitting = true);
    try {
      final provider = context.read<EventProvider>();
      final title = _title.text.trim().isEmpty ? 'مناسبة ${_type.label}' : _title.text.trim();
      final event = _editing
          ? await provider.updateEvent(
              eventId: widget.event!.id,
              existingEventTypeId: widget.event!.eventTypeId,
              title: title,
              type: _type,
              date: _date,
              city: _city.text.trim(),
              guests: int.parse(_guests.text),
              budget: int.parse(_budget.text),
              neededServices: _services.toList(),
            )
          : await provider.createEvent(
              title: title,
              type: _type,
              date: _date,
              city: _city.text.trim(),
              guests: int.parse(_guests.text),
              budget: int.parse(_budget.text),
              neededServices: _services.toList(),
            );
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (dialogContext) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: Row(children: [const Icon(Icons.verified_rounded, color: AppColors.success), const SizedBox(width: 8), Text(_editing ? 'تم تعديل المناسبة' : 'تم إنشاء المناسبة')]),
          content: Text(_editing ? 'تم حفظ تعديلات ${event.title} في الخادم.' : 'تم إنشاء ${event.title} وحفظ قائمة مهامها في الخادم.'),
          actions: [
            ElevatedButton(
              onPressed: () {
                Navigator.pop(dialogContext);
                Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => EventDetailsScreen(event: event)));
              },
              child: const Text('فتح المناسبة'),
            ),
          ],
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}
