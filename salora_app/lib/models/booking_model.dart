import '../core/network/api_config.dart';
import '../core/utils/arabic_text.dart';
import 'invoice_item.dart';
import 'provider_service_request_model.dart';

enum BookingStatus {
  pending,
  approved,
  paymentUploaded,
  paid,
  modificationRequested,
  cancellationRequested,
  rejected,
  cancelled,
  completed,
}

enum PaymentMethod { cash, bankTransfer }

extension BookingStatusX on BookingStatus {
  String get label {
    switch (this) {
      case BookingStatus.pending:
        return 'بانتظار الدفع';
      case BookingStatus.approved:
        return 'بانتظار الدفع';
      case BookingStatus.paymentUploaded:
        return 'بانتظار مراجعة الدفع';
      case BookingStatus.paid:
        return 'مؤكد';
      case BookingStatus.modificationRequested:
        return 'طلب تعديل قيد المراجعة';
      case BookingStatus.cancellationRequested:
        return 'طلب إلغاء قيد المراجعة';
      case BookingStatus.rejected:
        return 'مرفوض';
      case BookingStatus.cancelled:
        return 'ملغى';
      case BookingStatus.completed:
        return 'مكتمل';
    }
  }

  bool get canUploadPaymentProof => this == BookingStatus.approved;
  bool get canRequestProviderService => this == BookingStatus.paid;
  bool get canCancelDirectly =>
      this == BookingStatus.pending ||
      this == BookingStatus.approved ||
      this == BookingStatus.paymentUploaded ||
      this == BookingStatus.paid ||
      this == BookingStatus.modificationRequested;
  bool get canRequestCancellation => false;
  bool get canReview => this == BookingStatus.completed;
  bool get isFinal =>
      this == BookingStatus.completed ||
      this == BookingStatus.cancelled ||
      this == BookingStatus.rejected;
}

class BookingModel {
  final String id;
  final String bookingNumber;
  final String? eventId;
  final String eventTitle;
  final String venueId;
  final String venueName;
  final List<String> venueProviderCategories;
  final String city;
  final DateTime eventDate;
  final String startTime;
  final String endTime;
  final String eventType;
  final int guests;
  final List<String> services;
  final List<String> includedServices;
  final List<InvoiceItem> hallExtraServices;
  final List<InvoiceItem> vendorServices;
  final List<InvoiceItem> invoiceItems;
  final List<ProviderServiceRequestModel> providerRequests;
  final int totalAmount;
  final PaymentMethod paymentMethod;
  final String? receiptPath;
  final String? invoiceId;
  final String? invoiceNumber;
  final String? receiptNumber;
  final String? verificationUrl;
  final String? rejectionReason;
  final BookingStatus status;
  final DateTime createdAt;

  const BookingModel({
    required this.id,
    this.bookingNumber = '',
    this.eventId,
    this.eventTitle = 'مناسبة',
    required this.venueId,
    required this.venueName,
    this.venueProviderCategories = const [],
    required this.city,
    required this.eventDate,
    required this.startTime,
    required this.endTime,
    required this.eventType,
    required this.guests,
    required this.services,
    this.includedServices = const [],
    this.hallExtraServices = const [],
    this.vendorServices = const [],
    this.invoiceItems = const [],
    this.providerRequests = const [],
    required this.totalAmount,
    required this.paymentMethod,
    this.receiptPath,
    this.invoiceId,
    this.invoiceNumber,
    this.receiptNumber,
    this.verificationUrl,
    this.rejectionReason,
    this.status = BookingStatus.pending,
    required this.createdAt,
  });

