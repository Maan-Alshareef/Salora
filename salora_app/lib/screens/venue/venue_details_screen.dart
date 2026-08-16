import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';

import '../../core/theme/app_colors.dart';
import '../../core/widgets/price_text.dart';
import '../../models/review_model.dart';
import '../../models/venue_model.dart';
import '../../providers/booking_provider.dart';
import '../../providers/compare_provider.dart';
import '../../providers/favorite_provider.dart';
import '../../providers/review_provider.dart';
import '../booking/booking_screen.dart';

class VenueDetailsScreen extends StatefulWidget {
  final VenueModel venue;

  const VenueDetailsScreen({super.key, required this.venue});

  @override
  State<VenueDetailsScreen> createState() => _VenueDetailsScreenState();
}

class _VenueDetailsScreenState extends State<VenueDetailsScreen> {
  int imageIndex = 0;
  final TextEditingController _reviewCommentController =
      TextEditingController();
  bool _showReviewForm = false;
  int _selectedRating = 5;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => context.read<ReviewProvider>().loadVenueReviews(widget.venue.id),
    );
  }

  @override
  void dispose() {
    _reviewCommentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final venue = widget.venue;
    final isFav = context.watch<FavoriteProvider>().isFavorite(venue.id);
    final inCompare = context.watch<CompareProvider>().contains(venue.id);
    final localReviews = context.watch<ReviewProvider>().reviewsForVenue(
      venue.id,
    );
    final ratingSummary = _ratingSummary(venue, localReviews);

    return Scaffold(
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
          decoration: BoxDecoration(
            color: Theme.of(context).scaffoldBackgroundColor,
            border: const Border(top: BorderSide(color: Colors.white10)),
          ),
          child: Row(
            children: [
              Expanded(
                flex: 4,
                child: SizedBox(
                  height: 56,
                  child: _compareButton(context, venue, inCompare),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 6,
                child: SizedBox(
                  height: 56,
                  child: ElevatedButton(
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => BookingScreen(venue: venue),
                      ),
                    ),
                    child: const Text('احجز الآن'),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 300,
            pinned: true,
            actions: [
              IconButton.filledTonal(
                onPressed: () =>
                    context.read<FavoriteProvider>().toggleFavorite(venue),
                icon: Icon(
                  isFav ? Icons.favorite : Icons.favorite_border,
                  color: isFav ? Colors.redAccent : null,
                ),
              ),
              const SizedBox(width: 10),
            ],
            flexibleSpace: FlexibleSpaceBar(background: _gallery(venue)),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 18, 18, 120),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          venue.name,
                          style: const TextStyle(
                            fontSize: 26,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      PriceText(
                        priceSyp: venue.finalPrice,
                        style: const TextStyle(
                          color: AppColors.success,
                          fontWeight: FontWeight.w900,
                          fontSize: 16,
                        ),
                      ),
                    ],
                  ),
                  if (venue.hasDiscount) ...[
                    const SizedBox(height: 10),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      decoration: BoxDecoration(
                        color: AppColors.success.withValues(alpha: .12),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AppColors.success.withValues(alpha: .35)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.local_offer_outlined, color: AppColors.success),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              venue.discountPercentage == null
                                  ? 'يوجد عرض فعال على هذه الصالة'
                                  : 'عرض فعال: خصم ${venue.discountPercentage}% — السعر الظاهر محدث بعد الخصم',
                              style: const TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 8),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(
                        Icons.location_on_outlined,
                        size: 18,
                        color: AppColors.textSecondary,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          venue.address,
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            height: 1.35,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(
                        Icons.star_rounded,
                        color: Colors.amber,
                        size: 18,
                      ),
                      Text(
                        ' ${ratingSummary.average.toStringAsFixed(1)} (${ratingSummary.count} تقييم)',
                      ),
                      const Spacer(),
                      const Icon(Icons.people_outline, size: 18),
                      Text(' ${venue.capacity} ضيف'),
                    ],
                  ),
                  const SizedBox(height: 20),
                  _section('نظرة عامة', venue.description),
                  _venueVideos(venue),
                  _chips('مناسبة لـ', venue.eventTypes),
                  _chips('مجانية ضمن سعر الصالة', venue.includedServices),
                  _pricedHallExtras(venue),
                  _chips('المزايا', venue.amenities),
                  _policies(),
                  _reviews(context, venue, localReviews, ratingSummary),
                  _location(context, venue),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _gallery(VenueModel venue) {
    if (venue.images.isEmpty) {
      return _imageFallback(venue, 0);
    }

    return Stack(
      children: [
        PageView.builder(
          key: PageStorageKey<String>('venue-gallery-${venue.id}'),
          physics: const PageScrollPhysics(),
          allowImplicitScrolling: true,
          itemCount: venue.images.length,
          onPageChanged: (i) => setState(() => imageIndex = i),
          itemBuilder: (context, index) {
            final image = venue.images[index];
            final isNetwork =
                image.startsWith('http://') || image.startsWith('https://');
            if (image.isEmpty) return _imageFallback(venue, index);
            return isNetwork
                ? Image.network(
                    image,
                    fit: BoxFit.cover,
                    width: double.infinity,
                    cacheWidth: 1440,
                    filterQuality: FilterQuality.medium,
                    gaplessPlayback: true,
                    loadingBuilder: (context, child, progress) =>
                        progress == null ? child : _imageFallback(venue, index),
                    errorBuilder: (_, __, ___) => _imageFallback(venue, index),
                  )
                : Image.asset(
                    image,
                    fit: BoxFit.cover,
                    width: double.infinity,
                    errorBuilder: (_, __, ___) => _imageFallback(venue, index),
                  );
          },
        ),
        Positioned.fill(
          child: IgnorePointer(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    Colors.black.withValues(alpha: .30),
                    Colors.transparent,
                    Colors.black.withValues(alpha: .35),
                  ],
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                ),
              ),
            ),
          ),
        ),
        if (venue.images.length > 1)
          Positioned(
            bottom: 18,
            right: 18,
            child: IgnorePointer(
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 7,
                ),
                decoration: BoxDecoration(
                  color: Colors.black54,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  '${imageIndex + 1}/${venue.images.length}',
                  style: const TextStyle(color: Colors.white),
                ),
              ),
            ),
          ),
      ],
    );
  }

  Widget _imageFallback(VenueModel venue, int index) => Container(
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        colors: [AppColors.primary, AppColors.secondary],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
    ),
    child: Center(
      child: Text(
        '${venue.name}\nالصورة ${index + 1}',
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontSize: 25,
          fontWeight: FontWeight.w900,
          color: Colors.white,
        ),
      ),
    ),
  );

  Widget _compareButton(
    BuildContext context,
    VenueModel venue,
    bool inCompare,
  ) {
    return OutlinedButton.icon(
      onPressed: () {
        context.read<CompareProvider>().addOrReplace(venue);
      },
      icon: Icon(inCompare ? Icons.check_circle : Icons.compare_arrows_rounded),
      label: Text(inCompare ? 'تمت الإضافة' : 'المقارنة'),
    );
  }

  Widget _section(String title, String body) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        Text(
          body,
          style: const TextStyle(color: AppColors.textSecondary, height: 1.5),
        ),
      ],
    ),
  );

  Widget _venueVideos(VenueModel venue) {
    if (venue.videos.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'فيديوهات الصالة',
            style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 10),
          ...venue.videos.asMap().entries.map(
            (entry) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _VenueVideoCard(url: entry.value, index: entry.key + 1),
            ),
          ),
        ],
      ),
    );
  }

  Widget _pricedHallExtras(VenueModel venue) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'خدمات مدفوعة إضافية من الصالة',
          style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 10),
        if (venue.hallExtraServices.isEmpty)
          const Text(
            'لا توجد خدمات مدفوعة مضافة.',
            style: TextStyle(color: AppColors.textSecondary),
          )
        else
          ...venue.hallExtraServices.map(
            (service) => Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${_emojiFor(service.name, service.category)} ${service.name}',
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        Text(
                          service.category,
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  PriceText(
                    priceSyp: service.price,
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    ),
  );

  Widget _chips(String title, List<String> items) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: items
              .map(
                (item) => Chip(
                  label: Text('${_emojiFor(item, item)} $item'),
                  avatar: const Icon(Icons.check_circle_outline, size: 17),
                ),
              )
              .toList(),
        ),
      ],
    ),
  );

  Widget _policies() => _section(
    'السياسات',
    'يلزم دفع عربون لتأكيد الحجز. يمكن إلغاء الطلب قبل الموافقة. تتم مراجعة إيصال الدفع من لوحة التحكم.',
  );

  _RatingSummary _ratingSummary(VenueModel venue, List<ReviewModel> reviews) {
    if (reviews.isEmpty)
      return _RatingSummary(venue.rating, venue.reviewsCount);
    final total = reviews.fold<int>(0, (sum, review) => sum + review.rating);
    return _RatingSummary(total / reviews.length, reviews.length);
  }

  Widget _reviews(
    BuildContext context,
    VenueModel venue,
    List<ReviewModel> localReviews,
    _RatingSummary ratingSummary,
  ) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'التقييمات',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              TextButton.icon(
                onPressed: () {
                  final eligible = context
                      .read<BookingProvider>()
                      .completedBookingForVenue(venue.id);
                  if (eligible == null) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text(
                          'يمكن التقييم بعد إتمام حجز فعلي لهذه الصالة.',
                        ),
                      ),
                    );
                    return;
                  }
                  setState(() => _showReviewForm = !_showReviewForm);
                },
                icon: const Icon(Icons.star_rate_rounded),
                label: const Text('تقييم'),
              ),
            ],
          ),
          Text(
            '${ratingSummary.average.toStringAsFixed(1)} ★  •  ${ratingSummary.count} تقييم',
            style: const TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 10),
          if (_showReviewForm) ...[
            _inlineReviewForm(venue),
            const SizedBox(height: 12),
          ],
          if (localReviews.isEmpty)
            const Text(
              'لا توجد مراجعات منشورة حتى الآن.',
              style: TextStyle(color: AppColors.textSecondary),
            ),
          if (localReviews.isNotEmpty) ...[
            const SizedBox(height: 12),
            ...localReviews.map(
              (review) => Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            review.userName,
                            style: const TextStyle(fontWeight: FontWeight.w900),
                          ),
                        ),
                        Text(
                          '★' * review.rating,
                          style: const TextStyle(
                            color: Colors.amber,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      review.comment,
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _inlineReviewForm(VenueModel venue) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.white10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('تقييمك', style: TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(5, (index) {
              final value = index + 1;
              return IconButton(
                iconSize: 34,
                onPressed: () => setState(() => _selectedRating = value),
                icon: Icon(
                  value <= _selectedRating
                      ? Icons.star_rounded
                      : Icons.star_border_rounded,
                  color: Colors.amber,
                ),
              );
            }),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _reviewCommentController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'اكتب تعليقك',
              prefixIcon: Icon(Icons.comment_outlined),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () {
                    setState(() {
                      _showReviewForm = false;
                      _reviewCommentController.clear();
                      _selectedRating = 5;
                    });
                  },
                  child: const Text('إلغاء'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton(
                  onPressed: () => _submitInlineReview(venue),
                  child: const Text('إرسال'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _submitInlineReview(VenueModel venue) async {
    final booking = context.read<BookingProvider>().completedBookingForVenue(
      venue.id,
    );
    if (booking == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا يوجد حجز مكتمل صالح للتقييم.')),
      );
      return;
    }
    final commentText = _reviewCommentController.text.trim();
    try {
      await context.read<ReviewProvider>().addReview(
        venueId: venue.id,
        bookingId: booking.id,
        rating: _selectedRating,
        comment: commentText,
      );
      if (!mounted) return;
      setState(() {
        _showReviewForm = false;
        _reviewCommentController.clear();
        _selectedRating = 5;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم حفظ التقييم في قاعدة البيانات.')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('تعذر حفظ التقييم: $e')));
    }
  }

  Widget _location(BuildContext context, VenueModel venue) => Container(
    margin: const EdgeInsets.only(bottom: 20),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'الموقع',
          style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.location_on_outlined, color: AppColors.primary),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                '${venue.city}${venue.address.trim().isEmpty ? '' : ' • ${venue.address}'}',
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  height: 1.4,
                ),
              ),
            ),
          ],
        ),
        if (venue.hasCoordinates) ...[
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(18),
            child: SizedBox(
              height: 220,
              child: FlutterMap(
                options: MapOptions(
                  initialCenter: LatLng(venue.latitude, venue.longitude),
                  initialZoom: 16,
                  minZoom: 3,
                  maxZoom: 19,
                ),
                children: [
                  TileLayer(
                    urlTemplate:
                        'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    userAgentPackageName: 'com.salora.university',
                    maxZoom: 19,
                  ),
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: LatLng(venue.latitude, venue.longitude),
                        width: 56,
                        height: 56,
                        alignment: Alignment.topCenter,
                        child: Tooltip(
                          message: venue.name,
                          child: const Icon(
                            Icons.location_pin,
                            color: AppColors.primary,
                            size: 52,
                          ),
                        ),
                      ),
                    ],
                  ),
                  SimpleAttributionWidget(
                    source: const Text('OpenStreetMap contributors'),
                    onTap: _openOpenStreetMapCopyright,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => _openMaps(venue),
              icon: const Icon(Icons.directions_outlined),
              label: const Text('فتح الموقع في OpenStreetMap'),
            ),
          ),
        ] else ...[
          const SizedBox(height: 12),
          const Text(
            'لم يحدد مدير الصالة إحداثيات دقيقة بعد.',
            style: TextStyle(color: AppColors.textSecondary),
          ),
        ],
        if (venue.openingHours.isNotEmpty) ...[
          const Divider(height: 28),
          const Text(
            'أوقات العمل الأسبوعية',
            style: TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          ..._openingHourRows(venue),
        ],
      ],
    ),
  );

  List<Widget> _openingHourRows(VenueModel venue) {
    const days = <String, String>{
      'saturday': 'السبت',
      'sunday': 'الأحد',
      'monday': 'الاثنين',
      'tuesday': 'الثلاثاء',
      'wednesday': 'الأربعاء',
      'thursday': 'الخميس',
      'friday': 'الجمعة',
    };
    return days.entries.map((entry) {
      final hours = venue.openingHours[entry.key];
      final label = hours == null || !hours.enabled
          ? 'مغلق'
          : (hours.open.isEmpty || hours.close.isEmpty
                ? 'حسب الموعد'
                : '${hours.open} - ${hours.close}');
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            SizedBox(
              width: 78,
              child: Text(
                entry.value,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  color: label == 'مغلق'
                      ? Colors.redAccent
                      : AppColors.textSecondary,
                ),
              ),
            ),
          ],
        ),
      );
    }).toList();
  }

  Future<void> _openMaps(VenueModel venue) async {
    final latitude = venue.latitude.toStringAsFixed(6);
    final longitude = venue.longitude.toStringAsFixed(6);
    final uri = Uri.parse(
      'https://www.openstreetmap.org/?mlat=$latitude&mlon=$longitude'
      '#map=17/$latitude/$longitude',
    );

    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تعذر فتح الموقع في OpenStreetMap.')),
      );
    }
  }

  Future<void> _openOpenStreetMapCopyright() async {
    final uri = Uri.parse('https://www.openstreetmap.org/copyright');
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تعذر فتح معلومات OpenStreetMap.')),
      );
    }
  }

  String _emojiFor(String text, String category) {
    final value = '${text.toLowerCase()} ${category.toLowerCase()}';
    if (value.contains('wedding') || value.contains('زفاف')) return '💍';
    if (value.contains('engagement') || value.contains('خطوبة')) return '💞';
    if (value.contains('graduation') || value.contains('تخرج')) return '🎓';
    if (value.contains('birthday') || value.contains('عيد ميلاد')) return '🎂';
    if (value.contains('condolence') || value.contains('عزاء')) return '🕊️';
    if (value.contains('photography') ||
        value.contains('photo') ||
        value.contains('تصوير'))
      return '📸';
    if (value.contains('food') ||
        value.contains('drinks') ||
        value.contains('hospitality') ||
        value.contains('مأكولات') ||
        value.contains('مشروبات') ||
        value.contains('ضيافة'))
      return '🍽️';
    if (value.contains('cake') || value.contains('كيك')) return '🎂';
    if (value.contains('decoration') ||
        value.contains('flower') ||
        value.contains('ديكور') ||
        value.contains('ورود'))
      return '🌸';
    if (value.contains('lighting') ||
        value.contains('sound') ||
        value.contains('إضاءة') ||
        value.contains('صوت'))
      return '💡';
    if (value.contains('reader') ||
        value.contains('sheikh') ||
        value.contains('قارئ') ||
        value.contains('شيخ'))
      return '📖';
    if (value.contains('water') || value.contains('مياه')) return '💧';
    if (value.contains('clean') || value.contains('تنظيف')) return '🧹';
    if (value.contains('parking') || value.contains('موقف')) return '🅿️';
    if (value.contains('air') || value.contains('تكييف')) return '❄️';
    if (value.contains('stage') || value.contains('منصة')) return '🎤';
    if (value.contains('bridal') || value.contains('عروس')) return '👰';
    if (value.contains('valet') || value.contains('سيارات')) return '🚗';
    if (value.contains('tables') ||
        value.contains('chairs') ||
        value.contains('طاولات') ||
        value.contains('كراسي'))
      return '🪑';
    return '✅';
  }
}

