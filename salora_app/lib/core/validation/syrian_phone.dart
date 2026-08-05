import 'package:flutter/services.dart';

class SyrianPhone {
  static final RegExp pattern = RegExp(r'^09\d{8}$');

  static String normalize(String value) {
    const arabic = '٠١٢٣٤٥٦٧٨٩';
    const persian = '۰۱۲۳۴۵۶۷۸۹';
    var result = value;
    for (var i = 0; i < 10; i++) {
      result = result.replaceAll(arabic[i], '$i').replaceAll(persian[i], '$i');
    }
    return result.replaceAll(RegExp(r'\D'), '');
  }

  static String? validate(String? value) {
    final normalized = normalize(value ?? '');
    return pattern.hasMatch(normalized)
        ? null
        : 'أدخل رقم هاتف سوري من 10 أرقام ويبدأ بـ 09.';
  }

  static List<TextInputFormatter> get formatters => [
        FilteringTextInputFormatter.allow(RegExp(r'[0-9٠-٩۰-۹]')),
        LengthLimitingTextInputFormatter(10),
      ];
}