  factory BookingModel.fromJson(Map<String, dynamic> json) {
    final venue = json['venue'] is Map
        ? Map<String, dynamic>.from(json['venue'] as Map)
        : <String, dynamic>{};
    final eventTypeMap = json['event_type'] is Map
        ? Map<String, dynamic>.from(json['event_type'] as Map)
        : <String, dynamic>{};
    final eventMap = json['event'] is Map
        ? Map<String, dynamic>.from(json['event'] as Map)
        : <String, dynamic>{};
    final servicesJson = json['services'] is List
        ? json['services'] as List
        : const [];
    final providerRequestsJson = json['provider_requests'] is List
        ? json['provider_requests'] as List
        : const [];
    final invoiceItems = servicesJson.whereType<Map>().map((raw) {
      final item = Map<String, dynamic>.from(raw);
      final type = (item['service_type'] ?? item['type'] ?? 'hall_upgrade')
          .toString();
      final price = _toInt(
        item['total_syp'] ?? item['price_syp'] ?? item['unit_price_syp'] ?? 0,
      );
      return InvoiceItem(
        id: '${item['service_id'] ?? item['id'] ?? ''}',
        title:
            (item['service_name'] ??
                    item['name_ar'] ??
                    item['name_en'] ??
                    'خدمة')
                .toString(),
        category: type,
        amount: price,
        type: type == 'included'
            ? InvoiceItemType.includedService
            : InvoiceItemType.hallExtraService,
      );
    }).toList();

    final invoice = json['invoice'] is Map
        ? Map<String, dynamic>.from(json['invoice'] as Map)
        : <String, dynamic>{};
    final latestProof = json['latest_payment_proof'] is Map
        ? Map<String, dynamic>.from(json['latest_payment_proof'] as Map)
        : invoice['latest_payment_proof'] is Map
        ? Map<String, dynamic>.from(invoice['latest_payment_proof'] as Map)
        : <String, dynamic>{};
    final rawReceiptSource = (
      latestProof['image_full_url'] ??
      latestProof['image_url'] ??
      latestProof['local_path']
    )?.toString().trim();
    String? receiptSource;
    if (rawReceiptSource != null && rawReceiptSource.isNotEmpty) {
      receiptSource =
          rawReceiptSource.startsWith('/') || rawReceiptSource.startsWith('http')
          ? ApiConfig.resolveAssetUrl(rawReceiptSource)
          : rawReceiptSource;
    }

    return BookingModel(
      id: '${json['id'] ?? ''}',
      bookingNumber: (json['booking_number'] ?? '').toString(),
      eventId: json['event_id']?.toString(),
      eventTitle: (eventMap['name'] ?? json['event_name'] ?? 'مناسبة')
          .toString(),
      venueId: '${json['venue_id'] ?? venue['id'] ?? ''}',
      venueName: ArabicText.tr(
        (venue['name_ar'] ?? venue['name_en'] ?? json['venue_name'] ?? 'صالة')
            .toString(),
      ),
      venueProviderCategories: _toStringList(
        venue['vendor_categories'],
      ).map(ArabicText.tr).toList(),
      city: ArabicText.tr((venue['city'] ?? json['city'] ?? '').toString()),
      eventDate: _bookingDateFromApi(json),
      startTime: _bookingTimeFromApi(json['start_at'], json['start_time']),
      endTime: _bookingTimeFromApi(json['end_at'], json['end_time']),
      eventType: ArabicText.tr(
        (eventTypeMap['name_ar'] ?? eventTypeMap['name_en'] ?? 'مناسبة')
            .toString(),
      ),
      guests: _toInt(
        json['guests_count'] ?? json['guest_count'] ?? json['number_of_guests'],
      ),
      services: invoiceItems.map((item) => item.title).toList(),
      invoiceItems: invoiceItems,
      providerRequests: providerRequestsJson
          .whereType<Map>()
          .map(
            (item) => ProviderServiceRequestModel.fromJson(
              Map<String, dynamic>.from(item),
            ),
          )
          .toList(),
      totalAmount: _toInt(json['total_syp'] ?? json['invoice_total'] ?? 0),
      paymentMethod: PaymentMethod.bankTransfer,
      receiptPath: receiptSource,
      invoiceId: (invoice['id'] ?? json['invoice_id'])?.toString(),
      invoiceNumber: invoice['invoice_number']?.toString(),
      receiptNumber: invoice['receipt_number']?.toString(),
      verificationUrl: invoice['verification_url']?.toString(),
      rejectionReason: json['rejection_reason']?.toString(),
      status: _statusFromApi(
        (json['booking_status'] ?? '').toString(),
        (json['payment_status'] ?? '').toString(),
      ),
      createdAt:
          DateTime.tryParse(
            (json['created_at'] ?? DateTime.now().toIso8601String()).toString(),
          ) ??
          DateTime.now(),
    );
  }

  bool get isActiveForProviderServices {
    final today = DateTime.now();
    final todayOnly = DateTime(today.year, today.month, today.day);
    final eventOnly = DateTime(eventDate.year, eventDate.month, eventDate.day);
    return status.canRequestProviderService && !eventOnly.isBefore(todayOnly);
  }

  bool allowsProviderCategory(String category) {
    if (venueProviderCategories.isEmpty) return true;
    final key = _providerCategoryKey(category);
    return venueProviderCategories.map(_providerCategoryKey).contains(key);
  }

  bool hasProviderServiceRequest(String serviceId) => providerRequests.any(
    (request) =>
        request.serviceId == serviceId && request.status != 'cancelled',
  );

  BookingModel copyWith({
    String? receiptPath,
    BookingStatus? status,
    List<ProviderServiceRequestModel>? providerRequests,
    String? rejectionReason,
    String? receiptNumber,
  }) => BookingModel(
    id: id,
    bookingNumber: bookingNumber,
    eventId: eventId,
    eventTitle: eventTitle,
    venueId: venueId,
    venueName: venueName,
    venueProviderCategories: venueProviderCategories,
    city: city,
    eventDate: eventDate,
    startTime: startTime,
    endTime: endTime,
    eventType: eventType,
    guests: guests,
    services: services,
    includedServices: includedServices,
    hallExtraServices: hallExtraServices,
    vendorServices: vendorServices,
    invoiceItems: invoiceItems,
    providerRequests: providerRequests ?? this.providerRequests,
    totalAmount: totalAmount,
    paymentMethod: paymentMethod,
    receiptPath: receiptPath ?? this.receiptPath,
    invoiceId: invoiceId,
    invoiceNumber: invoiceNumber,
    receiptNumber: receiptNumber ?? this.receiptNumber,
    verificationUrl: verificationUrl,
    rejectionReason: rejectionReason ?? this.rejectionReason,
    status: status ?? this.status,
    createdAt: createdAt,
  );
}

