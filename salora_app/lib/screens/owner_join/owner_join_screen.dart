import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_colors.dart';
import '../../models/join_request_model.dart';
import '../../models/service_category_model.dart';
import '../../models/user_role.dart';
import '../../providers/auth_provider.dart';
import '../../providers/service_provider.dart';

enum JoinType { owner, provider }

class OwnerJoinScreen extends StatefulWidget {
  final JoinType initialType;
  const OwnerJoinScreen({super.key, this.initialType = JoinType.owner});

  @override
  State<OwnerJoinScreen> createState() => _OwnerJoinScreenState();
}

class _OwnerJoinScreenState extends State<OwnerJoinScreen> {
  final _formKey = GlobalKey<FormState>();
  late JoinType _type;
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _otp = TextEditingController();
  final _city = TextEditingController(text: 'دمشق');
  final _hallName = TextEditingController();
  final _address = TextEditingController();
  final _serviceDescription = TextEditingController();
  final _notes = TextEditingController();
  String? _serviceCategoryId;
  bool _loading = false;
  bool _otpSent = false;
  int _resendSeconds = 0;
  Timer? _timer;
  String? _demoOtp;
  List<JoinRequestModel> _requests = const [];
  bool _historyLoading = false;

  @override
  void initState() {
    super.initState();
    _type = widget.initialType;
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  Future<void> _bootstrap() async {
    final auth = context.read<AuthProvider>();
    if (auth.isLoggedIn && auth.role == UserRole.customer) {
      _name.text = auth.rawUserName;
      _phone.text = auth.rawPhone;
    }
    if (context.read<ServiceProviderState>().categoryModels.isEmpty) {
      await context.read<ServiceProviderState>().loadDirectory();
    }
    if (auth.isLoggedIn && auth.role == UserRole.customer) await _loadHistory();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _name.dispose();
    _phone.dispose();
    _email.dispose();
    _otp.dispose();
    _city.dispose();
    _hallName.dispose();
    _address.dispose();
    _serviceDescription.dispose();
    _notes.dispose();
    super.dispose();
  }

  void _startCountdown(int seconds) {
    _timer?.cancel();
    setState(() => _resendSeconds = seconds);
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return timer.cancel();
      if (_resendSeconds <= 1) {
        timer.cancel();
        setState(() => _resendSeconds = 0);
      } else {
        setState(() => _resendSeconds--);
      }
    });
  }

  Future<void> _requestOtp() async {
    final email = _email.text.trim().toLowerCase();
    if (!email.contains('@')) {
      _message('أدخل بريداً إلكترونياً صحيحاً لحساب العمل الجديد.');
      return;
    }
    setState(() => _loading = true);
    try {
      final challenge = await context.read<AuthProvider>().requestBusinessJoinOtp(email);
      if (!mounted) return;
      setState(() {
        _otpSent = true;
        _demoOtp = challenge.demoOtp;
      });
      _startCountdown(challenge.resendAfterSeconds);
      _message('تم إرسال رمز التحقق إلى ${challenge.maskedEmail}.');
    } catch (e) {
      if (mounted) _message(e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    if (!_otpSent) {
      _message('أرسل رمز التحقق إلى البريد التجاري أولاً.');
      return;
    }
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    try {
      final request = await context.read<AuthProvider>().submitBusinessJoinRequest(
            requestType: _type == JoinType.owner ? 'owner' : 'provider',
            fullName: _name.text,
            businessEmail: _email.text,
            otp: _otp.text,
            phone: _phone.text,
            city: _city.text,
            hallName: _hallName.text,
            address: _address.text,
            serviceCategoryId: _serviceCategoryId ?? '',
            serviceDescription: _serviceDescription.text,
            notes: _notes.text,
          );
      if (context.read<AuthProvider>().isLoggedIn) await _loadHistory();
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('تم إرسال الطلب'),
          content: Text(
            request.requestType == 'provider'
                ? 'وصل طلب مقدم الخدمة إلى الأدمن. عند الموافقة سيُنشأ حساب عمل مستقل بالبريد التجاري، ولن يتغير حساب العميل الحالي.'
                : 'وصل طلب مدير الصالة إلى الأدمن. عند الموافقة سيُنشأ حساب عمل مستقل للبريد التجاري وتُرسل بيانات الدخول إليه.',
          ),
          actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('حسنًا'))],
        ),
      );
      if (!mounted) return;
      setState(() {
        _otpSent = false;
        _otp.clear();
        _demoOtp = null;
      });
    } catch (e) {
      if (mounted) _message(e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadHistory() async {
    if (!mounted) return;
    final auth = context.read<AuthProvider>();
    if (!auth.isLoggedIn || auth.role != UserRole.customer) return;
    setState(() => _historyLoading = true);
    try {
      final values = await context.read<AuthProvider>().loadMyJoinRequests();
      if (mounted) setState(() => _requests = values);
    } catch (_) {
      // The submission form remains available if history loading fails.
    } finally {
      if (mounted) setState(() => _historyLoading = false);
    }
  }

  void _switchType(JoinType type) {
    setState(() {
      _type = type;
      _serviceCategoryId = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final categories = context.watch<ServiceProviderState>().categoryModels.where((item) => item.supportsProviders).toList();
    final isProvider = _type == JoinType.provider;


    return Scaffold(
      appBar: AppBar(title: const Text('الانضمام إلى Salora كشريك')),
      body: RefreshIndicator(
        onRefresh: auth.isLoggedIn ? _loadHistory : () async {},
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(18),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(24)),
              child: const Text(
                'يمكنك إرسال الطلب مباشرة دون حساب عميل. عند الموافقة ينشئ الأدمن حساب عمل مستقل ويرسل كلمة مرور مؤقتة عشوائية إلى البريد الموثق.',
                style: TextStyle(color: AppColors.textSecondary, height: 1.55),
              ),
            ),
            const SizedBox(height: 16),
            SegmentedButton<JoinType>(
              segments: const [
                ButtonSegment(value: JoinType.owner, label: Text('مدير صالة'), icon: Icon(Icons.storefront_outlined)),
                ButtonSegment(value: JoinType.provider, label: Text('مقدم خدمة'), icon: Icon(Icons.handshake_outlined)),
              ],
              selected: {_type},
              onSelectionChanged: (value) => _switchType(value.first),
            ),
            const SizedBox(height: 18),
            Form(
              key: _formKey,
              child: Column(
                children: [
                  TextFormField(
                    controller: _name,
                    decoration: const InputDecoration(labelText: 'الاسم الكامل *'),
                    validator: (value) => value == null || value.trim().isEmpty ? 'الاسم مطلوب' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _phone,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'رقم التواصل *'),
                    validator: (value) => RegExp(r'^[0-9]{10}$').hasMatch(value?.trim() ?? '') ? null : 'أدخل رقماً سورياً من 10 أرقام',
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _email,
                    enabled: !_otpSent,
                    keyboardType: TextInputType.emailAddress,
                    decoration: InputDecoration(
                      labelText: 'البريد التجاري للحساب الجديد *',
                      helperText: auth.isLoggedIn ? 'يجب أن يختلف عن بريد حساب العميل: ${auth.rawEmail}' : 'سيصبح هذا البريد هو بريد حساب العمل بعد موافقة الأدمن.',
                      suffixIcon: _otpSent
                          ? IconButton(
                              tooltip: 'تغيير البريد',
                              onPressed: () => setState(() {
                                _otpSent = false;
                                _otp.clear();
                                _demoOtp = null;
                                _timer?.cancel();
                                _resendSeconds = 0;
                              }),
                              icon: const Icon(Icons.edit_outlined),
                            )
                          : null,
                    ),
                    validator: (value) {
                      final email = value?.trim().toLowerCase() ?? '';
                      if (!email.contains('@')) return 'أدخل بريداً صحيحاً';
                      if (auth.isLoggedIn && email == auth.rawEmail.trim().toLowerCase()) return 'استخدم بريداً مختلفاً لحساب العمل';
                      return null;
                    },
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: _loading || _resendSeconds > 0 ? null : _requestOtp,
                      icon: const Icon(Icons.mark_email_read_outlined),
                      label: Text(_otpSent
                          ? (_resendSeconds > 0 ? 'إعادة الإرسال بعد $_resendSeconds ثانية' : 'إعادة إرسال OTP')
                          : 'إرسال OTP إلى البريد التجاري'),
                    ),
                  ),
                  if (_otpSent) ...[
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _otp,
                      keyboardType: TextInputType.number,
                      maxLength: 6,
                      decoration: InputDecoration(
                        labelText: 'رمز التحقق OTP *',
                        helperText: _demoOtp == null ? 'الرمز صالح لمدة 10 دقائق.' : 'رمز بيئة التطوير: $_demoOtp',
                      ),
                      validator: (value) => value == null || value.trim().length != 6 ? 'أدخل الرمز المؤلف من 6 أرقام' : null,
                    ),
                  ],
                  const SizedBox(height: 4),
                  TextFormField(controller: _city, decoration: const InputDecoration(labelText: 'المدينة *'), validator: (value) => value == null || value.trim().isEmpty ? 'المدينة مطلوبة' : null),
                  if (!isProvider) ...[
                    const SizedBox(height: 12),
                    TextFormField(controller: _hallName, decoration: const InputDecoration(labelText: 'اسم الصالة أو النشاط (اختياري)')),
                    const SizedBox(height: 12),
                    TextFormField(controller: _address, decoration: const InputDecoration(labelText: 'عنوان مبدئي (اختياري)')),
                  ],
                  if (isProvider) ...[
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      value: categories.any((item) => item.id == _serviceCategoryId) ? _serviceCategoryId : null,
                      items: _categoryItems(categories),
                      onChanged: (value) => setState(() => _serviceCategoryId = value),
                      decoration: const InputDecoration(labelText: 'تصنيف الخدمة *'),
                      validator: (value) => value == null || value.isEmpty ? 'اختر تصنيف الخدمة' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(controller: _serviceDescription, minLines: 3, maxLines: 5, decoration: const InputDecoration(labelText: 'نبذة عن الخدمة')),
                  ],
                  const SizedBox(height: 12),
                  TextFormField(controller: _notes, minLines: 2, maxLines: 4, decoration: const InputDecoration(labelText: 'ملاحظات إضافية')),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: _loading ? null : _submit,
                      icon: const Icon(Icons.send_rounded),
                      label: Text(_loading ? 'جاري التنفيذ...' : 'إرسال الطلب بعد توثيق البريد'),
                    ),
                  ),
                ],
              ),
            ),
            if (auth.isLoggedIn) ...[
              const SizedBox(height: 28),
              Row(
                children: [
                  const Expanded(child: Text('طلباتي السابقة', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900))),
                  if (_historyLoading) const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)),
                ],
              ),
              const SizedBox(height: 10),
              if (!_historyLoading && _requests.isEmpty)
                const Text('لم ترسل أي طلب انضمام بعد.', style: TextStyle(color: AppColors.textSecondary)),
              ..._requests.map(_historyCard),
            ],
          ],
        ),
      ),
    );
  }

  List<DropdownMenuItem<String>> _categoryItems(List<ServiceCategoryModel> categories) {
    final items = <DropdownMenuItem<String>>[];
    for (final category in categories) {
      items.add(DropdownMenuItem(value: category.id, child: Text(category.name)));
    }
    return items;
  }

  Widget _historyCard(JoinRequestModel request) {
    final color = switch (request.status) {
      'approved' => AppColors.success,
      'rejected' => AppColors.danger,
      _ => Colors.amber,
    };
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(18), border: Border.all(color: color.withOpacity(.28))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(request.requestType == 'provider' ? 'طلب مقدم خدمة' : 'طلب مدير صالة', style: const TextStyle(fontWeight: FontWeight.w900))),
              Text(request.statusLabel, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 12)),
            ],
          ),
          const SizedBox(height: 5),
          Text(request.email, textDirection: TextDirection.ltr, style: const TextStyle(color: AppColors.textSecondary)),
          if (request.serviceCategory.isNotEmpty) Text('التصنيف: ${request.serviceCategory}', style: const TextStyle(color: AppColors.textSecondary)),
          if (request.rejectionReason?.trim().isNotEmpty ?? false) ...[
            const SizedBox(height: 6),
            Text('سبب الرفض: ${request.rejectionReason}', style: const TextStyle(color: AppColors.danger)),
          ],
        ],
      ),
    );
  }

  void _message(String message) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message.replaceFirst('ApiException: ', ''))));
}
