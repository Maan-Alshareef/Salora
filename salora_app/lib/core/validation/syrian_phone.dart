import 'package:flutter/services.dart';

class SyrianPhone {
  static final RegExp pattern = RegExp(r'^\d{10}$');

  static String normalize(String value) {
    const arabic = '٠١٢٣٤٥٦٧٨٩';
    const persian = '۰۱۲۳۴۵۶۷۸۹';
    var result = value;
    for (var i = 0; i < 10; i++) {
      result = result.replaceAll(arabic[i], '$i').replaceAll(persian[i], '$i');
    }
    var digits = result.replaceAll(RegExp(r'\D'), '');
    // Backward compatibility for records that were previously stored as +9639XXXXXXXX.
    if (digits.startsWith('9639') && digits.length == 12) {
      digits = '0${digits.substring(3)}';
    }
    return digits;
  }

  static String? validate(String? value) {
    final normalized = normalize(value ?? '');
    return pattern.hasMatch(normalized)
        ? null
        : 'أدخل رقم هاتف مكوّناً من 10 أرقام فقط.';
  }

  static List<TextInputFormatter> get formatters => [
        FilteringTextInputFormatter.allow(RegExp(r'[0-9٠-٩۰-۹]')),
        LengthLimitingTextInputFormatter(10),
      ];
}
