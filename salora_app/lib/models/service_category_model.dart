import '../core/network/api_config.dart';
import '../core/utils/arabic_text.dart';

class ServiceCategoryModel {
  final String id;
  final String name;
  final String description;
  final String appliesTo;
  final String? parentId;
  final String imageUrl;
  final bool isActive;
  final int servicesCount;
  final List<ServiceCategoryModel> children;

  const ServiceCategoryModel({
    required this.id,
    required this.name,
    this.description = '',
    this.appliesTo = 'both',
    this.parentId,
    this.imageUrl = '',
    this.isActive = true,
    this.servicesCount = 0,
    this.children = const [],
  });

  bool get supportsProviders => appliesTo == 'provider' || appliesTo == 'both';

  factory ServiceCategoryModel.fromJson(Map<String, dynamic> json) => ServiceCategoryModel(
        id: '${json['id'] ?? ''}',
        name: ArabicText.tr((json['name_ar'] ?? json['name_en'] ?? 'تصنيف').toString()),
        description: ArabicText.tr((json['description'] ?? '').toString()),
        appliesTo: (json['applies_to'] ?? 'both').toString(),
        parentId: json['parent_id']?.toString(),
        imageUrl: ApiConfig.resolveAssetUrl((json['image_url'] ?? '').toString()) ?? '',
        isActive: json['is_active'] == null || json['is_active'] == true || json['is_active']?.toString() == '1',
        servicesCount: int.tryParse((json['services_count'] ?? 0).toString()) ?? 0,
        children: (json['children'] is List ? json['children'] as List : const [])
            .whereType<Map>()
            .map((item) => ServiceCategoryModel.fromJson(Map<String, dynamic>.from(item)))
            .toList(),
      );
}
