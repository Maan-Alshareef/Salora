import '../core/network/api_config.dart';
import '../core/utils/arabic_text.dart';

class ProviderServiceRequestModel {
  final String id;
  final String bookingId;
  final String serviceId;
  final String serviceName;
  final String category;
  final String providerName;
  final String providerPhone;
  final String providerEmail;
  final String customerName;
  final String customerPhone;
  final String venueName;
  final DateTime eventDate;
  final String startTime;
  final String endTime;
  final int priceSyp;
  final int priceUsd;
  final String paymentType;
  final String? invoiceId;
  final String invoiceNumber;
  final String invoiceStatus;
  final String? receiptNumber;
  final String status;
  final String paymentStatus;
  final String? paymentMethod;
  final String? paymentProofUrl;
  final String? paymentRejectionReason;
  final String? providerReply;
  final double providerCommissionRate;
  final int providerCommissionSyp;
  final int providerNetSyp;
  final String commissionStatus;

  const ProviderServiceRequestModel({
    required this.id,
    required this.bookingId,
    required this.serviceId,
    required this.serviceName,
    required this.category,
    required this.providerName,
    this.providerPhone = '',
    this.providerEmail = '',
    required this.customerName,
    this.customerPhone = '',
    required this.venueName,
    required this.eventDate,
    required this.startTime,
    required this.endTime,
    required this.priceSyp,
    required this.priceUsd,
    this.paymentType = 'manual_transfer',
    this.invoiceId,
    this.invoiceNumber = '',
    this.invoiceStatus = 'unpaid',
    this.receiptNumber,
    this.status = 'pending',
    this.paymentStatus = 'unpaid',
    this.paymentMethod,
    this.paymentProofUrl,
    this.paymentRejectionReason,
    this.providerReply,
    this.providerCommissionRate = 10,
    this.providerCommissionSyp = 0,
    this.providerNetSyp = 0,
    this.commissionStatus = 'not_due',
  });

  String get statusLabel {
    switch (status) {
      case 'accepted':
        return 'مقبول من مقدم الخدمة';
      case 'rejected':
        return 'مرفوض';
      case 'cancelled':
        return 'ملغى';
      default:
        return 'بانتظار موافقة مقدم الخدمة';
    }
  }

  String get paymentStatusLabel {
    switch (paymentStatus) {
      case 'proof_uploaded':
      case 'pending':
        return 'إيصال الدفع قيد مراجعة مقدم الخدمة';
      case 'approved':
      case 'paid':
        return 'مدفوع ومؤكد';
      case 'rejected':
        return 'إيصال الدفع مرفوض - ارفع إيصالاً جديداً';
      default:
        return status == 'accepted'
            ? 'بانتظار دفع فاتورة الخدمة'
            : 'لم تصدر الفاتورة بعد';
    }
  }

  String get paymentLabel => 'تحويل يدوي مع إيصال دفع';
  bool get hasProviderPhone => providerPhone.trim().isNotEmpty;
  bool get hasCustomerPhone => customerPhone.trim().isNotEmpty;
  bool get hasInvoice => invoiceId != null && invoiceId!.trim().isNotEmpty;

  bool get canUploadPayment =>
      status == 'accepted' &&
      (paymentStatus == 'unpaid' || paymentStatus == 'rejected');

  bool get canReviewPayment =>
      status == 'accepted' &&
      (paymentStatus == 'proof_uploaded' || paymentStatus == 'pending');

  bool get canViewPaymentDocument =>
      status == 'accepted' &&
      (paymentStatus == 'proof_uploaded' ||
          paymentStatus == 'pending' ||
          paymentStatus == 'approved' ||
          paymentStatus == 'paid' ||
          paymentStatus == 'rejected');

  bool get isConfirmed =>
      status == 'accepted' &&
      (paymentStatus == 'approved' || paymentStatus == 'paid');

