import 'package:flutter/material.dart';

import '../../models/booking_model.dart';
import 'invoice_payment_screen.dart';

class PaymentProofScreen extends StatelessWidget {
  const PaymentProofScreen({super.key, required this.booking});

  final BookingModel booking;

  @override
  Widget build(BuildContext context) {
    return InvoicePaymentScreen(
      invoiceId: booking.invoiceId ?? '',
      sourceTitle: booking.venueName,
      sourceSubtitle:
          '${booking.eventDate.year}-${booking.eventDate.month}-${booking.eventDate.day} • ${booking.startTime} - ${booking.endTime}',
    );
  }
}
