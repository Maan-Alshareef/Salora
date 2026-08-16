import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_constants.dart';
import '../../providers/theme_provider.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = context.watch<ThemeProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('الإعدادات')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SwitchListTile(
            title: const Text('الوضع الداكن'),
            subtitle: const Text('تغيير مظهر التطبيق'),
            value: theme.isDark,
            onChanged: (_) => context.read<ThemeProvider>().toggleTheme(),
          ),
          const ListTile(
            leading: Icon(Icons.language),
            title: Text('اللغة'),
            subtitle: Text('العربية'),
          ),
          const ListTile(
            leading: Icon(Icons.payments_outlined),
            title: Text('العملة'),
            subtitle: Text('الليرة السورية. لا يستخدم التطبيق سعر صرف ثابتاً.'),
          ),
          const ListTile(
            leading: Icon(Icons.notifications_active_outlined),
            title: Text('الإشعارات'),
            subtitle: Text('إشعارات الحجوزات والدفع وطلبات الخدمات محفوظة في الخادم.'),
          ),
          const ListTile(
            leading: Icon(Icons.info_outline),
            title: Text('حول التطبيق'),
            subtitle: Text('${AppConstants.appName} v${AppConstants.appVersion}'),
          ),
        ],
      ),
    );
  }
}