  factory ProviderServiceRequestModel.fromJson(Map<String, dynamic> json) {
    final booking = json['booking'] is Map
        ? Map<String, dynamic>.from(json['booking'] as Map)
        : <String, dynamic>{};
    final venue = booking['venue'] is Map
        ? Map<String, dynamic>.from(booking['venue'] as Map)
        : <String, dynamic>{};
    final provider = json['provider'] is Map
        ? Map<String, dynamic>.from(json['provider'] as Map)
        : <String, dynamic>{};
    final customer = json['customer'] is Map
        ? Map<String, dynamic>.from(json['customer'] as Map)
        : <String, dynamic>{};
    final service = json['service'] is Map
        ? Map<String, dynamic>.from(json['service'] as Map)
        : <String, dynamic>{};
    final invoice = json['invoice'] is Map
        ? Map<String, dynamic>.from(json['invoice'] as Map)
        : <String, dynamic>{};
    final latestProof = invoice['latest_payment_proof'] is Map
        ? Map<String, dynamic>.from(invoice['latest_payment_proof'] as Map)
        : <String, dynamic>{};
    final latestProofStatus = (latestProof['status'] ?? '')
        .toString()
        .toLowerCase();
    final invoiceStatus = (invoice['status'] ?? '').toString().toLowerCase();
    String resolvedPaymentStatus;
    if (latestProofStatus == 'pending' || invoiceStatus == 'proof_uploaded') {
      resolvedPaymentStatus = 'proof_uploaded';
    } else if (latestProofStatus == 'approved' || invoiceStatus == 'paid') {
      resolvedPaymentStatus = 'approved';
    } else if (latestProofStatus == 'rejected') {
      resolvedPaymentStatus = 'rejected';
    } else {
      resolvedPaymentStatus = (json['payment_status'] ?? 'unpaid')
          .toString()
          .toLowerCase();
    }

    return ProviderServiceRequestModel(
      id: '${json['id'] ?? ''}',
      bookingId: '${json['booking_id'] ?? booking['id'] ?? ''}',
      serviceId: '${json['service_id'] ?? service['id'] ?? ''}',
      serviceName: ArabicText.tr(
        (json['service_name'] ??
                service['name_ar'] ??
                service['name_en'] ??
                'خدمة')
            .toString(),
      ),
      category: ArabicText.tr(
        (json['service_category'] ?? service['category'] ?? 'خدمة خارجية')
            .toString(),
      ),
      providerName: ArabicText.tr(
        (provider['name'] ?? json['provider_name'] ?? 'مقدم خدمة').toString(),
      ),
      providerPhone: (provider['phone'] ?? json['provider_phone'] ?? '')
          .toString(),
      providerEmail: (provider['email'] ?? json['provider_email'] ?? '')
          .toString(),
      customerName: ArabicText.tr(
        (customer['name'] ??
                json['customer_name'] ??
                booking['customer'] ??
                'عميل')
            .toString(),
      ),
      customerPhone: (customer['phone'] ?? json['customer_phone'] ?? '')
          .toString(),
      venueName: ArabicText.tr(
        (venue['name_ar'] ??
                venue['name_en'] ??
                booking['venue_name'] ??
                json['venue_name'] ??
                'صالة')
            .toString(),
      ),
      eventDate:
          DateTime.tryParse(
            (booking['event_date'] ??
                    json['event_date'] ??
                    DateTime.now().toIso8601String())
                .toString(),
          ) ??
          DateTime.now(),
      startTime: (booking['start_time'] ?? json['start_time'] ?? '').toString(),
      endTime: (booking['end_time'] ?? json['end_time'] ?? '').toString(),
      priceSyp: _toInt(json['price_syp'] ?? service['price_syp'] ?? 0),
      priceUsd: _toInt(json['price_usd'] ?? service['price_usd'] ?? 0),
      paymentType: (json['payment_type'] ?? 'manual_transfer').toString(),
      invoiceId: (json['invoice_id'] ?? invoice['id'])?.toString(),
      invoiceNumber: (json['invoice_number'] ?? invoice['invoice_number'] ?? '')
          .toString(),
      invoiceStatus: (invoice['status'] ?? 'unpaid').toString(),
      receiptNumber: invoice['receipt_number']?.toString(),
      status: (json['status'] ?? 'pending').toString(),
      paymentStatus: resolvedPaymentStatus,
      paymentMethod: (latestProof['payment_method'] ?? json['payment_method'])
          ?.toString(),
      paymentProofUrl: _assetUrl(
        (latestProof['image_full_url'] ??
                latestProof['image_url'] ??
                json['payment_proof_url'])
            ?.toString(),
      ),
      paymentRejectionReason:
          (latestProof['rejection_reason'] ?? json['payment_rejection_reason'])
              ?.toString(),
      providerReply: json['provider_reply']?.toString(),
      providerCommissionRate: _toDouble(json['provider_commission_rate'] ?? 10),
      providerCommissionSyp: _toInt(json['provider_commission_syp'] ?? 0),
      providerNetSyp: _toInt(json['provider_net_syp'] ?? 0),
      commissionStatus: (json['commission_status'] ?? 'not_due').toString(),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'booking_id': bookingId,
    'service_id': serviceId,
    'service_name': serviceName,
    'service_category': category,
    'price_syp': priceSyp,
    'price_usd': priceUsd,
    'payment_type': paymentType,
    'invoice_id': invoiceId,
    'invoice_number': invoiceNumber,
    'invoice_status': invoiceStatus,
    'receipt_number': receiptNumber,
    'status': status,
    'payment_status': paymentStatus,
    'payment_method': paymentMethod,
    'payment_proof_url': paymentProofUrl,
    'payment_rejection_reason': paymentRejectionReason,
    'provider_reply': providerReply,
  };
}

String? _assetUrl(String? value) => ApiConfig.resolveAssetUrl(value);

int _toInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.round();
  return double.tryParse(
        value?.toString().replaceAll(',', '') ?? '',
      )?.round() ??
      0;
}

double _toDouble(dynamic value) {
  if (value is double) return value;
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? 0;
}
