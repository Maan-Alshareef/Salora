import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_colors.dart';
import '../../core/validation/syrian_phone.dart';
import '../../core/widgets/app_logo.dart';
import '../../providers/auth_provider.dart';
import 'email_verification_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _obscure = true;
  bool _submitting = false;

  static final _strongPassword = RegExp(r'^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$');

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('إنشاء حساب')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(22),
          children: [
            const Center(child: AppLogo(size: 82)),
            const SizedBox(height: 24),
            const Text('انضم إلى Salora', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text(
              'التسجيل العام مخصص للعملاء. بعد إنشاء الحساب سنرسل رمز تحقق إلى البريد الإلكتروني لتفعيله.',
              style: TextStyle(color: AppColors.textSecondary, height: 1.45),
            ),
            const SizedBox(height: 22),
            Form(
              key: _formKey,
              child: Column(
                children: [
                  TextFormField(
                    controller: _name,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(labelText: 'الاسم الكامل', prefixIcon: Icon(Icons.person_outline)),
                    validator: (v) => v == null || v.trim().length < 3 ? 'أدخل الاسم الكامل' : null,
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _phone,
                    keyboardType: TextInputType.phone,
                    inputFormatters: SyrianPhone.formatters,
                    maxLength: 10,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'رقم الهاتف - 10 أرقام',
                      hintText: 'xxxxxxxxxx',
                      prefixIcon: Icon(Icons.phone_outlined),
                      counterText: '',
                    ),
                    validator: SyrianPhone.validate,
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    autocorrect: false,
                    decoration: const InputDecoration(labelText: 'البريد الإلكتروني', hintText: 'name@email.com', prefixIcon: Icon(Icons.email_outlined)),
                    validator: (v) => v == null || !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(v.trim())
                        ? 'أدخل بريداً إلكترونياً صحيحاً'
                        : null,
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _password,
                    obscureText: _obscure,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: 'كلمة المرور',
                      helperText: '8 أحرف على الأقل مع حرف كبير وصغير ورقم ورمز',
                      prefixIcon: const Icon(Icons.lock_outline),
                      suffixIcon: IconButton(
                        icon: Icon(_obscure ? Icons.visibility_off : Icons.visibility),
                        onPressed: () => setState(() => _obscure = !_obscure),
                      ),
                    ),
                    validator: (v) => v == null || !_strongPassword.hasMatch(v)
                        ? 'كلمة المرور لا تحقق شروط الأمان'
                        : null,
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _confirm,
                    obscureText: _obscure,
                    textInputAction: TextInputAction.done,
                    decoration: const InputDecoration(labelText: 'تأكيد كلمة المرور', prefixIcon: Icon(Icons.lock_reset_rounded)),
                    validator: (v) => v != _password.text ? 'كلمتا المرور غير متطابقتين' : null,
                    onFieldSubmitted: (_) => _submit(),
                  ),
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: _submitting ? null : _submit,
                      icon: _submitting
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.person_add_alt_1_rounded),
                      label: Text(_submitting ? 'جاري إنشاء الحساب...' : 'إنشاء الحساب وإرسال الرمز'),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      final challenge = await context.read<AuthProvider>().register(
            name: _name.text.trim(),
            email: _email.text.trim(),
            phone: SyrianPhone.normalize(_phone.text),
            password: _password.text,
            passwordConfirmation: _confirm.text,
          );
      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => EmailVerificationScreen(challenge: challenge)),
      );
    } catch (e) {
      if (!mounted) return;
      if (e is ApiException && e.code == 'email_verification_required') {
        final errors = e.errors;
        final email = errors is Map ? (errors['email'] ?? _email.text).toString() : _email.text;
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => EmailVerificationScreen(challenge: EmailOtpChallenge.pending(email)),
          ),
        );
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}
