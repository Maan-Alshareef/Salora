import '../core/network/api_config.dart';
import '../core/utils/arabic_text.dart';
import 'invoice_item.dart';

class ServiceImageModel {
  const ServiceImageModel({
    required this.id,
    required this.url,
    this.isMain = false,
    this.sortOrder = 0,
  });

  final String id;
  final String url;
  final bool isMain;
  final int sortOrder;

  factory ServiceImageModel.fromJson(Map<String, dynamic> json) =>
      ServiceImageModel(
        id: '${json['id'] ?? ''}',
        url: _assetUrl(
          (json['resolved_url'] ?? json['image_url'] ?? json['url'] ?? '')
              .toString(),
        ),
        isMain: json['is_main'] == true || json['is_main']?.toString() == '1',
        sortOrder: _toInt(json['sort_order']),
      );
}

class ServiceModel {
  final String id;
  final String name;
  final String rawName;
  final String category;
  final String? categoryId;
  final String providerId;
  final String providerName;
  final String providerAvatarUrl;
  final String? contactPhone;
  final String? whatsappPhone;
  final String city;
  final String image;
  final List<ServiceImageModel> imageItems;
  final int price;
  final double rating;
  final int reviewsCount;
  final String description;
  final List<String> availableEventTypes;
  final String paymentType;
  final String pricingUnit;
  final int? durationMinutes;
  final String approvalStatus;
  final String? rejectionReason;
  final bool isActive;

  const ServiceModel({
    required this.id,
    required this.name,
    this.rawName = '',
    required this.category,
    this.categoryId,
    this.providerId = '',
    required this.providerName,
    this.providerAvatarUrl = '',
    this.contactPhone,
    this.whatsappPhone,
    required this.city,
    required this.image,
    this.imageItems = const [],
    required this.price,
    required this.rating,
    this.reviewsCount = 0,
    required this.description,
    this.availableEventTypes = const [],
    this.paymentType = 'manual_transfer',
    this.pricingUnit = 'per_event',
    this.durationMinutes,
    this.approvalStatus = 'approved',
    this.rejectionReason,
    this.isActive = true,
  });

  String get displayName => ArabicText.tr(name);
  String get editableName => rawName.trim().isEmpty ? name : rawName;
  String get displayCategory => ArabicText.tr(category);
  String get displayProviderName => ArabicText.tr(providerName);
  String get displayCity => ArabicText.tr(city);
  String get displayDescription => ArabicText.tr(description);
  List<String> get displayEventTypes => ArabicText.list(availableEventTypes);
  String get paymentLabel =>
      'تحويل يدوي مع إثبات عبر شام كاش أو سيريتل كاش أو الهرم';
  String get pricingLabel => 'للمناسبة';
  bool get hasRating => rating > 0;
  bool get hasImage => image.trim().isNotEmpty;
  bool get imageIsNetwork =>
      image.startsWith('http://') || image.startsWith('https://');
  List<String> get galleryImages {
    final values = imageItems
        .map((item) => item.url)
        .where((value) => value.trim().isNotEmpty)
        .toList();
    if (values.isEmpty && image.trim().isNotEmpty) values.add(image);
    return values;
  }

  bool supportsEvent(String eventType) {
    if (availableEventTypes.isEmpty) return true;
    final target = _eventTypeKey(eventType);
    if (target.isEmpty) return true;
    return availableEventTypes
        .map(_eventTypeKey)
        .where((value) => value.isNotEmpty)
        .contains(target);
  }

  InvoiceItem toInvoiceItem() => InvoiceItem(
    id: id,
    title: name,
    category: category,
    amount: price,
    type: InvoiceItemType.externalVendorService,
  );

  ServiceModel copyWith({
    String? approvalStatus,
    String? rejectionReason,
    bool? isActive,
    List<ServiceImageModel>? imageItems,
    String? image,
  }) => ServiceModel(
    id: id,
    name: name,
    rawName: rawName,
    category: category,
    categoryId: categoryId,
    providerId: providerId,
    providerName: providerName,
    providerAvatarUrl: providerAvatarUrl,
    contactPhone: contactPhone,
    whatsappPhone: whatsappPhone,
    city: city,
    image: image ?? this.image,
    imageItems: imageItems ?? this.imageItems,
    price: price,
    rating: rating,
    reviewsCount: reviewsCount,
    description: description,
    availableEventTypes: availableEventTypes,
    paymentType: paymentType,
    pricingUnit: pricingUnit,
    durationMinutes: durationMinutes,
    approvalStatus: approvalStatus ?? this.approvalStatus,
    rejectionReason: rejectionReason ?? this.rejectionReason,
    isActive: isActive ?? this.isActive,
  );
}

