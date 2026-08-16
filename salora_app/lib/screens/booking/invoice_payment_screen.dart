import 'dart:io';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:image_gallery_saver_plus/image_gallery_saver_plus.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_config.dart';
import '../../core/theme/app_colors.dart';
import 'invoice_document_screen.dart';

class InvoicePaymentScreen extends StatefulWidget {
  const InvoicePaymentScreen({
    super.key,
    required this.invoiceId,
    required this.sourceTitle,
    this.sourceSubtitle,
  });

  final String invoiceId;
  final String sourceTitle;
  final String? sourceSubtitle;

  @override
  State<InvoicePaymentScreen> createState() => _InvoicePaymentScreenState();
}

class _InvoicePaymentScreenState extends State<InvoicePaymentScreen> {
  final GlobalKey _claimKey = GlobalKey();
  final TextEditingController _senderController = TextEditingController();

  XFile? _receipt;
  List<Map<String, dynamic>> _methods = const [];
  Map<String, dynamic>? _method;
  Map<String, dynamic>? _account;
  Map<String, dynamic>? _invoice;

  bool _loading = true;
  bool _submitting = false;
  bool _claimCreated = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _senderController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      if (widget.invoiceId.trim().isEmpty) {
        throw const ApiException('مطالبة الدفع غير جاهزة بعد.');
      }

      final dynamic response = await context.read<ApiClient>().get(
        '/customer/invoices/${widget.invoiceId}',
      );
      final Map<String, dynamic> rawRoot = response is Map
          ? Map<String, dynamic>.from(response)
          : <String, dynamic>{};
      final Map<String, dynamic> root = rawRoot['data'] is Map
          ? Map<String, dynamic>.from(rawRoot['data'] as Map)
          : rawRoot;
      final dynamic rawInvoice = root['invoice'];
      final List<Map<String, dynamic>> options =
          (root['payment_options'] as List? ?? const [])
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .where((item) {
                final dynamic accounts = item['accounts'];
                return accounts is List && accounts.isNotEmpty;
              })
              .toList();

      final Map<String, dynamic>? firstMethod = options.isEmpty
          ? null
          : options.first;
      final List<Map<String, dynamic>> firstAccounts =
          (firstMethod?['accounts'] as List? ?? const [])
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList();

