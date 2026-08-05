import 'dart:io';

import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../models/booking_model.dart';
import '../models/invoice_item.dart';
import '../models/venue_availability_model.dart';
import '../models/venue_model.dart';

class BookingProvider extends ChangeNotifier {
  BookingProvider(this._api);

  final ApiClient _api;
  final List<BookingModel> _bookings = [];
  bool isLoading = false;
  String? error;
  final Map<String, VenueDayAvailability> _availability = {};
  final Set<String> _loadingAvailability = {};

  List<BookingModel> get bookings => List.unmodifiable(_bookings);
  List<BookingModel> get activeForProviderServices => _bookings
      .where((booking) => booking.isActiveForProviderServices)
      .toList(growable: false);

  BookingModel? completedBookingForVenue(String venueId) {
    for (final booking in _bookings) {
      if (booking.venueId == venueId &&
          booking.status == BookingStatus.completed)
        return booking;
    }
    return null;
  }

  String _availabilityKey(String venueId, DateTime date) =>
      '$venueId:${date.toIso8601String().split('T').first}';

  VenueDayAvailability? availabilityFor(String venueId, DateTime date) =>
      _availability[_availabilityKey(venueId, date)];

  bool isLoadingAvailability(String venueId, DateTime date) =>
      _loadingAvailability.contains(_availabilityKey(venueId, date));

  Future<VenueDayAvailability> loadVenueAvailability({
    required String venueId,
    required DateTime date,
    bool force = false,
  }) async {
    if (!_api.isAuthenticated)
      throw const ApiException('الرجاء تسجيل الدخول أولاً.');
    final key = _availabilityKey(venueId, date);
    if (!force && _availability[key] != null) return _availability[key]!;

    _loadingAvailability.add(key);
    notifyListeners();
    try {
      final data = await _api.get(
        '/venues/$venueId/availability',
        query: {'date': date.toIso8601String().split('T').first},
      );
      final availability = VenueDayAvailability.fromJson(
        Map<String, dynamic>.from(data as Map),
      );
      _availability[key] = availability;
      return availability;
    } finally {
      _loadingAvailability.remove(key);
      notifyListeners();
    }
  }

  Future<void> uploadProviderServiceReceipt(
    String requestId,
    String path, {
    String paymentMethod = 'sham_cash',
  }) async {
    if (!_api.isAuthenticated || int.tryParse(requestId) == null) {
      throw const ApiException(
        'لا يمكن رفع إيصال دفع الخدمة قبل تسجيل الدخول.',
      );
    }

    const allowedMethods = {'sham_cash', 'syriatel_cash', 'al_haram'};
    final resolvedMethod = allowedMethods.contains(paymentMethod)
        ? paymentMethod
        : 'sham_cash';

    await _api.multipartPost(
      '/customer/provider-service-requests/$requestId/payment-proof',
      fileField: 'image',
      file: File(path),
      fields: {'payment_method': resolvedMethod},
    );
    await loadMyBookings();
  }

