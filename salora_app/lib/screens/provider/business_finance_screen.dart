import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_config.dart';
import '../../core/theme/app_colors.dart';

class BusinessFinanceScreen extends StatefulWidget {
  const BusinessFinanceScreen({super.key, this.initialTab = 0});

  final int initialTab;

  @override
  State<BusinessFinanceScreen> createState() => _BusinessFinanceScreenState();
}

class _BusinessFinanceScreenState extends State<BusinessFinanceScreen>
    with SingleTickerProviderStateMixin {
  late final TabController tabs;
  List<Map<String, dynamic>> methods = const [];
  List<Map<String, dynamic>> accounts = const [];
  List<Map<String, dynamic>> payments = const [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    tabs = TabController(
      length: 2,
      initialIndex: widget.initialTab.clamp(0, 1),
      vsync: this,
    );
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  Future<void> load() async {
    if (mounted) setState(() => loading = true);
    try {
      final api = context.read<ApiClient>();
      final result = await Future.wait([
        api.get('/business/payment-methods'),
        api.get('/business/payout-accounts'),
        api.get('/business/payments'),
      ]);
      if (!mounted) return;
      setState(() {
        methods = _list(result[0]);
        accounts = _list(result[1]);
        payments = _list(result[2]);
      });
    } catch (exception) {
      message(exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  List<Map<String, dynamic>> _list(dynamic value) => value is List
      ? value
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList()
      : const [];

  void message(String value) {
    if (mounted)
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(value)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('الدفعات وحسابات الاستلام'),
        bottom: TabBar(
          controller: tabs,
          tabs: const [
            Tab(text: 'حسابات الاستلام'),
            Tab(text: 'مراجعة الدفعات'),
          ],
        ),
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : TabBarView(
              controller: tabs,
              children: [
                RefreshIndicator(onRefresh: load, child: _accountsTab()),
                RefreshIndicator(onRefresh: load, child: _paymentsTab()),
              ],
            ),
      floatingActionButton: loading
          ? null
          : FloatingActionButton.extended(
              onPressed: showAddAccount,
              icon: const Icon(Icons.add_card),
              label: const Text('حساب استلام'),
            ),
    );
  }

  Widget _accountsTab() {
    if (accounts.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: const [
          SizedBox(height: 130),
          Icon(Icons.account_balance_wallet_outlined, size: 64),
          SizedBox(height: 12),
          Text(
            'أضف حساب شام كاش أو سيريتل كاش أو بيانات حوالة الهرم ليستطيع العميل الدفع.',
            textAlign: TextAlign.center,
          ),
        ],
      );
    }
    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: accounts.length,
      itemBuilder: (_, index) {
        final account = accounts[index];
        final method = account['method'] is Map
            ? Map<String, dynamic>.from(account['method'] as Map)
            : <String, dynamic>{};
        final logo = ApiConfig.resolveAssetUrl(
          method['logo_url']?.toString() ?? method['logo_path']?.toString(),
        );
        return Card(
          child: ListTile(
            leading: logo == null
                ? const CircleAvatar(child: Icon(Icons.wallet_outlined))
                : CircleAvatar(
                    backgroundColor: Colors.white,
                    backgroundImage: NetworkImage(logo),
                  ),
            title: Text(
              (method['name_ar'] ?? 'وسيلة دفع').toString(),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            subtitle: Text(
              '${account['account_name'] ?? ''}\n${account['display_account'] ?? ''}',
            ),
            isThreeLine: true,
            trailing: PopupMenuButton<String>(
              onSelected: (value) async {
                if (value == 'disable') {
                  await context.read<ApiClient>().delete(
                    '/business/payout-accounts/${account['id']}',
                  );
                  await load();
                }
              },
              itemBuilder: (_) => const [
                PopupMenuItem(value: 'disable', child: Text('تعطيل الحساب')),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _paymentsTab() {
    if (payments.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: const [
          SizedBox(height: 130),
          Icon(Icons.receipt_long_outlined, size: 64),
          SizedBox(height: 12),
          Text(
            'لا توجد إيصالات دفع بانتظار المراجعة.',
            textAlign: TextAlign.center,
          ),
        ],
      );
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: payments.length,
      itemBuilder: (_, index) {
        final payment = payments[index];
        final invoice = payment['invoice'] is Map
            ? Map<String, dynamic>.from(payment['invoice'] as Map)
            : <String, dynamic>{};
        final customer = invoice['customer'] is Map
            ? Map<String, dynamic>.from(invoice['customer'] as Map)
            : <String, dynamic>{};
        final status = (payment['status'] ?? 'pending')
            .toString()
            .toLowerCase();
        final receiptUrl = _receiptUrl(payment);
        final statusColor = _paymentStatusColor(status);

        return Container(
          margin: const EdgeInsets.only(bottom: 16),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: statusColor.withValues(alpha: .35)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      (invoice['invoice_number'] ?? 'فاتورة').toString(),
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 20,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: .14),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      _paymentStatusLabel(status),
                      style: TextStyle(
                        color: statusColor,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              _paymentLine('العميل', customer['name'] ?? '-'),
              _paymentLine(
                'المبلغ',
                '${invoice['total_syp'] ?? payment['amount_syp'] ?? 0} ل.س',
              ),
              _paymentLine('اسم المحوّل', payment['sender_name'] ?? '-'),
              _paymentLine(
                'نوع الدفعة',
                invoice['source_type'] == 'provider_service'
                    ? 'خدمة مقدم'
                    : 'حجز صالة',
              ),
              if (payment['uploaded_at'] != null)
                _paymentLine('تاريخ رفع الإيصال', payment['uploaded_at']),
              const SizedBox(height: 12),
              if (receiptUrl != null) ...[
                InkWell(
                  onTap: () => _openReceiptImage(receiptUrl),
                  borderRadius: BorderRadius.circular(16),
                  child: Container(
                    width: double.infinity,
                    height: 300,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: Colors.white12),
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: Image.network(
                        receiptUrl,
                        headers: _imageHeaders,
                        fit: BoxFit.contain,
                        errorBuilder: (_, __, ___) =>
                            const Center(child: Text('تعذر عرض إيصال الدفع.')),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: () => _openReceiptImage(receiptUrl),
                  icon: const Icon(Icons.zoom_in_rounded),
                  label: const Text('عرض إيصال الدفع بالحجم الكامل'),
                ),
              ] else
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.orange.withValues(alpha: .08),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Text(
                    'لا توجد صورة إيصال مرتبطة بهذه الدفعة.',
                    textAlign: TextAlign.center,
                  ),
                ),
              if (_isReviewableStatus(status)) ...[
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: receiptUrl == null
                            ? null
                            : () => review(payment, true),
                        icon: const Icon(Icons.check_circle_outline),
                        label: const Text('قبول الإيصال'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => review(payment, false),
                        icon: const Icon(Icons.cancel_outlined),
                        label: const Text('رفض الإيصال'),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        );
      },
    );
  }

  Map<String, String>? get _imageHeaders {
    final token = context.read<ApiClient>().token;
    if (token == null || token.trim().isEmpty) return null;
    return <String, String>{
      'Authorization': 'Bearer ${token.trim()}',
      'Accept': 'image/*',
    };
  }

  String? _receiptUrl(Map<String, dynamic> payment) {
    final raw =
        payment['image_full_url'] ??
        payment['receipt_full_url'] ??
        payment['proof_full_url'] ??
        payment['image_url'];
    final value = raw?.toString().trim();
    if (value == null || value.isEmpty) return null;
    return ApiConfig.resolveAssetUrl(value) ?? value;
  }

  bool _isReviewableStatus(String status) => <String>{
    'pending',
    'proof_uploaded',
    'payment_under_review',
    'under_review',
  }.contains(status);

  String _paymentStatusLabel(String status) {
    switch (status) {
      case 'approved':
      case 'accepted':
      case 'paid':
        return 'مقبول';
      case 'rejected':
      case 'declined':
        return 'مرفوض';
      default:
        return 'بانتظار المراجعة';
    }
  }

  Color _paymentStatusColor(String status) {
    switch (status) {
      case 'approved':
      case 'accepted':
      case 'paid':
        return Colors.green;
      case 'rejected':
      case 'declined':
        return Colors.red;
      default:
        return Colors.amber.shade800;
    }
  }

  Widget _paymentLine(String label, dynamic value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: AppColors.textSecondary)),
          const Spacer(),
          Flexible(
            child: Text(
              value?.toString() ?? '-',
              textAlign: TextAlign.end,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openReceiptImage(String imageUrl) async {
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => Dialog.fullscreen(
        backgroundColor: Colors.black,
        child: SafeArea(
          child: Stack(
            children: [
              Positioned.fill(
                child: InteractiveViewer(
                  minScale: .7,
                  maxScale: 6,
                  child: Center(
                    child: Image.network(
                      imageUrl,
                      headers: _imageHeaders,
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => const Text(
                        'تعذر عرض إيصال الدفع.',
                        style: TextStyle(color: Colors.white),
                      ),
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
    );
  }

  Future<void> showAddAccount() async {
    if (methods.isEmpty) {
      message('لا توجد وسائل دفع فعالة.');
      return;
    }
    String methodId = methods.first['id'].toString();
    final name = TextEditingController();
    final number = TextEditingController();
    final phone = TextEditingController();
    final city = TextEditingController();
    final branch = TextEditingController();
    final notes = TextEditingController();
    bool isDefault = accounts.isEmpty;
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setLocal) => AlertDialog(
          title: const Text('إضافة حساب استلام'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: methodId,
                  items: methods
                      .map(
                        (method) => DropdownMenuItem(
                          value: method['id'].toString(),
                          child: Text((method['name_ar'] ?? '').toString()),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => methodId = value ?? methodId,
                  decoration: const InputDecoration(labelText: 'وسيلة الدفع'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: name,
                  decoration: const InputDecoration(
                    labelText: 'اسم صاحب الحساب/المستلم',
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: number,
                  decoration: const InputDecoration(
                    labelText: 'رقم المحفظة أو رقم الحوالة',
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(labelText: 'رقم الهاتف'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: city,
                  decoration: const InputDecoration(labelText: 'المدينة'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: branch,
                  decoration: const InputDecoration(
                    labelText: 'الفرع للهرم (اختياري)',
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: notes,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'تعليمات للعميل',
                  ),
                ),
                CheckboxListTile(
                  value: isDefault,
                  onChanged: (value) =>
                      setLocal(() => isDefault = value ?? false),
                  title: const Text('الحساب الافتراضي'),
                  contentPadding: EdgeInsets.zero,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('إلغاء'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('حفظ'),
            ),
          ],
        ),
      ),
    );
    if (accepted != true) return;
    if (name.text.trim().isEmpty) {
      message('اسم صاحب الحساب مطلوب.');
      return;
    }
    try {
      await context.read<ApiClient>().post('/business/payout-accounts', {
        'payment_method_id': int.parse(methodId),
        'account_name': name.text.trim(),
        'account_number': number.text.trim().isEmpty
            ? null
            : number.text.trim(),
        'phone': phone.text.trim().isEmpty ? null : phone.text.trim(),
        'city': city.text.trim().isEmpty ? null : city.text.trim(),
        'branch': branch.text.trim().isEmpty ? null : branch.text.trim(),
        'instructions': notes.text.trim().isEmpty ? null : notes.text.trim(),
        'is_default': isDefault,
        'is_active': true,
      });
      await load();
      message('تم حفظ حساب الاستلام.');
    } catch (exception) {
      message(exception.toString());
    } finally {
      name.dispose();
      number.dispose();
      phone.dispose();
      city.dispose();
      branch.dispose();
      notes.dispose();
    }
  }

  Future<void> review(Map<String, dynamic> payment, bool approve) async {
    String reason = '';
    if (!approve) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('رفض إيصال الدفع'),
          content: TextField(
            maxLines: 3,
            onChanged: (value) => reason = value.trim(),
            decoration: const InputDecoration(labelText: 'سبب الرفض *'),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('تراجع'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('رفض'),
            ),
          ],
        ),
      );
      if (confirmed != true || reason.isEmpty) return;
    }
    try {
      final id = payment['id'];
      if (approve) {
        await context.read<ApiClient>().post(
          '/business/payments/$id/approve',
          const {},
        );
      } else {
        await context.read<ApiClient>().post('/business/payments/$id/reject', {
          'reason': reason,
        });
      }
      await load();
      message(
        approve
            ? 'تم قبول الدفعة وإصدار الإيصال.'
            : 'تم رفض إيصال الدفع مع حفظ السبب.',
      );
    } catch (exception) {
      message(exception.toString());
    }
  }
}
