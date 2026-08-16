import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../core/widgets/price_text.dart';
import '../../models/service_model.dart';
import '../../providers/provider_account_provider.dart';

class ProviderServicesScreen extends StatelessWidget {
  const ProviderServicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ProviderAccountProvider>();
    final canCreate = provider.categories.isNotEmpty && provider.eventTypes.isNotEmpty;

    return Scaffold(
      appBar: AppBar(
        title: const Text('خدماتي'),
        actions: [
          IconButton(
            tooltip: 'إضافة خدمة',
            onPressed: canCreate ? () => _openForm(context) : null,
            icon: const Icon(Icons.add_circle_outline),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: context.read<ProviderAccountProvider>().load,
        child: provider.isLoading && provider.myServices.isEmpty
            ? ListView(children: const [SizedBox(height: 220), Center(child: CircularProgressIndicator())])
            : provider.error != null && provider.myServices.isEmpty
                ? _MessageList(
                    icon: Icons.cloud_off_outlined,
                    message: provider.error!,
                    buttonText: 'إعادة المحاولة',
                    onPressed: context.read<ProviderAccountProvider>().load,
                  )
                : provider.myServices.isEmpty
                    ? _MessageList(
                        icon: Icons.design_services_outlined,
                        message: canCreate
                            ? 'لا توجد خدمات بعد. أضف خدمتك الأولى وحدد السعر والصور.'
                            : 'لا توجد تصنيفات أو أنواع مناسبات متاحة بعد. تواصل مع الإدارة.',
                        buttonText: canCreate ? 'إضافة خدمة' : null,
                        onPressed: canCreate ? () => _openForm(context) : null,
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: provider.myServices.length,
                        itemBuilder: (context, index) {
                          final service = provider.myServices[index];
                          return _ServiceManagementCard(
                            service: service,
                            onEdit: canCreate ? () => _openForm(context, service: service) : null,
                            onDisable: service.isActive ? () => _confirmDisable(context, service.id) : null,
                          );
                        },
                      ),
      ),
      floatingActionButton: canCreate
          ? FloatingActionButton(onPressed: () => _openForm(context), child: const Icon(Icons.add))
          : null,
    );
  }

  void _openForm(BuildContext context, {ServiceModel? service}) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _ServiceForm(service: service),
    );
  }

  Future<void> _confirmDisable(BuildContext context, String id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('إيقاف الخدمة'),
        content: const Text('لن تظهر الخدمة للعملاء أو تقبل طلبات جديدة، وستبقى محفوظة في سجلاتك.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('تراجع')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('إيقاف')),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) return;
    try {
      await context.read<ProviderAccountProvider>().disableService(id);
    } catch (exception) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    }
  }
}

class _ServiceManagementCard extends StatelessWidget {
  const _ServiceManagementCard({
    required this.service,
    this.onEdit,
    this.onDisable,
  });

  final ServiceModel service;
  final VoidCallback? onEdit;
  final VoidCallback? onDisable;

  @override
  Widget build(BuildContext context) {
    final images = service.galleryImages.take(6).toList();
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: Colors.white10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 150,
            child: images.isEmpty
                ? Container(
                    color: AppColors.surface2,
                    child: const Center(child: Icon(Icons.photo_library_outlined, size: 48)),
                  )
                : PageView.builder(
                    itemCount: images.length,
                    itemBuilder: (_, imageIndex) => Stack(
                      fit: StackFit.expand,
                      children: [
                        Image.network(
                          images[imageIndex],
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => const Center(child: Icon(Icons.broken_image_outlined)),
                        ),
                        Positioned(
                          left: 10,
                          bottom: 10,
                          child: _ImageCounter(current: imageIndex + 1, total: images.length),
                        ),
                      ],
                    ),
                  ),
          ),
          Padding(
            padding: const EdgeInsets.all(15),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: Text(service.name, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900))),
                    PriceText(
                      priceSyp: service.price,
                      style: const TextStyle(color: AppColors.success, fontWeight: FontWeight.w900),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                Text('${service.category} • سعر ثابت للمناسبة', style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
                if (service.description.trim().isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(service.description, maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.textSecondary, height: 1.4)),
                ],
                const SizedBox(height: 10),
                _StatusBadge(
                  status: service.approvalStatus,
                  isActive: service.isActive,
                  rejectionReason: service.rejectionReason,
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: onEdit,
                    icon: const Icon(Icons.edit_outlined),
                    label: const Text('تعديل الخدمة والصور'),
                  ),
                ),
                const SizedBox(height: 6),
                SizedBox(width: double.infinity, child: TextButton.icon(onPressed: onDisable, icon: const Icon(Icons.pause_circle_outline), label: const Text('إيقاف الخدمة بأمان'))),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ServiceForm extends StatefulWidget {
  const _ServiceForm({this.service});

  final ServiceModel? service;

  @override
  State<_ServiceForm> createState() => _ServiceFormState();
}

