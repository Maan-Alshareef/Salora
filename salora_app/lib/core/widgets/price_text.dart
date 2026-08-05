import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/app_settings_provider.dart';

class PriceText extends StatelessWidget {
  final num priceSyp;
  final TextStyle? style;
  final TextAlign? textAlign;
  final String prefix;
  final String suffix;

  const PriceText({
    super.key,
    required this.priceSyp,
    this.style,
    this.textAlign,
    this.prefix = '',
    this.suffix = '',
  });

  @override
  Widget build(BuildContext context) {
    final settings = context.watch<AppSettingsProvider>();
    return Text('$prefix${settings.formatPrice(priceSyp)}$suffix', style: style, textAlign: textAlign);
  }
}
