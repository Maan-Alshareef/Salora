import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';

class PrivacyPolicyScreen extends StatelessWidget {
  const PrivacyPolicyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('سياسة الخصوصية')),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: const [
          Text(
            'سياسة خصوصية Salora',
            style: TextStyle(fontSize: 26, fontWeight: FontWeight.w900),
          ),
          SizedBox(height: 12),
          Text(
            'This prototype is designed for an academic project. When the backend is connected, Salora will collect only the information needed to create accounts, manage bookings, verify payments, and improve the user experience.',
            style: TextStyle(color: AppColors.textSecondary, height: 1.55),
          ),
          SizedBox(height: 22),
          _PolicySection(
            title: 'المعلومات التي نستخدمها',
            body:
                'الاسم والبريد الإلكتروني ورقم الهاتف وتفاصيل الحجوزات والصالات المفضلة وصورة إيصال الدفع وتفضيلات الخدمات.',
          ),
          _PolicySection(
            title: 'كيف نستخدمها',
            body:
                'To create booking requests, show booking status, send notifications, verify transfers, and help owners/providers manage requests through the dashboard.',
          ),
          _PolicySection(
            title: 'الأمان',
            body:
                'Sensitive actions should be protected by authentication tokens when the Laravel API is connected. Payment proof should be visible only to authorized admin users.',
          ),
          _PolicySection(
            title: 'تحكم المستخدم',
            body:
                'Users should be able to edit profile data, remove favorites, cancel pending bookings, and request account deletion in the final version.',
          ),
        ],
      ),
    );
  }
}

class _PolicySection extends StatelessWidget {
  final String title;
  final String body;
  const _PolicySection({required this.title, required this.body});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 18),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 7),
        Text(
          body,
          style: const TextStyle(color: AppColors.textSecondary, height: 1.5),
        ),
      ],
    ),
  );
}
