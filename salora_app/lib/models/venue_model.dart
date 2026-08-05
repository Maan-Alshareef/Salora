import 'invoice_item.dart';
import '../core/utils/arabic_text.dart';
import '../core/network/api_config.dart';

String _assetUrl(String value) => ApiConfig.resolveAssetUrl(value) ?? '';

class HallServiceOption {
  final String id;
  final String name;
  final String category;
  final int price;
  final List<String> eventTypes;

  const HallServiceOption({
    required this.id,
    required this.name,
    required this.category,
    required this.price,
    this.eventTypes = const [],
  });

  factory HallServiceOption.fromJson(Map<String, dynamic> json) {
    return HallServiceOption(
      id: '${json['id'] ?? json['service_id'] ?? ''}',
      name: ArabicText.tr(
        (json['name_ar'] ?? json['name_en'] ?? json['name'] ?? 'خدمة')
            .toString(),
      ),
      category: ArabicText.tr(
        (json['category'] ?? json['type'] ?? 'خدمة مدفوعة من الصالة')
            .toString(),
      ),
      price: _toInt(
        json['custom_price_syp'] ?? json['price_syp'] ?? json['price'] ?? 0,
      ),
      eventTypes: _toStringList(json['available_for']),
    );
  }

  InvoiceItem toInvoiceItem() => InvoiceItem(
    id: id,
    title: name,
    category: category,
    amount: price,
    type: InvoiceItemType.hallExtraService,
  );
}

class VenueOpeningDay {
  const VenueOpeningDay({
    required this.enabled,
    required this.open,
    required this.close,
  });

  final bool enabled;
  final String open;
  final String close;

  factory VenueOpeningDay.fromJson(dynamic value) {
    if (value is! Map)
      return const VenueOpeningDay(enabled: true, open: '', close: '');
    final map = Map<String, dynamic>.from(value);
    return VenueOpeningDay(
      enabled: map['enabled'] == null
          ? true
          : map['enabled'] == true || map['enabled']?.toString() == '1',
      open: (map['open'] ?? '').toString(),
      close: (map['close'] ?? '').toString(),
    );
  }
}

class VenueModel {
  final String id;
  final String name;
  final String city;
  final String address;
  final List<String> images;
  final List<String> videos;
  final int price;
  final int originalPrice;
  final double rating;
  final int reviewsCount;
  final int capacity;
  final String description;
  final List<String> services;
  final List<String> amenities;
  final List<String> eventTypes;
  final Map<String, String> eventTypeIds;
  final List<String> includedServices;
  final List<HallServiceOption> hallExtraServices;
  final List<String> externalServiceCategories;
  final bool hasOffer;
  final int? discountPercentage;
  final String? badge;
  final String mapUrl;
  final String googlePlaceId;
  final Map<String, VenueOpeningDay> openingHours;
  final double latitude;
  final double longitude;

  const VenueModel({
    required this.id,
    required this.name,
    required this.city,
    required this.address,
    required this.images,
    this.videos = const [],
    required this.price,
    this.originalPrice = 0,
    required this.rating,
    required this.reviewsCount,
    required this.capacity,
    required this.description,
    required this.services,
    required this.amenities,
    required this.eventTypes,
    this.eventTypeIds = const {},
    this.includedServices = const [],
    this.hallExtraServices = const [],
    this.externalServiceCategories = const [],
    required this.hasOffer,
    this.discountPercentage,
    this.badge,
    this.mapUrl = '',
    this.googlePlaceId = '',
    this.openingHours = const {},
    required this.latitude,
    required this.longitude,
  });

