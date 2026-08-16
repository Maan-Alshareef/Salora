import 'dart:io';

import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../core/network/api_config.dart';
import '../models/join_request_model.dart';
import '../models/user_role.dart';

class EmailOtpChallenge {
  const EmailOtpChallenge({
    required this.email,
    required this.maskedEmail,
    required this.expiresInSeconds,
    required this.resendAfterSeconds,
    this.mailSent = true,
    this.demoOtp,
  });

  final String email;
  final String maskedEmail;
  final int expiresInSeconds;
  final int resendAfterSeconds;
  final bool mailSent;
  final String? demoOtp;

  factory EmailOtpChallenge.fromMap(
    Map<String, dynamic> map, {
    required String fallbackEmail,
  }) {
    int asInt(dynamic value, int fallback) =>
        int.tryParse(value?.toString() ?? '') ?? fallback;
    return EmailOtpChallenge(
      email: (map['email'] ?? fallbackEmail).toString(),
      maskedEmail: (map['masked_email'] ?? fallbackEmail).toString(),
      expiresInSeconds: asInt(map['expires_in_seconds'], 600),
      resendAfterSeconds: asInt(map['resend_after_seconds'], 60),
      mailSent:
          map['mail_sent'] == null ||
          map['mail_sent'] == true ||
          map['mail_sent']?.toString() == '1',
      demoOtp: map['demo_otp']?.toString(),
    );
  }

  factory EmailOtpChallenge.pending(
    String email, {
    int resendAfterSeconds = 0,
  }) => EmailOtpChallenge(
    email: email,
    maskedEmail: email,
    expiresInSeconds: 600,
    resendAfterSeconds: resendAfterSeconds,
    mailSent: false,
  );
}

class AuthProvider extends ChangeNotifier {
  AuthProvider(this._api) {
    _api.onUnauthorized = _handleUnauthorized;
  }

  final ApiClient _api;

  String _userName = '';
  String _userEmail = '';
  String _phone = '';
  String? _profileImageUrl;
  bool _mustChangePassword = false;
  UserRole _role = UserRole.customer;
  bool _isLoggedIn = false;
  bool _isInitialized = false;
  Future<void>? _restoreFuture;

  bool isLoading = false;
  String? error;

  String get rawUserName => _userName;
  String get userName => _userName.trim().isEmpty ? 'مستخدم' : _userName;
  String get userEmail =>
      _userEmail.isEmpty ? 'لا يوجد بريد إلكتروني' : _userEmail;
  String get rawEmail => _userEmail;
  String get phone => _phone.isEmpty ? 'لا يوجد رقم هاتف' : _phone;
  String get rawPhone => _phone;
  String? get profileImageUrl => _profileImageUrl;
  UserRole get role => _role;
  bool get isLoggedIn => _isLoggedIn;
  bool get mustChangePassword => _mustChangePassword;
  bool get isInitialized => _isInitialized;

  Future<void> restoreSession() {
    return _restoreFuture ??= _restoreSessionInternal();
  }