ServiceModel serviceModelFromJson(Map<String, dynamic> json) {
  final provider = json['provider'] is Map
      ? Map<String, dynamic>.from(json['provider'] as Map)
      : const <String, dynamic>{};
  final providerProfile = provider['provider_profile'] is Map
      ? Map<String, dynamic>.from(provider['provider_profile'] as Map)
      : (provider['providerProfile'] is Map
            ? Map<String, dynamic>.from(provider['providerProfile'] as Map)
            : const <String, dynamic>{});
  final categoryModel = json['category_model'] is Map
      ? Map<String, dynamic>.from(json['category_model'] as Map)
      : const <String, dynamic>{};
  final rawName = ArabicText.tr(
    (json['name_ar'] ?? json['name_en'] ?? json['name'] ?? 'خدمة').toString(),
  );
  final emoji = (json['emoji'] ?? '').toString().trim();
  final category = ArabicText.tr(
    (categoryModel['name_ar'] ??
            categoryModel['name_en'] ??
            json['category'] ??
            'خدمة خارجية')
        .toString(),
  );

  final imageItems =
      (json['images'] is List ? json['images'] as List : const [])
          .whereType<Map>()
          .map(
            (item) =>
                ServiceImageModel.fromJson(Map<String, dynamic>.from(item)),
          )
          .where((item) => item.url.isNotEmpty)
          .toList()
        ..sort((a, b) {
          if (a.isMain != b.isMain) return a.isMain ? -1 : 1;
          return a.sortOrder.compareTo(b.sortOrder);
        });
  final legacyImage = _assetUrl(
    (json['cover_image_url'] ?? json['image_url'] ?? '').toString(),
  );
  final cover = imageItems.isNotEmpty ? imageItems.first.url : legacyImage;

  final providerPhone = (providerProfile['contact_phone'] ?? provider['phone'])
      ?.toString();
  final whatsapp =
      (providerProfile['whatsapp_phone'] ??
              providerProfile['contact_phone'] ??
              provider['phone'])
          ?.toString();
  final allowPhone =
      providerProfile['allow_phone'] == null ||
      providerProfile['allow_phone'] == true ||
      providerProfile['allow_phone']?.toString() == '1';
  final allowWhatsapp =
      providerProfile['allow_whatsapp'] == null ||
      providerProfile['allow_whatsapp'] == true ||
      providerProfile['allow_whatsapp']?.toString() == '1';

  return ServiceModel(
    id: '${json['id'] ?? ''}',
    name: emoji.isEmpty ? rawName : '$emoji $rawName',
    rawName: rawName,
    category: _serviceCategoryAr(category),
    categoryId: (json['category_id'] ?? categoryModel['id'])?.toString(),
    providerId: (provider['id'] ?? json['provider_id'] ?? '').toString(),
    providerName: ArabicText.tr(
      (provider['name'] ?? json['provider_name'] ?? 'مقدم خدمة').toString(),
    ),
    providerAvatarUrl: _assetUrl(
      (provider['avatar_url'] ?? provider['avatar'] ?? '').toString(),
    ),
    contactPhone: allowPhone ? providerPhone : null,
    whatsappPhone: allowWhatsapp ? whatsapp : null,
    city: ArabicText.tr(
      (providerProfile['city'] ?? json['city'] ?? '').toString(),
    ),
    image: cover,
    imageItems: imageItems,
    price: _toInt(json['price_syp'] ?? json['price'] ?? 0),
    rating: _toDouble(json['rating_avg'] ?? json['rating'] ?? 0),
    reviewsCount: _toInt(json['reviews_count'] ?? 0),
    description: ArabicText.tr(
      (json['description_ar'] ?? json['description_en'] ?? '').toString(),
    ),
    availableEventTypes: ArabicText.list(_toList(json['available_for'])),
    paymentType: (json['payment_type'] ?? 'manual_transfer').toString(),
    pricingUnit: 'per_event',
    durationMinutes: json['duration_minutes'] == null
        ? null
        : _toInt(json['duration_minutes']),
    approvalStatus: (json['approval_status'] ?? 'approved').toString(),
    rejectionReason: json['rejection_reason']?.toString(),
    isActive: json['is_active'] == true || json['is_active']?.toString() == '1',
  );
}

String _assetUrl(String value) => ApiConfig.resolveAssetUrl(value) ?? '';

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

List<String> _toList(dynamic value) {
  if (value is List) return value.map((item) => item.toString()).toList();
  return const [];
}

String _eventTypeKey(String value) {
  final normalized = ArabicText.tr(value)
      .replaceAll(RegExp(r'[^A-Za-zأ-ي0-9]+'), ' ')
      .replaceAll(RegExp(r'\s+'), ' ')
      .trim()
      .toLowerCase();
  if (normalized.contains('wedding') ||
      normalized.contains('زفاف') ||
      normalized.contains('عرس')) {
    return 'wedding';
  }
  if (normalized.contains('engagement') || normalized.contains('خطوبة')) {
    return 'engagement';
  }
  if (normalized.contains('graduation') || normalized.contains('تخرج')) {
    return 'graduation';
  }
  if (normalized.contains('birthday') || normalized.contains('عيد ميلاد')) {
    return 'birthday';
  }
  if (normalized.contains('family') || normalized.contains('عائل')) {
    return 'family';
  }
  if (normalized.contains('condolence') || normalized.contains('عزاء')) {
    return 'condolence';
  }
  if (normalized.contains('conference') || normalized.contains('مؤتمر')) {
    return 'conference';
  }
  if (normalized.contains('meeting') || normalized.contains('اجتماع')) {
    return 'meeting';
  }
  return normalized;
}

String _serviceCategoryAr(String value) {
  final normalized = value.toLowerCase().trim();
  switch (normalized) {
    case 'external_vendor':
      return 'خدمة خارجية';
    case 'photography':
      return 'تصوير';
    case 'catering':
      return 'ضيافة';
    case 'decoration':
      return 'ديكور';
    case 'lighting':
      return 'إضاءة وصوت';
    case 'cake':
      return 'كيك';
    default:
      return ArabicText.tr(value);
  }
}
