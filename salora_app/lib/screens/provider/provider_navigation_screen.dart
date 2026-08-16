import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../core/widgets/user_avatar.dart';
import '../../models/user_role.dart';
import '../../providers/auth_provider.dart';
import '../../providers/provider_account_provider.dart';
import '../../providers/theme_provider.dart';
import '../auth/login_screen.dart';
import '../profile/edit_profile_screen.dart';
import 'provider_business_profile_screen.dart';
import 'business_finance_screen.dart';
import 'provider_requests_screen.dart';
import 'provider_services_screen.dart';

class ProviderNavigationScreen extends StatefulWidget {
  const ProviderNavigationScreen({super.key});

  @override
  State<ProviderNavigationScreen> createState() => _ProviderNavigationScreenState();
}

class _ProviderNavigationScreenState extends State<ProviderNavigationScreen> {
  int _index = 0;
  bool _loadedAfterPasswordChange = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadProviderDataIfAllowed());
  }

  void _loadProviderDataIfAllowed() {
    if (!mounted) return;
    final auth = context.read<AuthProvider>();
    if (auth.mustChangePassword) return;
    context.read<ProviderAccountProvider>().load();
    _loadedAfterPasswordChange = true;
  }

  @override
  Widget build(BuildContext context) {
    final mustChangePassword = context.watch<AuthProvider>().mustChangePassword;

    if (mustChangePassword) {
      _loadedAfterPasswordChange = false;
      return ProviderPasswordGate(onPasswordChanged: _loadProviderDataIfAllowed);
    }

    if (!_loadedAfterPasswordChange) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _loadProviderDataIfAllowed());
    }

    final screens = [const _ProviderHomeScreen(), const ProviderServicesScreen(), const ProviderRequestsScreen(), const _ProviderProfileScreen()];
    return Scaffold(
      body: screens[_index],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (v) => setState(() => _index = v),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'الرئيسية'),
          NavigationDestination(icon: Icon(Icons.design_services_outlined), selectedIcon: Icon(Icons.design_services), label: 'خدماتي'),
          NavigationDestination(icon: Icon(Icons.inbox_outlined), selectedIcon: Icon(Icons.inbox), label: 'الطلبات'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'حسابي'),
        ],
      ),
    );
  }
}

class ProviderPasswordGate extends StatelessWidget {
  const ProviderPasswordGate({super.key, required this.onPasswordChanged});

  final VoidCallback onPasswordChanged;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(18),
          children: [
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(28), border: Border.all(color: Colors.white10)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Icon(Icons.lock_reset_rounded, size: 46, color: AppColors.primary),
                const SizedBox(height: 14),
                const Text('تغيير كلمة السر مطلوب', style: TextStyle(fontSize: 25, fontWeight: FontWeight.w900)),
                const SizedBox(height: 8),
                Text('أهلًا ${auth.userName}، تم إنشاء حساب مقدم الخدمة بكلمة سر مؤقتة من الإدارة. قبل الدخول إلى خدماتك وطلبات العملاء، يجب وضع كلمة سر جديدة خاصة بك.', style: const TextStyle(color: AppColors.textSecondary, height: 1.5)),
                const SizedBox(height: 16),
                _ForcedChangePasswordCard(onPasswordChanged: onPasswordChanged),
              ]),
            ),
            const SizedBox(height: 14),
            OutlinedButton.icon(
              onPressed: () async {
                await context.read<AuthProvider>().logout();
                if (context.mounted) {
                  Navigator.pushAndRemoveUntil(context, MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
                }
              },
              icon: const Icon(Icons.logout),
              label: const Text('تسجيل الخروج'),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProviderHomeScreen extends StatelessWidget {
  const _ProviderHomeScreen();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final provider = context.watch<ProviderAccountProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('لوحة مقدم الخدمة')),
      body: RefreshIndicator(
        onRefresh: () => context.read<ProviderAccountProvider>().load(),
        child: ListView(padding: const EdgeInsets.all(16), children: [
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(24)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('أهلًا ${auth.userName}', style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              const Text('أدر خدماتك وطلبات العملاء ودفعاتك المستقلة من التطبيق. الدفع يتم عبر شام كاش أو سيريتل كاش أو الهرم مع إثبات تحويل.', style: TextStyle(color: AppColors.textSecondary, height: 1.5)),
            ]),
          ),
          const SizedBox(height: 14),
          Row(children: [
            Expanded(child: _stat('خدماتي', '${provider.myServices.length}', Icons.design_services_outlined)),
            const SizedBox(width: 10),
            Expanded(child: _stat('طلبات جديدة', '${provider.pendingRequestsCount}', Icons.inbox_outlined)),
          ]),
        ]),
      ),
    );
  }

  Widget _stat(String label, String value, IconData icon) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
        child: Column(children: [Icon(icon), const SizedBox(height: 8), Text(value, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900)), Text(label, style: const TextStyle(color: AppColors.textSecondary))]),
      );
}