      if (!mounted) return;
      setState(() {
        _invoice = rawInvoice is Map
            ? Map<String, dynamic>.from(rawInvoice)
            : <String, dynamic>{};
        _methods = options;
        _method = firstMethod;
        _account = firstAccounts.isEmpty ? null : firstAccounts.first;
        _claimCreated = false;
        _loading = false;
      });
    } catch (exception) {
      if (!mounted) return;
      setState(() => _loading = false);
      _message(exception.toString());
    }
  }

  List<Map<String, dynamic>> get _accounts {
    return (_method?['accounts'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .where((item) => '${item['id'] ?? ''}'.isNotEmpty)
        .fold<Map<String, Map<String, dynamic>>>(
          <String, Map<String, dynamic>>{},
          (result, item) {
            result[item['id'].toString()] = item;
            return result;
          },
        )
        .values
        .toList();
  }

  String get _invoiceStatus =>
      (_invoice?['status'] ?? 'unpaid').toString().toLowerCase();

  Map<String, dynamic> get _latestProof {
    final dynamic raw = _invoice?['latest_payment_proof'];
    return raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
  }

  bool get _hasPendingOrAcceptedReceipt {
    final String proofStatus = (_latestProof['status'] ?? '')
        .toString()
        .toLowerCase();
    return _invoiceStatus == 'proof_uploaded' ||
        _invoiceStatus == 'paid' ||
        proofStatus == 'pending' ||
        proofStatus == 'approved';
  }

  void _selectMethod(String id) {
    final Map<String, dynamic> selected = _methods.firstWhere(
      (item) => item['id']?.toString() == id,
    );
    final List<Map<String, dynamic>> accounts =
        (selected['accounts'] as List? ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();

    setState(() {
      _method = selected;
      _account = accounts.isEmpty ? null : accounts.first;
      _claimCreated = false;
      _receipt = null;
    });
  }

  void _selectAccount(String? id) {
    final List<Map<String, dynamic>> accounts = _accounts;
    setState(() {
      _account = id == null
          ? null
          : accounts.firstWhere((item) => item['id'].toString() == id);
      _claimCreated = false;
      _receipt = null;
    });
  }

  void _createClaim() {
    if (_method == null || _account == null) {
      _message('اختر وسيلة الدفع وحساب الاستلام أولاً.');
      return;
    }
    setState(() => _claimCreated = true);
  }

  Future<void> _pick(ImageSource source) async {
    final XFile? picked = await ImagePicker().pickImage(
      source: source,
      imageQuality: 90,
      maxWidth: 2200,
    );
    if (picked != null && mounted) {
      setState(() => _receipt = picked);
    }
  }

  Future<void> _openLocalReceipt() async {
    final XFile? receipt = _receipt;
    if (receipt == null || !mounted) return;
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => Dialog(
        insetPadding: const EdgeInsets.all(12),
        backgroundColor: Colors.black,
        child: Stack(
          children: [
            Positioned.fill(
              child: InteractiveViewer(
                minScale: 0.7,
                maxScale: 6,
                child: Center(
                  child: Image.file(File(receipt.path), fit: BoxFit.contain),
                ),
              ),
            ),
            PositionedDirectional(
              top: 8,
              end: 8,
              child: IconButton.filled(
                onPressed: () => Navigator.pop(dialogContext),
                icon: const Icon(Icons.close),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<Uint8List?> _captureClaim() async {
    final RenderRepaintBoundary? boundary =
        _claimKey.currentContext?.findRenderObject() as RenderRepaintBoundary?;
    if (boundary == null) return null;
    final ui.Image image = await boundary.toImage(pixelRatio: 3);
    final ByteData? data = await image.toByteData(
      format: ui.ImageByteFormat.png,
    );
    return data?.buffer.asUint8List();
  }

  Future<void> _saveClaim() async {
    final Uint8List? bytes = await _captureClaim();
    if (bytes == null) return;
    await ImageGallerySaverPlus.saveImage(
      bytes,
      quality: 100,
      name:
          'salora_payment_claim_${_invoice?['invoice_number'] ?? widget.invoiceId}',
    );
    _message('تم حفظ مطالبة الدفع في الاستديو.');
  }

  Future<void> _shareClaimPdf() async {
    final Uint8List? bytes = await _captureClaim();
    if (bytes == null) return;

    final pw.Document document = pw.Document();
    final pw.MemoryImage image = pw.MemoryImage(bytes);
    document.addPage(
      pw.Page(
        build: (_) => pw.Center(child: pw.Image(image, fit: pw.BoxFit.contain)),
      ),
    );

    final Directory directory = await getTemporaryDirectory();
    final File file = File(
      '${directory.path}/Salora_payment_claim_${widget.invoiceId}.pdf',
    );
    await file.writeAsBytes(await document.save(), flush: true);
    await Share.shareXFiles(<XFile>[
      XFile(file.path),
    ], text: 'مطالبة دفع Salora');
  }

  Future<void> _submit() async {
    final XFile? receipt = _receipt;
    final Map<String, dynamic>? method = _method;
    final Map<String, dynamic>? account = _account;

    if (!_claimCreated) {
      _message('أنشئ مطالبة الدفع أولاً.');
      return;
    }
    if (receipt == null || method == null || account == null) {
      _message('اختر حساب الاستلام وارفع صورة إيصال الدفع.');
      return;
    }
    if (_senderController.text.trim().isEmpty) {
      _message('اكتب اسم الشخص الذي تم التحويل منه.');
      return;
    }

    setState(() => _submitting = true);
    try {
      await context.read<ApiClient>().multipartPost(
        '/customer/invoices/${widget.invoiceId}/payment-proof',
        fields: <String, String>{
          'payment_method_id': '${method['id']}',
          'payout_account_id': '${account['id']}',
          'sender_name': _senderController.text.trim(),
          'transferred_at': DateTime.now().toIso8601String(),
        },
        fileField: 'image',
        file: File(receipt.path),
      );

      if (!mounted) return;
      _message('تم رفع إيصال الدفع وهو بانتظار مراجعة صاحب المبلغ.');
      await Navigator.pushReplacement(
        context,
        MaterialPageRoute<void>(
          builder: (_) => InvoiceDocumentScreen(
            invoiceId: widget.invoiceId,
            sourceTitle: widget.sourceTitle,
            sourceSubtitle: widget.sourceSubtitle,
          ),
        ),
      );
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _message(String value) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (_hasPendingOrAcceptedReceipt) {
      return Scaffold(
        appBar: AppBar(title: const Text('حالة إيصال الدفع')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  _invoiceStatus == 'paid'
                      ? Icons.verified_rounded
                      : Icons.hourglass_top_rounded,
                  size: 72,
                  color: _invoiceStatus == 'paid'
                      ? Colors.green
                      : Colors.orange,
                ),
                const SizedBox(height: 16),
                Text(
                  _invoiceStatus == 'paid'
                      ? 'إيصال الدفع مقبول والحجز مؤكد.'
                      : 'إيصال الدفع قيد المراجعة.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 14),
                FilledButton.icon(
                  onPressed: () => Navigator.pushReplacement(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) => InvoiceDocumentScreen(
                        invoiceId: widget.invoiceId,
                        sourceTitle: widget.sourceTitle,
                        sourceSubtitle: widget.sourceSubtitle,
                      ),
                    ),
                  ),
                  icon: const Icon(Icons.receipt_long_outlined),
                  label: const Text('عرض وثيقة الدفع'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final List<Map<String, dynamic>> accounts = _accounts;
    final String? selectedMethodId = _method?['id']?.toString();
    final String? selectedAccountId = _account?['id']?.toString();
    final Set<String> accountIds = accounts
        .map((item) => item['id'].toString())
        .toSet();
    final String? safeAccountId = accountIds.contains(selectedAccountId)
        ? selectedAccountId
        : null;

    return Scaffold(
      appBar: AppBar(title: const Text('الدفع ورفع إيصال الدفع')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _FlowHeader(claimCreated: _claimCreated),
          const SizedBox(height: 16),
          const Text(
            '1. اختر وسيلة الدفع',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 5),
          const Text(
            'اختر وسيلة الدفع وحساب المستلم. بعد تثبيت الاختيار يصدر النظام مطالبة دفع رسمية بالمبلغ والحساب.',
            style: TextStyle(color: AppColors.textSecondary, height: 1.5),
          ),
          const SizedBox(height: 14),
          if (_methods.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(18),
                child: Text(
                  'لا يوجد حساب استلام فعال لهذه الفاتورة. تواصل مع صاحب المبلغ.',
                ),
              ),
            )
          else
            ..._methods.map(
              (item) => Card(
                child: RadioListTile<String>(
                  value: item['id'].toString(),
                  groupValue: selectedMethodId,
                  onChanged: _submitting || _claimCreated
                      ? null
                      : (value) {
                          if (value != null) _selectMethod(value);
                        },
                  title: Row(
                    children: [
                      if ((item['logo_url'] ?? item['logo_path']) != null)
                        Image.network(
                          ApiConfig.resolveAssetUrl(
                                (item['logo_url'] ?? item['logo_path'])
                                    .toString(),
                              ) ??
                              '',
                          width: 46,
                          height: 32,
                          errorBuilder: (_, __, ___) =>
                              const Icon(Icons.account_balance_wallet),
                        ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(item['name_ar']?.toString() ?? 'وسيلة دفع'),
                      ),
                    ],
                  ),
                  subtitle: Text(item['instructions']?.toString() ?? ''),
                ),
              ),
            ),
          if (accounts.isNotEmpty) ...[
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              key: ValueKey<String>('account-$selectedMethodId'),
              initialValue: safeAccountId,
              items: accounts
                  .map(
                    (item) => DropdownMenuItem<String>(
                      value: item['id'].toString(),
                      child: Text(
                        '${item['account_name'] ?? ''} • '
                        '${item['display_account'] ?? item['phone'] ?? item['branch'] ?? ''}',
                      ),
                    ),
                  )
                  .toList(),
              onChanged: _submitting || _claimCreated ? null : _selectAccount,
              decoration: const InputDecoration(labelText: 'حساب الاستلام'),
            ),
          ],
          const SizedBox(height: 14),
          if (!_claimCreated)
            FilledButton.icon(
              onPressed: _methods.isEmpty || _account == null
                  ? null
                  : _createClaim,
              icon: const Icon(Icons.description_outlined),
              label: const Text('إنشاء مطالبة الدفع والمتابعة'),
            )
          else
            OutlinedButton.icon(
              onPressed: _submitting
                  ? null
                  : () => setState(() {
                      _claimCreated = false;
                      _receipt = null;
                    }),
              icon: const Icon(Icons.edit_outlined),
              label: const Text('تغيير وسيلة الدفع أو الحساب'),
            ),
          if (_claimCreated) ...[
            const SizedBox(height: 22),
            const Text(
              '2. مطالبة الدفع الرسمية',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            RepaintBoundary(
              key: _claimKey,
              child: PaymentClaimCard(
                invoice: _invoice,
                sourceTitle: widget.sourceTitle,
                sourceSubtitle: widget.sourceSubtitle,
                method: _method,
                account: _account,
              ),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: _saveClaim,
                  icon: const Icon(Icons.photo_library_outlined),
                  label: const Text('حفظ المطالبة'),
                ),
                OutlinedButton.icon(
                  onPressed: _shareClaimPdf,
                  icon: const Icon(Icons.picture_as_pdf_outlined),
                  label: const Text('PDF ومشاركة'),
                ),
              ],
            ),
            const Divider(height: 34),
            const Text(
              '3. ارفع إيصال الدفع',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _senderController,
              decoration: const InputDecoration(
                labelText: 'اسم الشخص الذي تم التحويل منه *',
              ),
            ),
            const SizedBox(height: 14),
            InkWell(
              onTap: _receipt == null ? null : _openLocalReceipt,
              borderRadius: BorderRadius.circular(20),
              child: Container(
                height: 250,
                width: double.infinity,
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  border: Border.all(color: Colors.white24),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: _receipt == null
                    ? const Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.receipt_long_outlined, size: 48),
                          SizedBox(height: 10),
                          Padding(
                            padding: EdgeInsets.symmetric(horizontal: 22),
                            child: Text(
                              'ارفع صورة إيصال الدفع الأصلي الصادر عن جهة الدفع',
                              textAlign: TextAlign.center,
                            ),
                          ),
                        ],
                      )
                    : ClipRRect(
                        borderRadius: BorderRadius.circular(20),
                        child: Stack(
                          fit: StackFit.expand,
                          children: [
                            Image.file(
                              File(_receipt!.path),
                              fit: BoxFit.contain,
                            ),
                            const PositionedDirectional(
                              end: 10,
                              bottom: 10,
                              child: Chip(
                                avatar: Icon(Icons.zoom_in, size: 18),
                                label: Text('اضغط للتكبير'),
                              ),
                            ),
                          ],
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _submitting
                        ? null
                        : () => _pick(ImageSource.gallery),
                    icon: const Icon(Icons.photo_library),
                    label: const Text('المعرض'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _submitting
                        ? null
                        : () => _pick(ImageSource.camera),
                    icon: const Icon(Icons.camera_alt),
                    label: const Text('الكاميرا'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed:
                  _submitting ||
                      _receipt == null ||
                      _account == null ||
                      _methods.isEmpty
                  ? null
                  : _submit,
              icon: const Icon(Icons.cloud_upload_outlined),
              label: Text(
                _submitting
                    ? 'جاري رفع إيصال الدفع...'
                    : 'رفع إيصال الدفع للمراجعة',
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _FlowHeader extends StatelessWidget {
  const _FlowHeader({required this.claimCreated});

  final bool claimCreated;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          _StepBadge(number: '1', text: 'اختيار', done: claimCreated),
          const Expanded(child: Divider()),
          _StepBadge(number: '2', text: 'مطالبة', done: claimCreated),
          const Expanded(child: Divider()),
          const _StepBadge(number: '3', text: 'إيصال', done: false),
          const Expanded(child: Divider()),
          const _StepBadge(number: '4', text: 'مراجعة', done: false),
        ],
      ),
    );
  }
}

class _StepBadge extends StatelessWidget {
  const _StepBadge({
    required this.number,
    required this.text,
    required this.done,
  });

  final String number;
  final String text;
  final bool done;

  @override
  Widget build(BuildContext context) {
    final Color color = done
        ? Colors.green
        : Theme.of(context).colorScheme.primary;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        CircleAvatar(
          radius: 16,
          backgroundColor: color.withValues(alpha: .15),
          foregroundColor: color,
          child: done
              ? const Icon(Icons.check, size: 18)
              : Text(
                  number,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
        ),
        const SizedBox(height: 5),
        Text(text, style: const TextStyle(fontSize: 11)),
      ],
    );
  }
}

class PaymentClaimCard extends StatelessWidget {
  const PaymentClaimCard({
    super.key,
    required this.invoice,
    required this.sourceTitle,
    this.sourceSubtitle,
    this.method,
    this.account,
  });

  final Map<String, dynamic>? invoice;
  final String sourceTitle;
  final String? sourceSubtitle;
  final Map<String, dynamic>? method;
  final Map<String, dynamic>? account;

  String _value(dynamic value) {
    final String text = value?.toString().trim() ?? '';
    return text.isEmpty ? '-' : text;
  }

  String _amount() {
    final Map<String, dynamic> data = invoice ?? const <String, dynamic>{};
    final String currency = _value(data['currency']);
    final dynamic raw = currency == 'USD'
        ? data['total_usd']
        : data['total_syp'];
    final double number =
        double.tryParse(raw?.toString().replaceAll(',', '') ?? '') ?? 0;
    return currency == 'USD'
        ? '${number.toStringAsFixed(2)} USD'
        : '${number.toStringAsFixed(number % 1 == 0 ? 0 : 2)} ل.س';
  }

  @override
  Widget build(BuildContext context) {
    final String? logo = ApiConfig.resolveAssetUrl(
      method?['logo_url']?.toString() ?? method?['logo_path']?.toString(),
    );

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.all(22),
      child: DefaultTextStyle(
        style: const TextStyle(
          color: Colors.black87,
          height: 1.6,
          fontSize: 14,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const CircleAvatar(
                  radius: 24,
                  child: Text(
                    'S',
                    style: TextStyle(fontSize: 21, fontWeight: FontWeight.w900),
                  ),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'SALORA',
                        style: TextStyle(
                          fontSize: 21,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        'مطالبة دفع رسمية — غير مدفوعة',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ],
                  ),
                ),
                if (logo != null)
                  SizedBox(
                    width: 82,
                    height: 42,
                    child: Image.network(
                      logo,
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                    ),
                  ),
              ],
            ),
            const Divider(height: 30),
            _line('رقم المطالبة', invoice?['invoice_number'], bold: true),
            _line('الصالة/الخدمة', sourceTitle),
            if ((sourceSubtitle ?? '').trim().isNotEmpty)
              _line('التفاصيل', sourceSubtitle),
            _line('وسيلة الدفع', method?['name_ar']),
            _line('اسم المستلم', account?['account_name']),
            _line(
              'حساب الاستلام',
              account?['display_account'] ??
                  account?['phone'] ??
                  account?['branch'],
            ),
            const Divider(height: 28),
            _line('المبلغ المطلوب', _amount(), bold: true),
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xfffff8e1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Text(
                'ادفع وارفع إيصال الدفع في أي وقت ما دام الحجز فعالاً. لا توجد مهلة للدفع، وهذه المطالبة لا تثبت أن الدفع تم.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Color(0xff7a5c00),
                  fontWeight: FontWeight.w700,
                  fontSize: 11,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _line(String label, dynamic raw, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 125,
            child: Text(label, style: const TextStyle(color: Colors.black54)),
          ),
          Expanded(
            child: Text(
              _value(raw),
              textAlign: TextAlign.end,
              style: TextStyle(
                fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
