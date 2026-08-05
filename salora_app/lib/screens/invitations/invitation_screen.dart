import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/theme/app_colors.dart';
import '../../models/event_model.dart';

class InvitationScreen extends StatefulWidget {
  final EventModel event;
  const InvitationScreen({super.key, required this.event});

  @override
  State<InvitationScreen> createState() => _InvitationScreenState();
}

class _InvitationScreenState extends State<InvitationScreen> {
  final _host = TextEditingController();
  final _location = TextEditingController();
  final _message = TextEditingController();
  final _previewKey = GlobalKey();
  String _template = 'أنيق';
  bool _exporting = false;
  String? _lastSavedPath;

  @override
  void initState() {
    super.initState();
    _host.text = widget.event.type == EventType.condolence ? 'عائلة الفقيد' : '';
    _location.text = widget.event.displayLocation;
    _message.text = _defaultMessage(widget.event.type);
  }

  @override
  void dispose() {
    _host.dispose();
    _location.dispose();
    _message.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final date = '${widget.event.date.day}/${widget.event.date.month}/${widget.event.date.year}';
    final time = widget.event.timeRange.isEmpty ? '-' : widget.event.timeRange;
    final title = _invitationTitle(widget.event.type);
    final isCondolence = widget.event.type == EventType.condolence;

    return Scaffold(
      appBar: AppBar(title: Text(isCondolence ? 'دعوة عزاء إلكترونية' : 'دعوة إلكترونية')),
      body: ListView(padding: const EdgeInsets.all(18), children: [
        Text('اختر القالب', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
        const SizedBox(height: 10),
        Wrap(spacing: 8, runSpacing: 8, children: ['أنيق', 'كلاسيكي', 'ذهبي', 'بسيط'].map((item) => ChoiceChip(label: Text(item), selected: _template == item, onSelected: (_) => setState(() => _template = item))).toList()),
        const SizedBox(height: 16),
        TextField(controller: _host, decoration: const InputDecoration(labelText: 'اسم المضيف أو العائلة', prefixIcon: Icon(Icons.person_outline)), onChanged: (_) => setState(() {})),
        const SizedBox(height: 12),
        TextField(controller: _location, decoration: const InputDecoration(labelText: 'الموقع', prefixIcon: Icon(Icons.location_on_outlined)), onChanged: (_) => setState(() {})),
        const SizedBox(height: 12),
        TextField(controller: _message, maxLines: 3, decoration: InputDecoration(labelText: isCondolence ? 'رسالة العزاء' : 'رسالة الدعوة', prefixIcon: const Icon(Icons.message_outlined)), onChanged: (_) => setState(() {})),
        const SizedBox(height: 20),
        RepaintBoundary(
          key: _previewKey,
          child: _InvitationPreview(
            isCondolence: isCondolence,
            template: _template,
            emoji: widget.event.type.iconEmoji,
            title: title,
            eventName: widget.event.title,
            message: _message.text,
            date: date,
            location: _location.text,
            time: time,
            hallName: widget.event.venueName ?? '',
            address: widget.event.venueAddress ?? '',
            host: _host.text,
          ),
        ),
        const SizedBox(height: 18),
        if (_lastSavedPath != null) ...[
          Text('تم حفظ الصورة: $_lastSavedPath', style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
          const SizedBox(height: 10),
        ],
        Row(children: [
          Expanded(child: OutlinedButton.icon(onPressed: _exporting ? null : _shareImage, icon: const Icon(Icons.share_rounded), label: const Text('مشاركة الصورة'))),
          const SizedBox(width: 12),
          Expanded(child: ElevatedButton.icon(onPressed: _exporting ? null : _saveImage, icon: const Icon(Icons.download_rounded), label: Text(_exporting ? 'جارٍ الحفظ...' : 'حفظ الصورة'))),
        ]),
      ]),
    );
  }

  Future<File> _captureInvitationImage() async {
    final boundary = _previewKey.currentContext?.findRenderObject() as RenderRepaintBoundary?;
    if (boundary == null) throw Exception('معاينة الدعوة غير جاهزة');
    final image = await boundary.toImage(pixelRatio: 3);
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    final bytes = byteData?.buffer.asUint8List();
    if (bytes == null) throw Exception('تعذر تصدير صورة الدعوة');
    final directory = await getApplicationDocumentsDirectory();
    final file = File('${directory.path}/salora_invitation_${DateTime.now().millisecondsSinceEpoch}.png');
    await file.writeAsBytes(bytes);
    return file;
  }

  Future<void> _saveImage() async {
    try {
      setState(() => _exporting = true);
      final file = await _captureInvitationImage();
      if (!mounted) return;
      setState(() {
        _lastSavedPath = file.path;
        _exporting = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('تم حفظ صورة الدعوة: ${file.path}')));
    } catch (e) {
      if (!mounted) return;
      setState(() => _exporting = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('تعذر حفظ الصورة: $e')));
    }
  }

  Future<void> _shareImage() async {
    try {
      setState(() => _exporting = true);
      final file = await _captureInvitationImage();
      if (!mounted) return;
      setState(() {
        _lastSavedPath = file.path;
        _exporting = false;
      });
      await Share.shareXFiles([XFile(file.path)], text: 'دعوة Salora');
    } catch (e) {
      if (!mounted) return;
      setState(() => _exporting = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('تعذر مشاركة الصورة: $e')));
    }
  }