class _ServiceFormState extends State<_ServiceForm> {
  late final TextEditingController name;
  late final TextEditingController price;
  late final TextEditingController duration;
  late final TextEditingController description;
  String? categoryId;
  late Set<String> selectedEventTypeIds;
  final List<XFile> selectedImages = [];
  bool loading = false;
  bool managingImages = false;

  ServiceModel? get service {
    final original = widget.service;
    if (original == null) return null;
    final account = context.read<ProviderAccountProvider>();
    for (final current in account.myServices) {
      if (current.id == original.id) return current;
    }
    return original;
  }

  @override
  void initState() {
    super.initState();
    final current = widget.service;
    name = TextEditingController(text: current?.editableName ?? '');
    price = TextEditingController(text: current == null || current.price == 0 ? '' : current.price.toString());
    duration = TextEditingController(text: current?.durationMinutes?.toString() ?? '');
    description = TextEditingController(text: current?.description ?? '');

    final account = context.read<ProviderAccountProvider>();
    final selected = current == null
        ? <String>[]
        : account.eventTypes
            .where((eventType) => current.displayEventTypes.any((value) => value == eventType.name || value == eventType.nameEn))
            .map((eventType) => eventType.id)
            .toList();
    selectedEventTypeIds = selected.toSet();
    final currentCategoryId = current?.categoryId;
    categoryId = currentCategoryId != null &&
            account.categories.any((item) => item.id == currentCategoryId)
        ? currentCategoryId
        : (account.categories.isEmpty ? null : account.categories.first.id);
  }

