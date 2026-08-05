import '../core/network/api_config.dart';

class ProviderBusinessProfileModel {
  const ProviderBusinessProfileModel({
    required this.name,
    required this.businessName,
    required this.email,
    required this.accountPhone,
    required this.avatarUrl,
    required this.city,
    required this.coverageAreas,
    required this.workingHours,
    required this.daysOff,
    required this.bio,
    required this.contactPhone,
    required this.whatsappPhone,
    required this.allowPhone,
    required this.allowWhatsapp,
  });

  final String name;
  final String businessName;
  final String email;
  final String accountPhone;
  final String avatarUrl;
  final String city;
  final List<String> coverageAreas;
  final Map<String, dynamic> workingHours;
  final List<String> daysOff;
  final String bio;
  final String contactPhone;
  final String whatsappPhone;
  final bool allowPhone;
  final bool allowWhatsapp;

  factory ProviderBusinessProfileModel.fromJson(Map<String, dynamic> json) {
    final user = json['user'] is Map
        ? Map<String, dynamic>.from(json['user'] as Map)
        : const <String, dynamic>{};
    final profile = json['profile'] is Map
        ? Map<String, dynamic>.from(json['profile'] as Map)
        : const <String, dynamic>{};

    return ProviderBusinessProfileModel(
      name: (user['name'] ?? '').toString(),
      businessName: (profile['business_name'] ?? user['name'] ?? '').toString(),
      email: (user['email'] ?? '').toString(),
      accountPhone: (user['phone'] ?? '').toString(),
      avatarUrl: ApiConfig.resolveAssetUrl((user['avatar_url'] ?? user['avatar'] ?? '').toString()) ?? '',
      city: (profile['city'] ?? '').toString(),
      coverageAreas: profile['coverage_areas'] is List ? (profile['coverage_areas'] as List).map((e) => e.toString()).toList() : const [],
      workingHours: profile['working_hours'] is Map ? Map<String, dynamic>.from(profile['working_hours'] as Map) : const {},
      daysOff: profile['days_off'] is List ? (profile['days_off'] as List).map((e) => e.toString()).toList() : const [],
      bio: (profile['bio'] ?? '').toString(),
      contactPhone: (profile['contact_phone'] ?? user['phone'] ?? '').toString(),
      whatsappPhone: (profile['whatsapp_phone'] ?? profile['contact_phone'] ?? user['phone'] ?? '').toString(),
      allowPhone: _toBool(profile['allow_phone'], fallback: true),
      allowWhatsapp: _toBool(profile['allow_whatsapp'], fallback: true),
    );
  }

  ProviderBusinessProfileModel copyWith({
    String? name,
    String? businessName,
    String? email,
    String? accountPhone,
    String? avatarUrl,
    String? city,
    List<String>? coverageAreas,
    Map<String, dynamic>? workingHours,
    List<String>? daysOff,
    String? bio,
    String? contactPhone,
    String? whatsappPhone,
    bool? allowPhone,
    bool? allowWhatsapp,
  }) {
    return ProviderBusinessProfileModel(
      name: name ?? this.name,
      businessName: businessName ?? this.businessName,
      email: email ?? this.email,
      accountPhone: accountPhone ?? this.accountPhone,
      avatarUrl: avatarUrl ?? this.avatarUrl,
      city: city ?? this.city,
      coverageAreas: coverageAreas ?? this.coverageAreas,
      workingHours: workingHours ?? this.workingHours,
      daysOff: daysOff ?? this.daysOff,
      bio: bio ?? this.bio,
      contactPhone: contactPhone ?? this.contactPhone,
      whatsappPhone: whatsappPhone ?? this.whatsappPhone,
      allowPhone: allowPhone ?? this.allowPhone,
      allowWhatsapp: allowWhatsapp ?? this.allowWhatsapp,
    );
  }
}

bool _toBool(dynamic value, {required bool fallback}) {
  if (value == null) return fallback;
  if (value is bool) return value;
  return value.toString() == '1' || value.toString().toLowerCase() == 'true';
}
