import 'package:flutter/material.dart';

/// The university edition uses a single, explicit display currency.
/// The backend still stores both SYP and USD amounts where available, but the
/// mobile UI never invents an exchange rate locally.
enum AppCurrency { syp }
enum AppLanguage { ar }

extension AppCurrencyX on AppCurrency {
  String get code => 'ل.س';
  String get label => 'ليرة سورية';
}

extension AppLanguageX on AppLanguage {
  String get code => 'ar';
  String get label => 'العربية';
  Locale get locale => const Locale('ar');
  TextDirection get direction => TextDirection.rtl;
}

class AppSettingsProvider extends ChangeNotifier {
  AppCurrency get currency => AppCurrency.syp;
  AppLanguage get language => AppLanguage.ar;

  String formatPrice(num sypPrice) => '${_formatSyp(sypPrice.round())} ل.س';

  String _formatSyp(int value) {
    final negative = value < 0;
    final text = value.abs().toString();
    final buffer = StringBuffer();
    for (var index = 0; index < text.length; index++) {
      final remaining = text.length - index;
      buffer.write(text[index]);
      if (remaining > 1 && remaining % 3 == 1) buffer.write(',');
    }
    return negative ? '-$buffer' : buffer.toString();
  }
}
