import 'package:flutter/material.dart';
import '../models/venue_model.dart';

class FavoriteProvider extends ChangeNotifier {
  final List<VenueModel> _favorites = [];
  List<VenueModel> get favorites => List.unmodifiable(_favorites);

  void toggleFavorite(VenueModel venue) {
    final exists = _favorites.any((item) => item.id == venue.id);
    if (exists) {
      _favorites.removeWhere((item) => item.id == venue.id);
    } else {
      _favorites.add(venue);
    }
    notifyListeners();
  }

  bool isFavorite(String venueId) => _favorites.any((item) => item.id == venueId);
}