  factory VenueModel.fromJson(Map<String, dynamic> json) {
    final imageItems = (json['images'] is List)
        ? (json['images'] as List)
        : const [];
    final imageRecords =
        imageItems
            .map((item) {
              if (item is Map) {
                final map = Map<String, dynamic>.from(item);
                return (
                  url: _assetUrl(
                    (map['resolved_url'] ??
                            map['image_url'] ??
                            map['url'] ??
                            '')
                        .toString(),
                  ),
                  isMain:
                      map['is_main'] == true ||
                      map['is_main']?.toString() == '1',
                  sortOrder: _toInt(map['sort_order']),
                );
              }
              return (
                url: _assetUrl(item.toString()),
                isMain: false,
                sortOrder: 0,
              );
            })
            .where((item) => item.url.isNotEmpty)
            .toList()
          ..sort((a, b) {
            if (a.isMain != b.isMain) return a.isMain ? -1 : 1;
            return a.sortOrder.compareTo(b.sortOrder);
          });
    final imageUrls = imageRecords.map((item) => item.url).take(10).toList();
    final legacyCover = _assetUrl((json['cover_image_url'] ?? '').toString());
    if (imageUrls.isEmpty && legacyCover.isNotEmpty) imageUrls.add(legacyCover);

    final videoItems = (json['videos'] is List)
        ? (json['videos'] as List)
        : const [];
    final videoRecords =
        videoItems
            .map((item) {
              if (item is Map) {
                final map = Map<String, dynamic>.from(item);
                return (
                  url: _assetUrl(
                    (map['resolved_url'] ??
                            map['video_url'] ??
                            map['url'] ??
                            '')
                        .toString(),
                  ),
                  sortOrder: _toInt(map['sort_order']),
                );
              }
              return (url: _assetUrl(item.toString()), sortOrder: 0);
            })
            .where((item) => item.url.isNotEmpty)
            .toList()
          ..sort((a, b) => a.sortOrder.compareTo(b.sortOrder));
    final videoUrls = videoRecords.map((item) => item.url).take(5).toList();

    final rawOpeningHours = json['opening_hours'];
    final openingHours = <String, VenueOpeningDay>{};
    if (rawOpeningHours is Map) {
      for (final entry in rawOpeningHours.entries) {
        openingHours[entry.key.toString().toLowerCase()] =
            VenueOpeningDay.fromJson(entry.value);
      }
    }

    final serviceItems = (json['services'] is List)
        ? (json['services'] as List)
        : const [];
    final included = <String>[];
    final hallExtras = <HallServiceOption>[];
    final external = <String>[];
    final allServices = <String>[];

    for (final item in serviceItems) {
      if (item is! Map<String, dynamic>) continue;
      final name = ArabicText.tr(
        (item['name_ar'] ?? item['name_en'] ?? item['name'] ?? 'خدمة')
            .toString(),
      );
      final type = (item['type'] ?? '').toString();
      allServices.add(name);
      if (type == 'included') {
        included.add(name);
      } else if (type == 'hall_upgrade') {
        hallExtras.add(HallServiceOption.fromJson(item));
      } else if (type == 'external_vendor') {
        final category = _normalizeCategory(
          ArabicText.tr((item['category'] ?? name).toString()),
        );
        if (category.isNotEmpty) external.add(category);
      }
    }

    final eventItems = (json['event_types'] is List)
        ? (json['event_types'] as List)
        : const [];
    final eventTypeIds = <String, String>{};
    final eventLabels = eventItems
        .map((item) {
          if (item is Map<String, dynamic>) {
            final label = ArabicText.tr(
              (item['name_ar'] ?? item['name_en'] ?? item['name'] ?? '')
                  .toString(),
            );
            final id = '${item['id'] ?? ''}';
            if (label.isNotEmpty && id.isNotEmpty) eventTypeIds[label] = id;
            return label;
          }
          return item.toString();
        })
        .where((item) => item.isNotEmpty)
        .toList();

    final externalServiceItems = (json['external_services'] is List)
        ? (json['external_services'] as List)
        : const [];
    final externalCategoriesFromVenue =
        _toStringList(
              json['external_service_categories'] ?? json['vendor_categories'],
            )
            .map((e) => _normalizeCategory(ArabicText.tr(e)))
            .where((e) => e.isNotEmpty)
            .toList();
    final externalCategoriesFromServices = externalServiceItems
        .whereType<Map<String, dynamic>>()
        .map(
          (item) => _normalizeCategory(
            ArabicText.tr((item['category'] ?? item['type'] ?? '').toString()),
          ),
        )
        .where((e) => e.isNotEmpty)
        .toList();

    return VenueModel(
      id: '${json['id'] ?? ''}',
      name: ArabicText.tr(
        (json['name_ar'] ?? json['name_en'] ?? json['name'] ?? 'صالة')
            .toString(),
      ),
      city: ArabicText.tr((json['city'] ?? '').toString()),
      address: ArabicText.tr((json['address'] ?? '').toString()),
      images: imageUrls.isEmpty ? [''] : imageUrls,
      videos: videoUrls,
      price: _priceSypFromJson(json),
      originalPrice: _toInt(
        json['hourly_price_syp'] ?? json['price_syp'] ?? json['price'] ?? 0,
      ),
      rating: _toDouble(json['rating_avg'] ?? json['rating'] ?? 0),
      reviewsCount: _toInt(json['reviews_count'] ?? 0),
      capacity: _toInt(json['capacity'] ?? 0),
      description: ArabicText.tr(
        (json['description_ar'] ??
                json['description_en'] ??
                json['description'] ??
                '')
            .toString(),
      ),
      services: allServices,
      amenities: ArabicText.list(_toStringList(json['amenities'])),
      eventTypes: eventLabels,
      eventTypeIds: eventTypeIds,
      includedServices: included,
      hallExtraServices: hallExtras,
      externalServiceCategories: <String>{
        ...external,
        ...externalCategoriesFromVenue,
        ...externalCategoriesFromServices,
      }.toList(),
      hasOffer: json['has_offer'] == true || json['active_offer'] != null,
      discountPercentage: _toInt(json['discount_percentage'] ?? 0) == 0
          ? null
          : _toInt(json['discount_percentage']),
      badge: (json['badge']?.toString().trim().isEmpty ?? true)
          ? null
          : json['badge'].toString(),
      mapUrl: (json['map_url'] ?? '').toString(),
      googlePlaceId: (json['google_place_id'] ?? '').toString(),
      openingHours: openingHours,
      latitude: _toDouble(json['latitude'] ?? 0),
      longitude: _toDouble(json['longitude'] ?? 0),
    );
  }