  @override
  void dispose() {
    name.dispose();
    price.dispose();
    duration.dispose();
    description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final account = context.watch<ProviderAccountProvider>();
    final currentService = service;
    final currentImages = currentService?.imageItems ?? const <ServiceImageModel>[];
    final remaining = 6 - currentImages.length;

    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 12,
        bottom: MediaQuery.of(context).viewInsets.bottom + 16,
      ),
      child: ListView(
        children: [
          Text(
            currentService == null ? 'إضافة خدمة' : 'تعديل الخدمة ومعرض الأعمال',
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          const Text(
            'حدد سعراً ثابتاً للخدمة. يمكن إضافة من صورة واحدة إلى 6 صور، وتظهر الخدمة للعملاء بعد موافقة الإدارة.',
            textAlign: TextAlign.center,
            style: TextStyle(color: AppColors.textSecondary, fontSize: 12, height: 1.45),
          ),
          const SizedBox(height: 16),
          TextField(controller: name, decoration: const InputDecoration(labelText: 'اسم الخدمة *')),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: categoryId,
            items: account.categories.map((category) => DropdownMenuItem(value: category.id, child: Text(category.name))).toList(),
            onChanged: (value) => setState(() => categoryId = value),
            decoration: const InputDecoration(labelText: 'تصنيف الخدمة *'),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: price,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'السعر الثابت بالليرة السورية *',
              helperText: 'يظهر هذا السعر للعميل على أنه سعر الخدمة للمناسبة.',
            ),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: duration,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'المدة التقديرية بالدقائق (اختياري)'),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: description,
            minLines: 3,
            maxLines: 6,
            decoration: const InputDecoration(labelText: 'الوصف والتفاصيل *'),
          ),
          const SizedBox(height: 15),
          const Text('أنواع المناسبات المناسبة للخدمة *', style: TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: account.eventTypes.map((eventType) => FilterChip(
              label: Text('${eventType.emoji} ${eventType.name}'),
              selected: selectedEventTypeIds.contains(eventType.id),
              onSelected: (selected) => setState(() {
                if (selected) {
                  selectedEventTypeIds.add(eventType.id);
                } else {
                  selectedEventTypeIds.remove(eventType.id);
                }
              }),
            )).toList(),
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              const Expanded(child: Text('معرض صور الخدمة', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w900))),
              Text('${currentImages.length + selectedImages.length}/6', style: const TextStyle(color: AppColors.textSecondary)),
            ],
          ),
          const SizedBox(height: 6),
          const Text('الصورة الأولى أو التي تحمل علامة النجمة هي صورة الغلاف.', style: TextStyle(color: AppColors.textSecondary, fontSize: 12)),
          if (currentImages.isNotEmpty) ...[
            const SizedBox(height: 10),
            _ExistingImagesEditor(
              serviceId: currentService!.id,
              images: currentImages,
              disabled: managingImages || loading,
              onBusyChanged: (value) => setState(() => managingImages = value),
            ),
          ],
          if (selectedImages.isNotEmpty) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: List.generate(selectedImages.length, (index) {
                final image = selectedImages[index];
                return Stack(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(14),
                      child: Image.file(File(image.path), width: 100, height: 86, fit: BoxFit.cover),
                    ),
                    Positioned(
                      top: 4,
                      left: 4,
                      child: CircleAvatar(
                        radius: 15,
                        backgroundColor: Colors.black.withValues(alpha: .65),
                        child: IconButton(
                          padding: EdgeInsets.zero,
                          iconSize: 16,
                          onPressed: loading ? null : () => setState(() => selectedImages.removeAt(index)),
                          icon: const Icon(Icons.close, color: Colors.white),
                        ),
                      ),
                    ),
                  ],
                );
              }),
            ),
          ],
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: loading || managingImages || remaining - selectedImages.length <= 0 ? null : () => _pickImages(remaining),
            icon: const Icon(Icons.add_photo_alternate_outlined),
            label: Text(remaining - selectedImages.length <= 0
                ? 'وصلت إلى الحد الأقصى (6 صور)'
                : 'إضافة صور (${remaining - selectedImages.length} متاحة)'),
          ),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: loading || managingImages || categoryId == null ? null : _submit,
            child: Text(loading ? 'جاري الحفظ ورفع الصور...' : 'حفظ وإرسال للمراجعة'),
          ),
        ],
      ),
    );
  }

  Future<void> _pickImages(int remaining) async {
    final picked = await ImagePicker().pickMultiImage(imageQuality: 88, maxWidth: 1800);
    if (!mounted || picked.isEmpty) return;
    final available = remaining - selectedImages.length;
    if (available <= 0) return;
    setState(() => selectedImages.addAll(picked.take(available)));
    if (picked.length > available && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم اختيار أول الصور فقط لأن الحد الأقصى للخدمة هو 6 صور.')));
    }
  }

  Future<void> _submit() async {
    final currentImagesCount = service?.imageItems.length ?? 0;
    if (currentImagesCount + selectedImages.length < 1) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('أضف صورة واحدة على الأقل قبل إرسال الخدمة للمراجعة.')));
      return;
    }
    if (description.text.trim().length < 10) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('اكتب وصفاً واضحاً للخدمة من 10 أحرف على الأقل.')));
      return;
    }
    final durationMinutes = duration.text.trim().isEmpty ? null : int.tryParse(duration.text.trim());
    if (duration.text.trim().isNotEmpty && (durationMinutes == null || durationMinutes < 15)) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('المدة يجب أن تكون 15 دقيقة على الأقل.')));
      return;
    }

    setState(() => loading = true);
    try {
      final imageUploadFailed = await context.read<ProviderAccountProvider>().saveService(
            id: service?.id,
            name: name.text,
            categoryId: categoryId!,
            priceSyp: int.tryParse(price.text.trim()) ?? 0,
            durationMinutes: durationMinutes,
            description: description.text,
            eventTypeIds: selectedEventTypeIds.toList(),
            imagePaths: selectedImages.map((image) => image.path).toList(),
          );
      if (!mounted) return;
      final messenger = ScaffoldMessenger.of(context);
      Navigator.pop(context);
      messenger.showSnackBar(SnackBar(
        content: Text(imageUploadFailed
            ? 'تم حفظ بيانات الخدمة، لكن تعذر رفع بعض الصور. افتح الخدمة وأعد رفعها.'
            : 'تم حفظ الخدمة ومعرض الصور وإرسالها لمراجعة الإدارة.'),
      ));
    } catch (exception) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }
}

class _ExistingImagesEditor extends StatelessWidget {
  const _ExistingImagesEditor({
    required this.serviceId,
    required this.images,
    required this.disabled,
    required this.onBusyChanged,
  });

