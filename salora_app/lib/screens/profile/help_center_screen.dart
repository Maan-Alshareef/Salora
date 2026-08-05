import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';

class HelpCenterScreen extends StatelessWidget {
  const HelpCenterScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('مركز المساعدة')),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: const [
          _HelpCard(
            icon: Icons.phone_outlined,
            title: 'التواصل مع الدعم',
            body: 'للتجربة والدعم تواصل معنا على: 0980906963',
          ),
          _HelpCard(
            icon: Icons.event_available_outlined,
            title: 'طريقة الحجز',
            body:
                'Choose a hall, select event date and services, then submit your request. The hall owner/admin can approve it later from the dashboard.',
          ),
          _HelpCard(
            icon: Icons.receipt_long_outlined,
            title: 'إيصال الدفع',
            body:
                'عند اختيار التحويل، ارفع صورة الإيصال ليتم تأكيد الدفع من لوحة التحكم.',
          ),
        ],
      ),
    );
  }
}

class _HelpCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String body;
  const _HelpCard({
    required this.icon,
    required this.title,
    required this.body,
  });

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 14),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(22),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CircleAvatar(
          backgroundColor: AppColors.surface2,
          child: Icon(icon, color: AppColors.primary),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                body,
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  height: 1.45,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