  Map<String, dynamic> toCacheJson() => {
    'id': id,
    'name_ar': name,
    'city': city,
    'address': address,
    'images': images,
    'videos': videos,
    'final_price_syp': price,
    'price_syp': originalPrice,
    'rating_avg': rating,
    'reviews_count': reviewsCount,
    'capacity': capacity,
    'description_ar': description,
    'amenities': amenities,
    'event_types': eventTypes
        .map((e) => {'id': eventTypeIds[e] ?? '', 'name_ar': e})
        .toList(),
    'has_offer': hasOffer,
    'discount_percentage': discountPercentage,
    'badge': badge,
    'map_url': mapUrl,
    'google_place_id': googlePlaceId,
    'latitude': latitude,
    'longitude': longitude,
    'vendor_categories': externalServiceCategories,
  };

  String get mainImage => images.isEmpty ? '' : images.first;
  String get image => mainImage;
  String get location => city;
  List<String> get supportedEventTypes => eventTypes;
  bool get hasCoordinates =>
      latitude.abs() > 0.000001 || longitude.abs() > 0.000001;

  VenueOpeningDay? openingHoursFor(DateTime date) {
    const keys = [
      'monday',
      'tuesday',
      'wednesday',
      'thursday',
      'friday',
      'saturday',
      'sunday',
    ];
    return openingHours[keys[date.weekday - 1]];
  }

  // The backend already returns the final public price after active approved offers.
  int get finalPrice => price;
  int get displayOriginalPrice => originalPrice > 0 ? originalPrice : price;
  bool get hasDiscount => hasOffer && displayOriginalPrice > finalPrice;

  String? eventTypeIdFor(String eventType) =>
      eventTypeIds[eventType] ?? eventTypeIds[ArabicText.tr(eventType)];

  List<HallServiceOption> hallExtrasFor(String eventType) => hallExtraServices
      .where(
        (service) =>
            service.eventTypes.isEmpty ||
            service.eventTypes.map(ArabicText.tr).contains(eventType),
      )
      .toList();
}

String _normalizeCategory(String value) {
  final text = value.trim();
  if (text.isEmpty) return '';
  final cleaned = text.replaceAll(RegExp(r'^[^A-Za-zأ-ي]+\s*'), '').trim();
  final lower = cleaned.toLowerCase();
  if (lower.contains('photo') || cleaned.contains('تصوير')) return 'تصوير';
  if (lower.contains('decor') || cleaned.contains('ديكور')) return 'ديكور';
  if (lower.contains('food') ||
      lower.contains('hospitality') ||
      cleaned.contains('ضيافة') ||
      cleaned.contains('مأكولات'))
    return 'ضيافة';
  if (lower.contains('lighting') ||
      lower.contains('sound') ||
      cleaned.contains('إضاءة') ||
      cleaned.contains('صوت'))
    return 'إضاءة وصوت';
  if (lower.contains('cake') || cleaned.contains('كيك')) return 'كيك';
  if (lower.contains('reader') ||
      lower.contains('sheikh') ||
      cleaned.contains('قارئ') ||
      cleaned.contains('شيخ'))
    return 'قارئ / شيخ';
  return cleaned;
}

int _priceSypFromJson(Map<String, dynamic> json) {
  final syp = _toInt(
    json['final_hourly_price_syp'] ??
        json['final_price_syp'] ??
        json['hourly_price_syp'] ??
        json['price_syp'] ??
        json['price'],
  );
  if (syp > 0) return syp;
  return 0;
}

int _toInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.round();
  final text = value?.toString().replaceAll(',', '').trim() ?? '';
  if (text.isEmpty) return 0;
  return double.tryParse(text)?.round() ?? int.tryParse(text) ?? 0;
}

double _toDouble(dynamic value) {
  if (value is double) return value;
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? 0;
}

List<String> _toStringList(dynamic value) {
  if (value is List) return value.map((e) => e.toString()).toList();
  if (value is String && value.isNotEmpty)
    return value
        .split(',')
        .map((e) => e.trim())
        .where((e) => e.isNotEmpty)
        .toList();
  return const [];
}
