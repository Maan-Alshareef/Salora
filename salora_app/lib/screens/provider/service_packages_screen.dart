import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/theme/app_colors.dart';

class ServicePackagesScreen extends StatefulWidget {
  const ServicePackagesScreen({super.key, required this.serviceId, required this.serviceName});
  final String serviceId;
  final String serviceName;

  @override
  State<ServicePackagesScreen> createState() => _ServicePackagesScreenState();
}

class _ServicePackagesScreenState extends State<ServicePackagesScreen> {
  List<Map<String, dynamic>> packages = const [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  Future<void> load() async {
    if (mounted) setState(() => loading = true);
    try {
      final data = await context.read<ApiClient>().get('/provider/services/${widget.serviceId}/packages');
      if (mounted) {
        setState(() {
          packages = data is List
              ? data.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
              : const [];
        });
      }
    } catch (exception) {
      message(exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void message(String value) {
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('باقات ${widget.serviceName}')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: packages.isEmpty
                  ? ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: const [
                        SizedBox(height: 160),
                        Icon(Icons.inventory_2_outlined, size: 64),
                        SizedBox(height: 12),
                        Text('لا توجد باقات بعد.', textAlign: TextAlign.center),
                      ],
                    )
                  : ListView.builder(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.all(16),
                      itemCount: packages.length,
                      itemBuilder: (_, index) {
                        final package = packages[index];
                        final included = package['included_items'] is List
                            ? (package['included_items'] as List).map((item) => item.toString()).toList()
                            : const <String>[];
                        return Container(
                          margin: const EdgeInsets.only(bottom: 12),
                          padding: const EdgeInsets.all(15),
                          decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Row(children: [
                              Expanded(child: Text((package['name'] ?? 'باقة').toString(), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17))),
                              Text('${package['price_syp'] ?? 0} ل.س', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w900)),
                            ]),
                            if ((package['description'] ?? '').toString().trim().isNotEmpty) ...[
                              const SizedBox(height: 6),
                              Text(package['description'].toString(), style: const TextStyle(color: AppColors.textSecondary)),
                            ],
                            if (included.isNotEmpty) ...[
                              const SizedBox(height: 8),
                              ...included.map((item) => Text('• $item')),
                            ],
                            const SizedBox(height: 10),
                            Row(children: [
                              Expanded(child: OutlinedButton.icon(onPressed: () => edit(package), icon: const Icon(Icons.edit_outlined), label: const Text('تعديل'))),
                              const SizedBox(width: 8),
                              Expanded(child: TextButton.icon(onPressed: () => disable(package), icon: const Icon(Icons.pause_circle_outline), label: const Text('تعطيل'))),
                            ]),
                          ]),
                        );
                      },
                    ),
            ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => edit(null),
        icon: const Icon(Icons.add),
        label: const Text('إضافة باقة'),
      ),
    );
  }

  Future<void> edit(Map<String, dynamic>? package) async {
    final name = TextEditingController(text: package?['name']?.toString() ?? '');
    final price = TextEditingController(text: package?['price_syp']?.toString() ?? '');
    final duration = TextEditingController(text: package?['duration_minutes']?.toString() ?? '');
    final description = TextEditingController(text: package?['description']?.toString() ?? '');
    final included = TextEditingController(
      text: package?['included_items'] is List ? (package!['included_items'] as List).join('\n') : '',
    );
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(package == null ? 'إضافة باقة' : 'تعديل الباقة'),
        content: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(controller: name, decoration: const InputDecoration(labelText: 'اسم الباقة *')),
            const SizedBox(height: 10),
            TextField(controller: price, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'السعر بالليرة *')),
            const SizedBox(height: 10),
            TextField(controller: duration, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'المدة بالدقائق')),
            const SizedBox(height: 10),
            TextField(controller: description, maxLines: 3, decoration: const InputDecoration(labelText: 'الوصف')),
            const SizedBox(height: 10),
            TextField(controller: included, maxLines: 5, decoration: const InputDecoration(labelText: 'المحتويات - كل بند بسطر')),
          ]),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('إلغاء')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('حفظ')),
        ],
      ),
    );
    if (accepted != true) return;
    if (name.text.trim().isEmpty || (double.tryParse(price.text.trim()) ?? -1) < 0) {
      message('أدخل اسم الباقة وسعراً صحيحاً.');
      return;
    }
    final payload = <String, dynamic>{
      'name': name.text.trim(),
      'price_syp': double.parse(price.text.trim()),
      'description': description.text.trim().isEmpty ? null : description.text.trim(),
      'duration_minutes': duration.text.trim().isEmpty ? null : int.tryParse(duration.text.trim()),
      'included_items': included.text.split('\n').map((item) => item.trim()).where((item) => item.isNotEmpty).toList(),
      'is_active': true,
    };
    try {
      final api = context.read<ApiClient>();
      if (package == null) {
        await api.post('/provider/services/${widget.serviceId}/packages', payload);
      } else {
        await api.put('/provider/services/${widget.serviceId}/packages/${package['id']}', payload);
      }
      await load();
      message('تم حفظ الباقة وإرسال الخدمة للمراجعة.');
    } catch (exception) {
      message(exception.toString());
    } finally {
      name.dispose();
      price.dispose();
      duration.dispose();
      description.dispose();
      included.dispose();
    }
  }

  Future<void> disable(Map<String, dynamic> package) async {
    try {
      await context.read<ApiClient>().delete('/provider/services/${widget.serviceId}/packages/${package['id']}');
      await load();
      message('تم تعطيل الباقة.');
    } catch (exception) {
      message(exception.toString());
    }
  }
}
