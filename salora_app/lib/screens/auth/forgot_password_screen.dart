import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/auth_provider.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _email = TextEditingController();
  final _otp = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  Timer? _timer;
  EmailOtpChallenge? _challenge;
  int _resendRemaining = 0;
  bool _loading = false;
  bool _obscure = true;
  String? _message;

  static final _strongPassword = RegExp(r'^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$');

  @override
  void dispose() {
    _timer?.cancel();
    _email.dispose();
    _otp.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  bool get _codeRequested => _challenge != null;

  void _startCooldown(int seconds) {
    _timer?.cancel();
    if (!mounted) return;
    setState(() => _resendRemaining = seconds.clamp(0, 3600).toInt());
    if (_resendRemaining <= 0) return;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_resendRemaining <= 1) {
        timer.cancel();
        setState(() => _resendRemaining = 0);
      } else {
        setState(() => _resendRemaining--);
      }
    });
  }

  Future<void> _requestCode({bool resend = false}) async {
    final email = _email.text.trim();
    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      setState(() => _message = 'أدخل بريداً إلكترونياً صحيحاً.');
      return;
    }
    if (resend && _resendRemaining > 0) return;

    setState(() {
      _loading = true;
      _message = null;
    });
    try {
      final challenge = await context.read<AuthProvider>().requestPasswordReset(email);
      if (!mounted) return;
      setState(() {
        _challenge = challenge;
        _message = resend
            ? 'تم طلب رمز جديد. إذا كان الحساب موجوداً فسيصل الرمز إلى بريده.'
            : 'إذا كان الحساب موجوداً، فقد أرسلنا رمز الاستعادة إلى بريده الإلكتروني.';
      });
      _startCooldown(challenge.resendAfterSeconds);
    } catch (e) {
      if (!mounted) return;
      if (e is ApiException && e.code == 'otp_cooldown') {
        final errors = e.errors;
        final retryAfter = errors is Map ? int.tryParse(errors['retry_after_seconds']?.toString() ?? '') : null;
        if (retryAfter != null) _startCooldown(retryAfter);
      }
      setState(() => _message = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _reset() async {
    if (!RegExp(r'^\d{6}$').hasMatch(_otp.text.trim())) {
      setState(() => _message = 'رمز التحقق يجب أن يتكون من 6 أرقام.');
      return;
    }
    if (!_strongPassword.hasMatch(_password.text)) {
      setState(() => _message = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل وتحتوي حرفاً كبيراً وصغيراً ورقماً ورمزاً.');
      return;
    }
    if (_password.text != _confirmation.text) {
      setState(() => _message = 'تأكيد كلمة المرور غير مطابق.');
      return;
    }
    setState(() {
      _loading = true;
      _message = null;
    });
    try {
      await context.read<AuthProvider>().resetPassword(
            email: _email.text,
            otp: _otp.text,
            password: _password.text,
            passwordConfirmation: _confirmation.text,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم تغيير كلمة المرور. يمكنك تسجيل الدخول الآن.')));
      Navigator.pop(context);
    } catch (e) {
      if (mounted) setState(() => _message = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _changeEmail() {
    _timer?.cancel();
    setState(() {
      _challenge = null;
      _resendRemaining = 0;
      _otp.clear();
      _password.clear();
      _confirmation.clear();
      _message = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('استعادة كلمة المرور')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(26),
                border: Border.all(color: Colors.white10),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.lock_reset_rounded, size: 50, color: AppColors.primary),
                  const SizedBox(height: 14),
                  const Text('نسيت كلمة المرور؟', style: TextStyle(fontSize: 26, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 8),
                  const Text(
                    'أدخل بريد الحساب. سيصل رمز تحقق من 6 أرقام إلى البريد المسجل، ثم استخدمه لتعيين كلمة مرور جديدة.',
                    style: TextStyle(color: AppColors.textSecondary, height: 1.5),
                  ),
                  const SizedBox(height: 18),
                  TextField(
                    controller: _email,
                    enabled: !_codeRequested && !_loading,
                    keyboardType: TextInputType.emailAddress,
                    autocorrect: false,
                    decoration: InputDecoration(
                      labelText: 'البريد الإلكتروني',
                      prefixIcon: const Icon(Icons.email_outlined),
                      suffixIcon: _codeRequested
                          ? IconButton(onPressed: _loading ? null : _changeEmail, icon: const Icon(Icons.edit_outlined), tooltip: 'تغيير البريد')
                          : null,
                    ),
                  ),
                  if (_codeRequested) ...[
                    const SizedBox(height: 14),
                    TextField(
                      controller: _otp,
                      keyboardType: TextInputType.number,
                      textAlign: TextAlign.center,
                      maxLength: 6,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, letterSpacing: 8),
                      decoration: const InputDecoration(
                        labelText: 'رمز التحقق',
                        counterText: '',
                        prefixIcon: Icon(Icons.pin_outlined),
                      ),
                    ),
                    if (_challenge?.demoOtp != null) ...[
                      const SizedBox(height: 8),
                      SelectableText('رمز بيئة التطوير: ${_challenge!.demoOtp}', style: const TextStyle(fontWeight: FontWeight.w900)),
                    ],
                    const SizedBox(height: 14),
                    TextField(
                      controller: _password,
                      obscureText: _obscure,
                      decoration: InputDecoration(
                        labelText: 'كلمة المرور الجديدة',
                        helperText: '8 أحرف مع حرف كبير وصغير ورقم ورمز',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          onPressed: () => setState(() => _obscure = !_obscure),
                          icon: Icon(_obscure ? Icons.visibility_off : Icons.visibility),
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: _confirmation,
                      obscureText: _obscure,
                      decoration: const InputDecoration(labelText: 'تأكيد كلمة المرور', prefixIcon: Icon(Icons.lock_reset_rounded)),
                      onSubmitted: (_) => _reset(),
                    ),
                  ],
                  if (_message != null) ...[
                    const SizedBox(height: 14),
                    Text(_message!, style: const TextStyle(fontWeight: FontWeight.w700, height: 1.4)),
                  ],
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: _loading ? null : (_codeRequested ? _reset : _requestCode),
                      icon: _loading
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : Icon(_codeRequested ? Icons.check_circle_outline : Icons.send_outlined),
                      label: Text(_loading
                          ? 'جاري التنفيذ...'
                          : (_codeRequested ? 'تغيير كلمة المرور' : 'إرسال رمز التحقق')),
                    ),
                  ),
                  if (_codeRequested)
                    Center(
                      child: TextButton(
                        onPressed: _resendRemaining == 0 && !_loading ? () => _requestCode(resend: true) : null,
                        child: Text(
                          _resendRemaining > 0
                              ? 'إعادة الإرسال بعد $_resendRemaining ثانية'
                              : 'إعادة إرسال الرمز',
                        ),
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
}
