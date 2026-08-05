import 'dart:io';

import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../models/event_type_option_model.dart';
import '../models/provider_business_profile_model.dart';
import '../models/provider_service_request_model.dart';
import '../models/service_category_model.dart';
import '../models/service_model.dart';

class ProviderAccountProvider extends ChangeNotifier {
  ProviderAccountProvider(this._api);

  final ApiClient _api;
  final List<ServiceModel> _myServices = [];
  final List<ProviderServiceRequestModel> _requests = [];
  final List<ServiceCategoryModel> _categories = [];
  final List<EventTypeOptionModel> _eventTypes = [];

  ProviderBusinessProfileModel? profile;
  bool isLoading = false;
  bool isSavingProfile = false;
  String? error;

  List<ServiceModel> get myServices => List.unmodifiable(_myServices);
  List<ProviderServiceRequestModel> get requests => List.unmodifiable(_requests);
  List<ServiceCategoryModel> get categories => List.unmodifiable(_categories);
  List<EventTypeOptionModel> get eventTypes => List.unmodifiable(_eventTypes);
  int get pendingRequestsCount => _requests.where((request) => request.status == 'pending').length;

  Future<void> load() async {
    if (!_api.isAuthenticated) return;
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final results = await Future.wait([
        _api.get('/provider/profile'),
        _api.get('/provider/services'),
        _api.get('/provider/requests'),
        _api.get('/service-categories', query: {'for': 'provider'}),
        _api.get('/event-types'),
      ]);

      profile = ProviderBusinessProfileModel.fromJson(
        Map<String, dynamic>.from(results[0] as Map),
      );
      _myServices
        ..clear()
        ..addAll(
          (results[1] is List ? results[1] as List : const [])
              .whereType<Map>()
              .map((item) => serviceModelFromJson(Map<String, dynamic>.from(item))),
        );
      _requests
        ..clear()
        ..addAll(
          (results[2] is List ? results[2] as List : const [])
              .whereType<Map>()
              .map((item) => ProviderServiceRequestModel.fromJson(Map<String, dynamic>.from(item))),
        );
      _categories
        ..clear()
        ..addAll(
          (results[3] is List ? results[3] as List : const [])
              .whereType<Map>()
              .map((item) => ServiceCategoryModel.fromJson(Map<String, dynamic>.from(item)))
              .where((item) => item.supportsProviders && item.isActive),
        );
      _eventTypes
        ..clear()
        ..addAll(
          (results[4] is List ? results[4] as List : const [])
              .whereType<Map>()
              .map((item) => EventTypeOptionModel.fromJson(Map<String, dynamic>.from(item))),
        );
    } catch (exception) {
      error = exception.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<void> updateProfile({
    required String businessName,
    required String city,
    required List<String> coverageAreas,
    required Map<String, dynamic> workingHours,
    required List<String> daysOff,
    required String bio,
    required String contactPhone,
    required String whatsappPhone,
    required bool allowPhone,
    required bool allowWhatsapp,
  }) async {
    if (city.trim().isEmpty) throw const ApiException('المدينة مطلوبة.');
    if (bio.trim().length < 10) {
      throw const ApiException('اكتب نبذة واضحة من 10 أحرف على الأقل.');
    }
    if (allowPhone && contactPhone.trim().isEmpty) {
      throw const ApiException('أدخل رقم الاتصال أو أوقف خيار إظهاره.');
    }
    if (allowWhatsapp && whatsappPhone.trim().isEmpty) {
      throw const ApiException('أدخل رقم واتساب أو أوقف خيار إظهاره.');
    }

    isSavingProfile = true;
    notifyListeners();
    try {
      final data = await _api.put('/provider/profile', {
        'business_name': businessName.trim().isEmpty ? null : businessName.trim(),
        'city': city.trim(),
        'coverage_areas': coverageAreas,
        'working_hours': workingHours,
        'days_off': daysOff,
        'bio': bio.trim(),
        'contact_phone': contactPhone.trim().isEmpty ? null : contactPhone.trim(),
        'whatsapp_phone': whatsappPhone.trim().isEmpty ? null : whatsappPhone.trim(),
        'allow_phone': allowPhone,
        'allow_whatsapp': allowWhatsapp,
      });
      profile = ProviderBusinessProfileModel.fromJson(Map<String, dynamic>.from(data as Map));
    } finally {
      isSavingProfile = false;
      notifyListeners();
    }
  }

  /// Saves the service first, then uploads any newly selected gallery images.
  /// Returns true when the service was saved but one or more images failed.
  Future<bool> saveService({
    String? id,
    required String name,
    required String categoryId,
    required int priceSyp,
    required List<String> eventTypeIds,
    int? durationMinutes,
    String description = '',
    String emoji = '🧩',
    List<String> imagePaths = const [],
  }) async {
    if (name.trim().isEmpty) throw const ApiException('اسم الخدمة مطلوب.');
    if (description.trim().length < 10) {
      throw const ApiException('اكتب وصفاً واضحاً للخدمة من 10 أحرف على الأقل.');
    }
    if (int.tryParse(categoryId) == null) throw const ApiException('اختر تصنيف خدمة صالحاً.');
    if (priceSyp < 1) throw const ApiException('أدخل سعراً أكبر من صفر.');
    if (eventTypeIds.isEmpty) throw const ApiException('اختر نوع مناسبة واحداً على الأقل.');
    if (imagePaths.length > 6) throw const ApiException('يمكن اختيار 6 صور كحد أقصى.');

    ServiceCategoryModel? category;
    for (final item in _categories) {
      if (item.id == categoryId) {
        category = item;
        break;
      }
    }
    if (category == null || !category.supportsProviders || !category.isActive) {
      throw const ApiException('هذا التصنيف غير متاح لخدمات مقدمي الخدمة.');
    }

    final numericEventTypeIds = eventTypeIds.map(int.tryParse).whereType<int>().toList();
    if (numericEventTypeIds.length != eventTypeIds.length) {
      throw const ApiException('تعذر تحديد أنواع المناسبات المختارة.');
    }

    ServiceModel? existing;
    if (id != null) {
      for (final item in _myServices) {
        if (item.id == id) {
          existing = item;
          break;
        }
      }
    }
    final remainingSlots = 6 - (existing?.imageItems.length ?? 0);
    if (imagePaths.length > remainingSlots) {
      throw ApiException('يمكنك إضافة $remainingSlots صورة فقط؛ الحد الأقصى للخدمة هو 6 صور.');
    }

    final payload = {
      'name_ar': name.trim(),
      'name_en': name.trim(),
      'category': category.name,
      'category_id': int.parse(categoryId),
      'description_ar': description.trim(),
      'description_en': description.trim(),
      'emoji': emoji,
      'price_syp': priceSyp,
      'price_usd': 0,
      'pricing_unit': 'per_event',
      if (durationMinutes != null) 'duration_minutes': durationMinutes,
      'event_type_ids': numericEventTypeIds,
    };

    final data = id == null
        ? await _api.post('/provider/services', payload)
        : await _api.put('/provider/services/$id', payload);
    var service = serviceModelFromJson(Map<String, dynamic>.from(data as Map));
    _upsertService(service);

    var imageUploadFailed = false;
    if (imagePaths.isNotEmpty) {
      try {
        final uploadData = await _api.multipartPostFiles(
          '/provider/services/${service.id}/images',
          files: {
            'images[]': imagePaths.map(File.new).toList(),
          },
        );
        final uploadMap = Map<String, dynamic>.from(uploadData as Map);
        final serviceData = uploadMap['service'];
        if (serviceData is Map) {
          service = serviceModelFromJson(Map<String, dynamic>.from(serviceData));
          _upsertService(service);
        }
      } catch (_) {
        imageUploadFailed = true;
      }
    }

    notifyListeners();
    return imageUploadFailed;
  }

  Future<void> deleteServiceImage(String serviceId, String imageId) async {
    final data = await _api.delete('/provider/services/$serviceId/images/$imageId');
    _upsertService(serviceModelFromJson(Map<String, dynamic>.from(data as Map)));
    notifyListeners();
  }

  Future<void> setMainServiceImage(String serviceId, String imageId) async {
    final data = await _api.post('/provider/services/$serviceId/images/$imageId/main', {});
    _upsertService(serviceModelFromJson(Map<String, dynamic>.from(data as Map)));
    notifyListeners();
  }

  Future<void> reorderServiceImages(String serviceId, List<String> imageIds) async {
    final numericIds = imageIds.map(int.tryParse).whereType<int>().toList();
    if (numericIds.length != imageIds.length) throw const ApiException('ترتيب الصور غير صالح.');
    final data = await _api.post('/provider/services/$serviceId/images/reorder', {'image_ids': numericIds});
    _upsertService(serviceModelFromJson(Map<String, dynamic>.from(data as Map)));
    notifyListeners();
  }

  void _upsertService(ServiceModel service) {
    final index = _myServices.indexWhere((item) => item.id == service.id);
    if (index == -1) {
      _myServices.insert(0, service);
    } else {
      _myServices[index] = service;
    }
  }

  Future<void> disableService(String id) async {
    await _api.delete('/provider/services/$id');
    final index = _myServices.indexWhere((service) => service.id == id);
    if (index != -1) {
      _myServices[index] = _myServices[index].copyWith(isActive: false);
    }
    notifyListeners();
  }

  Future<void> acceptRequest(String id) async {
    final data = await _api.post('/provider/requests/$id/accept', {});
    _replaceRequest(ProviderServiceRequestModel.fromJson(Map<String, dynamic>.from(data as Map)));
  }

  Future<void> rejectRequest(String id, {required String reply}) async {
    if (reply.trim().isEmpty) throw const ApiException('سبب الرفض مطلوب.');
    final data = await _api.post('/provider/requests/$id/reject', {'reply': reply.trim()});
    _replaceRequest(ProviderServiceRequestModel.fromJson(Map<String, dynamic>.from(data as Map)));
  }

  void _replaceRequest(ProviderServiceRequestModel request) {
    final index = _requests.indexWhere((item) => item.id == request.id);
    if (index == -1) {
      _requests.insert(0, request);
    } else {
      _requests[index] = request;
    }
    notifyListeners();
  }
}
