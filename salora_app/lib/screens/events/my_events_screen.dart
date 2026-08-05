import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../models/event_model.dart';
import '../../providers/app_settings_provider.dart';
import '../../providers/event_provider.dart';
import '../../widgets/empty_state.dart';
import 'create_event_screen.dart';
import 'event_details_screen.dart';

class MyEventsScreen extends StatefulWidget {
  const MyEventsScreen({super.key});

  @override
  State<MyEventsScreen> createState() => _MyEventsScreenState();
}

class _MyEventsScreenState extends State<MyEventsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => context.read<EventProvider>().loadEvents());
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<EventProvider>();
    final events = provider.events;
    return Scaffold(
      appBar: AppBar(
        title: const Text('مناسباتي'),
        actions: [IconButton(onPressed: provider.isLoading ? null : provider.loadEvents, icon: const Icon(Icons.refresh_rounded))],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CreateEventScreen())),
        icon: const Icon(Icons.add_rounded),
        label: const Text('مناسبة جديدة'),
      ),
      body: provider.isLoading && events.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : provider.error != null && events.isEmpty
              ? EmptyState(
                  icon: Icons.cloud_off_rounded,
                  title: 'تعذر تحميل المناسبات',
                  subtitle: provider.error!,
                  buttonText: 'إعادة المحاولة',
                  onPressed: provider.loadEvents,
                )
              : events.isEmpty
                  ? EmptyState(
                      icon: Icons.event_available_rounded,
                      title: 'لا توجد مناسبات بعد',
                      subtitle: 'أنشئ مناسبة لتوليد قائمة مهام ثم اربط بها حجز الصالة.',
                      buttonText: 'إنشاء مناسبة',
                      onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CreateEventScreen())),
                    )
                  : RefreshIndicator(
                      onRefresh: provider.loadEvents,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: events.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (context, index) => _EventCard(event: events[index]),
                      ),
                    ),
    );
  }
}

class _EventCard extends StatelessWidget {
  final EventModel event;
  const _EventCard({required this.event});

  @override
  Widget build(BuildContext context) {
    final date = '${event.date.day}/${event.date.month}/${event.date.year}';
    final settings = context.watch<AppSettingsProvider>();
    return InkWell(
      borderRadius: BorderRadius.circular(24),
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EventDetailsScreen(event: event))),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(24)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
              width: 54,
              height: 54,
              decoration: BoxDecoration(borderRadius: BorderRadius.circular(18), gradient: const LinearGradient(colors: [AppColors.primary, AppColors.secondary])),
              child: Center(child: Text(event.type.iconEmoji, style: const TextStyle(fontSize: 24))),
            ),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(event.title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
              const SizedBox(height: 3),
              Text('${event.displayEventType} • $date • ${event.guests} ضيف', style: const TextStyle(color: AppColors.textSecondary)),
              const SizedBox(height: 3),
              Text('الميزانية: ${settings.formatPrice(event.budget)}', style: const TextStyle(color: AppColors.success, fontWeight: FontWeight.w800, fontSize: 12)),
              if ((event.venueName ?? '').isNotEmpty) ...[
                const SizedBox(height: 3),
                Text('الصالة: ${event.venueName}', style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
              ],
            ])),
            const Icon(Icons.arrow_forward_ios_rounded, size: 16),
          ]),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: LinearProgressIndicator(value: event.progress, minHeight: 8, backgroundColor: AppColors.surface2),
          ),
          const SizedBox(height: 8),
          Text('${event.completedTasks}/${event.tasks.length} مهام مكتملة', style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
        ]),
      ),
    );
  }
}
