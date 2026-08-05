import '../core/utils/arabic_text.dart';

enum InvoiceItemType { hallPrice, includedService, hallExtraService, externalVendorService }

extension InvoiceItemTypeX on InvoiceItemType {
  String get label {
    switch (this) {
      case InvoiceItemType.hallPrice:
        return 'سعر الصالة';
      case InvoiceItemType.includedService:
        return 'خدمة مجانية';
      case InvoiceItemType.hallExtraService:
        return 'خدمة إضافية من الصالة';
      case InvoiceItemType.externalVendorService:
        return 'خدمة من مقدم خارجي';
    }
  }
}

class InvoiceItem {
  final String id;
  final String title;
  final String category;
  final int amount;
  final InvoiceItemType type;

  const InvoiceItem({
    required this.id,
    required this.title,
    required this.category,
    required this.amount,
    required this.type,
  });

  bool get isIncluded => type == InvoiceItemType.includedService;
}
