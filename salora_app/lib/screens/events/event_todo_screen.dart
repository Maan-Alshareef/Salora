import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../models/event_model.dart';
import '../../providers/event_provider.dart';

class EventTodoScreen extends StatefulWidget {
  final EventModel event;
  const EventTodoScreen({super.key, required this.event});

  @override
  State<EventTodoScreen> createState() => _EventTodoScreenState();
}

class _EventTodoScreenState extends State<EventTodoScreen> {
  final _task = TextEditingController();
  String? _busyTaskId;
  bool _adding = false;

  @override
  void dispose() {
    _task.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> Function() action) async {
    try {
      await action();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    final events = context.watch<EventProvider>().events;
    EventModel event = widget.event;
    for (final item in events) {
      if (item.id == widget.event.id) event = item;
    }
    return Scaffold(
      appBar: AppBar(title: const Text('قائمة مهام المناسبة')),
      body: ListView(padding: const EdgeInsets.all(18), children: [
        Text(event.title, style: const TextStyle(fontSize: 25, fontWeight: FontWeight.w900)),
        const SizedBox(height: 6),
        Text('${event.completedTasks}/${event.tasks.length} مهام مكتملة', style: const TextStyle(color: AppColors.textSecondary)),
        const SizedBox(height: 14),
        ...event.tasks.map((task) => _TaskTile(
              eventId: event.id,
              task: task,
              busy: _busyTaskId == task.id,
              onToggle: () async {
                setState(() => _busyTaskId = task.id);
                await _run(() => context.read<EventProvider>().toggleTask(event.id, task.id));
                if (mounted) setState(() => _busyTaskId = null);
              },
              onDelete: () async {
                setState(() => _busyTaskId = task.id);
                await _run(() => context.read<EventProvider>().deleteTask(event.id, task.id));
                if (mounted) setState(() => _busyTaskId = null);
              },
            )),
        const SizedBox(height: 18),
        Row(children: [
          Expanded(child: TextField(controller: _task, decoration: const InputDecoration(labelText: 'إضافة مهمة مخصصة', prefixIcon: Icon(Icons.add_task_rounded)))),
          const SizedBox(width: 10),
          FilledButton(
            onPressed: _adding
                ? null
                : () async {
                    setState(() => _adding = true);
                    await _run(() => context.read<EventProvider>().addCustomTask(event.id, _task.text));
                    if (mounted) {
                      _task.clear();
                      setState(() => _adding = false);
                    }
                  },
            child: _adding ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.add_rounded),
          ),
        ]),
      ]),
    );
  }
}

class _TaskTile extends StatelessWidget {
  final String eventId;
  final EventTask task;
  final bool busy;
  final VoidCallback onToggle;
  final VoidCallback onDelete;

  const _TaskTile({required this.eventId, required this.task, required this.busy, required this.onToggle, required this.onDelete});

  @override
  Widget build(BuildContext context) {
    final done = task.status == EventTaskStatus.done;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
      child: ListTile(
        leading: busy
            ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2))
            : Checkbox(value: done, onChanged: (_) => onToggle()),
        title: Text(task.title, style: TextStyle(fontWeight: FontWeight.w800, decoration: done ? TextDecoration.lineThrough : null)),
        subtitle: Text(task.subtitle, style: const TextStyle(color: AppColors.textSecondary)),
        trailing: IconButton(onPressed: busy ? null : onDelete, icon: const Icon(Icons.delete_outline_rounded)),
      ),
    );
  }
}