  final String serviceId;
  final List<ServiceImageModel> images;
  final bool disabled;
  final ValueChanged<bool> onBusyChanged;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: List.generate(images.length, (index) {
        final image = images[index];
        return Container(
          key: ValueKey(image.id),
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(color: AppColors.surface2, borderRadius: BorderRadius.circular(16)),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: Image.network(
                  image.url,
                  width: 72,
                  height: 60,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => const SizedBox(width: 72, height: 60, child: Icon(Icons.broken_image_outlined)),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  image.isMain ? 'صورة الغلاف' : 'الصورة ${index + 1}',
                  style: TextStyle(fontWeight: FontWeight.w800, color: image.isMain ? AppColors.primary : null),
                ),
              ),
              IconButton(
                tooltip: 'تعيين كغلاف',
                onPressed: disabled || image.isMain ? null : () => _run(context, () => context.read<ProviderAccountProvider>().setMainServiceImage(serviceId, image.id)),
                icon: Icon(image.isMain ? Icons.star_rounded : Icons.star_border_rounded, color: Colors.amber),
              ),
              IconButton(
                tooltip: 'نقل للأعلى',
                onPressed: disabled || index == 0 ? null : () {
                  final order = images.map((item) => item.id).toList();
                  final moved = order.removeAt(index);
                  order.insert(index - 1, moved);
                  _run(context, () => context.read<ProviderAccountProvider>().reorderServiceImages(serviceId, order));
                },
                icon: const Icon(Icons.arrow_upward_rounded),
              ),
              IconButton(
                tooltip: 'حذف الصورة',
                onPressed: disabled ? null : () => _confirmDelete(context, image.id),
                icon: const Icon(Icons.delete_outline, color: Colors.redAccent),
              ),
            ],
          ),
        );
      }),
    );
  }

  Future<void> _confirmDelete(BuildContext context, String imageId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('حذف الصورة'),
        content: const Text('سيعاد إرسال الخدمة لمراجعة الأدمن بعد تعديل معرض الصور.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('تراجع')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('حذف')),
        ],
      ),
    );
    if (confirmed == true && context.mounted) {
      await _run(context, () => context.read<ProviderAccountProvider>().deleteServiceImage(serviceId, imageId));
    }
  }

  Future<void> _run(BuildContext context, Future<void> Function() action) async {
    onBusyChanged(true);
    try {
      await action();
    } catch (exception) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      onBusyChanged(false);
    }
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status, required this.isActive, this.rejectionReason});

  final String status;
  final bool isActive;
  final String? rejectionReason;

  @override
  Widget build(BuildContext context) {
    final label = !isActive && status == 'approved'
        ? 'متوقفة'
        : switch (status) {
            'pending' => 'قيد مراجعة الإدارة',
            'rejected' => 'مرفوضة',
            'approved' => 'معتمدة',
            _ => status,
          };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        DecoratedBox(
          decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: .10), borderRadius: BorderRadius.circular(20)),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            child: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800)),
          ),
        ),
        if (rejectionReason != null && rejectionReason!.trim().isNotEmpty) ...[
          const SizedBox(height: 6),
          Text('سبب الرفض: $rejectionReason', style: const TextStyle(color: Colors.redAccent, fontSize: 12)),
        ],
      ],
    );
  }
}

class _ImageCounter extends StatelessWidget {
  const _ImageCounter({required this.current, required this.total});

  final int current;
  final int total;

  @override
  Widget build(BuildContext context) => DecoratedBox(
        decoration: BoxDecoration(color: Colors.black.withValues(alpha: .60), borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          child: Text('$current/$total', style: const TextStyle(color: Colors.white, fontSize: 12)),
        ),
      );
}

class _MessageList extends StatelessWidget {
  const _MessageList({required this.icon, required this.message, this.buttonText, this.onPressed});

  final IconData icon;
  final String message;
  final String? buttonText;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 110),
          Icon(icon, size: 58, color: AppColors.textSecondary),
          const SizedBox(height: 14),
          Text(message, textAlign: TextAlign.center, style: const TextStyle(height: 1.5)),
          if (buttonText != null) ...[
            const SizedBox(height: 14),
            ElevatedButton(onPressed: onPressed, child: Text(buttonText!)),
          ],
        ],
      );
}
