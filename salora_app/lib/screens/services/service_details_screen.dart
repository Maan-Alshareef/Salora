import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../core/widgets/currency_toggle_chip.dart';
import '../../core/widgets/price_text.dart';
import '../../models/event_model.dart';
import '../../models/service_model.dart';
import '../../providers/booking_provider.dart';
import 'service_booking_sheet.dart';

class ServiceDetailsScreen extends StatefulWidget {
  final ServiceModel service;
  final EventModel? event;
  final String? preferredBookingId;

  const ServiceDetailsScreen({
    super.key,
    required this.service,
    this.event,
    this.preferredBookingId,
  });

  @override
  State<ServiceDetailsScreen> createState() => _ServiceDetailsScreenState();
}

class _ServiceDetailsScreenState extends State<ServiceDetailsScreen> {
  bool _openingRequest = false;
  int _galleryIndex = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<BookingProvider>().loadMyBookings();
    });
  }

  @override
  Widget build(BuildContext context) {
    final service = widget.service;
    final activeBookings = context
        .watch<BookingProvider>()
        .activeForProviderServices;

    return Scaffold(
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.all(16),
        child: ElevatedButton(
          onPressed: _openingRequest ? null : _requestService,
          child: Text(
            _openingRequest
                ? 'جاري فتح الحجوزات...'
                : activeBookings.isEmpty
                ? 'يتطلب حجز صالة فعالاً'
                : 'إضافة الخدمة إلى حجز فعال',
          ),
        ),
      ),
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 250,
            pinned: true,
            actions: const [CurrencyToggleChip(), SizedBox(width: 8)],
            flexibleSpace: FlexibleSpaceBar(
              background: Stack(
                fit: StackFit.expand,
                children: [
                  _headerGallery(service),
                  Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          Colors.black.withOpacity(.65),
                          Colors.transparent,
                        ],
                        begin: Alignment.bottomCenter,
                        end: Alignment.topCenter,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(18),
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
                            fontSize: 28,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          PriceText(
                            priceSyp: service.price,
                            style: const TextStyle(
                              color: AppColors.success,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          Text(
                            service.pricingLabel,
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    [
                      service.category,
                      service.providerName,
                      service.city,
                    ].where((value) => value.trim().isNotEmpty).join(' • '),
                    style: const TextStyle(color: AppColors.textSecondary),
                  ),
                  if (service.hasRating) ...[
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Icon(Icons.star_rounded, color: AppColors.accent),
                        Text(
                          ' ${service.rating.toStringAsFixed(1)}',
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 20),
                  const Text(
                    'نظرة عامة',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    service.description.trim().isEmpty
                        ? 'لم يضف مقدم الخدمة وصفاً بعد.'
                        : service.description,
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      height: 1.5,
                    ),
                  ),
                  if (service.availableEventTypes.isNotEmpty) ...[
                    const SizedBox(height: 20),
                    const Text(
                      'أنواع المناسبات المدعومة',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: service.displayEventTypes
                          .map((name) => Chip(label: Text(name)))
                          .toList(),
                    ),
                  ],
                  if (service.durationMinutes != null) ...[
                    const SizedBox(height: 20),
                    Text(
                      'المدة التقديرية: ${service.durationMinutes} دقيقة',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ],
                  const SizedBox(height: 20),
                  const Text(
                    'آلية الدفع',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${service.paymentLabel}. لا تضاف قيمتها تلقائياً إلى فاتورة الصالة.',
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 20),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withOpacity(.08),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Text(
                      activeBookings.isEmpty
                          ? 'لا يوجد حجز صالة مثبت حالياً. ارفع إيصال الدفع أولاً، وبعد أن يقبله مالك الصالة يمكنك طلب هذه الخدمة.'
                          : 'لديك ${activeBookings.length} حجز فعال يمكن ربط الخدمة بأحدها.',
                      style: const TextStyle(height: 1.45),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _headerGallery(ServiceModel service) {
    final images = service.galleryImages.take(6).toList();
    if (images.isEmpty) {
      return Container(
        color: AppColors.surface2,
        child: const Icon(Icons.room_service_outlined, size: 72),
      );
    }

    return Stack(
      fit: StackFit.expand,
      children: [
        PageView.builder(
          itemCount: images.length,
          onPageChanged: (value) => setState(() => _galleryIndex = value),
          itemBuilder: (_, index) => Image.network(
            images[index],
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => Container(
              color: AppColors.surface2,
              child: const Icon(Icons.broken_image_outlined, size: 58),
            ),
          ),
        ),
        if (images.length > 1)
          Positioned(
            left: 14,
            bottom: 14,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
              decoration: BoxDecoration(
                color: Colors.black54,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                '${_galleryIndex + 1}/${images.length}',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
      ],
    );
  }

  Future<void> _requestService() async {
    setState(() => _openingRequest = true);
    try {
      final result = await showServiceBookingSheet(
        context,
        service: widget.service,
        preferredBookingId:
            widget.preferredBookingId ?? widget.event?.bookingId,
      );
      if (result == true && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('تم إرسال طلب الخدمة إلى مقدم الخدمة.')),
        );
      }
    } finally {
      if (mounted) setState(() => _openingRequest = false);
    }
  }
}
