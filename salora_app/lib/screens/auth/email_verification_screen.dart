import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/auth_provider.dart';
import '../../providers/booking_provider.dart';
import '../../providers/event_provider.dart';
import '../../providers/notification_provider.dart';
import '../../providers/service_provider.dart';
import '../../providers/venue_provider.dart';
import '../home/main_navigation_screen.dart';

class EmailVerificationScreen extends StatefulWidget {
  const EmailVerificationScreen({
    super.key,
    required this.challenge,
  });

  final EmailOtpChallenge challenge;

  @override
  State<EmailVerificationScreen> createState() => _EmailVerificationScreenState();
}

class _EmailVerificationScreenState extends State<EmailVerificationScreen> {
  final _otp = TextEditingController();
  Timer? _timer;
  late EmailOtpChallenge _challenge;
  int _resendRemaining = 0;
  bool _loading = false;
  String? _message;

  @override
  void initState() {
    super.initState();
    _challenge = widget.challenge;
    _resendRemaining = _challenge.resendAfterSeconds.clamp(0, 3600).toInt();
    _startTimer();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _otp.dispose();
    super.dispose();
  }

  void _startCooldown(int seconds) {
    _timer?.cancel();
    if (!mounted) return;
    setState(() => _resendRemaining = seconds.clamp(0, 3600).toInt());
    _startTimer();
  }

  void _startTimer() {
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

  Future<void> _verify() async {
    final code = _otp.text.trim();
    if (!RegExp(r'^\d{6}$').hasMatch(code)) {
      setState(() => _message = 'أدخل رمز التحقق المكوّن من 6 أرقام.');
      return;
    }

    setState(() {
      _loading = true;
      _message = null;
    });
    try {
      await context.read<AuthProvider>().verifyEmail(email: _challenge.email, otp: code);
      if (!mounted) return;
      await Future.wait([
        context.read<EventProvider>().loadTemplates(),
        context.read<EventProvider>().loadEvents(),
        context.read<BookingProvider>().loadMyBookings(),
        context.read<NotificationProvider>().loadNotifications(),
        context.read<VenueProvider>().loadVenues(),
        context.read<ServiceProviderState>().loadDirectory(),
      ]);
      if (!mounted) return;
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const MainNavigationScreen()),
        (_) => false,
      );
    } catch (e) {
      if (mounted) setState(() => _message = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _resend() async {
    if (_resendRemaining > 0 || _loading) return;
    setState(() {
      _loading = true;
      _message = null;
    });
    try {
      final next = await context.read<AuthProvider>().resendEmailVerification(_challenge.email);
      if (!mounted) return;
      setState(() {
        _challenge = next;
        _message = 'أرسلنا رمزاً جديداً إلى بريدك الإلكتروني.';
      });
      _startCooldown(next.resendAfterSeconds);
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

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: true,
      child: Scaffold(
        appBar: AppBar(title: const Text('توثيق البريد الإلكتروني')),
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.all(22),
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(28),
                  border: Border.all(color: Colors.white10),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.mark_email_read_outlined, size: 52, color: AppColors.primary),
                    const SizedBox(height: 16),
                    const Text('تحقق من بريدك', style: TextStyle(fontSize: 27, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 8),
                    Text(
                      _challenge.mailSent
                          ? 'أرسلنا رمزاً من 6 أرقام إلى ${_challenge.maskedEmail}. الرمز صالح لمدة 10 دقائق.'
                          : 'الحساب بانتظار التوثيق. استخدم زر إعادة الإرسال للحصول على رمز عبر البريد.',
                      style: const TextStyle(color: AppColors.textSecondary, height: 1.5),
                    ),
                    const SizedBox(height: 22),
                    TextField(
                      controller: _otp,
                      autofocus: true,
                      keyboardType: TextInputType.number,
                      textAlign: TextAlign.center,
                      maxLength: 6,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      style: const TextStyle(fontSize: 27, fontWeight: FontWeight.w900, letterSpacing: 10),
                      decoration: const InputDecoration(
                        labelText: 'رمز التحقق',
                        counterText: '',
                        prefixIcon: Icon(Icons.password_rounded),
                      ),
                      onSubmitted: (_) => _verify(),
                    ),
                    if (_challenge.demoOtp != null) ...[
                      const SizedBox(height: 10),
                      SelectableText(
                        'رمز بيئة التطوير: ${_challenge.demoOtp}',
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ],
                    if (_message != null) ...[
                      const SizedBox(height: 12),
                      Text(_message!, style: const TextStyle(fontWeight: FontWeight.w700, height: 1.4)),
                    ],
                    const SizedBox(height: 18),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: _loading ? null : _verify,
                        icon: _loading
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Icon(Icons.verified_outlined),
                        label: Text(_loading ? 'جاري التحقق...' : 'تفعيل الحساب'),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Center(
                      child: TextButton(
                        onPressed: _resendRemaining == 0 && !_loading ? _resend : null,
                        child: Text(
                          _resendRemaining > 0
                              ? 'إعادة إرسال الرمز بعد $_resendRemaining ثانية'
                              : 'لم يصلك الرمز؟ إعادة الإرسال',
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              const Text(
                'قد يصل البريد إلى مجلد الرسائل غير المرغوبة. لا تشارك رمز التحقق مع أي شخص.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textSecondary, height: 1.5),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
