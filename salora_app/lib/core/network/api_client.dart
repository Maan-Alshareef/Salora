import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import 'api_config.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final dynamic errors;
  final String? code;

  const ApiException(this.message, {this.statusCode, this.errors, this.code});

  @override
  String toString() => message;
}

typedef UnauthorizedHandler = FutureOr<void> Function();

class ApiClient {
  ApiClient({String? baseUrl, FlutterSecureStorage? secureStorage})
      : baseUrl = (baseUrl ?? ApiConfig.baseUrl).replaceFirst(RegExp(r'/$'), ''),
        _secureStorage = secureStorage ?? const FlutterSecureStorage();

  static const _tokenKey = 'salora_access_token';

  final String baseUrl;
  final FlutterSecureStorage _secureStorage;
  String? _token;
  UnauthorizedHandler? onUnauthorized;

  String? get token => _token;
  bool get isAuthenticated => _token != null && _token!.isNotEmpty;

  Future<void> restoreToken() async {
    _token = await _secureStorage.read(key: _tokenKey);
  }

  Future<void> setToken(String? token) async {
    final normalized = token?.trim();
    _token = normalized == null || normalized.isEmpty ? null : normalized;
    if (_token == null) {
      await _secureStorage.delete(key: _tokenKey);
    } else {
      await _secureStorage.write(key: _tokenKey, value: _token);
    }
  }

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final normalizedPath = path.startsWith('/') ? path : '/$path';
    final uri = Uri.parse('$baseUrl$normalizedPath');
    if (query == null || query.isEmpty) return uri;
    return uri.replace(queryParameters: {
      ...uri.queryParameters,
      ...query.entries
          .where((entry) => entry.value != null && entry.value.toString().isNotEmpty)
          .fold<Map<String, String>>({}, (result, entry) {
        result[entry.key] = entry.value.toString();
        return result;
      }),
    });
  }

  Map<String, String> _headers({bool jsonBody = true}) => {
        'Accept': 'application/json',
        if (jsonBody) 'Content-Type': 'application/json',
        if (isAuthenticated) 'Authorization': 'Bearer $_token',
      };

  Future<http.Response> _withTimeout(Future<http.Response> request) async {
    try {
      return await request.timeout(ApiConfig.requestTimeout);
    } on TimeoutException {
      throw const ApiException('انتهت مهلة الاتصال بالخادم. تحقق من الشبكة ثم أعد المحاولة.', statusCode: 408);
    } on SocketException {
      throw const ApiException('تعذر الوصول إلى الخادم. تحقق من رابط API واتصال الشبكة.');
    } on http.ClientException {
      throw const ApiException('تعذر الاتصال بالخادم.');
    }
  }

  Future<dynamic> _unwrap(http.Response response) async {
    dynamic body;
    try {
      body = response.body.isEmpty ? null : jsonDecode(response.body);
    } catch (_) {
      body = response.body;
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final bodyMap = body is Map ? Map<String, dynamic>.from(body) : null;
      final errors = bodyMap?['errors'];
      final code = bodyMap?['code']?.toString() ?? (errors is Map ? errors['code']?.toString() : null);
      if (response.statusCode == 401 ||
          code == 'account_inactive' ||
          code == 'account_suspended' ||
          code == 'account_deleted') {
        await setToken(null);
        await onUnauthorized?.call();
      }
      final message = bodyMap?['message']?.toString() ?? _firstValidationMessage(errors) ?? 'فشل تنفيذ الطلب.';
      throw ApiException(message, statusCode: response.statusCode, errors: errors, code: code);
    }

    if (body is Map<String, dynamic> && body.containsKey('data')) return body['data'];
    return body;
  }

  String? _firstValidationMessage(dynamic errors) {
    if (errors is! Map) return null;
    for (final value in errors.values) {
      if (value is List && value.isNotEmpty) return value.first.toString();
      if (value is String && value.isNotEmpty) return value;
    }
    return null;
  }

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    final response = await _withTimeout(http.get(_uri(path, query), headers: _headers()));
    return _unwrap(response);
  }

  Future<dynamic> post(String path, Map<String, dynamic> data) async {
    final response = await _withTimeout(http.post(_uri(path), headers: _headers(), body: jsonEncode(data)));
    return _unwrap(response);
  }

  Future<dynamic> put(String path, Map<String, dynamic> data) async {
    final response = await _withTimeout(http.put(_uri(path), headers: _headers(), body: jsonEncode(data)));
    return _unwrap(response);
  }

  Future<dynamic> delete(String path, {Map<String, dynamic>? data}) async {
    final response = await _withTimeout(http.delete(
      _uri(path),
      headers: _headers(),
      body: data == null ? null : jsonEncode(data),
    ));
    return _unwrap(response);
  }

  Future<dynamic> multipartPost(
    String path, {
    required Map<String, String> fields,
    required String fileField,
    required File file,
  }) async {
    final request = http.MultipartRequest('POST', _uri(path));
    request.headers.addAll(_headers(jsonBody: false));
    request.fields.addAll(fields);
    request.files.add(await http.MultipartFile.fromPath(fileField, file.path));

    try {
      final streamed = await request.send().timeout(ApiConfig.requestTimeout);
      return _unwrap(await http.Response.fromStream(streamed));
    } on TimeoutException {
      throw const ApiException('انتهت مهلة رفع الملف. حاول مرة أخرى.', statusCode: 408);
    } on SocketException {
      throw const ApiException('تعذر رفع الملف بسبب مشكلة في الشبكة.');
    }
  }

  Future<dynamic> multipartPostFiles(
    String path, {
    Map<String, String> fields = const {},
    required Map<String, List<File>> files,
  }) async {
    final request = http.MultipartRequest('POST', _uri(path));
    request.headers.addAll(_headers(jsonBody: false));
    request.fields.addAll(fields);
    for (final entry in files.entries) {
      for (final file in entry.value) {
        request.files.add(await http.MultipartFile.fromPath(entry.key, file.path));
      }
    }

    try {
      final streamed = await request.send().timeout(ApiConfig.requestTimeout);
      return _unwrap(await http.Response.fromStream(streamed));
    } on TimeoutException {
      throw const ApiException('انتهت مهلة رفع الملفات. حاول مرة أخرى.', statusCode: 408);
    } on SocketException {
      throw const ApiException('تعذر رفع الملفات بسبب مشكلة في الشبكة.');
    }
  }
}
