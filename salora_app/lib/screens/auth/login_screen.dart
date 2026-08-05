import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/theme/app_colors.dart';
import '../../core/widgets/app_logo.dart';
import '../../models/user_role.dart';
import '../../providers/auth_provider.dart';
import '../../providers/booking_provider.dart';
import '../../providers/event_provider.dart';
import '../../providers/notification_provider.dart';
import '../../providers/service_provider.dart';
import '../../providers/venue_provider.dart';
import '../home/main_navigation_screen.dart';
import '../provider/provider_navigation_screen.dart';
import '../owner_join/owner_join_screen.dart';
import 'register_screen.dart';
import 'forgot_password_screen.dart';
import 'email_verification_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;
  UserRole _role = UserRole.customer;

  @override
  void dispose() { _email.dispose(); _password.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    const roles = [UserRole.customer, UserRole.provider];
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(22, 18, 22, 22),
          children: [
            const SizedBox(height: 10),
            const Center(child: AppLogo(size: 86)),
            const SizedBox(height: 24),
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(28), border: Border.all(color: Colors.white10)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  const Expanded(child: Text('أهلًا بك في Salora', style: TextStyle(fontSize: 25, fontWeight: FontWeight.w900))),
                  TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RegisterScreen())), child: const Text('إنشاء حساب عميل')),
                ]),
                const SizedBox(height: 4),
                const Text('التطبيق مخصص للعميل ومقدم الخدمة. الأدمن ومالك الصالة يدخلون من الداشبورد.', style: TextStyle(color: AppColors.textSecondary, height: 1.4)),
                const SizedBox(height: 18),
                const Text('تسجيل الدخول كـ', style: TextStyle(fontWeight: FontWeight.w900)),
                const SizedBox(height: 10),
                Row(
                  children: roles.map((role) {
                    final selected = _role == role;
                    return Expanded(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 3),
                        child: ChoiceChip(
                          label: SizedBox(width: double.infinity, child: Text(role.label, textAlign: TextAlign.center, style: const TextStyle(fontSize: 12))),
                          avatar: Icon(selected ? Icons.check_rounded : _iconForRole(role), size: 18),
                          selected: selected,
                          onSelected: (_) => setState(() => _role = role),
                        ),
                      ),
                    );
                  }).toList(),
                ),
                const SizedBox(height: 20),
                Form(
                  key: _formKey,
                  child: Column(children: [
                    TextFormField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'البريد الإلكتروني', hintText: 'name@email.com', prefixIcon: Icon(Icons.email_outlined)), validator: (v) => v == null || !v.contains('@') ? 'أدخل بريدًا إلكترونيًا صحيحًا' : null),
                    const SizedBox(height: 14),
                    TextFormField(controller: _password, obscureText: _obscure, decoration: InputDecoration(labelText: 'كلمة المرور', hintText: 'أدخل كلمة المرور', prefixIcon: const Icon(Icons.lock_outline), suffixIcon: IconButton(icon: Icon(_obscure ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _obscure = !_obscure))), validator: (v) => v == null || v.isEmpty ? 'أدخل كلمة المرور' : null),
                    const SizedBox(height: 10),
                    const Text(
                      'يمكن إرسال طلب الانضمام كمدير صالة أو مقدم خدمة مباشرة دون إنشاء حساب عميل.',
                      style: TextStyle(color: AppColors.textSecondary, fontSize: 12, height: 1.4),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 10),
                    Row(children: [
                      Expanded(child: OutlinedButton.icon(
                        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const OwnerJoinScreen(initialType: JoinType.owner))),
                        icon: const Icon(Icons.storefront_outlined),
                        label: const Text('طلب مدير صالة'),
                      )),
                      const SizedBox(width: 8),
                      Expanded(child: OutlinedButton.icon(
                        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const OwnerJoinScreen(initialType: JoinType.provider))),
                        icon: const Icon(Icons.handshake_outlined),
                        label: const Text('طلب مقدم خدمة'),
                      )),
                    ]),
                    Align(alignment: Alignment.centerRight, child: TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ForgotPasswordScreen())), child: const Text('نسيت كلمة المرور؟'))),
                    const SizedBox(height: 8),
                    ElevatedButton(onPressed: _login, child: Text('تسجيل الدخول كـ ${_role.arabicLabel}')),
                  ]),
                ),
              ]),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    try {
      await context.read<AuthProvider>().login(email: _email.text.trim(), password: _password.text, role: _role);
      if (!mounted) return;
      final actualRole = context.read<AuthProvider>().role;
      if (actualRole != _role) {
        await context.read<AuthProvider>().logout();
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(actualRole == UserRole.owner || actualRole == UserRole.admin ? 'هذا الحساب مخصص للداشبورد وليس تطبيق الموبايل.' : 'نوع الحساب لا يطابق الاختيار.')));
        return;
      }
      final mustChangePassword = context.read<AuthProvider>().mustChangePassword;
      if (actualRole == UserRole.customer) {
        await Future.wait([
          context.read<EventProvider>().loadTemplates(),
          context.read<EventProvider>().loadEvents(),
          context.read<BookingProvider>().loadMyBookings(),
          context.read<NotificationProvider>().loadNotifications(),
          context.read<VenueProvider>().loadVenues(),
          context.read<ServiceProviderState>().loadDirectory(),
        ]);
      } else if (!mustChangePassword) {
        // Business accounts must finish the first-login password change before
        // the app calls protected business endpoints such as notifications.
        await context.read<NotificationProvider>().loadNotifications();
      }
      if (!mounted) return;
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => actualRole == UserRole.provider ? const ProviderNavigationScreen() : const MainNavigationScreen()));
    } catch (e) {
      if (!mounted) return;
      if (e is ApiException && e.code == 'email_not_verified') {
        final errors = e.errors;
        final email = errors is Map ? (errors['email'] ?? _email.text).toString() : _email.text;
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => EmailVerificationScreen(
              challenge: EmailOtpChallenge.pending(email),
            ),
          ),
        );
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  IconData _iconForRole(UserRole role) {
    switch (role) {
      case UserRole.customer:
        return Icons.person_outline;
      case UserRole.provider:
        return Icons.handshake_outlined;
      case UserRole.owner:
        return Icons.storefront_outlined;
      case UserRole.admin:
        return Icons.admin_panel_settings_outlined;
    }
  }
}
