import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../core/widgets/user_avatar.dart';
import '../../models/user_role.dart';
import '../../providers/auth_provider.dart';
import '../../providers/theme_provider.dart';
import '../auth/login_screen.dart';
import '../booking/my_payments_screen.dart';
import '../owner_join/owner_join_screen.dart';
import '../support/support_center_screen.dart';
import 'edit_profile_screen.dart';
import 'privacy_policy_screen.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('الملف الشخصي')),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(24)),
            child: Column(
              children: [
                Row(
                  children: [
                    UserAvatar(imageUrl: auth.profileImageUrl, radius: 38, heroTag: 'profile-avatar'),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(auth.userName, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
                          const SizedBox(height: 4),
                          Text(auth.role.label, style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold)),
                        ],
                      ),
                    ),
                    IconButton.filledTonal(
                      onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const EditProfileScreen())),
                      icon: const Icon(Icons.edit_rounded),
                      tooltip: 'تعديل الملف الشخصي',
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _infoRow(Icons.email_outlined, auth.userEmail),
                const SizedBox(height: 8),
                _infoRow(Icons.phone_outlined, auth.phone),
              ],
            ),
          ),
          const SizedBox(height: 18),
          _tile(context, Icons.storefront_outlined, 'هل تملك صالة؟ انضم إلى Salora', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const OwnerJoinScreen(initialType: JoinType.owner)))),
          _tile(context, Icons.handshake_outlined, 'هل لديك خدمة تقدمها؟', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const OwnerJoinScreen(initialType: JoinType.provider)))),
          _tile(context, Icons.payments_outlined, 'مدفوعاتي', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const MyPaymentsScreen()))),
          _tile(context, Icons.support_agent_outlined, 'الدعم والشكاوى', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SupportCenterScreen()))),
          _tile(context, Icons.privacy_tip_outlined, 'سياسة الخصوصية', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const PrivacyPolicyScreen()))),
          const SizedBox(height: 12),
          Consumer<ThemeProvider>(
            builder: (context, theme, _) => Container(
              decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(18)),
              child: SwitchListTile.adaptive(
                value: theme.isDark,
                onChanged: (_) => context.read<ThemeProvider>().toggleTheme(),
                secondary: Icon(theme.isDark ? Icons.dark_mode_outlined : Icons.light_mode_outlined),
                title: const Text('الوضع الليلي / النهاري'),
                subtitle: Text(theme.isDark ? 'الوضع الحالي: ليلي' : 'الوضع الحالي: نهاري'),
              ),
            ),
          ),
          const SizedBox(height: 12),
          ListTile(
            leading: const Icon(Icons.logout_rounded, color: AppColors.danger),
            title: const Text('تسجيل الخروج', style: TextStyle(color: AppColors.danger, fontWeight: FontWeight.w800)),
            onTap: () async {
              await context.read<AuthProvider>().logout();
              if (!context.mounted) return;
              Navigator.pushAndRemoveUntil(context, MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
            },
          ),
        ],
      ),
    );
  }

  Widget _infoRow(IconData icon, String value) => Row(
        children: [
          Icon(icon, size: 20, color: AppColors.textSecondary),
          const SizedBox(width: 10),
          Expanded(child: Text(value, style: const TextStyle(color: AppColors.textSecondary))),
        ],
      );

  Widget _tile(BuildContext context, IconData icon, String title, VoidCallback onTap) => ListTile(
        leading: Icon(icon),
        title: Text(title),
        trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
        onTap: onTap,
      );
}
