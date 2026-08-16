import 'dart:io';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:image_gallery_saver_plus/image_gallery_saver_plus.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:provider/provider.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_config.dart';
import '../../core/theme/app_colors.dart';

class InvoiceDocumentScreen extends StatefulWidget {
  const InvoiceDocumentScreen({
    super.key,
    required this.invoiceId,
    required this.sourceTitle,
    this.sourceSubtitle,
  });

  final String invoiceId;
  final String sourceTitle;
  final String? sourceSubtitle;

  @override
  State<InvoiceDocumentScreen> createState() => _InvoiceDocumentScreenState();
}

class _InvoiceDocumentScreenState extends State<InvoiceDocumentScreen> {
  final _boundaryKey = GlobalKey();

  Map<String, dynamic>? _invoice;
  bool _loading = true;
  bool _exporting = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final data = await context.read<ApiClient>().get(
        '/customer/invoices/${widget.invoiceId}',
      );

      final root = data is Map
          ? Map<String, dynamic>.from(data)
          : <String, dynamic>{};
      final rawInvoice = root['invoice'];

      if (!mounted) return;

      setState(() {
        _invoice = rawInvoice is Map
            ? Map<String, dynamic>.from(rawInvoice)
            : root;
        _loading = false;
      });
    } catch (exception) {
      if (!mounted) return;
      setState(() => _loading = false);
      _message(exception.toString());
    }
  }

  Future<Uint8List> _capture() async {
    await Future<void>.delayed(const Duration(milliseconds: 120));

    final boundary =
        _boundaryKey.currentContext?.findRenderObject()
            as RenderRepaintBoundary?;

    if (boundary == null) {
      throw StateError('تعذر تجهيز صورة الوثيقة.');
    }

    final image = await boundary.toImage(pixelRatio: 3);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);

    if (bytes == null) {
      throw StateError('تعذر إنشاء صورة الوثيقة.');
    }

    return bytes.buffer.asUint8List();
  }

  Future<void> _saveGallery() async {
    setState(() => _exporting = true);

    try {
      final bytes = await _capture();

      await ImageGallerySaverPlus.saveImage(
        bytes,
        quality: 100,
        name:
            'Salora_${_documentNumber.replaceAll(RegExp(r'[^A-Za-z0-9_-]'), '_')}',
      );

      _message('تم حفظ الوثيقة داخل الاستديو.');
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  Future<void> _sharePdf() async {
    setState(() => _exporting = true);

    try {
      final bytes = await _capture();
      final document = pw.Document();
      final image = pw.MemoryImage(bytes);

      document.addPage(
        pw.Page(
          margin: const pw.EdgeInsets.all(24),
          build: (_) =>
              pw.Center(child: pw.Image(image, fit: pw.BoxFit.contain)),
        ),
      );

      final directory = await getTemporaryDirectory();
      final file = File(
        '${directory.path}/Salora_${_documentNumber.replaceAll(RegExp(r'[^A-Za-z0-9_-]'), '_')}.pdf',
      );

      await file.writeAsBytes(await document.save(), flush: true);

      await Share.shareXFiles([
        XFile(file.path),
      ], text: 'وثيقة دفع Salora رقم $_documentNumber');
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  String get _documentNumber {
    return (_invoice?['receipt_number'] ??
            _invoice?['invoice_number'] ??
            widget.invoiceId)
        .toString();
  }

  void _message(String value) {
    if (mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(value)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final invoice = _invoice;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          invoice?['status']?.toString() == 'paid'
              ? 'إيصال دفع معتمد'
              : 'إيصال الدفع قيد المراجعة',
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : invoice == null
          ? const Center(child: Text('لم يتم العثور على بيانات الوثيقة.'))
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  RepaintBoundary(
                    key: _boundaryKey,
                    child: PaymentDocumentCard(
                      invoice: invoice,
                      sourceTitle: widget.sourceTitle,
                      sourceSubtitle: widget.sourceSubtitle,
                      authorizationToken: context.read<ApiClient>().token,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: _exporting ? null : _saveGallery,
                          icon: const Icon(Icons.photo_library_outlined),
                          label: const Text('حفظ بالاستديو'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _exporting ? null : _sharePdf,
                          icon: const Icon(Icons.picture_as_pdf_outlined),
                          label: const Text('PDF ومشاركة'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    _helpText(invoice),
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      height: 1.45,
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  String _helpText(Map<String, dynamic> invoice) {
    final proof = invoice['latest_payment_proof'] is Map
        ? Map<String, dynamic>.from(invoice['latest_payment_proof'] as Map)
        : <String, dynamic>{};

    if (invoice['status'] == 'paid') {
      return 'هذه وثيقة دفع نهائية صدرت بعد قبول صاحب المبلغ لإيصال التحويل.';
    }

    if (proof['status'] == 'rejected') {
      return 'رفض صاحب المبلغ إيصال الدفع السابق. راجع السبب ثم ارفع إيصالاً جديداً.';
    }

    return 'هذه وثيقة تؤكد رفع إيصال الدفع وهو بانتظار تحقق صاحب المبلغ. لا تصبح نهائية إلا بعد القبول.';
  }
}

class PaymentDocumentCard extends StatelessWidget {
  const PaymentDocumentCard({
    super.key,
    required this.invoice,
    required this.sourceTitle,
    this.sourceSubtitle,
    this.authorizationToken,
  });

  final Map<String, dynamic> invoice;
  final String sourceTitle;
  final String? sourceSubtitle;
  final String? authorizationToken;

  Map<String, dynamic> get _proof {
    final raw = invoice['latest_payment_proof'];
    return raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
  }

  Map<String, dynamic> get _method {
    final raw = _proof['method'];
    return raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
  }

  Map<String, dynamic> get _account {
    final raw = _proof['payout_account'];
    return raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
  }

  bool get _paid => invoice['status']?.toString() == 'paid';
  bool get _hasProof => _proof.isNotEmpty && '${_proof['id'] ?? ''}'.isNotEmpty;
  bool get _rejected => _proof['status']?.toString() == 'rejected';

  String _value(dynamic value) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? '-' : text;
  }

  String _formatDateTime(dynamic raw) {
    final text = raw?.toString().trim() ?? '';
    if (text.isEmpty) return '-';
    final parsed = DateTime.tryParse(text);
    if (parsed == null) return text;
    final local = parsed.isUtc ? parsed.toLocal() : parsed;
    String two(int value) => value.toString().padLeft(2, '0');
    return '${local.year}-${two(local.month)}-${two(local.day)}\n${two(local.hour)}:${two(local.minute)}';
  }

  String _amount() {
    final currency = _value(invoice['currency']);
    final raw = currency == 'USD' ? invoice['total_usd'] : invoice['total_syp'];
    final number =
        double.tryParse(raw?.toString().replaceAll(',', '') ?? '') ?? 0;

    return currency == 'USD'
        ? '${number.toStringAsFixed(2)} USD'
        : '${number.toStringAsFixed(number % 1 == 0 ? 0 : 2)} ل.س';
  }

  String get _statusLabel {
    if (_paid) return 'مدفوع ومقبول';
    if (!_hasProof) return 'لم يتم رفع إيصال الدفع بعد';
    if (_rejected) return 'إيصال دفع مرفوض — مطلوب إعادة الرفع';
    return 'إيصال دفع مرفوع — بانتظار التحقق';
  }

  Color get _statusBackground {
    if (_paid) return const Color(0xffe8f5e9);
    if (_rejected) return const Color(0xffffebee);
    return const Color(0xfffff8e1);
  }

  Color get _statusForeground {
    if (_paid) return const Color(0xff1b5e20);
    if (_rejected) return const Color(0xffb71c1c);
    return const Color(0xff8d6e00);
  }

  @override
  Widget build(BuildContext context) {
    final logo = ApiConfig.resolveAssetUrl(
      _method['logo_url']?.toString() ?? _method['logo_path']?.toString(),
    );
    final verificationUrl = _value(invoice['verification_url']);
    final imageUrl = ApiConfig.resolveAssetUrl(
      (_proof['image_full_url'] ?? _proof['image_url'])?.toString(),
    );
    final headers =
        authorizationToken == null || authorizationToken!.trim().isEmpty
        ? const <String, String>{}
        : <String, String>{
            'Authorization': 'Bearer ${authorizationToken!.trim()}',
          };

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.all(22),
      child: DefaultTextStyle(
        style: const TextStyle(
          color: Colors.black87,
          height: 1.55,
          fontSize: 14,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const CircleAvatar(
                  radius: 25,
                  child: Text(
                    'S',
                    style: TextStyle(fontWeight: FontWeight.w900, fontSize: 22),
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
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        'إيصال دفع إلكتروني مرفوع',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ],
                  ),
                ),
                if (logo != null)
                  SizedBox(
                    width: 90,
                    height: 48,
                    child: Image.network(
                      logo,
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                    ),
                  ),
              ],
            ),
            const Divider(height: 30),
            _line(
              _paid ? 'رقم الإيصال' : 'رقم الوثيقة',
              invoice['receipt_number'] ?? invoice['invoice_number'],
              bold: true,
            ),
            _line('رقم الفاتورة', invoice['invoice_number']),
            _line('الصالة/الخدمة', sourceTitle),
            if ((sourceSubtitle ?? '').trim().isNotEmpty)
              _line('التفاصيل', sourceSubtitle),
            _line('اسم المحوّل', _proof['sender_name']),
            _line(
              'وسيلة الدفع',
              _method['name_ar'] ?? _proof['payment_method'],
            ),
            _line('المستلم', _account['account_name']),
            _line(
              'حساب المستلم',
              _account['display_account'] ??
                  _account['phone'] ??
                  _account['branch'],
            ),
            _line('تاريخ رفع الإيصال', _formatDateTime(_proof['uploaded_at'])),
            if (_paid)
              _line(
                'تاريخ قبول الدفعة',
                _formatDateTime(invoice['accepted_at'] ?? invoice['paid_at']),
              ),
            const Divider(height: 28),
            _line('المبلغ', _amount(), bold: true),
            if (_rejected)
              _line('سبب الرفض', _proof['rejection_reason'], bold: true),
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(11),
              decoration: BoxDecoration(
                color: _statusBackground,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                _statusLabel,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: _statusForeground,
                  fontWeight: FontWeight.w900,
                  fontSize: 16,
                ),
              ),
            ),
            if (imageUrl != null) ...[
              const SizedBox(height: 16),
              const Text(
                'صورة إيصال التحويل الأصلي',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              InkWell(
                onTap: () => showDialog<void>(
                  context: context,
                  builder: (dialogContext) => Dialog(
                    insetPadding: const EdgeInsets.all(12),
                    backgroundColor: Colors.black,
                    child: Stack(
                      children: [
                        Positioned.fill(
                          child: InteractiveViewer(
                            minScale: .7,
                            maxScale: 6,
                            child: Center(
                              child: Image.network(
                                imageUrl,
                                headers: headers,
                                fit: BoxFit.contain,
                              ),
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
                ),
                child: Container(
                  width: double.infinity,
                  constraints: const BoxConstraints(
                    minHeight: 180,
                    maxHeight: 420,
                  ),
                  color: const Color(0xfff7f7f7),
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      Image.network(
                        imageUrl,
                        headers: headers,
                        fit: BoxFit.contain,
                        errorBuilder: (_, __, ___) => const Center(
                          child: Padding(
                            padding: EdgeInsets.all(24),
                            child: Text('تعذر عرض إيصال الدفع.'),
                          ),
                        ),
                      ),
                      const PositionedDirectional(
                        end: 8,
                        bottom: 8,
                        child: Chip(
                          avatar: Icon(Icons.zoom_in, size: 18),
                          label: Text('اضغط للتكبير'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
            if (verificationUrl != '-') ...[
              const SizedBox(height: 18),
              Center(
                child: QrImageView(
                  data: verificationUrl,
                  version: QrVersions.auto,
                  size: 112,
                  backgroundColor: Colors.white,
                ),
              ),
              const SizedBox(height: 6),
              const Center(
                child: Text(
                  'امسح الرمز للتحقق من الحالة الحالية للوثيقة',
                  style: TextStyle(fontSize: 11),
                ),
              ),
            ],
            const Divider(height: 26),
            Text(
              _paid
                  ? 'تم اعتماد هذه الوثيقة بعد تحقق صاحب المبلغ من صورة التحويل.'
                  : 'هذه الوثيقة تؤكد أن العميل رفع إيصال الدفع، لكنها لا تعني قبول الدفع قبل مراجعة صاحب المبلغ.',
              style: const TextStyle(fontSize: 10.5),
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
            width: 135,
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
