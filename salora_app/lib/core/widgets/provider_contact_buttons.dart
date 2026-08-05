import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../theme/app_colors.dart';

class ProviderContactButtons extends StatelessWidget {
  const ProviderContactButtons({
    super.key,
    required this.phone,
    this.compact = false,
  });

  final String phone;
  final bool compact;

  String get _digits => phone.replaceAll(RegExp(r'[^0-9]'), '');
  String get _international {
    final value = _digits;
    if (value.startsWith('09') && value.length == 10) return '963${value.substring(1)}';
    if (value.startsWith('963')) return value;
    return value;
  }

  Future<void> _call(BuildContext context) async {
    final uri = Uri.parse('tel:$_digits');
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تعذر فتح تطبيق الاتصال.')));
    }
  }

  Future<void> _whatsApp(BuildContext context) async {
    final uri = Uri.parse('https://wa.me/$_international');
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تعذر فتح واتساب.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_digits.isEmpty) return const SizedBox.shrink();
    final call = OutlinedButton.icon(
      onPressed: () => _call(context),
      icon: const Icon(Icons.call_outlined),
      label: Text(compact ? 'اتصال' : 'اتصال بمقدم الخدمة'),
    );
    final whatsApp = ElevatedButton.icon(
      onPressed: () => _whatsApp(context),
      icon: const Icon(Icons.chat_outlined),
      label: Text(compact ? 'واتساب' : 'التواصل عبر واتساب'),
      style: ElevatedButton.styleFrom(backgroundColor: AppColors.success),
    );
    if (compact) {
      return Row(children: [Expanded(child: call), const SizedBox(width: 8), Expanded(child: whatsApp)]);
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [call, const SizedBox(height: 8), whatsApp]);
  }
}
