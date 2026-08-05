import 'package:flutter/material.dart';

import '../../models/booking_model.dart';
import 'invoice_document_screen.dart';

class PaymentReceiptScreen extends StatelessWidget {
  const PaymentReceiptScreen({super.key, required this.booking});

  final BookingModel booking;

  @override
  Widget build(BuildContext context) {
    return InvoiceDocumentScreen(
      invoiceId: booking.invoiceId ?? '',
      sourceTitle: booking.venueName,
      sourceSubtitle:
          '${booking.eventDate.year}-${booking.eventDate.month}-${booking.eventDate.day} • ${booking.startTime} - ${booking.endTime}',
    );
  }
}
