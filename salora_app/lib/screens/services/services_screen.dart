import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../core/widgets/currency_toggle_chip.dart';
import '../../core/widgets/price_text.dart';
import '../../core/widgets/user_avatar.dart';
import '../../models/event_model.dart';
import '../../models/provider_directory_model.dart';
import '../../providers/booking_provider.dart';
import '../../providers/service_provider.dart';
import 'provider_details_screen.dart';

class ServicesScreen extends StatefulWidget {
  final EventModel? event;
  final String? preferredBookingId;
  final String? preferredEventType;

  const ServicesScreen({
    super.key,
    this.event,
    this.preferredBookingId,
    this.preferredEventType,
  });

  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  String _categoryId = '';
  final _search = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    await Future.wait([
      context.read<ServiceProviderState>().loadDirectory(
        categoryId: _categoryId,
        query: _search.text,
      ),
      context.read<BookingProvider>().loadMyBookings(),
    ]);
  }

  void _onSearch(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted)
        context.read<ServiceProviderState>().loadDirectory(
          categoryId: _categoryId,
          query: value,
        );
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<ServiceProviderState>();
    final categories = state.categoryModels;
    final requestedEventType =
        widget.event?.displayEventType ?? widget.preferredEventType;
    final providers =
        requestedEventType == null || requestedEventType.trim().isEmpty
        ? state.providers
        : state.providers
              .map(
                (provider) => ProviderDirectoryModel(
                  id: provider.id,
                  name: provider.name,
                  avatarUrl: provider.avatarUrl,
                  city: provider.city,
                  bio: provider.bio,
                  contactPhone: provider.contactPhone,
                  whatsappPhone: provider.whatsappPhone,
                  rating: provider.rating,
                  reviewsCount: provider.reviewsCount,
                  servicesCount: provider.services
                      .where(
                        (service) => service.supportsEvent(requestedEventType),
                      )
                      .length,
                  lowestPriceSyp: provider.lowestPriceSyp,
                  services: provider.services
                      .where(
                        (service) => service.supportsEvent(requestedEventType),
                      )
                      .toList(),
                ),
              )
              .where((provider) => provider.services.isNotEmpty)
              .toList();

    return Scaffold(
      appBar: AppBar(
        title: const Text('مقدمو الخدمات'),
        actions: const [CurrencyToggleChip(), SizedBox(width: 8)],
      ),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(26),
                gradient: const LinearGradient(
                  colors: [AppColors.primary, AppColors.secondary],
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.storefront_rounded,
                    color: Colors.white,
                    size: 36,
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'دليل مقدمي الخدمات المعتمدين',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 23,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    requestedEventType == null ||
                            requestedEventType.trim().isEmpty
                        ? 'شاهد ملف مقدم الخدمة وصور أعماله، تواصل معه بالاتصال أو واتساب، ثم أرسل طلب الخدمة من داخل Salora.'
                        : 'مقدمو الخدمات المناسبون لـ $requestedEventType.',
                    style: const TextStyle(color: Colors.white70, height: 1.45),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _search,
              onChanged: _onSearch,
              decoration: InputDecoration(
                hintText: 'ابحث باسم المقدم أو الخدمة أو المدينة...',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: _search.text.isEmpty
                    ? null
                    : IconButton(
                        onPressed: () {
                          _search.clear();
                          _onSearch('');
                          setState(() {});
                        },
                        icon: const Icon(Icons.close),
                      ),
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              height: 43,
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: [
                  ChoiceChip(
                    label: const Text('الكل'),
                    selected: _categoryId.isEmpty,
                    onSelected: (_) {
                      setState(() => _categoryId = '');
                      context.read<ServiceProviderState>().loadDirectory(
                        categoryId: '',
                        query: _search.text,
                      );
                    },
                  ),
                  const SizedBox(width: 8),
                  ...categories.map(
                    (category) => Padding(
                      padding: const EdgeInsets.only(left: 8),
                      child: ChoiceChip(
                        avatar: category.imageUrl.isEmpty
                            ? null
                            : CircleAvatar(
                                backgroundImage: NetworkImage(
                                  category.imageUrl,
                                ),
                              ),
                        label: Text(category.name),
                        selected: _categoryId == category.id,
                        onSelected: (_) {
                          setState(() => _categoryId = category.id);
                          context.read<ServiceProviderState>().loadDirectory(
                            categoryId: category.id,
                            query: _search.text,
                          );
                        },
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            if (state.isLoading && state.providers.isEmpty)
              const Padding(
                padding: EdgeInsets.all(40),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (state.error != null && state.providers.isEmpty)
              _Message(
                icon: Icons.cloud_off_outlined,
                text: state.error!,
                action: _refresh,
              )
            else if (providers.isEmpty)
              const _Message(
                icon: Icons.person_search_outlined,
                text: 'لا يوجد مقدمو خدمات ضمن البحث أو التصنيف المحدد.',
              )
            else ...[
              Text(
                '${providers.length} مقدم خدمة',
                style: const TextStyle(color: AppColors.textSecondary),
              ),
              const SizedBox(height: 10),
              ...providers.map(
                (provider) => _ProviderCard(
                  provider: provider,
                  event: widget.event,
                  preferredBookingId: widget.preferredBookingId,
                  preferredEventType: requestedEventType,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ProviderCard extends StatelessWidget {
  const _ProviderCard({
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
  Widget build(BuildContext context) {
    final previewImages = provider.services
        .expand((service) => service.galleryImages)
        .take(3)
        .toList();
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: Colors.white10),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ProviderDetailsScreen(
              provider: provider,
              event: event,
              preferredBookingId: preferredBookingId,
              preferredEventType: preferredEventType,
            ),
          ),
        ),
        child: Column(
          children: [
            Row(
              children: [
                UserAvatar(imageUrl: provider.avatarUrl, radius: 34),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        provider.name,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      if (provider.city.isNotEmpty)
                        Text(
                          provider.city,
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      const SizedBox(height: 5),
                      Row(
                        children: [
                          const Icon(
                            Icons.star_rounded,
                            color: Colors.amber,
                            size: 18,
                          ),
                          Text(' ${provider.rating.toStringAsFixed(1)}'),
                          const SizedBox(width: 12),
                          Text(
                            '${provider.services.length} خدمة معتمدة',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.arrow_forward_ios_rounded, size: 16),
              ],
            ),
            if (previewImages.isNotEmpty) ...[
              const SizedBox(height: 12),
              SizedBox(
                height: 82,
                child: Row(
                  children: previewImages
                      .map(
                        (image) => Expanded(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 3),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(13),
                              child: Image.network(
                                image,
                                fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) => Container(
                                  color: AppColors.surface2,
                                  child: const Icon(
                                    Icons.broken_image_outlined,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      )
                      .toList(),
                ),
              ),
            ],
            const SizedBox(height: 11),
            Row(
              children: [
                Expanded(
                  child: Text(
                    provider.services
                        .map((service) => service.category)
                        .toSet()
                        .join(' • '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 12,
                    ),
                  ),
                ),
                if (provider.lowestPriceSyp > 0)
                  PriceText(
                    priceSyp: provider.lowestPriceSyp,
                    prefix: 'يبدأ من ',
                    style: const TextStyle(
                      color: AppColors.success,
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.text, this.action});

  final IconData icon;
  final String text;
  final Future<void> Function()? action;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 36),
    child: Column(
      children: [
        Icon(icon, size: 58, color: AppColors.textSecondary),
        const SizedBox(height: 10),
        Text(text, textAlign: TextAlign.center),
        if (action != null) ...[
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: action,
            child: const Text('إعادة المحاولة'),
          ),
        ],
      ],
    ),
  );
}
