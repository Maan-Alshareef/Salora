import 'dart:async';
import 'dart:io';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:image_gallery_saver_plus/image_gallery_saver_plus.dart';
import 'package:path_provider/path_provider.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/theme/app_colors.dart';
import '../../models/event_model.dart';
import '../../providers/event_provider.dart';

class InvitationScreen extends StatefulWidget {
  final EventModel event;
  const InvitationScreen({super.key, required this.event});

  @override
  State<InvitationScreen> createState() => _InvitationScreenState();
}

class _InvitationScreenState extends State<InvitationScreen> {
  static const Map<String, String> _styles = {
    'classic': 'كلاسيكي',
    'gold': 'ذهبي',
    'rose': 'وردي',
  };

  final _host = TextEditingController();
  final _location = TextEditingController();
  final _message = TextEditingController();
  final _previewKey = GlobalKey();

  String _style = 'classic';
  bool _loadingDraft = true;
  bool _savingDraft = false;
  bool _exporting = false;
  bool _savedToGallery = false;
  bool _pendingSave = false;
  String _saveStatus = '';
  Timer? _saveTimer;

  @override
  void initState() {
    super.initState();
    _host.text = widget.event.type == EventType.condolence ? 'عائلة الفقيد' : '';
    _location.text = widget.event.displayLocation;
    _message.text = _defaultMessage(widget.event.type);
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadDraft());
  }

  @override
  void dispose() {
    _saveTimer?.cancel();
    _host.dispose();
    _location.dispose();
    _message.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final date = _formattedDate;
    final time = widget.event.timeRange.isEmpty ? '-' : widget.event.timeRange;
    final title = _invitationTitle(widget.event.type);
    final isCondolence = widget.event.type == EventType.condolence;

    return Scaffold(
      appBar: AppBar(
        title: Text(isCondolence ? 'دعوة عزاء إلكترونية' : 'دعوة إلكترونية'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Text(
            'اختر القالب',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 6),
          const Text(
            'ثلاثة تصاميم ثابتة، وتقدر تغيّر النص والموقع بدون ما يضيع شغلك.',
            style: TextStyle(color: AppColors.textSecondary, fontSize: 12),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _styles.entries.map((entry) {
              return ChoiceChip(
                avatar: CircleAvatar(
                  radius: 7,
                  backgroundColor: _stylePreviewColor(entry.key, isCondolence),
                ),
                label: Text(entry.value),
                selected: _style == entry.key,
                onSelected: _loadingDraft
                    ? null
                    : (_) {
                        setState(() {
                          _style = entry.key;
                          _savedToGallery = false;
                        });
                        _scheduleDraftSave();
                      },
              );
            }).toList(),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _host,
            decoration: const InputDecoration(
              labelText: 'اسم المضيف أو العائلة',
              prefixIcon: Icon(Icons.person_outline),
            ),
            onChanged: (_) => _onDraftChanged(),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _location,
            decoration: const InputDecoration(
              labelText: 'الموقع الظاهر على الدعوة',
              prefixIcon: Icon(Icons.location_on_outlined),
              helperText: 'يمكنك كتابة الصالة أو العنوان أو أي وصف مختصر للموقع.',
            ),
            onChanged: (_) => _onDraftChanged(),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _message,
            maxLines: 3,
            decoration: InputDecoration(
              labelText: isCondolence ? 'رسالة العزاء' : 'رسالة الدعوة',
              prefixIcon: const Icon(Icons.message_outlined),
            ),
            onChanged: (_) => _onDraftChanged(),
          ),
          const SizedBox(height: 10),
          _saveIndicator(),
          const SizedBox(height: 18),
          RepaintBoundary(
            key: _previewKey,
            child: _InvitationPreview(
              isCondolence: isCondolence,
              style: _style,
              emoji: widget.event.type.iconEmoji,
              title: title,
              eventName: widget.event.title,
              message: _message.text,
              date: date,
              location: _location.text,
              time: time,
              hallName: widget.event.venueName ?? '',
              host: _host.text,
            ),
          ),
          const SizedBox(height: 16),
          if (_savedToGallery) ...[
            const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.check_circle_rounded, color: AppColors.success, size: 18),
                SizedBox(width: 6),
                Text(
                  'تم حفظ آخر نسخة في الاستديو',
                  style: TextStyle(color: AppColors.success, fontWeight: FontWeight.w700),
                ),
              ],
            ),
            const SizedBox(height: 12),
          ],
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _exporting ? null : _shareImage,
                  icon: const Icon(Icons.share_rounded),
                  label: const Text('مشاركة الصورة'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _exporting ? null : _saveImage,
                  icon: const Icon(Icons.photo_library_outlined),
                  label: Text(_exporting ? 'جارٍ الحفظ...' : 'حفظ في الاستديو'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _saveIndicator() {
    if (_loadingDraft) {
      return const Row(
        children: [
          SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
          SizedBox(width: 8),
          Text('جارٍ تحميل آخر دعوة محفوظة...', style: TextStyle(fontSize: 12)),
        ],
      );
    }

    final text = _savingDraft
        ? 'جارٍ حفظ التعديلات...'
        : _saveStatus.isEmpty
            ? 'التعديلات تُحفظ تلقائيًا على حسابك.'
            : _saveStatus;
    final failed = _saveStatus.startsWith('تعذر');

    return Row(
      children: [
        Icon(
          failed
              ? Icons.cloud_off_outlined
              : _savingDraft
                  ? Icons.cloud_sync_outlined
                  : Icons.cloud_done_outlined,
          size: 17,
          color: failed ? AppColors.warning : AppColors.textSecondary,
        ),
        const SizedBox(width: 7),
        Expanded(
          child: Text(
            text,
            style: TextStyle(
              color: failed ? AppColors.warning : AppColors.textSecondary,
              fontSize: 12,
            ),
          ),
        ),
        if (failed)
          TextButton(
            onPressed: _savingDraft ? null : () => _persistDraft(showError: true),
            child: const Text('إعادة المحاولة'),
          ),
      ],
    );
  }

  Future<void> _loadDraft() async {
    final provider = context.read<EventProvider>();

    try {
      if (provider.invitationMessageForType(widget.event.type) == null) {
        await provider.loadTemplates();
      }

      final draft = await provider.loadInvitationDraft(widget.event.id);
      if (!mounted) return;

      if (draft != null) {
        _style = _styles.containsKey(draft.style) ? draft.style : 'classic';
        _host.text = draft.hostName;
        _location.text = draft.location.isEmpty ? widget.event.displayLocation : draft.location;
        _message.text = draft.message.isEmpty
            ? _resolvedDefaultMessage(provider)
            : draft.message;
      } else {
        _message.text = _resolvedDefaultMessage(provider);
      }

      setState(() {
        _loadingDraft = false;
        _saveStatus = draft == null ? '' : 'تم تحميل آخر نسخة محفوظة.';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingDraft = false;
        _saveStatus = 'تعذر تحميل النسخة المحفوظة؛ يمكنك متابعة التعديل.';
      });
    }
  }

  String _resolvedDefaultMessage(EventProvider provider) {
    final remote = provider.invitationMessageForType(widget.event.type)?.trim() ?? '';
    if (remote.isEmpty) return _defaultMessage(widget.event.type);

    return remote
        .replaceAll('{event_name}', widget.event.title)
        .replaceAll('{event_date}', _formattedDate)
        .replaceAll('{event_time}', widget.event.timeRange)
        .replaceAll('{venue_name}', widget.event.venueName?.trim().isNotEmpty == true
            ? widget.event.venueName!.trim()
            : widget.event.displayLocation)
        .replaceAll('{location}', widget.event.displayLocation);
  }

  String get _formattedDate =>
      '${widget.event.date.day}/${widget.event.date.month}/${widget.event.date.year}';

  void _onDraftChanged() {
    setState(() => _savedToGallery = false);
    _scheduleDraftSave();
  }

  void _scheduleDraftSave() {
    if (_loadingDraft) return;
    _saveTimer?.cancel();
    _saveTimer = Timer(const Duration(milliseconds: 650), _persistDraft);
  }

  Future<void> _persistDraft({bool showError = false}) async {
    if (_loadingDraft) return;
    if (_savingDraft) {
      _pendingSave = true;
      return;
    }

    _saveTimer?.cancel();
    setState(() {
      _savingDraft = true;
      _saveStatus = '';
    });

    try {
      await context.read<EventProvider>().saveInvitationDraft(
            eventId: widget.event.id,
            eventType: widget.event.type,
            style: _style,
            hostName: _host.text,
            location: _location.text,
            message: _message.text,
          );
      if (!mounted) return;
      setState(() => _saveStatus = 'تم حفظ التعديلات.');
    } catch (error) {
      if (!mounted) return;
      setState(() => _saveStatus = 'تعذر حفظ التعديلات على الخادم.');
      if (showError) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('تعذر حفظ الدعوة: $error')),
        );
      }
    } finally {
      if (mounted) setState(() => _savingDraft = false);
      if (mounted && _pendingSave) {
        _pendingSave = false;
        unawaited(_persistDraft());
      }
    }
  }

  Future<Uint8List> _captureInvitationBytes() async {
    await Future<void>.delayed(const Duration(milliseconds: 80));
    final boundary = _previewKey.currentContext?.findRenderObject() as RenderRepaintBoundary?;
    if (boundary == null) throw StateError('معاينة الدعوة غير جاهزة');

    final image = await boundary.toImage(pixelRatio: 3);
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    final bytes = byteData?.buffer.asUint8List();
    if (bytes == null) throw StateError('تعذر تصدير صورة الدعوة');
    return bytes;
  }

  Future<void> _saveImage() async {
    try {
      setState(() => _exporting = true);
      await _persistDraft();
      final bytes = await _captureInvitationBytes();
      await ImageGallerySaverPlus.saveImage(
        bytes,
        quality: 100,
        name: 'Salora_invitation_${widget.event.id}_${DateTime.now().millisecondsSinceEpoch}',
      );
      if (!mounted) return;
      setState(() {
        _savedToGallery = true;
        _exporting = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم حفظ صورة الدعوة في الاستديو.')),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _exporting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('تعذر حفظ الصورة: $error')),
      );
    }
  }

  Future<void> _shareImage() async {
    try {
      setState(() => _exporting = true);
      await _persistDraft();
      final bytes = await _captureInvitationBytes();
      final directory = await getTemporaryDirectory();
      final file = File(
        '${directory.path}/Salora_invitation_${widget.event.id}_${DateTime.now().millisecondsSinceEpoch}.png',
      );
      await file.writeAsBytes(bytes, flush: true);
      if (!mounted) return;
      setState(() => _exporting = false);
      await Share.shareXFiles([XFile(file.path)], text: 'دعوة Salora');
    } catch (error) {
      if (!mounted) return;
      setState(() => _exporting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('تعذر مشاركة الصورة: $error')),
      );
    }
  }

  Color _stylePreviewColor(String style, bool isCondolence) {
    if (isCondolence) {
      switch (style) {
        case 'gold':
          return const Color(0xFFB0894F);
        case 'rose':
          return const Color(0xFF7F3D52);
        default:
          return const Color(0xFF374151);
      }
    }

    switch (style) {
      case 'gold':
        return const Color(0xFFF59E0B);
      case 'rose':
        return const Color(0xFFEC4899);
      default:
        return AppColors.primary;
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
  final String style;
  final String emoji;
  final String title;
  final String eventName;
  final String message;
  final String date;
  final String location;
  final String time;
  final String hallName;
  final String host;

  const _InvitationPreview({
    required this.isCondolence,
    required this.style,
    required this.emoji,
    required this.title,
    required this.eventName,
    required this.message,
    required this.date,
    required this.location,
    required this.time,
    required this.hallName,
    required this.host,
  });

  @override
  Widget build(BuildContext context) {
    final colors = _palette();

    return Material(
      color: Colors.transparent,
      child: Container(
        padding: const EdgeInsets.all(6),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(32),
          gradient: LinearGradient(colors: colors),
        ),
        child: Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(28),
            border: Border.all(color: Colors.white30, width: 1.2),
            color: Colors.white.withOpacity(.05),
          ),
          child: Column(
            children: [
              Text(emoji, style: const TextStyle(fontSize: 42)),
              const SizedBox(height: 10),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white70,
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                eventName,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 28,
                  fontWeight: FontWeight.w900,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 14),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  height: 1.55,
                  fontSize: 15,
                ),
              ),
              const SizedBox(height: 20),
              Container(height: 1, color: Colors.white24),
              const SizedBox(height: 16),
              _line(Icons.calendar_month_rounded, date),
              _line(Icons.schedule_rounded, time),
              if (hallName.trim().isNotEmpty) _line(Icons.location_city_rounded, hallName),
              if ((location.trim().isEmpty ? hallName.trim() : location.trim()).isNotEmpty)
                _line(
                  Icons.location_on_outlined,
                  location.trim().isEmpty ? hallName.trim() : location.trim(),
                ),
              if (host.trim().isNotEmpty) _line(Icons.person_outline, host),
              const SizedBox(height: 12),
              const Text(
                'تصميم Salora',
                style: TextStyle(
                  color: Colors.white54,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  List<Color> _palette() {
    if (isCondolence) {
      switch (style) {
        case 'gold':
          return const [Color(0xFF30261B), Color(0xFF806A43)];
        case 'rose':
          return const [Color(0xFF2B1720), Color(0xFF6B354A)];
        default:
          return const [Color(0xFF111827), Color(0xFF374151)];
      }
    }

    switch (style) {
      case 'gold':
        return const [Color(0xFF92400E), Color(0xFFF59E0B)];
      case 'rose':
        return const [Color(0xFF831843), Color(0xFFEC4899)];
      default:
        return const [AppColors.secondary, AppColors.primary];
    }
  }

  Widget _line(IconData icon, String text) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 18, color: Colors.white),
            const SizedBox(width: 8),
            Flexible(
              child: Text(
                text,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      );
}
