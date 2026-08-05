import '../core/utils/arabic_text.dart';

class EventTypeOptionModel {
  final String id;
  final String name;
  final String nameEn;
  final String emoji;

  const EventTypeOptionModel({
    required this.id,
    required this.name,
    required this.nameEn,
    this.emoji = '🎯',
  });

  factory EventTypeOptionModel.fromJson(Map<String, dynamic> json) => EventTypeOptionModel(
        id: '${json['id'] ?? ''}',
        name: ArabicText.tr((json['name_ar'] ?? json['name_en'] ?? 'مناسبة').toString()),
        nameEn: (json['name_en'] ?? json['name_ar'] ?? '').toString(),
        emoji: (json['emoji'] ?? '🎯').toString(),
      );
}