  Future<void> loadMyBookings() async {
    if (!_api.isAuthenticated) {
      _bookings.clear();
      notifyListeners();
      return;
    }
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final data = await _api.get('/customer/bookings');
      final list = data is List ? data : const [];
      _bookings
        ..clear()
        ..addAll(
          list.whereType<Map>().map(
            (item) => BookingModel.fromJson(Map<String, dynamic>.from(item)),
          ),
        );
    } catch (e) {
      error = e.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<BookingModel> createBooking({
    required VenueModel venue,
    String? eventId,
    String? eventTypeId,
    String eventTitle = 'مناسبة',
    String? hostName,
    String? notes,
    required DateTime date,
    required String startTime,
    required String endTime,
    required String eventType,
    required int guests,
    List<String> services = const [],
    List<InvoiceItem> hallExtraServices = const [],
    required PaymentMethod paymentMethod,
  }) async {
    if (!_api.isAuthenticated)
      throw const ApiException('الرجاء تسجيل الدخول قبل إنشاء الحجز.');

    final resolvedEventTypeId = int.tryParse(
      eventTypeId ?? venue.eventTypeIdFor(eventType) ?? '',
    );
    if (resolvedEventTypeId == null) {
      throw const ApiException(
        'نوع المناسبة غير مرتبط بهذه الصالة في قاعدة البيانات.',
      );
    }

    final data = await _api.post('/customer/bookings', {
      if (eventId != null && int.tryParse(eventId) != null)
        'event_id': int.parse(eventId),
      'venue_id': int.tryParse(venue.id) ?? venue.id,
      'event_type_id': resolvedEventTypeId,
      'event_name': eventTitle,
      if (hostName != null && hostName.trim().isNotEmpty)
        'host_name': hostName.trim(),
      'event_date': date.toIso8601String().split('T').first,
      'start_time': _apiClock(startTime),
      'end_time': _apiClock(endTime),
      'guests_count': guests,
      'service_ids': hallExtraServices
          .map((item) => int.tryParse(item.id))
          .whereType<int>()
          .toList(),
      if (notes != null && notes.trim().isNotEmpty)
        'notes': notes.trim()
      else if (services.isNotEmpty)
        'notes': services.join(', '),
      'currency': 'SYP',
    });

    final booking = BookingModel.fromJson(
      Map<String, dynamic>.from(data as Map),
    );
    _bookings.insert(0, booking);
    error = null;
    notifyListeners();
    return booking;
  }

  Future<void> uploadReceipt(
    String bookingId,
    String path, {
    String paymentMethod = 'bank_transfer',
  }) async {
    if (!_api.isAuthenticated || int.tryParse(bookingId) == null) {
      throw const ApiException(
        'لا يمكن رفع إيصال الدفع قبل تسجيل الدخول أو حفظ الحجز.',
      );
    }

    await _api.multipartPost(
      '/customer/bookings/$bookingId/payment-proof',
      fileField: 'image',
      file: File(path),
      fields: {'payment_method': paymentMethod},
    );

    final index = _bookings.indexWhere((booking) => booking.id == bookingId);
    if (index != -1) {
      _bookings[index] = _bookings[index].copyWith(
        receiptPath: path,
        status: BookingStatus.paymentUploaded,
      );
      notifyListeners();
    }
    await loadMyBookings();
  }

  Future<void> cancelBooking(String bookingId, {required String reason}) async {
    final index = _bookings.indexWhere((booking) => booking.id == bookingId);
    if (index == -1) throw const ApiException('تعذر العثور على الحجز.');
    final booking = _bookings[index];

    if (booking.status.canRequestCancellation) {
      await _api.post('/customer/bookings/$bookingId/change-requests', {
        'type': 'cancellation',
        'reason': reason.trim(),
      });
      _bookings[index] = booking.copyWith(
        status: BookingStatus.cancellationRequested,
      );
    } else if (booking.status.canCancelDirectly) {
      final data = await _api.post('/customer/bookings/$bookingId/cancel', {
        'reason': reason.trim(),
      });
      _bookings[index] = BookingModel.fromJson(
        Map<String, dynamic>.from(data as Map),
      );
    } else {
      throw const ApiException(
        'حالة الحجز الحالية لا تسمح بإرسال طلب إلغاء جديد.',
      );
    }
    notifyListeners();
  }

  Future<void> requestProviderServices({
    required String bookingId,
    required List<String> serviceIds,
    String? notes,
  }) async {
    final numericIds = serviceIds.map(int.tryParse).whereType<int>().toList();
    if (int.tryParse(bookingId) == null || numericIds.isEmpty) {
      throw const ApiException('بيانات الحجز أو الخدمة غير صحيحة.');
    }

    final data = await _api
        .post('/customer/bookings/$bookingId/provider-services', {
          'provider_service_ids': numericIds,
          if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
        });
    final updated = BookingModel.fromJson(
      Map<String, dynamic>.from(data as Map),
    );
    final index = _bookings.indexWhere((booking) => booking.id == bookingId);
    if (index == -1) {
      _bookings.insert(0, updated);
    } else {
      _bookings[index] = updated;
    }
    notifyListeners();
  }

  Future<void> requestModification({
    required String bookingId,
    required DateTime date,
    required String startTime,
    required String endTime,
    required int guests,
    required String reason,
  }) async {
    await _api.post('/customer/bookings/$bookingId/change-requests', {
      'type': 'modification',
      'reason': reason.trim(),
      'event_date': date.toIso8601String().split('T').first,
      'start_time': _apiClock(startTime),
      'end_time': _apiClock(endTime),
      'guests_count': guests,
    });
    final index = _bookings.indexWhere((booking) => booking.id == bookingId);
    if (index != -1) {
      _bookings[index] = _bookings[index].copyWith(
        status: BookingStatus.modificationRequested,
      );
      notifyListeners();
    }
  }
}

String _apiClock(String value) {
  final text = value.trim();
  // Keep the clock written by the API. DateTime.parse would convert +03:00
  // ISO values to UTC and could send a different hour to Laravel.
  final match = RegExp(r'(\d{1,2}):(\d{2})').firstMatch(text);
  if (match == null) return text;
  return '${match.group(1)!.padLeft(2, '0')}:${match.group(2)}';
}
