import '../core/network/api_config.dart';
import '../core/utils/arabic_text.dart';
import 'service_model.dart';

class ProviderDirectoryModel {
  const ProviderDirectoryModel({
    required this.id,
    required this.name,
    required this.avatarUrl,
    required this.city,
    required this.bio,
    this.contactPhone,
    this.whatsappPhone,
    required this.rating,
    required this.reviewsCount,
    required this.servicesCount,
    required this.lowestPriceSyp,
    required this.services,
  });

  final String id;
  final String name;
  final String avatarUrl;
  final String city;
  final String bio;
  final String? contactPhone;
  final String? whatsappPhone;
  final double rating;
  final int reviewsCount;
  final int servicesCount;
  final int lowestPriceSyp;
  final List<ServiceModel> services;

  factory ProviderDirectoryModel.fromJson(Map<String, dynamic> json) {
    final providerMap = <String, dynamic>{
      'id': json['id'],
      'name': json['name'],
      'avatar_url': json['avatar_url'],
      'phone': json['contact_phone'],
      'provider_profile': {
        'city': json['city'],
        'bio': json['bio'],
        'contact_phone': json['contact_phone'],
        'whatsapp_phone': json['whatsapp_phone'],
        'allow_phone': json['allow_phone'],
        'allow_whatsapp': json['allow_whatsapp'],
      },
    };
    final services = (json['services'] is List ? json['services'] as List : const [])
        .whereType<Map>()
        .map((item) {
          final serviceJson = Map<String, dynamic>.from(item);
          serviceJson['provider'] = providerMap;
          return serviceModelFromJson(serviceJson);
        })
        .toList();
    return ProviderDirectoryModel(
      id: '${json['id'] ?? ''}',
      name: ArabicText.tr((json['name'] ?? 'مقدم خدمة').toString()),
      avatarUrl: ApiConfig.resolveAssetUrl((json['avatar_url'] ?? '').toString()) ?? '',
      city: ArabicText.tr((json['city'] ?? '').toString()),
      bio: ArabicText.tr((json['bio'] ?? '').toString()),
      contactPhone: json['contact_phone']?.toString(),
      whatsappPhone: json['whatsapp_phone']?.toString(),
      rating: double.tryParse((json['rating_avg'] ?? 0).toString()) ?? 0,
      reviewsCount: int.tryParse((json['reviews_count'] ?? 0).toString()) ?? 0,
      servicesCount: int.tryParse((json['services_count'] ?? services.length).toString()) ?? services.length,
      lowestPriceSyp: double.tryParse((json['lowest_price_syp'] ?? 0).toString())?.round() ?? 0,
      services: services,
    );
  }
}
