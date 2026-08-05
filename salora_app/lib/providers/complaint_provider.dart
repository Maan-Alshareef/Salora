import 'dart:io';

import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../models/complaint_model.dart';

class ComplaintProvider extends ChangeNotifier {
  ComplaintProvider(this._api);

  final ApiClient _api;
  final List<ComplaintModel> _complaints = [];
  List<ComplaintModel> get complaints => List.unmodifiable(_complaints);
  bool isLoading = false;
  bool isSubmitting = false;
  String? error;

  Future<void> loadComplaints() async {
    if (!_api.isAuthenticated) return;
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final data = await _api.get('/customer/complaints');
      final list = data is List ? data : const [];
      _complaints
        ..clear()
        ..addAll(list.whereType<Map>().map((item) => ComplaintModel.fromJson(Map<String, dynamic>.from(item))));
    } catch (e) {
      error = e.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<ComplaintModel> submitComplaint({
    required String subject,
    required String category,
    required String description,
    String bookingId = '',
    String venueId = '',
    File? attachment,
  }) async {
    if (!_api.isAuthenticated) throw const ApiException('الرجاء تسجيل الدخول قبل إرسال الشكوى.');
    isSubmitting = true;
    error = null;
    notifyListeners();
    try {
      dynamic data;
      final fields = <String, String>{
        'category': category,
        'subject': subject.trim(),
        'message': description.trim(),
        if (int.tryParse(bookingId) != null) 'booking_id': bookingId,
        if (int.tryParse(venueId) != null) 'venue_id': venueId,
      };
      if (attachment != null) {
        data = await _api.multipartPost(
          '/customer/complaints',
          fields: fields,
          fileField: 'attachments[]',
          file: attachment,
        );
      } else {
        data = await _api.post('/customer/complaints', fields);
      }
      final complaint = ComplaintModel.fromJson(Map<String, dynamic>.from(data as Map));
      _complaints.insert(0, complaint);
      return complaint;
    } catch (e) {
      error = e.toString();
      rethrow;
    } finally {
      isSubmitting = false;
      notifyListeners();
    }
  }
}
