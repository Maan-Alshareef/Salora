import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/venue_model.dart';
import '../../providers/app_settings_provider.dart';
import '../../providers/compare_provider.dart';

class CompareScreen extends StatelessWidget {
  const CompareScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final compare = context.watch<CompareProvider>();
    final settings = context.watch<AppSettingsProvider>();
    final venues = compare.items;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('مقارنة الصالات'),
        actions: [
          if (venues.isNotEmpty)
            TextButton.icon(
              onPressed: () => context.read<CompareProvider>().clear(),
              icon: const Icon(Icons.delete_sweep_outlined),
              label: const Text('مسح'),
            ),
        ],
      ),
      body: venues.length < 2
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.compare_arrows_rounded,
                      size: 54,
                      color: theme.colorScheme.primary,
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'أضف صالتين على الأقل إلى المقارنة',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'ستظهر كل صالة بكرت مستقل تحت الثانية حتى تكون المواصفات واضحة بدون قص أو ازدحام.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: theme.colorScheme.onSurfaceVariant, height: 1.45),
                    ),
                  ],
                ),
              ),
            )
          : ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              itemCount: venues.length,
              separatorBuilder: (_, __) => const SizedBox(height: 18),
              itemBuilder: (context, index) {
                final venue = venues[index];
                return _VenueCompareCard(
                  index: index + 1,
                  venue: venue,
                  priceText: settings.formatPrice(venue.price),
                  onRemove: () => context.read<CompareProvider>().remove(venue.id),
                );
              },
            ),
    );
  }
}

class _VenueCompareCard extends StatelessWidget {
  const _VenueCompareCard({
    required this.index,
    required this.venue,
    required this.priceText,
    required this.onRemove,
  });

  final int index;
  final VenueModel venue;
  final String priceText;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final services = venue.amenities.isNotEmpty ? venue.amenities : venue.services;

    return Material(
      color: colors.surface,
      borderRadius: BorderRadius.circular(26),
      clipBehavior: Clip.antiAlias,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(26),
          border: Border.all(color: colors.outlineVariant.withOpacity(.55)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                AspectRatio(
                  aspectRatio: 16 / 8.4,
                  child: venue.images.isNotEmpty
                      ? Image.network(
                          venue.images.first,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => _imageFallback(context),
                        )
                      : _imageFallback(context),
                ),
                PositionedDirectional(
                  top: 12,
                  start: 12,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
                    decoration: BoxDecoration(
                      color: Colors.black.withOpacity(.62),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      'الصالة $index',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900),
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(17),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              venue.name,
                              style: const TextStyle(fontSize: 23, fontWeight: FontWeight.w900),
                            ),
                            const SizedBox(height: 5),
                            Row(
                              children: [
                                Icon(Icons.location_on_outlined, size: 18, color: colors.primary),
                                const SizedBox(width: 5),
                                Expanded(
                                  child: Text(
                                    venue.city,
                                    style: TextStyle(color: colors.onSurfaceVariant, fontWeight: FontWeight.w700),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      IconButton.filledTonal(
                        onPressed: onRemove,
                        tooltip: 'إزالة من المقارنة',
                        icon: const Icon(Icons.close_rounded),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  _InfoTile(
                    icon: Icons.payments_outlined,
                    title: 'السعر',
                    value: priceText,
                    emphasize: true,
                  ),
                  _InfoTile(
                    icon: Icons.groups_2_outlined,
                    title: 'السعة',
                    value: '${venue.capacity} ضيف',
                  ),
                  _InfoTile(
                    icon: Icons.star_rounded,
                    title: 'التقييم',
                    value: '⭐ ${venue.rating.toStringAsFixed(1)} (${venue.reviewsCount} تقييم)',
                  ),
                  _InfoTile(
                    icon: Icons.celebration_outlined,
                    title: 'المناسبات',
                    value: venue.eventTypes.isEmpty ? 'غير محدد' : venue.eventTypes.join('، '),
                  ),
                  _InfoTile(
                    icon: Icons.room_service_outlined,
                    title: 'الخدمات والمزايا',
                    value: services.isEmpty ? 'لا توجد بيانات' : services.join('، '),
                    last: true,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _imageFallback(BuildContext context) => Container(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        alignment: Alignment.center,
        child: Icon(
          Icons.image_not_supported_outlined,
          size: 44,
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
      );
}

class _InfoTile extends StatelessWidget {
  const _InfoTile({
    required this.icon,
    required this.title,
    required this.value,
    this.emphasize = false,
    this.last = false,
  });

  final IconData icon;
  final String title;
  final String value;
  final bool emphasize;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      margin: EdgeInsets.only(bottom: last ? 0 : 10),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest.withOpacity(.42),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: colors.primary),
          const SizedBox(width: 10),
          SizedBox(
            width: 92,
            child: Text(
              title,
              style: TextStyle(color: colors.onSurfaceVariant, fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                fontSize: emphasize ? 18 : 15,
                fontWeight: FontWeight.w900,
                color: emphasize ? colors.primary : colors.onSurface,
                height: 1.45,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