  Future<void> _restoreSessionInternal() async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      await _api.restoreToken();
      if (!_api.isAuthenticated) return;
      final data = await _api.get('/auth/me');
      _applyUser(Map<String, dynamic>.from(data as Map));
      _isLoggedIn = true;
    } catch (e) {
      await _api.setToken(null);
      _clearUser();
      if (e is! ApiException || (e.statusCode != 401 && e.statusCode != 403)) {
        error = e.toString();
      }
    } finally {
      _isInitialized = true;
      isLoading = false;
      notifyListeners();
    }
  }

  Future<void> login({
    required String email,
    required String password,
    UserRole role = UserRole.customer,
  }) async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final data = await _api.post('/auth/login', {
        'email': email.trim(),
        'password': password,
        'client_type': 'mobile',
        'expected_role': role.name,
      });
      await _establishSession(Map<String, dynamic>.from(data as Map));
    } catch (e) {
      error = e.toString();
      rethrow;
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<EmailOtpChallenge> register({
    required String name,
    required String email,
    required String phone,
    required String password,
    required String passwordConfirmation,
  }) async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final normalizedEmail = email.trim().toLowerCase();
      final data = await _api.post('/auth/register', {
        'name': name.trim(),
        'email': normalizedEmail,
        'phone': phone.trim(),
        'password': password,
        'password_confirmation': passwordConfirmation,
      });
      return EmailOtpChallenge.fromMap(
        Map<String, dynamic>.from(data as Map),
        fallbackEmail: normalizedEmail,
      );
    } catch (e) {
      error = e.toString();
      rethrow;
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<void> verifyEmail({required String email, required String otp}) async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final data = await _api.post('/auth/verify-email', {
        'email': email.trim().toLowerCase(),
        'otp': otp.trim(),
      });
      await _establishSession(Map<String, dynamic>.from(data as Map));
    } catch (e) {
      error = e.toString();
      rethrow;
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<EmailOtpChallenge> resendEmailVerification(String email) async {
    final normalizedEmail = email.trim().toLowerCase();
    final data = await _api.post('/auth/resend-verification', {
      'email': normalizedEmail,
    });
    return EmailOtpChallenge.fromMap(
      Map<String, dynamic>.from(data as Map),
      fallbackEmail: normalizedEmail,
    );
  }

  Future<EmailOtpChallenge> requestPasswordReset(String email) async {
    final normalizedEmail = email.trim().toLowerCase();
    final data = await _api.post('/auth/forgot-password', {
      'email': normalizedEmail,
    });
    return EmailOtpChallenge.fromMap(
      Map<String, dynamic>.from(data as Map),
      fallbackEmail: normalizedEmail,
    );
  }

  Future<void> resetPassword({
    required String email,
    required String otp,
    required String password,
    required String passwordConfirmation,
  }) async {
    await _api.post('/auth/reset-password', {
      'email': email.trim().toLowerCase(),
      'otp': otp.trim(),
      'password': password,
      'password_confirmation': passwordConfirmation,
    });
  }

  Future<void> _establishSession(Map<String, dynamic> map) async {
    final token = map['token']?.toString();
    if (token == null || token.isEmpty || map['user'] is! Map) {
      throw const ApiException('استجابة المصادقة غير مكتملة.');
    }
    await _api.setToken(token);
    _applyUser(Map<String, dynamic>.from(map['user'] as Map));
    _isLoggedIn = true;
    _isInitialized = true;
  }

  void _applyUser(Map<String, dynamic> user) {
    _userName = (user['name'] ?? '').toString();
    _userEmail = (user['email'] ?? '').toString();
    _phone = (user['phone'] ?? '').toString();
    _profileImageUrl = ApiConfig.resolveAssetUrl(
      (user['avatar_url'] ?? user['avatar'])?.toString(),
    );
    _role = _roleFromApi((user['role'] ?? 'customer').toString());
    _mustChangePassword =
        user['must_change_password'] == true ||
        user['must_change_password']?.toString() == '1';
  }

  UserRole _roleFromApi(String role) {
    switch (role.toLowerCase()) {
      case 'admin':
        return UserRole.admin;
      case 'owner':
        return UserRole.owner;
      case 'provider':
        return UserRole.provider;
      default:
        return UserRole.customer;
    }
  }

  Future<EmailOtpChallenge> requestBusinessJoinOtp(String email) async {
    final normalizedEmail = email.trim().toLowerCase();
    if (isLoggedIn && normalizedEmail == rawEmail.trim().toLowerCase()) {
      throw const ApiException(
        'بريد حساب العمل يجب أن يكون مختلفاً عن بريد حساب العميل.',
      );
    }
    final path = isLoggedIn && role == UserRole.customer
        ? '/customer/join-requests/request-otp'
        : '/business-applications/request-otp';
    final data = await _api.post(path, {'email': normalizedEmail});
    return EmailOtpChallenge.fromMap(
      Map<String, dynamic>.from(data as Map),
      fallbackEmail: normalizedEmail,
    );
  }

  Future<JoinRequestModel> submitBusinessJoinRequest({
    required String requestType,
    required String fullName,
    required String businessEmail,
    required String otp,
    required String phone,
    required String city,
    String hallName = '',
    String address = '',
    String serviceCategoryId = '',
    String serviceDescription = '',
    String notes = '',
  }) async {
    if (requestType != 'owner' && requestType != 'provider') {
      throw const ApiException('نوع طلب الانضمام غير صحيح.');
    }
    final path = isLoggedIn && role == UserRole.customer
        ? '/customer/join-requests'
        : '/business-applications';
    final data = await _api.post(path, {
      'request_type': requestType,
      'full_name': fullName.trim(),
      'email': businessEmail.trim().toLowerCase(),
      'otp': otp.trim(),
      'phone': phone.trim(),
      'city': city.trim().isEmpty ? null : city.trim(),
      if (requestType == 'owner')
        'hall_name': hallName.trim().isEmpty ? null : hallName.trim(),
      if (requestType == 'owner')
        'address': address.trim().isEmpty ? null : address.trim(),
      if (requestType == 'provider')
        'service_category_id': int.tryParse(serviceCategoryId),
      if (requestType == 'provider')
        'service_description': serviceDescription.trim().isEmpty
            ? null
            : serviceDescription.trim(),
      'notes': notes.trim().isEmpty ? null : notes.trim(),
    });
    return JoinRequestModel.fromJson(Map<String, dynamic>.from(data as Map));
  }

  Future<List<JoinRequestModel>> loadMyJoinRequests() async {
    if (!isLoggedIn || role != UserRole.customer) return const [];
    final data = await _api.get('/customer/join-requests');
    final list = data is List ? data : const [];
    return list
        .whereType<Map>()
        .map(
          (item) => JoinRequestModel.fromJson(Map<String, dynamic>.from(item)),
        )
        .toList();
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    final data = await _api.post('/auth/change-password', {
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': newPassword,
    });
    if (data is Map) _applyUser(Map<String, dynamic>.from(data));
    _mustChangePassword = false;
    notifyListeners();
  }

  Future<void> updateProfile({
    required String name,
    required String phone,
  }) async {
    final data = await _api.put('/auth/profile', {
      'name': name.trim(),
      'phone': phone.trim(),
    });
    _applyUser(Map<String, dynamic>.from(data as Map));
    notifyListeners();
  }

  Future<void> uploadAvatar(File image) async {
    final data = await _api.multipartPost(
      '/auth/profile/avatar',
      fields: const {},
      fileField: 'image',
      file: image,
    );
    _applyUser(Map<String, dynamic>.from(data as Map));
    notifyListeners();
  }

  Future<void> deleteAvatar() async {
    final data = await _api.delete('/auth/profile/avatar');
    if (data is Map)
      _applyUser(Map<String, dynamic>.from(data));
    else
      _profileImageUrl = null;
    notifyListeners();
  }

  Future<EmailOtpChallenge> requestEmailChange(String email) async {
    final normalized = email.trim().toLowerCase();
    final data = await _api.post('/auth/email-change/request', {
      'email': normalized,
      'new_email': normalized,
    });
    return EmailOtpChallenge.fromMap(
      Map<String, dynamic>.from(data as Map),
      fallbackEmail: normalized,
    );
  }

  Future<void> verifyEmailChange(String email, String otp) async {
    final normalized = email.trim().toLowerCase();
    final data = await _api.post('/auth/email-change/verify', {
      'email': normalized,
      'new_email': normalized,
      'otp': otp.trim(),
      'code': otp.trim(),
    });

    final map = Map<String, dynamic>.from(data as Map);
    final nestedUser = map['user'];
    if (nestedUser is Map) {
      _applyUser(Map<String, dynamic>.from(nestedUser));
    } else {
      await refreshProfile();
    }
    notifyListeners();
  }

  Future<void> refreshProfile() async {
    final data = await _api.get('/auth/me');
    _applyUser(Map<String, dynamic>.from(data as Map));
    notifyListeners();
  }

  Future<void> _handleUnauthorized() async {
    _clearUser();
    error = 'انتهت الجلسة أو تم تعطيل الحساب. سجّل الدخول مجدداً.';
    notifyListeners();
  }

  void _clearUser() {
    _userName = '';
    _userEmail = '';
    _phone = '';
    _profileImageUrl = null;
    _mustChangePassword = false;
    _role = UserRole.customer;
    _isLoggedIn = false;
  }

  Future<void> logout() async {
    try {
      if (_api.isAuthenticated) await _api.post('/auth/logout', {});
    } catch (_) {
      // Local session cleanup must still occur if the server is unavailable.
    }
    await _api.setToken(null);
    _clearUser();
    notifyListeners();
  }
}