class _VenueVideoCard extends StatefulWidget {
  const _VenueVideoCard({required this.url, required this.index});
  final String url;
  final int index;
  @override
  State<_VenueVideoCard> createState() => _VenueVideoCardState();
}

class _VenueVideoCardState extends State<_VenueVideoCard> {
  late final VideoPlayerController _controller;
  late final Future<void> _initialise;
  @override
  void initState() {
    super.initState();
    _controller = VideoPlayerController.networkUrl(Uri.parse(widget.url));
    _initialise = _controller.initialize().then((_) {
      _controller.setLooping(false);
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(18),
    child: DecoratedBox(
      decoration: const BoxDecoration(color: Colors.black),
      child: FutureBuilder<void>(
        future: _initialise,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done)
            return const AspectRatio(
              aspectRatio: 16 / 9,
              child: Center(child: CircularProgressIndicator()),
            );
          if (_controller.value.hasError)
            return const AspectRatio(
              aspectRatio: 16 / 9,
              child: Center(
                child: Text(
                  'تعذر تشغيل الفيديو',
                  style: TextStyle(color: Colors.white70),
                ),
              ),
            );
          final ratio = _controller.value.aspectRatio == 0
              ? 16 / 9
              : _controller.value.aspectRatio;
          return Stack(
            alignment: Alignment.center,
            children: [
              AspectRatio(aspectRatio: ratio, child: VideoPlayer(_controller)),
              Positioned(
                top: 10,
                right: 10,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.black54,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    'فيديو ${widget.index}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
              Positioned.fill(
                child: Material(
                  color: Colors.transparent,
                  child: InkWell(
                    onTap: () => setState(
                      () => _controller.value.isPlaying
                          ? _controller.pause()
                          : _controller.play(),
                    ),
                    child: Center(
                      child: AnimatedOpacity(
                        opacity: _controller.value.isPlaying ? 0 : 1,
                        duration: const Duration(milliseconds: 180),
                        child: Container(
                          padding: const EdgeInsets.all(14),
                          decoration: const BoxDecoration(
                            color: Colors.black54,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(
                            Icons.play_arrow_rounded,
                            color: Colors.white,
                            size: 38,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                child: VideoProgressIndicator(
                  _controller,
                  allowScrubbing: true,
                  padding: const EdgeInsets.symmetric(vertical: 5),
                ),
              ),
            ],
          );
        },
      ),
    ),
  );
}

class _RatingSummary {
  final double average;
  final int count;

  const _RatingSummary(this.average, this.count);
}
