import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../core/widgets/price_text.dart';
import '../../models/venue_model.dart';
import '../../providers/compare_provider.dart';
import '../../widgets/empty_state.dart';

class CompareScreen extends StatelessWidget {
  const CompareScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final items = context.watch<CompareProvider>().items;
    return Scaffold(
      appBar: AppBar(
        title: const Text('مقارنة الصالات'),
        actions: [
          if (items.isNotEmpty)
            TextButton(
              onPressed: () => context.read<CompareProvider>().clear(),
              child: const Text('مسح'),
            ),
        ],
      ),
      body: items.length < 2
          ? const EmptyState(
              icon: Icons.compare_arrows_rounded,
              title: 'اختر صالتين أو ثلاثاً',
              subtitle: 'يمكن حفظ ثلاث صالات كحد أقصى، وتبقى المقارنة محفوظة بعد إغلاق التطبيق.',
            )
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: SizedBox(
                  width: 150 + (items.length * 190),
                  child: Column(
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const SizedBox(width: 150),
                          ...items.map((venue) => SizedBox(width: 190, child: _HallHeader(venue: venue))),
                        ],
                      ),
                      const SizedBox(height: 14),
                      _CompareRow(title: 'المدينة', values: items.map((v) => Text(v.city, textAlign: TextAlign.center)).toList()),
                      _CompareRow(
                        title: 'السعر',
                        values: items
                            .map((v) => PriceText(
                                  priceSyp: v.finalPrice,
                                  style: const TextStyle(fontWeight: FontWeight.w900),
                                ))
                            .toList(),
                      ),
                      _CompareRow(title: 'السعة', values: items.map((v) => Text('${v.capacity} ضيف', textAlign: TextAlign.center)).toList()),
                      _CompareRow(title: 'التقييم', values: items.map((v) => Text('${v.rating} ⭐', textAlign: TextAlign.center)).toList()),
                      _CompareRow(title: 'العنوان', values: items.map((v) => Text(v.address, textAlign: TextAlign.center)).toList()),
                      _CompareRow(title: 'الخدمات', values: items.map((v) => Text(v.services.join(', '), textAlign: TextAlign.center)).toList()),
                      _CompareRow(title: 'المزايا', values: items.map((v) => Text(v.amenities.join(', '), textAlign: TextAlign.center)).toList()),
                      _CompareRow(
                        title: 'العرض',
                        values: items
                            .map((v) => Text(v.hasOffer ? '${v.discountPercentage}% خصم' : 'لا يوجد عرض', textAlign: TextAlign.center))
                            .toList(),
                      ),
                    ],
                  ),
                ),
              ),
            ),
    );
  }
}

class _HallHeader extends StatelessWidget {
  const _HallHeader({required this.venue});
  final VenueModel venue;

  @override
  Widget build(BuildContext context) {
    final image = venue.image;
    final imageWidget = image.startsWith('http')
        ? Image.network(image, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _fallback())
        : Image.asset(image, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _fallback());
    return Container(
      margin: const EdgeInsetsDirectional.only(start: 10),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: Colors.white10),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        AspectRatio(aspectRatio: 1.45, child: imageWidget),
        Padding(
          padding: const EdgeInsets.all(12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(venue.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w900)),
            const SizedBox(height: 6),
            TextButton.icon(
              onPressed: () => context.read<CompareProvider>().remove(venue.id),
              icon: const Icon(Icons.close, size: 17),
              label: const Text('إزالة'),
            ),
          ]),
        ),
      ]),
    );
  }

  Widget _fallback() => Container(
        color: AppColors.surface2,
        child: const Center(child: Icon(Icons.location_city_rounded, size: 44)),
      );
}

class _CompareRow extends StatelessWidget {
  const _CompareRow({required this.title, required this.values});
  final String title;
  final List<Widget> values;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(18)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 150,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              child: Text(title, style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w900)),
            ),
          ),
          ...values.map(
            (value) => SizedBox(
              width: 190,
              child: Padding(padding: const EdgeInsets.symmetric(horizontal: 10), child: value),
            ),
          ),
        ],
      ),
    );
  }
}
