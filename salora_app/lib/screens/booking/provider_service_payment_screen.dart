import 'package:flutter/material.dart';

import '../../models/provider_service_request_model.dart';
import 'invoice_document_screen.dart';
import 'invoice_payment_screen.dart';

class ProviderServicePaymentScreen extends StatelessWidget {
  const ProviderServicePaymentScreen({
    super.key,
    required this.request,
    this.showDocument = false,
  });

  final ProviderServiceRequestModel request;
  final bool showDocument;

  @override
  Widget build(BuildContext context) {
    final invoiceId = request.invoiceId ?? '';
    final subtitle = '${request.providerName} • ${request.venueName}';

    if (showDocument) {
      return InvoiceDocumentScreen(
        invoiceId: invoiceId,
        sourceTitle: request.serviceName,
        sourceSubtitle: subtitle,
      );
    }

    return InvoicePaymentScreen(
      invoiceId: invoiceId,
      sourceTitle: request.serviceName,
      sourceSubtitle: subtitle,
    );
  }
}
