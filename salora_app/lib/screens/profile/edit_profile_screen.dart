import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/widgets/user_avatar.dart';
import '../../providers/auth_provider.dart';

class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _picker = ImagePicker();
  final _newEmailController = TextEditingController();
  final _otpController = TextEditingController();

  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  late final TextEditingController _phoneController;

  File? _image;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final auth = context.read<AuthProvider>();
    _nameController = TextEditingController(text: auth.rawUserName);
    _emailController = TextEditingController(text: auth.rawEmail);
    _phoneController = TextEditingController(text: auth.rawPhone);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _newEmailController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _pick(ImageSource source) async {
    final picked = await _picker.pickImage(
      source: source,
      imageQuality: 88,
      maxWidth: 1200,
      maxHeight: 1200,
    );
    if (picked != null && mounted) {
      setState(() => _image = File(picked.path));
    }
  }

  void _message(String text) {
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    messenger.clearSnackBars();
    messenger.showSnackBar(
      SnackBar(
        content: Text(text),
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 4),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('تعديل الملف الشخصي')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(18),
          children: [
            Center(
              child: UserAvatar(
                imageUrl: auth.profileImageUrl,
                localFile: _image,
                radius: 58,
                heroTag: 'profile-avatar',
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              alignment: WrapAlignment.center,
              spacing: 8,
              runSpacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: _busy ? null : () => _pick(ImageSource.gallery),
                  icon: const Icon(Icons.photo_library_outlined),
                  label: const Text('المعرض'),
                ),
                OutlinedButton.icon(
                  onPressed: _busy ? null : () => _pick(ImageSource.camera),
                  icon: const Icon(Icons.camera_alt_outlined),
                  label: const Text('الكاميرا'),
                ),
                if (auth.profileImageUrl != null)
                  OutlinedButton.icon(
                    onPressed: _busy ? null : _deleteAvatar,
                    icon: const Icon(Icons.delete_outline),
                    label: const Text('حذف الصورة'),
                  ),
              ],
            ),
            const SizedBox(height: 22),
            TextFormField(
              controller: _nameController,
              decoration: const InputDecoration(labelText: 'الاسم الكامل'),
              validator: (value) => value == null || value.trim().length < 3
                  ? 'أدخل الاسم الكامل'
                  : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              maxLength: 10,
              decoration: const InputDecoration(
                labelText: 'رقم الهاتف السوري - 10 أرقام',
              ),
              validator: (value) =>
                  RegExp(r'^\d{10}$').hasMatch(value?.trim() ?? '')
                  ? null
                  : 'الرقم يجب أن يكون 10 أرقام',
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _emailController,
              readOnly: true,
              decoration: const InputDecoration(labelText: 'البريد الحالي'),
            ),
            const SizedBox(height: 18),
            ElevatedButton.icon(
              onPressed: _busy ? null : _save,
              icon: _busy
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.save_outlined),
              label: Text(_busy ? 'جاري الحفظ...' : 'حفظ الملف والصورة'),
            ),
            const Divider(height: 42),
            const Text(
              'تغيير البريد عبر OTP',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _newEmailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'البريد الجديد'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: _busy ? null : _requestEmailChange,
              child: const Text('إرسال الرمز'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _otpController,
              keyboardType: TextInputType.number,
              maxLength: 6,
              decoration: const InputDecoration(labelText: 'رمز OTP'),
            ),
            ElevatedButton(
              onPressed: _busy ? null : _verifyEmailChange,
              child: const Text('تأكيد البريد'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _deleteAvatar() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('حذف الصورة الشخصية؟'),
        content: const Text('سيتم إظهار الصورة الافتراضية بعد الحذف.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('حذف'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _busy = true);
    try {
      await context.read<AuthProvider>().deleteAvatar();
      if (mounted) setState(() => _image = null);
      _message('تم حذف الصورة الشخصية.');
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _busy = true);
    try {
      final auth = context.read<AuthProvider>();
      await auth.updateProfile(
        name: _nameController.text,
        phone: _phoneController.text,
      );
      if (_image != null) {
        await auth.uploadAvatar(_image!);
      }
      _message('تم تحديث الملف الشخصي.');
      if (mounted) Navigator.pop(context);
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _requestEmailChange() async {
    final email = _newEmailController.text.trim().toLowerCase();
    if (!RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(email)) {
      _message('أدخل بريداً إلكترونياً جديداً صحيحاً.');
      return;
    }
    if (email == _emailController.text.trim().toLowerCase()) {
      _message('البريد الجديد مطابق للبريد الحالي.');
      return;
    }
    setState(() => _busy = true);
    try {
      final challenge = await context.read<AuthProvider>().requestEmailChange(
        email,
      );
      if (challenge.demoOtp != null && challenge.demoOtp!.isNotEmpty) {
        _otpController.text = challenge.demoOtp!;
      }
      _message(
        'تم إرسال رمز OTP إلى ${challenge.maskedEmail}. صلاحيته 10 دقائق.',
      );
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _verifyEmailChange() async {
    final otp = _otpController.text.trim();
    if (!RegExp(r'^\d{6}$').hasMatch(otp)) {
      _message('رمز OTP يجب أن يتكون من 6 أرقام.');
      return;
    }
    setState(() => _busy = true);
    try {
      final auth = context.read<AuthProvider>();
      await auth.verifyEmailChange(
        _newEmailController.text.trim().toLowerCase(),
        otp,
      );
      _emailController.text = auth.rawEmail;
      _newEmailController.clear();
      _otpController.clear();
      _message('تم تغيير البريد الإلكتروني وتأكيده بنجاح.');
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }
}
