import 'venue_model.dart';

/// Backward-compatible model for old files that import models/venue.dart
/// and create Venue(id, name, location, image, price, rating, capacity).
class Venue extends VenueModel {
  Venue({
    required String id,
    required String name,
    String? city,
    String? location,
    String image = '',
    required int price,
    required double rating,
    required int capacity,
    String address = '',
    String description = 'صالة جميلة للمناسبات الخاصة.',
    List<String> services = const [],
    List<String> amenities = const [],
    List<String> eventTypes = const [],
    int reviewsCount = 0,
    bool hasOffer = false,
    int? discountPercentage,
    double latitude = 0,
    double longitude = 0,
  }) : super(
          id: id,
          name: name,
          city: city ?? location ?? '',
          address: address == '' ? (city ?? location ?? '') : address,
          images: image == '' ? const [] : [image],
          price: price,
          rating: rating,
          reviewsCount: reviewsCount,
          capacity: capacity,
          description: description,
          services: services,
          amenities: amenities,
          eventTypes: eventTypes,
          hasOffer: hasOffer,
          discountPercentage: discountPercentage,
          latitude: latitude,
          longitude: longitude,
        );
}
