import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../providers/booking_provider.dart';
import '../../providers/complaint_provider.dart';

class ComplaintFormScreen extends StatefulWidget {
  const ComplaintFormScreen({super.key});

  @override
  State<ComplaintFormScreen> createState() => _ComplaintFormScreenState();
}

class _ComplaintFormScreenState extends State<ComplaintFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _subject = TextEditingController();
  final _description = TextEditingController();
  String _category = 'general';
  String _bookingId = '';
  File? _attachment;
  bool _submitting = false;

  static const _categories = <String, String>{
    'general': 'استفسار عام',
    'technical': 'مشكلة تقنية',
    'financial': 'مشكلة مالية',
    'venue': 'شكوى على صالة',
    'provider': 'شكوى على مقدم خدمة',
  };

  @override
  void dispose() {
    _subject.dispose();
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bookings = context.watch<BookingProvider>().bookings;
    return Scaffold(
      appBar: AppBar(title: const Text('إرسال شكوى')),
      body: Form(
        key: _formKey,
        child: ListView(padding: const EdgeInsets.all(18), children: [
          TextFormField(
            controller: _subject,
            decoration: const InputDecoration(labelText: 'عنوان الشكوى', prefixIcon: Icon(Icons.subject_rounded)),
            validator: (value) => value == null || value.trim().length < 3 ? 'أدخل عنوان الشكوى' : null,
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _bookingId,
            decoration: const InputDecoration(labelText: 'الحجز المرتبط اختياري', prefixIcon: Icon(Icons.confirmation_number_outlined)),
            items: [
              const DropdownMenuItem<String>(value: '', child: Text('بدون حجز محدد')),
              ...bookings.map((booking) => DropdownMenuItem(value: booking.id, child: Text('${booking.bookingNumber.isEmpty ? booking.id : booking.bookingNumber} - ${booking.venueName}'))),
            ],
            onChanged: (value) => setState(() => _bookingId = value ?? ''),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _category,
            decoration: const InputDecoration(labelText: 'نوع الشكوى', prefixIcon: Icon(Icons.category_outlined)),
            items: _categories.entries.map((entry) => DropdownMenuItem(value: entry.key, child: Text(entry.value))).toList(),
            onChanged: (value) => setState(() => _category = value ?? 'general'),
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _description,
            maxLines: 5,
            decoration: const InputDecoration(labelText: 'الوصف', alignLabelWithHint: true),
            validator: (value) => value == null || value.trim().length < 10 ? 'اكتب تفاصيل أكثر' : null,
          ),
          const SizedBox(height: 20),
          OutlinedButton.icon(
            onPressed: _pickAttachment,
            icon: const Icon(Icons.attach_file_rounded),
            label: Text(_attachment == null ? 'إرفاق صورة اختيارية' : 'تم اختيار: ${_attachment!.path.split(Platform.pathSeparator).last}'),
          ),
          const SizedBox(height: 12),
          ElevatedButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('إرسال الشكوى'),
          ),
        ]),
      ),
    );
  }

  Future<void> _pickAttachment() async {
    final image = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (image != null && mounted) setState(() => _attachment = File(image.path));
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      final complaint = await context.read<ComplaintProvider>().submitComplaint(
            subject: _subject.text.trim(),
            category: _category,
            description: _description.text.trim(),
            bookingId: _bookingId,
            attachment: _attachment,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('تم إرسال الشكوى برقم ${complaint.referenceNumber}')));
      Navigator.pop(context);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('تعذر إرسال الشكوى: $e')));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}
