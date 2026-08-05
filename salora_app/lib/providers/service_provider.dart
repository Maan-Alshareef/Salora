import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../core/utils/arabic_text.dart';
import '../models/provider_directory_model.dart';
import '../models/service_category_model.dart';
import '../models/service_model.dart';

class ServiceProviderState extends ChangeNotifier {
  ServiceProviderState(this._api);

  final ApiClient _api;
  final List<ProviderDirectoryModel> _providers = [];
  final List<ServiceCategoryModel> _categories = [];

  bool isLoading = false;
  String? error;
  String selectedCategoryId = '';
  String searchQuery = '';

  List<ProviderDirectoryModel> get providers => List.unmodifiable(_providers);
  List<ServiceCategoryModel> get categoryModels => List.unmodifiable(_categories);
  List<ServiceModel> get services => List.unmodifiable(_providers.expand((provider) => provider.services));
  List<String> get categories => _categories.map((item) => item.name).toList(growable: false);

  Future<void> loadServices({String? categoryId, String? query}) => loadDirectory(categoryId: categoryId, query: query);

  Future<void> loadDirectory({String? categoryId, String? query}) async {
    if (!_api.isAuthenticated) {
      _providers.clear();
      _categories.clear();
      error = null;
      notifyListeners();
      return;
    }

    if (categoryId != null) selectedCategoryId = categoryId;
    if (query != null) searchQuery = query;
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final results = await Future.wait([
        _api.get('/providers', query: {
          'per_page': 100,
          if (selectedCategoryId.isNotEmpty) 'category_id': selectedCategoryId,
          if (searchQuery.trim().isNotEmpty) 'q': searchQuery.trim(),
          'sort': 'rating',
        }),
        _api.get('/service-categories', query: {'for': 'provider'}),
      ]);

      final providerPayload = results[0];
      final providerList = providerPayload is Map && providerPayload['data'] is List
          ? providerPayload['data'] as List
          : (providerPayload is List ? providerPayload : const []);
      _providers
        ..clear()
        ..addAll(providerList.whereType<Map>().map(
              (item) => ProviderDirectoryModel.fromJson(Map<String, dynamic>.from(item)),
            ));

      final categoryPayload = results[1];
      final categoryList = categoryPayload is List ? categoryPayload : const [];
      _categories
        ..clear()
        ..addAll(categoryList.whereType<Map>().map(
              (item) => ServiceCategoryModel.fromJson(Map<String, dynamic>.from(item)),
            ).where((item) => item.supportsProviders));
    } catch (exception) {
      _providers.clear();
      error = exception.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<ProviderDirectoryModel> loadProvider(String id) async {
    final data = await _api.get('/providers/$id');
    final provider = ProviderDirectoryModel.fromJson(Map<String, dynamic>.from(data as Map));
    final index = _providers.indexWhere((item) => item.id == provider.id);
    if (index == -1) {
      _providers.add(provider);
    } else {
      _providers[index] = provider;
    }
    notifyListeners();
    return provider;
  }

  List<ServiceModel> byCategory(String category) {
    if (category == 'الكل') return services;
    return services.where((service) => service.category == category).toList(growable: false);
  }

  List<ServiceModel> byEventType(String eventType, {List<String> allowedCategories = const []}) {
    final allowedArabic = allowedCategories.map(_normalizeCategory).where((value) => value.isNotEmpty).toSet();
    return services.where((service) {
      if (!service.supportsEvent(eventType)) return false;
      if (allowedArabic.isEmpty) return true;
      return allowedArabic.contains(_normalizeCategory(service.category));
    }).toList(growable: false);
  }
}

String _normalizeCategory(String value) {
  final text = ArabicText.tr(value).trim();
  if (text.isEmpty) return '';
  final cleaned = text.replaceAll(RegExp(r'^[^A-Za-zأ-ي]+\s*'), '').trim();
  final lower = cleaned.toLowerCase();
  if (lower.contains('photo') || cleaned.contains('تصوير')) return 'تصوير';
  if (lower.contains('decor') || cleaned.contains('ديكور')) return 'ديكور';
  if (lower.contains('food') || lower.contains('hospitality') || cleaned.contains('ضيافة') || cleaned.contains('مأكولات')) return 'ضيافة';
  if (lower.contains('lighting') || lower.contains('sound') || cleaned.contains('إضاءة') || cleaned.contains('صوت')) return 'إضاءة وصوت';
  if (lower.contains('cake') || cleaned.contains('كيك')) return 'كيك';
  if (lower.contains('reader') || lower.contains('sheikh') || cleaned.contains('قارئ') || cleaned.contains('شيخ')) return 'قارئ / شيخ';
  return cleaned;
}