class _ProviderProfileScreen extends StatelessWidget {
  const _ProviderProfileScreen();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final providerAccount = context.watch<ProviderAccountProvider>();
    final business = providerAccount.profile;

    return Scaffold(
      appBar: AppBar(title: const Text('حساب مقدم الخدمة')),
      body: RefreshIndicator(
        onRefresh: context.read<ProviderAccountProvider>().load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(24)),
              child: Column(
                children: [
                  Row(
                    children: [
                      UserAvatar(imageUrl: auth.profileImageUrl, radius: 36),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(auth.userName, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
                            const SizedBox(height: 4),
                            Text(auth.role.label, style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w800)),
                          ],
                        ),
                      ),
                      IconButton.filledTonal(
                        tooltip: 'تعديل الاسم والصورة الشخصية',
                        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const EditProfileScreen())),
                        icon: const Icon(Icons.edit_outlined),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  ListTile(dense: true, contentPadding: EdgeInsets.zero, leading: const Icon(Icons.email_outlined), title: Text(auth.userEmail)),
                  ListTile(dense: true, contentPadding: EdgeInsets.zero, leading: const Icon(Icons.phone_outlined), title: Text(auth.phone)),
                  if (business?.city.trim().isNotEmpty ?? false)
                    ListTile(dense: true, contentPadding: EdgeInsets.zero, leading: const Icon(Icons.location_city_outlined), title: Text(business!.city)),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('بيانات الظهور في دليل مقدمي الخدمات', style: TextStyle(fontWeight: FontWeight.w900)),
                  const SizedBox(height: 8),
                  Text(
                    business == null || business.bio.trim().isEmpty
                        ? 'أكمل المدينة والنبذة ورقم الاتصال أو واتساب حتى يعرف العملاء تفاصيل عملك.'
                        : business.bio,
                    style: const TextStyle(color: AppColors.textSecondary, height: 1.5),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      if (business?.allowPhone == true) const Chip(avatar: Icon(Icons.call_outlined, size: 17), label: Text('الاتصال مفعّل')),
                      if (business?.allowWhatsapp == true) const Chip(avatar: Icon(Icons.chat_outlined, size: 17), label: Text('واتساب مفعّل')),
                    ],
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ProviderBusinessProfileScreen())),
                      icon: const Icon(Icons.storefront_outlined),
                      label: const Text('تعديل بيانات العمل والتواصل'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const BusinessFinanceScreen())),
                      icon: const Icon(Icons.account_balance_wallet_outlined),
                      label: const Text('حسابات الاستلام ومراجعة الدفعات'),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
              child: Consumer<ThemeProvider>(
                builder: (context, theme, _) => SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  value: theme.isDark,
                  onChanged: (_) => theme.toggleTheme(),
                  secondary: Icon(theme.isDark ? Icons.dark_mode_outlined : Icons.light_mode_outlined),
                  title: const Text("الوضع الليلي / النهاري"),
                  subtitle: Text(theme.isDark ? "الوضع الحالي: ليلي" : "الوضع الحالي: نهاري"),
                ),
              ),
            ),
            const SizedBox(height: 12),
            const _ChangePasswordCard(),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: () async {
                await context.read<AuthProvider>().logout();
                if (context.mounted) {
                  Navigator.pushAndRemoveUntil(context, MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
                }
              },
              icon: const Icon(Icons.logout),
              label: const Text('تسجيل الخروج'),
            ),
          ],
        ),
      ),
    );
  }
}