BookingStatus _statusFromApi(String bookingStatus, String paymentStatus) {
  switch (bookingStatus.toLowerCase()) {
    case 'pending_owner_review':
      return BookingStatus.approved;
    case 'pending_payment':
    case 'owner_approved':
      return BookingStatus.approved;
    case 'payment_under_review':
      return BookingStatus.paymentUploaded;
    case 'confirmed':
      return BookingStatus.paid;
    case 'modification_requested':
      return BookingStatus.modificationRequested;
    case 'cancellation_requested':
      return BookingStatus.cancellationRequested;
    case 'owner_rejected':
    case 'rejected':
      return BookingStatus.rejected;
    case 'cancelled':
      return BookingStatus.cancelled;
    case 'completed':
      return BookingStatus.completed;
    default:
      if (paymentStatus.toLowerCase() == 'approved') return BookingStatus.paid;
      if (paymentStatus.toLowerCase() == 'proof_uploaded')
        return BookingStatus.paymentUploaded;
      return BookingStatus.pending;
  }
}

DateTime _bookingDateFromApi(Map<String, dynamic> json) {
  final startAt = DateTime.tryParse((json['start_at'] ?? '').toString());
  if (startAt != null) {
    return DateTime(startAt.year, startAt.month, startAt.day);
  }

  return DateTime.tryParse(
        (json['event_date'] ??
                json['booking_date'] ??
                json['date'] ??
                DateTime.now().toIso8601String())
            .toString(),
      ) ??
      DateTime.now();
}

String _bookingTimeFromApi(dynamic dateTimeValue, dynamic timeValue) {
  final parsed = DateTime.tryParse(dateTimeValue?.toString() ?? '');
  if (parsed != null) {
    return '${parsed.hour.toString().padLeft(2, '0')}:'
        '${parsed.minute.toString().padLeft(2, '0')}';
  }
  return _shortTime(timeValue?.toString() ?? '');
}

String _shortTime(String value) =>
    value.length >= 5 ? value.substring(0, 5) : value;

int _toInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.round();
  final text = value?.toString().replaceAll(',', '').trim() ?? '';
  if (text.isEmpty) return 0;
  return double.tryParse(text)?.round() ?? 0;
}

List<String> _toStringList(dynamic value) {
  if (value is List)
    return value
        .map((item) => item.toString())
        .where((item) => item.trim().isNotEmpty)
        .toList();
  if (value is String && value.trim().isNotEmpty) {
    return value
        .split(',')
        .map((item) => item.trim())
        .where((item) => item.isNotEmpty)
        .toList();
  }
  return const [];
}

String _providerCategoryKey(String value) {
  final normalized = ArabicText.tr(
    value,
  ).replaceFirst(RegExp(r'^[^A-Za-zأ-ي0-9]+'), '').trim().toLowerCase();
  if (normalized.contains('photo') || normalized.contains('تصوير'))
    return 'photography';
  if (normalized.contains('hospital') ||
      normalized.contains('cater') ||
      normalized.contains('food') ||
      normalized.contains('drink') ||
      normalized.contains('ضياف') ||
      normalized.contains('مأكول') ||
      normalized.contains('مشروب') ||
      normalized.contains('قهوة') ||
      normalized.contains('شاي'))
    return 'hospitality';
  if (normalized.contains('equipment') ||
      normalized.contains('decor') ||
      normalized.contains('light') ||
      normalized.contains('sound') ||
      normalized.contains('تجهيز') ||
      normalized.contains('معدات') ||
      normalized.contains('ديكور') ||
      normalized.contains('إضاءة') ||
      normalized.contains('صوت'))
    return 'equipment';
  if (normalized.contains('cake') ||
      normalized.contains('كيك') ||
      normalized.contains('حلويات'))
    return 'cake';
  if (normalized.contains('print') ||
      normalized.contains('invitation') ||
      normalized.contains('طباعة') ||
      normalized.contains('دعوات'))
    return 'printing';
  if (normalized.contains('reader') ||
      normalized.contains('sheikh') ||
      normalized.contains('قارئ') ||
      normalized.contains('شيخ'))
    return 'religious';
  if (normalized.contains('organ') ||
      normalized.contains('planning') ||
      normalized.contains('تنظيم'))
    return 'organization';
  return normalized;
}
