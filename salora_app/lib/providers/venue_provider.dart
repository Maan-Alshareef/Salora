import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../data/repositories/venue_repository.dart';
import '../models/venue_model.dart';

class VenueProvider extends ChangeNotifier {
  VenueProvider(this._repository);

  final VenueRepository _repository;

  bool isLoading = false;
  bool isSearching = false;
  String? error;
  String? searchError;
  List<VenueModel> venues = [];
  List<VenueModel> filteredVenues = [];

  int _searchRequestId = 0;

  Future<void> loadVenues() async {
    if (isLoading) return;

    isLoading = true;
    error = null;
    notifyListeners();

    try {
      final result = await _repository.getVenues();
      venues = result;
      filteredVenues = List<VenueModel>.from(result);
    } catch (exception) {
      error = _messageFrom(exception, 'تعذر تحميل الصالات');
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<void> searchVenues({
    String query = '',
    String? city,
    String? eventType,
    int? minGuests,
    int? maxPrice,
    double? minRating,
    bool hasOfferOnly = false,
    String sort = 'الموصى بها',
  }) async {
    final requestId = ++_searchRequestId;
    isSearching = true;
    searchError = null;
    notifyListeners();

    try {
      final result = await _repository.getVenues(
        query: query,
        city: city,
        eventType: eventType,
        minGuests: minGuests,
        maxPrice: maxPrice,
        minRating: minRating,
        hasOfferOnly: hasOfferOnly,
        sort: sort,
      );

      if (requestId != _searchRequestId) return;
      filteredVenues = result;
    } catch (exception) {
      if (requestId != _searchRequestId) return;
      searchError = _messageFrom(exception, 'تعذر تطبيق فلاتر الصالات');
    } finally {
      if (requestId == _searchRequestId) {
        isSearching = false;
        notifyListeners();
      }
    }
  }

  void showAllLoadedVenues() {
    _searchRequestId++;
    filteredVenues = List<VenueModel>.from(venues);
    isSearching = false;
    searchError = null;
    notifyListeners();
  }

  // Kept for compatibility with any older widgets that still perform a local
  // filter. The main search screen now uses searchVenues() and the API.
  List<VenueModel> search({
    String query = '',
    String? city,
    String? eventType,
    int? minGuests,
    int? maxPrice,
    double? minRating,
    bool hasOfferOnly = false,
    String sort = 'الموصى بها',
  }) {
    var result = venues.where((venue) {
      final q = query.trim().toLowerCase();
      final matchesQuery =
          q.isEmpty ||
          venue.name.toLowerCase().contains(q) ||
          venue.city.toLowerCase().contains(q);
      final matchesCity = city == null || city == 'الكل' || venue.city == city;
      final matchesEvent =
          eventType == null ||
          eventType == 'الكل' ||
          venue.eventTypes.contains(eventType);
      final matchesGuests = minGuests == null || venue.capacity >= minGuests;
      final matchesPrice = maxPrice == null || venue.finalPrice <= maxPrice;
      final matchesRating = minRating == null || venue.rating >= minRating;
      final matchesOffer = !hasOfferOnly || venue.hasOffer;
      return matchesQuery &&
          matchesCity &&
          matchesEvent &&
          matchesGuests &&
          matchesPrice &&
          matchesRating &&
          matchesOffer;
    }).toList();

    switch (sort) {
      case 'الأقل سعرًا':
        result.sort((a, b) => a.finalPrice.compareTo(b.finalPrice));
        break;
      case 'الأعلى تقييمًا':
        result.sort((a, b) => b.rating.compareTo(a.rating));
        break;
      case 'الأكبر سعة':
        result.sort((a, b) => b.capacity.compareTo(a.capacity));
        break;
    }
    return result;
  }

  String _messageFrom(Object exception, String fallback) {
    if (exception is ApiException && exception.message.trim().isNotEmpty) {
      return exception.message;
    }
    return fallback;
  }
}
