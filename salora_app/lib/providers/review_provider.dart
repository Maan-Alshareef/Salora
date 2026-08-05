import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../models/review_model.dart';

class ReviewProvider extends ChangeNotifier {
  ReviewProvider(this._api);

  final ApiClient _api;
  final List<ReviewModel> _reviews = [];
  bool isLoading = false;
  bool isSubmitting = false;
  String? error;

  List<ReviewModel> reviewsForVenue(String venueId) =>
      _reviews.where((review) => review.venueId == venueId).toList(growable: false);

  Future<void> loadVenueReviews(String venueId) async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final data = await _api.get('/venues/$venueId/reviews');
      final list = data is List ? data : const [];
      _reviews.removeWhere((review) => review.venueId == venueId);
      _reviews.addAll(list.whereType<Map>().map((item) => ReviewModel.fromJson(Map<String, dynamic>.from(item))));
    } catch (e) {
      error = e.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<ReviewModel> addReview({
    required String venueId,
    required String bookingId,
    required int rating,
    required String comment,
  }) async {
    if (!_api.isAuthenticated) throw const ApiException('الرجاء تسجيل الدخول قبل إضافة التقييم.');
    if (int.tryParse(venueId) == null || int.tryParse(bookingId) == null) {
      throw const ApiException('بيانات الحجز أو الصالة غير صحيحة.');
    }

    isSubmitting = true;
    error = null;
    notifyListeners();
    try {
      final data = await _api.post('/customer/reviews', {
        'venue_id': int.parse(venueId),
        'booking_id': int.parse(bookingId),
        'rating': rating.clamp(1, 5),
        'comment': comment.trim().isEmpty ? null : comment.trim(),
      });
      final review = ReviewModel.fromJson(Map<String, dynamic>.from(data as Map));
      _reviews.insert(0, review);
      return review;
    } catch (e) {
      error = e.toString();
      rethrow;
    } finally {
      isSubmitting = false;
      notifyListeners();
    }
  }
}
