import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_colors.dart';

class BookingAdjustmentPaymentScreen extends StatefulWidget {
  const BookingAdjustmentPaymentScreen({
    super.key,
    required this.bookingId,
    required this.adjustmentId,
    required this.amountSyp,
  });

  final int bookingId;
  final int adjustmentId;
  final double amountSyp;

  @override
  State<BookingAdjustmentPaymentScreen> createState() =>
      _BookingAdjustmentPaymentScreenState();
}

class _BookingAdjustmentPaymentScreenState
    extends State<BookingAdjustmentPaymentScreen> {
  final _sender = TextEditingController();
  final _reference = TextEditingController();
  final _notes = TextEditingController();
  final _picker = ImagePicker();

  bool _loading = true;
  bool _submitting = false;
  List<Map<String, dynamic>> _methods = const [];
  Map<String, dynamic>? _method;
  Map<String, dynamic>? _account;
  XFile? _proof;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _sender.dispose();
    _reference.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final raw = await context.read<ApiClient>().get(
        '/salora-v2/bookings/${widget.bookingId}/payment-adjustment',
      );
      final root = raw is Map
          ? Map<String, dynamic>.from(raw)
          : <String, dynamic>{};
      final methods = (root['payment_options'] as List? ?? const [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .where((e) => (e['accounts'] as List? ?? const []).isNotEmpty)
          .toList();
      if (!mounted) return;
      setState(() {
        _methods = methods;
        _method = methods.isEmpty ? null : methods.first;
        final accounts = _accounts;
        _account = accounts.isEmpty ? null : accounts.first;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _loading = false);
      _message(e.toString());
    }
  }

  List<Map<String, dynamic>> get _accounts =>
      (_method?['accounts'] as List? ?? const [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();

  String get _amount =>
      '${widget.amountSyp.toStringAsFixed(widget.amountSyp % 1 == 0 ? 0 : 2)} ل.س';

  Future<void> _pick(ImageSource source) async {
    final file = await _picker.pickImage(
      source: source,
      imageQuality: 88,
      maxWidth: 1800,
    );
    if (file != null && mounted) setState(() => _proof = file);
  }

  Future<void> _submit() async {
    if (_proof == null || _method == null || _account == null) {
      _message('اختر طريقة الدفع والحساب وصورة إثبات التحويل.');
      return;
    }
    if (_sender.text.trim().isEmpty) {
      _message('اكتب اسم المرسل كما يظهر في عملية التحويل.');
      return;
    }

    setState(() => _submitting = true);
    try {
      await context.read<ApiClient>().multipartPost(
        '/salora-v2/bookings/${widget.bookingId}/payment-adjustments/${widget.adjustmentId}/proof',
        fields: {
          'payment_method_id': _method!['id'].toString(),
          'payout_account_id': _account!['id'].toString(),
          'sender_name': _sender.text.trim(),
          if (_reference.text.trim().isNotEmpty)
            'transaction_reference': _reference.text.trim(),
          if (_notes.text.trim().isNotEmpty) 'customer_notes': _notes.text.trim(),
        },
        fileField: 'image',
        file: File(_proof!.path),
      );
      if (!mounted) return;
      _message('تم رفع إثبات فرق الدفع وهو بانتظار مراجعة مالك الصالة.');
      Navigator.pop(context, true);
    } catch (e) {
      _message(e.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('دفع فرق تعديل الحجز')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'المبلغ المطلوب لإتمام التعديل',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          _amount,
                          style: const TextStyle(
                            fontSize: 26,
                            fontWeight: FontWeight.w900,
                            color: AppColors.primary,
                          ),
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'لا توجد مهلة للدفع أو لمراجعة الإثبات. يبقى التعديل بانتظار التسوية حتى يقبل مالك الصالة الإثبات.',
                          style: TextStyle(color: AppColors.textSecondary),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                if (_methods.isEmpty)
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Text('لا يوجد حساب استلام فعال حالياً.'),
                    ),
                  )
                else ...[
                  DropdownButtonFormField<String>(
                    value: _method?['id']?.toString(),
                    decoration: const InputDecoration(labelText: 'طريقة الدفع'),
                    items: _methods
                        .map(
                          (m) => DropdownMenuItem(
                            value: m['id'].toString(),
                            child: Text(m['name_ar']?.toString() ?? 'طريقة دفع'),
                          ),
                        )
                        .toList(),
                    onChanged: _submitting
                        ? null
                        : (id) {
                            final selected = _methods.firstWhere(
                              (m) => m['id'].toString() == id,
                            );
                            setState(() {
                              _method = selected;
                              final accounts = _accounts;
                              _account = accounts.isEmpty ? null : accounts.first;
                            });
                          },
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    value: _account?['id']?.toString(),
                    decoration: const InputDecoration(labelText: 'حساب الاستلام'),
                    items: _accounts
                        .map(
                          (a) => DropdownMenuItem(
                            value: a['id'].toString(),
                            child: Text(
                              '${a['account_name'] ?? ''} • ${a['display_account'] ?? a['phone'] ?? a['branch'] ?? ''}',
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: _submitting
                        ? null
                        : (id) => setState(() {
                            _account = _accounts.firstWhere(
                              (a) => a['id'].toString() == id,
                            );
                          }),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _sender,
                    decoration: const InputDecoration(labelText: 'اسم المرسل'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _reference,
                    decoration: const InputDecoration(
                      labelText: 'رقم العملية/الحوالة - اختياري',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _notes,
                    maxLines: 2,
                    decoration: const InputDecoration(labelText: 'ملاحظات - اختياري'),
                  ),
                  const SizedBox(height: 16),
                  if (_proof != null)
                    ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: Image.file(
                        File(_proof!.path),
                        height: 210,
                        fit: BoxFit.contain,
                      ),
                    ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _submitting
                              ? null
                              : () => _pick(ImageSource.gallery),
                          icon: const Icon(Icons.photo_library_outlined),
                          label: const Text('اختيار صورة'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _submitting
                              ? null
                              : () => _pick(ImageSource.camera),
                          icon: const Icon(Icons.camera_alt_outlined),
                          label: const Text('الكاميرا'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  FilledButton.icon(
                    onPressed: _submitting ? null : _submit,
                    icon: const Icon(Icons.cloud_upload_outlined),
                    label: Text(
                      _submitting ? 'جاري الرفع...' : 'رفع إثبات فرق الدفع',
                    ),
                  ),
                ],
              ],
            ),
    );
  }

  void _message(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(text)));
  }
}
