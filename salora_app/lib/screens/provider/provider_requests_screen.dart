import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../providers/app_settings_provider.dart';
import '../../providers/provider_account_provider.dart';
import 'business_finance_screen.dart';

class ProviderRequestsScreen extends StatefulWidget {
  const ProviderRequestsScreen({super.key});

  @override
  State<ProviderRequestsScreen> createState() => _ProviderRequestsScreenState();
}

class _ProviderRequestsScreenState extends State<ProviderRequestsScreen> {
  String? _busyId;

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ProviderAccountProvider>();
    final settings = context.watch<AppSettingsProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('طلبات العملاء'),
        actions: [
          IconButton(
            tooltip: 'مراجعة الدفعات',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => const BusinessFinanceScreen(initialTab: 1),
              ),
            ),
            icon: const Icon(Icons.payments_outlined),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: context.read<ProviderAccountProvider>().load,
        child: provider.isLoading && provider.requests.isEmpty
            ? ListView(children: const [SizedBox(height: 220), Center(child: CircularProgressIndicator())])
            : provider.error != null && provider.requests.isEmpty
                ? ListView(
                    padding: const EdgeInsets.all(24),
                    children: [
                      Text(provider.error!, textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      ElevatedButton(
                        onPressed: context.read<ProviderAccountProvider>().load,
                        child: const Text('إعادة المحاولة'),
                      ),
                    ],
                  )
                : provider.requests.isEmpty
                    ? ListView(
                        padding: const EdgeInsets.all(24),
                        children: const [Text('لا توجد طلبات خدمة حتى الآن.')],
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: provider.requests.length,
                        itemBuilder: (_, index) {
                          final request = provider.requests[index];
                          final busy = _busyId == request.id;

                          return Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: AppColors.surface,
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        request.serviceName,
                                        style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17),
                                      ),
                                    ),
                                    _pill(request.statusLabel),
                                  ],
                                ),
                                const SizedBox(height: 6),
                                Text('${request.venueName} • ${request.customerName}', style: const TextStyle(color: AppColors.textSecondary)),
                                Text(
                                  '${request.eventDate.year}-${request.eventDate.month}-${request.eventDate.day} • ${request.startTime} - ${request.endTime}',
                                  style: const TextStyle(color: AppColors.textSecondary),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  '${settings.formatPrice(request.priceSyp)} • ${request.paymentStatusLabel}',
                                  style: const TextStyle(color: AppColors.success, fontWeight: FontWeight.w900),
                                ),
                                if (request.cancellationStatus.isNotEmpty) ...[
                                  const SizedBox(height: 6),
                                  Text(
                                    request.cancellationStatus == 'waiting_refund'
                                        ? 'الاسترداد المطلوب: ${request.refundPercentage.toStringAsFixed(0)}% (${settings.formatPrice(request.refundedSyp)})'
                                        : 'حالة الإلغاء: ${request.cancellationStatus}',
                                    style: const TextStyle(color: AppColors.warning, fontWeight: FontWeight.w700),
                                  ),
                                ],
                                if (request.status == 'pending') ...[
                                  const SizedBox(height: 10),
                                  Row(
                                    children: [
                                      Expanded(
                                        child: ElevatedButton(
                                          onPressed: busy ? null : () => _accept(request.id),
                                          child: busy
                                              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                                              : const Text('قبول'),
                                        ),
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: OutlinedButton(
                                          onPressed: busy ? null : () => _reject(request.id),
                                          child: const Text('رفض'),
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                                if (request.canReviewPayment) ...[
                                  const SizedBox(height: 10),
                                  FilledButton.tonalIcon(
                                    onPressed: () => Navigator.push(
                                      context,
                                      MaterialPageRoute(builder: (_) => const BusinessFinanceScreen(initialTab: 1)),
                                    ),
                                    icon: const Icon(Icons.verified_outlined),
                                    label: const Text('مراجعة إيصال الدفع'),
                                  ),
                                ],
                                if (request.canCancelByProvider) ...[
                                  const SizedBox(height: 10),
                                  OutlinedButton.icon(
                                    onPressed: busy ? null : () => _cancel(request.id),
                                    icon: const Icon(Icons.cancel_outlined),
                                    label: const Text('إلغاء الخدمة'),
                                  ),
                                ],
                                if (request.canConfirmRefund) ...[
                                  const SizedBox(height: 8),
                                  FilledButton.tonalIcon(
                                    onPressed: busy ? null : () => _confirmRefund(request.id),
                                    icon: const Icon(Icons.assignment_turned_in_outlined),
                                    label: const Text('تأكيد رد المبلغ للعميل'),
                                  ),
                                ],
                              ],
                            ),
                          );
                        },
                      ),
      ),
    );
  }

  Future<void> _accept(String id) async {
    setState(() => _busyId = id);
    try {
      await context.read<ProviderAccountProvider>().acceptRequest(id);
      _message('تم قبول الطلب وإصدار مطالبة دفع للعميل.');
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _reject(String id) async {
    var reason = '';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('رفض طلب الخدمة'),
        content: TextField(
          minLines: 2,
          maxLines: 4,
          onChanged: (value) => reason = value.trim(),
          decoration: const InputDecoration(labelText: 'سبب الرفض'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('تراجع')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('تأكيد الرفض')),
        ],
      ),
    );

    if (confirmed != true || reason.isEmpty || !mounted) return;

    setState(() => _busyId = id);
    try {
      await context.read<ProviderAccountProvider>().rejectRequest(id, reply: reason);
      _message('تم رفض الطلب وإبلاغ العميل.');
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _cancel(String id) async {
    var reason = '';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('إلغاء خدمة مقدم الخدمة'),
        content: TextField(
          minLines: 2,
          maxLines: 4,
          onChanged: (value) => reason = value.trim(),
          decoration: const InputDecoration(labelText: 'سبب الإلغاء'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('تراجع')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('تأكيد الإلغاء')),        ],
      ),
    );

    if (confirmed != true || reason.isEmpty || !mounted) return;

    setState(() => _busyId = id);
    try {
      await context.read<ProviderAccountProvider>().cancelRequest(id, reason: reason);
      _message('تم إلغاء الخدمة. إذا كانت مدفوعة فسيبقى الطلب بانتظار تأكيد رد المبلغ.');
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _confirmRefund(String id) async {
    setState(() => _busyId = id);
    try {
      await context.read<ProviderAccountProvider>().confirmRefund(id);
      _message('تم تأكيد تنفيذ الاسترداد وإشعار العميل.');
    } catch (exception) {
      _message(exception.toString());
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  void _message(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  Widget _pill(String text) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: AppColors.primary.withValues(alpha: .12),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(text, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800)),
      );
}
