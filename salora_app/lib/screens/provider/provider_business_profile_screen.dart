import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../core/validation/syrian_phone.dart';
import '../../providers/provider_account_provider.dart';

class ProviderBusinessProfileScreen extends StatefulWidget {
  const ProviderBusinessProfileScreen({super.key});

  @override
  State<ProviderBusinessProfileScreen> createState() =>
      _ProviderBusinessProfileScreenState();
}

class _ProviderBusinessProfileScreenState
    extends State<ProviderBusinessProfileScreen> {
  static const Map<String, String> _weekdays = {
    'saturday': 'السبت',
    'sunday': 'الأحد',
    'monday': 'الاثنين',
    'tuesday': 'الثلاثاء',
    'wednesday': 'الأربعاء',
    'thursday': 'الخميس',
    'friday': 'الجمعة',
  };

  late final TextEditingController businessName;
  late final TextEditingController city;
  late final TextEditingController coverageAreas;
  late final TextEditingController workingHours;
  late final TextEditingController bio;
  late final TextEditingController contactPhone;
  late final TextEditingController whatsappPhone;
  final Set<String> selectedDaysOff = <String>{};
  bool allowPhone = true;
  bool allowWhatsapp = true;
  bool initialized = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (initialized) return;
    final profile = context.read<ProviderAccountProvider>().profile;
    businessName = TextEditingController(text: profile?.businessName ?? '');
    city = TextEditingController(text: profile?.city ?? '');
    coverageAreas = TextEditingController(
      text: profile?.coverageAreas.join('، ') ?? '',
    );
    workingHours = TextEditingController(
      text: profile?.workingHours['daily']?.toString() ?? '09:00-18:00',
    );
    bio = TextEditingController(text: profile?.bio ?? '');
    contactPhone = TextEditingController(text: SyrianPhone.normalize(profile?.contactPhone ?? ''));
    whatsappPhone = TextEditingController(text: SyrianPhone.normalize(profile?.whatsappPhone ?? ''));
    selectedDaysOff.addAll(
      (profile?.daysOff ?? const <String>[])
          .map(_normaliseWeekday)
          .where(_weekdays.containsKey),
    );
    allowPhone = profile?.allowPhone ?? true;
    allowWhatsapp = profile?.allowWhatsapp ?? true;
    initialized = true;
  }

  @override
  void dispose() {
    if (initialized) {
      businessName.dispose();
      city.dispose();
      coverageAreas.dispose();
      workingHours.dispose();
      bio.dispose();
      contactPhone.dispose();
      whatsappPhone.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ProviderAccountProvider>();
    if (!initialized) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      appBar: AppBar(title: const Text('بيانات العمل والتواصل')),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: .10),
              borderRadius: BorderRadius.circular(22),
              border: Border.all(
                color: AppColors.primary.withValues(alpha: .25),
              ),
            ),
            child: const Text(
              'تظهر هذه البيانات في دليل مقدمي الخدمات. التواصل عبر الاتصال أو واتساب للاستفسار، أما طلب الخدمة وقبوله فيبقيان داخل Salora لحفظ السجل والإشعارات والتقييمات.',
              style: TextStyle(height: 1.55),
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: businessName,
            decoration: const InputDecoration(
              labelText: 'الاسم التجاري',
              prefixIcon: Icon(Icons.badge_outlined),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: city,
            decoration: const InputDecoration(
              labelText: 'المدينة *',
              prefixIcon: Icon(Icons.location_city_outlined),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: coverageAreas,
            decoration: const InputDecoration(
              labelText: 'مناطق تقديم الخدمة',
              prefixIcon: Icon(Icons.map_outlined),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: workingHours,
            decoration: const InputDecoration(
              labelText: 'أوقات العمل اليومية',
              hintText: '09:00-18:00',
              prefixIcon: Icon(Icons.schedule_outlined),
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: Colors.white12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(Icons.event_busy_outlined, size: 20),
                    SizedBox(width: 8),
                    Text(
                      'أيام الإجازة الأسبوعية',
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: _weekdays.entries.map((entry) {
                    final selected = selectedDaysOff.contains(entry.key);
                    return FilterChip(
                      label: Text(entry.value),
                      selected: selected,
                      onSelected: (value) => setState(() {
                        if (value) {
                          selectedDaysOff.add(entry.key);
                        } else {
                          selectedDaysOff.remove(entry.key);
                        }
                      }),
                    );
                  }).toList(),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: bio,
            minLines: 4,
            maxLines: 7,
            decoration: const InputDecoration(
              labelText: 'نبذة عن خدماتك *',
              prefixIcon: Icon(Icons.description_outlined),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: contactPhone,
            keyboardType: TextInputType.phone,
            inputFormatters: SyrianPhone.formatters,
            maxLength: 10,
            decoration: const InputDecoration(
              labelText: 'رقم الاتصال - 10 أرقام',
              prefixIcon: Icon(Icons.call_outlined),
              counterText: '',
            ),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: allowPhone,
            onChanged: (value) => setState(() => allowPhone = value),
            title: const Text('إظهار زر الاتصال للعملاء'),
            subtitle: const Text('لا يظهر الرقم في الدليل عند إيقاف هذا الخيار.'),
          ),
          const SizedBox(height: 6),
          TextField(
            controller: whatsappPhone,
            keyboardType: TextInputType.phone,
            inputFormatters: SyrianPhone.formatters,
            maxLength: 10,
            decoration: const InputDecoration(
              labelText: 'رقم واتساب - 10 أرقام',
              prefixIcon: Icon(Icons.chat_outlined),
              counterText: '',
            ),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: allowWhatsapp,
            onChanged: (value) => setState(() => allowWhatsapp = value),
            title: const Text('إظهار زر واتساب للعملاء'),
            subtitle: const Text(
              'يفتح التطبيق محادثة خارجية مع رسالة تعريفية من Salora.',
            ),
          ),
          const SizedBox(height: 20),
          ElevatedButton.icon(
            onPressed: provider.isSavingProfile ? null : _save,
            icon: provider.isSavingProfile
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.save_outlined),
            label: Text(
              provider.isSavingProfile
                  ? 'جاري الحفظ...'
                  : 'حفظ بيانات العمل',
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _save() async {
    try {
      await context.read<ProviderAccountProvider>().updateProfile(
        businessName: businessName.text,
        city: city.text,
        coverageAreas: coverageAreas.text
            .split(RegExp(r'[,،]'))
            .map((e) => e.trim())
            .where((e) => e.isNotEmpty)
            .toList(),
        workingHours: {'daily': workingHours.text.trim()},
        daysOff: _weekdays.keys
            .where(selectedDaysOff.contains)
            .toList(growable: false),
        bio: bio.text,
        contactPhone: SyrianPhone.normalize(contactPhone.text),
        whatsappPhone: SyrianPhone.normalize(whatsappPhone.text),
        allowPhone: allowPhone,
        allowWhatsapp: allowWhatsapp,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم تحديث بيانات العمل والتواصل.')),
      );
      Navigator.pop(context);
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(exception.toString())),
        );
      }
    }
  }

  String _normaliseWeekday(String value) {
    final text = value.trim().toLowerCase();
    const aliases = {
      'السبت': 'saturday',
      'الأحد': 'sunday',
      'الاحد': 'sunday',
      'الاثنين': 'monday',
      'الإثنين': 'monday',
      'الثلاثاء': 'tuesday',
      'الأربعاء': 'wednesday',
      'الاربعاء': 'wednesday',
      'الخميس': 'thursday',
      'الجمعة': 'friday',
    };
    return aliases[value.trim()] ?? text;
  }
}
