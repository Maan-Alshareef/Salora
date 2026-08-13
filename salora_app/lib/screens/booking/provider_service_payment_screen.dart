import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../models/provider_service_request_model.dart';
import 'invoice_document_screen.dart';
import 'invoice_payment_screen.dart';

class ProviderServicePaymentScreen extends StatefulWidget {
  const ProviderServicePaymentScreen({
    super.key,
    required this.request,
    this.showDocument = false,
  });

  final ProviderServiceRequestModel request;
  final bool showDocument;

  @override
  State<ProviderServicePaymentScreen> createState() =>
      _ProviderServicePaymentScreenState();
}

class _ProviderServicePaymentScreenState
    extends State<ProviderServicePaymentScreen> {
  late Future<String> _invoiceIdFuture;

  @override
  void initState() {
    super.initState();
    _invoiceIdFuture = _resolveInvoiceId();
  }

  Future<String> _resolveInvoiceId() async {
    final existing = widget.request.invoiceId?.trim() ?? '';
    if (existing.isNotEmpty) return existing;

    final data = await context.read<ApiClient>().get(
      '/customer/provider-service-requests/${widget.request.id}/invoice',
    );
    final invoice = data is Map
        ? Map<String, dynamic>.from(data)
        : <String, dynamic>{};
    final resolved = invoice['id']?.toString().trim() ?? '';
    if (resolved.isEmpty) {
      throw const ApiException('تعذر تجهيز فاتورة مقدم الخدمة. أعد المحاولة.');
    }
    return resolved;
  }

  @override
  Widget build(BuildContext context) {
    final subtitle = '${widget.request.providerName} • ${widget.request.venueName}';

    return FutureBuilder<String>(
      future: _invoiceIdFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return Scaffold(
            appBar: AppBar(title: const Text('دفع الخدمة')),
            body: const Center(child: CircularProgressIndicator()),
          );
        }

        if (snapshot.hasError || (snapshot.data ?? '').isEmpty) {
          return Scaffold(
            appBar: AppBar(title: const Text('دفع الخدمة')),
            body: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.error_outline, size: 48),
                    const SizedBox(height: 12),
                    Text(
                      snapshot.error?.toString() ??
                          'تعذر تجهيز فاتورة مقدم الخدمة.',
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: () => setState(
                        () => _invoiceIdFuture = _resolveInvoiceId(),
                      ),
                      icon: const Icon(Icons.refresh),
                      label: const Text('إعادة المحاولة'),
                    ),
                  ],
                ),
              ),
            ),
          );
        }

        final invoiceId = snapshot.data!;
        if (widget.showDocument) {
          return InvoiceDocumentScreen(
            invoiceId: invoiceId,
            sourceTitle: widget.request.serviceName,
            sourceSubtitle: subtitle,
          );
        }

        return InvoicePaymentScreen(
          invoiceId: invoiceId,
          sourceTitle: widget.request.serviceName,
          sourceSubtitle: subtitle,
        );
      },
    );
  }
}
