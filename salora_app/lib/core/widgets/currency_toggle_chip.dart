import 'package:flutter/material.dart';

/// Kept as a compact indicator because several existing screens place this
/// widget in the app bar. Currency switching was intentionally removed: using
/// a hard-coded client-side exchange rate produced incorrect financial data.
class CurrencyToggleChip extends StatelessWidget {
  final bool compact;

  const CurrencyToggleChip({super.key, this.compact = true});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Tooltip(
        message: 'الأسعار المعروضة بالليرة السورية',
        child: Chip(
          avatar: Icon(Icons.payments_outlined, size: compact ? 16 : 18),
          label: Text(
            compact ? 'SYP' : 'ليرة سورية',
            style: TextStyle(fontSize: compact ? 11 : 13, fontWeight: FontWeight.w900),
          ),
        ),
      ),
    );
  }
}
