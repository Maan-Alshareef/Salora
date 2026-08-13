class InvitationDraft {
  final String id;
  final String eventId;
  final int? invitationTemplateId;
  final String style;
  final String hostName;
  final String location;
  final String message;
  final DateTime? updatedAt;

  const InvitationDraft({
    required this.id,
    required this.eventId,
    required this.invitationTemplateId,
    required this.style,
    required this.hostName,
    required this.location,
    required this.message,
    this.updatedAt,
  });

  factory InvitationDraft.fromJson(Map<String, dynamic> json) {
    return InvitationDraft(
      id: '${json['id'] ?? ''}',
      eventId: '${json['event_id'] ?? ''}',
      invitationTemplateId: _toNullableInt(json['invitation_template_id']),
      style: _normalizeStyle(json['style']?.toString()),
      hostName: (json['host_name'] ?? '').toString(),
      location: (json['location'] ?? '').toString(),
      message: (json['message'] ?? '').toString(),
      updatedAt: DateTime.tryParse((json['updated_at'] ?? '').toString()),
    );
  }

  static String _normalizeStyle(String? value) {
    switch (value) {
      case 'gold':
      case 'rose':
      case 'classic':
        return value!;
      default:
        return 'classic';
    }
  }

  static int? _toNullableInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    return int.tryParse(value.toString());
  }
}
