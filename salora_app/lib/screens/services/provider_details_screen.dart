import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/theme/app_colors.dart';
import '../../core/widgets/price_text.dart';
import '../../core/widgets/user_avatar.dart';
import '../../models/event_model.dart';
import '../../models/provider_directory_model.dart';
import '../../models/service_model.dart';
import '../../providers/service_provider.dart';
import 'service_booking_sheet.dart';
import 'service_details_screen.dart';

class ProviderDetailsScreen extends StatefulWidget {
  const ProviderDetailsScreen({
    super.key,
    required this.provider,
    this.event,
    this.preferredBookingId,
    this.preferredEventType,
  });

  final ProviderDirectoryModel provider;
  final EventModel? event;
  final String? preferredBookingId;
  final String? preferredEventType;

  @override
  State<ProviderDetailsScreen> createState() => _ProviderDetailsScreenState();
}

class _ProviderDetailsScreenState extends State<ProviderDetailsScreen> {
  ProviderDirectoryModel? _provider;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _provider = widget.provider;
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
  }

  Future<void> _refresh() async {
    setState(() => _loading = true);
    try {
      final fresh = await context.read<ServiceProviderState>().loadProvider(
        widget.provider.id,
      );
      if (mounted) setState(() => _provider = fresh);
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = _provider ?? widget.provider;
    final requestedEventType =
        widget.event?.displayEventType ?? widget.preferredEventType;
    final services =
        requestedEventType == null || requestedEventType.trim().isEmpty
        ? provider.services
        : provider.services
              .where((service) => service.supportsEvent(requestedEventType))
              .toList();

    return Scaffold(
      appBar: AppBar(title: const Text('ملف مقدم الخدمة')),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(26),
                border: Border.all(color: Colors.white10),
              ),
              child: Column(
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      UserAvatar(imageUrl: provider.avatarUrl, radius: 42),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              provider.name,
                              style: const TextStyle(
                                fontSize: 23,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            if (provider.city.isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Text(
                                provider.city,
                                style: const TextStyle(
                                  color: AppColors.textSecondary,
                                ),
                              ),
                            ],
                            const SizedBox(height: 7),
                            Row(
                              children: [
                                const Icon(
                                  Icons.star_rounded,
                                  color: Colors.amber,
                                  size: 19,
                                ),
                                Text(
                                  ' ${provider.rating.toStringAsFixed(1)} (${provider.reviewsCount})',
                                ),
                                const SizedBox(width: 12),
                                Text(
                                  '${provider.servicesCount} خدمة',
                                  style: const TextStyle(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      if (_loading)
                        const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                    ],
                  ),
                  if (provider.bio.trim().isNotEmpty) ...[
                    const SizedBox(height: 14),
                    Align(
                      alignment: Alignment.centerRight,
                      child: Text(
                        provider.bio,
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          height: 1.55,
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      if (provider.contactPhone?.trim().isNotEmpty ?? false)
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () => _openPhone(provider.contactPhone!),
                            icon: const Icon(Icons.call_outlined),
                            label: const Text('اتصال'),
                          ),
                        ),
                      if ((provider.contactPhone?.trim().isNotEmpty ?? false) &&
                          (provider.whatsappPhone?.trim().isNotEmpty ?? false))
                        const SizedBox(width: 10),
                      if (provider.whatsappPhone?.trim().isNotEmpty ?? false)
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: () => _openWhatsApp(
                              provider.whatsappPhone!,
                              provider.name,
                            ),
                            icon: const Icon(Icons.chat_outlined),
                            label: const Text('واتساب'),
                          ),
                        ),
                    ],
                  ),
                  if ((provider.contactPhone?.trim().isEmpty ?? true) &&
                      (provider.whatsappPhone?.trim().isEmpty ?? true))
                    const Text(
                      'مقدم الخدمة لم يفعّل وسيلة تواصل خارجية بعد.',
                      style: TextStyle(color: AppColors.textSecondary),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            Text(
              requestedEventType == null || requestedEventType.trim().isEmpty
                  ? 'الخدمات المعتمدة'
                  : 'خدمات مناسبة لـ $requestedEventType',
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 10),
            if (services.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 32),
                child: Center(
                  child: Text('لا توجد خدمات مناسبة للاختيار الحالي.'),
                ),
              )
            else
              ...services.map(
                (service) => _ProviderServiceCard(
                  service: service,
                  event: widget.event,
                  preferredBookingId: widget.preferredBookingId,
                ),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _openPhone(String phone) async {
    final uri = Uri(scheme: 'tel', path: phone.trim());
    if (!await launchUrl(uri)) _showLaunchError('تعذر فتح تطبيق الاتصال.');
  }

  Future<void> _openWhatsApp(String phone, String providerName) async {
    var normalized = phone.replaceAll(RegExp(r'[^0-9]'), '');
    if (normalized.startsWith('00')) normalized = normalized.substring(2);
    // Syrian local numbers are commonly stored as 09XXXXXXXX. WhatsApp links
    // require an international number without + or leading zero.
    if (normalized.startsWith('0'))
      normalized = '963${normalized.substring(1)}';
    if (normalized.length == 9 && normalized.startsWith('9'))
      normalized = '963$normalized';
    if (normalized.isEmpty) {
      _showLaunchError('رقم واتساب غير صالح.');
      return;
    }
    final text = Uri.encodeComponent(
      'مرحباً $providerName، أتواصل معك من تطبيق Salora للاستفسار عن خدماتك.',
    );
    final uri = Uri.parse('https://wa.me/$normalized?text=$text');
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication))
      _showLaunchError('تعذر فتح واتساب.');
  }

  void _showLaunchError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _ProviderServiceCard extends StatelessWidget {
  const _ProviderServiceCard({
    required this.service,
    this.event,
    this.preferredBookingId,
  });

  final ServiceModel service;
  final EventModel? event;
  final String? preferredBookingId;

  @override
  Widget build(BuildContext context) {
    final images = service.galleryImages.take(6).toList();
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
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
            height: 190,
            child: images.isEmpty
                ? Container(
                    color: AppColors.surface2,
                    child: const Center(
                      child: Icon(Icons.photo_library_outlined, size: 54),
                    ),
                  )
                : PageView.builder(
                    itemCount: images.length,
                    itemBuilder: (_, index) => Stack(
                      fit: StackFit.expand,
                      children: [
                        Image.network(
                          images[index],
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => const Center(
                            child: Icon(Icons.broken_image_outlined),
                          ),
                        ),
                        Positioned(
                          left: 10,
                          bottom: 10,
                          child: DecoratedBox(
                            decoration: BoxDecoration(
                              color: Colors.black54,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 5,
                              ),
                              child: Text(
                                '${index + 1}/${images.length}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                          ),
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
                    Expanded(
                      child: Text(
                        service.name,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    PriceText(
                      priceSyp: service.price,
                      style: const TextStyle(
                        color: AppColors.success,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                Text(
                  '${service.category} • سعر ثابت للمناسبة',
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontSize: 12,
                  ),
                ),
                if (service.description.trim().isNotEmpty) ...[
                  const SizedBox(height: 9),
                  Text(
                    service.description,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      height: 1.45,
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => ServiceDetailsScreen(
                              service: service,
                              event: event,
                              preferredBookingId: preferredBookingId,
                            ),
                          ),
                        ),
                        child: const Text('التفاصيل والصور'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () async {
                          final result = await showServiceBookingSheet(
                            context,
                            service: service,
                            preferredBookingId:
                                preferredBookingId ?? event?.bookingId,
                          );
                          if (result == true && context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text(
                                  'تم إرسال طلب الخدمة داخل Salora.',
                                ),
                              ),
                            );
                          }
                        },
                        child: const Text('طلب الخدمة'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
