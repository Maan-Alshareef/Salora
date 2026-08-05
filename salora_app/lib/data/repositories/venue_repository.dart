import '../../core/network/api_client.dart';
import '../../models/venue_model.dart';

abstract class VenueRepository {
  Future<List<VenueModel>> getVenues({
    String query = '',
    String? city,
    String? eventType,
    int? minGuests,
    int? maxPrice,
    double? minRating,
    bool hasOfferOnly = false,
    String sort = 'الموصى بها',
    int perPage = 100,
  });

  Future<VenueModel> getVenueDetails(String id);
}

class RemoteVenueRepository implements VenueRepository {
  RemoteVenueRepository(this._api);

  final ApiClient _api;

  @override
  Future<List<VenueModel>> getVenues({
    String query = '',
    String? city,
    String? eventType,
    int? minGuests,
    int? maxPrice,
    double? minRating,
    bool hasOfferOnly = false,
    String sort = 'الموصى بها',
    int perPage = 100,
  }) async {
    final normalizedCity = city?.trim();
    final normalizedEventType = eventType?.trim();

    final data = await _api.get(
      '/venues',
      query: {
        'search': query.trim(),
        if (normalizedCity != null &&
            normalizedCity.isNotEmpty &&
            normalizedCity != 'الكل')
          'city': normalizedCity,
        if (normalizedEventType != null &&
            normalizedEventType.isNotEmpty &&
            normalizedEventType != 'الكل')
          'event_type': normalizedEventType,
        'min_capacity': minGuests,
        'max_price_syp': maxPrice,
        'min_rating': minRating,
        if (hasOfferOnly) 'has_offer': 1,
        'sort': _sortForApi(sort),
        'per_page': perPage.clamp(1, 100),
      },
    );

    final list = data is Map && data['data'] is List
        ? data['data'] as List
        : (data is List ? data : const []);

    return list
        .whereType<Map>()
        .map((item) => VenueModel.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  @override
  Future<VenueModel> getVenueDetails(String id) async {
    final data = await _api.get('/venues/$id');
    return VenueModel.fromJson(Map<String, dynamic>.from(data as Map));
  }

  String _sortForApi(String value) {
    switch (value) {
      case 'الأقل سعرًا':
        return 'price_asc';
      case 'الأعلى تقييمًا':
        return 'rating';
      case 'الأكبر سعة':
        return 'capacity';
      default:
        return 'latest';
    }
  }
}