  String _invitationTitle(EventType type) {
    switch (type) {
      case EventType.wedding:
        return 'دعوة زفاف';
      case EventType.engagement:
        return 'دعوة خطوبة';
      case EventType.graduation:
        return 'دعوة تخرج';
      case EventType.condolence:
        return 'نعوة / دعوة عزاء';
      case EventType.conference:
        return 'دعوة مؤتمر';
      case EventType.meeting:
        return 'دعوة اجتماع';
      case EventType.birthday:
        return 'دعوة عيد ميلاد';
    }
  }

  String _defaultMessage(EventType type) {
    switch (type) {
      case EventType.wedding:
        return 'ندعوكم بكل حب وسرور لحضور حفل الزفاف ومشاركتنا أجمل اللحظات.';
      case EventType.engagement:
        return 'ندعوكم لمشاركتنا فرحة الخطوبة، حضوركم يزيد فرحتنا.';
      case EventType.graduation:
        return 'ندعوكم لحضور حفل التخرج ومشاركتنا فرحة النجاح.';
      case EventType.condolence:
        return 'ببالغ الحزن والأسى وبقلوب مؤمنة بقضاء الله وقدره، ندعوكم لحضور مجلس العزاء.';
      case EventType.conference:
        return 'يسرنا دعوتكم لحضور المؤتمر والمشاركة في فعالياته.';
      case EventType.meeting:
        return 'ندعوكم لحضور الاجتماع في الموعد والمكان المحددين.';
      case EventType.birthday:
        return 'ندعوكم لمشاركتنا الاحتفال بعيد الميلاد وقضاء وقت جميل.';
    }
  }
}

class _InvitationPreview extends StatelessWidget {
  final bool isCondolence;
  final String template;
  final String emoji;
  final String title;
  final String eventName;
  final String message;
  final String date;
  final String location;
  final String time;
  final String hallName;
  final String address;
  final String host;

  const _InvitationPreview({
    required this.isCondolence,
    required this.template,
    required this.emoji,
    required this.title,
    required this.eventName,
    required this.message,
    required this.date,
    required this.location,
    required this.time,
    required this.hallName,
    required this.address,
    required this.host,
  });

  @override
  Widget build(BuildContext context) {
    final colors = isCondolence
        ? [const Color(0xFF111827), const Color(0xFF374151)]
        : template == 'ذهبي'
            ? [const Color(0xFF92400E), const Color(0xFFF59E0B)]
            : [AppColors.secondary, AppColors.primary];

    return Material(
      color: Colors.transparent,
      child: Container(
        padding: const EdgeInsets.all(6),
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(32), gradient: LinearGradient(colors: colors)),
        child: Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(28), border: Border.all(color: Colors.white30, width: 1.2), color: Colors.white.withOpacity(.05)),
          child: Column(children: [
            Text(emoji, style: const TextStyle(fontSize: 42)),
            const SizedBox(height: 10),
            Text(title, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white70, fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            Text(eventName, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900, height: 1.15)),
            const SizedBox(height: 14),
            Text(message, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, height: 1.55, fontSize: 15)),
            const SizedBox(height: 20),
            Container(height: 1, color: Colors.white24),
            const SizedBox(height: 16),
            _line(Icons.calendar_month_rounded, date),
            _line(Icons.schedule_rounded, time),
            if (hallName.trim().isNotEmpty) _line(Icons.location_city_rounded, hallName),
            _line(Icons.location_on_outlined, address.trim().isEmpty ? location : address),
            if (host.trim().isNotEmpty) _line(Icons.person_outline, host),
            const SizedBox(height: 12),
            const Text('تصميم Salora', style: TextStyle(color: Colors.white54, fontWeight: FontWeight.w600)),
          ]),
        ),
      ),
    );
  }

  Widget _line(IconData icon, String text) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(icon, size: 18, color: Colors.white), const SizedBox(width: 8), Flexible(child: Text(text, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)))]),
      );
}
