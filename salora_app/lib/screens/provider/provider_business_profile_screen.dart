import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../providers/provider_account_provider.dart';

class ProviderBusinessProfileScreen extends StatefulWidget {
  const ProviderBusinessProfileScreen({super.key});

  @override
  State<ProviderBusinessProfileScreen> createState() => _ProviderBusinessProfileScreenState();
}

class _ProviderBusinessProfileScreenState extends State<ProviderBusinessProfileScreen> {
  late final TextEditingController businessName;
  late final TextEditingController city;
  late final TextEditingController coverageAreas;
  late final TextEditingController workingHours;
  late final TextEditingController daysOff;
  late final TextEditingController bio;
  late final TextEditingController contactPhone;
  late final TextEditingController whatsappPhone;
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
    coverageAreas = TextEditingController(text: profile?.coverageAreas.join('، ') ?? '');
    workingHours = TextEditingController(text: profile?.workingHours['daily']?.toString() ?? '09:00-18:00');
    daysOff = TextEditingController(text: profile?.daysOff.join('، ') ?? '');
    bio = TextEditingController(text: profile?.bio ?? '');
    contactPhone = TextEditingController(text: profile?.contactPhone ?? '');
    whatsappPhone = TextEditingController(text: profile?.whatsappPhone ?? '');
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
      daysOff.dispose();
      bio.dispose();
      contactPhone.dispose();
      whatsappPhone.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ProviderAccountProvider>();
    if (!initialized) return const Scaffold(body: Center(child: CircularProgressIndicator()));

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
              border: Border.all(color: AppColors.primary.withValues(alpha: .25)),
            ),
            child: const Text(
              'تظهر هذه البيانات في دليل مقدمي الخدمات. التواصل عبر الاتصال أو واتساب للاستفسار، أما طلب الخدمة وقبوله فيبقيان داخل Salora لحفظ السجل والإشعارات والتقييمات.',
              style: TextStyle(height: 1.55),
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: businessName,
            decoration: const InputDecoration(labelText: 'الاسم التجاري', prefixIcon: Icon(Icons.badge_outlined)),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: city,
            decoration: const InputDecoration(labelText: 'المدينة *', prefixIcon: Icon(Icons.location_city_outlined)),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: coverageAreas,
            decoration: const InputDecoration(labelText: 'مناطق تقديم الخدمة', helperText: 'افصل بين المناطق بفاصلة.'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: workingHours,
            decoration: const InputDecoration(labelText: 'أوقات العمل اليومية', hintText: '09:00-18:00'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: daysOff,
            decoration: const InputDecoration(labelText: 'أيام الإجازة الخاصة', helperText: 'تواريخ مفصولة بفاصلة، مثال: 2026-08-10'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: bio,
            minLines: 4,
            maxLines: 7,
            decoration: const InputDecoration(
              labelText: 'نبذة عن خدماتك *',
              prefixIcon: Icon(Icons.description_outlined),
              helperText: 'اكتب خبرتك ونوع الأعمال التي تقدمها، من دون وضع كلمات مرور أو معلومات حساسة.',
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: contactPhone,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(labelText: 'رقم الاتصال', prefixIcon: Icon(Icons.call_outlined)),
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
            decoration: const InputDecoration(
              labelText: 'رقم واتساب',
              prefixIcon: Icon(Icons.chat_outlined),
              helperText: 'استخدم رمز الدولة، مثال: +9639XXXXXXXX.',
            ),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: allowWhatsapp,
            onChanged: (value) => setState(() => allowWhatsapp = value),
            title: const Text('إظهار زر واتساب للعملاء'),
            subtitle: const Text('يفتح التطبيق محادثة خارجية مع رسالة تعريفية من Salora.'),
          ),
          const SizedBox(height: 20),
          ElevatedButton.icon(
            onPressed: provider.isSavingProfile ? null : _save,
            icon: provider.isSavingProfile
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.save_outlined),
            label: Text(provider.isSavingProfile ? 'جاري الحفظ...' : 'حفظ بيانات العمل'),
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
            coverageAreas: coverageAreas.text.split(RegExp(r'[,،]')).map((e) => e.trim()).where((e) => e.isNotEmpty).toList(),
            workingHours: {'daily': workingHours.text.trim()},
            daysOff: daysOff.text.split(RegExp(r'[,،]')).map((e) => e.trim()).where((e) => e.isNotEmpty).toList(),
            bio: bio.text,
            contactPhone: contactPhone.text,
            whatsappPhone: whatsappPhone.text,
            allowPhone: allowPhone,
            allowWhatsapp: allowWhatsapp,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم تحديث بيانات العمل والتواصل.')));
      Navigator.pop(context);
    } catch (exception) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }
}