class _ForcedChangePasswordCard extends StatefulWidget {
  const _ForcedChangePasswordCard({required this.onPasswordChanged});

  final VoidCallback onPasswordChanged;

  @override
  State<_ForcedChangePasswordCard> createState() => _ForcedChangePasswordCardState();
}

class _ForcedChangePasswordCardState extends State<_ForcedChangePasswordCard> {
  static final _strongPassword = RegExp(r'^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$');
  final current = TextEditingController();
  final next = TextEditingController();
  final confirm = TextEditingController();
  bool loading = false;

  @override
  void dispose() { current.dispose(); next.dispose(); confirm.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      TextField(controller: current, obscureText: true, decoration: const InputDecoration(labelText: 'كلمة السر المؤقتة الحالية')),
      const SizedBox(height: 10),
      TextField(controller: next, obscureText: true, decoration: const InputDecoration(labelText: 'كلمة السر الجديدة')),
      const SizedBox(height: 10),
      TextField(controller: confirm, obscureText: true, decoration: const InputDecoration(labelText: 'تأكيد كلمة السر الجديدة')),
      const SizedBox(height: 12),
      SizedBox(
        width: double.infinity,
        child: ElevatedButton.icon(
          onPressed: loading ? null : () async {
            if (!_strongPassword.hasMatch(next.text)) {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('كلمة المرور يجب أن تكون 8 أحرف على الأقل وتحتوي حرفاً كبيراً وصغيراً ورقماً ورمزاً')));
              return;
            }
            if (next.text != confirm.text) {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تأكيد كلمة السر غير مطابق')));
              return;
            }
            setState(() => loading = true);
            try {
              await context.read<AuthProvider>().changePassword(currentPassword: current.text, newPassword: next.text);
              widget.onPasswordChanged();
              if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم تغيير كلمة السر، يمكنك الآن إدارة خدماتك')));
            } catch (e) {
              if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
            } finally { if (mounted) setState(() => loading = false); }
          },
          icon: const Icon(Icons.check_circle_outline),
          label: Text(loading ? 'جاري الحفظ...' : 'تغيير كلمة السر والمتابعة'),
        ),
      ),
    ]);
  }
}

class _ChangePasswordCard extends StatefulWidget {
  const _ChangePasswordCard();

  @override
  State<_ChangePasswordCard> createState() => _ChangePasswordCardState();
}

class _ChangePasswordCardState extends State<_ChangePasswordCard> {
  static final _strongPassword = RegExp(r'^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$');
  final current = TextEditingController();
  final next = TextEditingController();
  bool loading = false;

  @override
  void dispose() { current.dispose(); next.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('تغيير كلمة السر', style: TextStyle(fontWeight: FontWeight.w900)),
        const SizedBox(height: 10),
        TextField(controller: current, obscureText: true, decoration: const InputDecoration(labelText: 'كلمة السر الحالية')),
        const SizedBox(height: 10),
        TextField(controller: next, obscureText: true, decoration: const InputDecoration(labelText: 'كلمة السر الجديدة')),
        const SizedBox(height: 12),
        ElevatedButton(
          onPressed: loading ? null : () async {
            if (!_strongPassword.hasMatch(next.text)) {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('كلمة المرور يجب أن تكون 8 أحرف على الأقل وتحتوي حرفاً كبيراً وصغيراً ورقماً ورمزاً')));
              return;
            }
            setState(() => loading = true);
            try {
              await context.read<AuthProvider>().changePassword(currentPassword: current.text, newPassword: next.text);
              if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم تغيير كلمة السر')));
            } catch (e) {
              if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
            } finally { if (mounted) setState(() => loading = false); }
          },
          child: Text(loading ? 'جاري الحفظ...' : 'حفظ كلمة السر'),
        ),
      ]),
    );
  }
}
