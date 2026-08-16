import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/venue_model.dart';
import '../../providers/app_settings_provider.dart';
import '../../providers/compare_provider.dart';

class CompareScreen extends StatelessWidget {
  const CompareScreen({super.key});

  static const double _criterionWidth = 104;
  static const double _venueColumnWidth = 148;

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
                      style: TextStyle(
                        fontSize: 19,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'ستظهر معايير المقارنة في عمود ثابت، وقيمة كل صالة أمام المعيار نفسه مباشرة.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: theme.colorScheme.onSurfaceVariant,
                        height: 1.45,
                      ),
                    ),
                  ],
                ),
              ),
            )
          : LayoutBuilder(
              builder: (context, constraints) {
                final minimumWidth =
                    _criterionWidth + (venues.length * _venueColumnWidth) + 24;
                final tableWidth = math.max(constraints.maxWidth - 24, minimumWidth);

                return SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 30),
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Directionality(
                    textDirection: TextDirection.rtl,
                    child: SizedBox(
                      width: tableWidth,
                      child: Column(
                        children: [
                          _ComparisonHeader(
                            venues: venues,
                            criterionWidth: _criterionWidth,
                            onRemove: (venue) => context
                                .read<CompareProvider>()
                                .remove(venue.id),
                          ),
                          const SizedBox(height: 10),
                          _ComparisonRow(
                            criterionWidth: _criterionWidth,
                            icon: Icons.payments_outlined,
                            title: 'السعر',
                            values: venues
                                .map((venue) => settings.formatPrice(venue.price))
                                .toList(),
                            emphasize: true,
                          ),
                          _ComparisonRow(
                            criterionWidth: _criterionWidth,
                            icon: Icons.groups_2_outlined,
                            title: 'السعة',
                            values: venues
                                .map((venue) => '${venue.capacity} ضيف')
                                .toList(),
                          ),
                          _ComparisonRow(
                            criterionWidth: _criterionWidth,
                            icon: Icons.star_rounded,
                            title: 'التقييم',
                            values: venues
                                .map(
                                  (venue) =>
                                      '⭐ ${venue.rating.toStringAsFixed(1)}\n${venue.reviewsCount} تقييم',
                                )
                                .toList(),
                          ),
                          _ComparisonRow(
                            criterionWidth: _criterionWidth,
                            icon: Icons.location_on_outlined,
                            title: 'الموقع',
                            values: venues
                                .map(
                                  (venue) => venue.address.trim().isNotEmpty
                                      ? '${venue.city}\n${venue.address}'
                                      : venue.city,
                                )
                                .toList(),
                          ),
                          _ComparisonRow(
                            criterionWidth: _criterionWidth,
                            icon: Icons.celebration_outlined,
                            title: 'المناسبات',
                            values: venues
                                .map(
                                  (venue) => venue.eventTypes.isEmpty
                                      ? 'غير محدد'
                                      : venue.eventTypes.join('، '),
                                )
                                .toList(),
                          ),
                          _ComparisonRow(
                            criterionWidth: _criterionWidth,
                            icon: Icons.room_service_outlined,
                            title: 'الخدمات\nوالمزايا',
                            values: venues.map((venue) {
                              final services = venue.amenities.isNotEmpty
                                  ? venue.amenities
                                  : venue.services;
                              return services.isEmpty
                                  ? 'لا توجد بيانات'
                                  : services.join('، ');
                            }).toList(),
                          ),
                          _ComparisonRow(
                            criterionWidth: _criterionWidth,
                            icon: Icons.check_circle_outline,
                            title: 'المجاني\nضمن الصالة',
                            values: venues
                                .map(
                                  (venue) => venue.includedServices.isEmpty
                                      ? 'لا توجد بيانات'
                                      : venue.includedServices.join('، '),
                                )
                                .toList(),
                          ),
                        ],
                      ),
                    ),
                  ),
                  ),
                );
              },
            ),
    );
  }
}

class _ComparisonHeader extends StatelessWidget {
  const _ComparisonHeader({
    required this.venues,
    required this.criterionWidth,
    required this.onRemove,
  });

  final List<VenueModel> venues;
  final double criterionWidth;
  final ValueChanged<VenueModel> onRemove;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: criterionWidth,
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: colors.primary.withValues(alpha: .10),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: colors.primary.withValues(alpha: .25)),
            ),
            child: const Center(
              child: Text(
                'المعيار',
                textAlign: TextAlign.center,
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
          ),
        ),
        const SizedBox(width: 8),
        for (var index = 0; index < venues.length; index++) ...[
          if (index > 0) const SizedBox(width: 8),
          Expanded(
            child: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: colors.surface,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(
                  color: colors.outlineVariant.withValues(alpha: .55),
                ),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  CircleAvatar(
                    radius: 22,
                    backgroundColor: colors.surfaceContainerHighest,
                    backgroundImage: venues[index].images.isNotEmpty
                        ? NetworkImage(venues[index].images.first)
                        : null,
                    child: venues[index].images.isEmpty
                        ? const Icon(Icons.meeting_room_outlined)
                        : null,
                  ),
                  const SizedBox(height: 7),
                  Text(
                    venues[index].name,
                    maxLines: 2,
                    textAlign: TextAlign.center,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 6),
                  IconButton.outlined(
                    tooltip: 'إزالة من المقارنة',
                    onPressed: () => onRemove(venues[index]),
                    icon: const Icon(Icons.close_rounded, size: 18),
                    visualDensity: VisualDensity.compact,
                  ),
                ],
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _ComparisonRow extends StatelessWidget {
  const _ComparisonRow({
    required this.criterionWidth,
    required this.icon,
    required this.title,
    required this.values,
    this.emphasize = false,
  });

  final double criterionWidth;
  final IconData icon;
  final String title;
  final List<String> values;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colors.outlineVariant.withValues(alpha: .40)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: criterionWidth - 16,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
              decoration: BoxDecoration(
                color: colors.primary.withValues(alpha: .08),
                borderRadius: BorderRadius.circular(13),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(icon, size: 19, color: colors.primary),
                  const SizedBox(height: 6),
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: colors.onSurfaceVariant,
                      fontWeight: FontWeight.w900,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(width: 8),
          for (var index = 0; index < values.length; index++) ...[
            if (index > 0) const SizedBox(width: 8),
            Expanded(
              child: Container(
                constraints: const BoxConstraints(minHeight: 68),
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
                decoration: BoxDecoration(
                  color: colors.surfaceContainerHighest.withValues(alpha: .35),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Center(
                  child: Text(
                    values[index],
                    textAlign: TextAlign.center,
                    softWrap: true,
                    style: TextStyle(
                      fontSize: emphasize ? 16 : 13.5,
                      fontWeight: FontWeight.w900,
                      color: emphasize ? colors.primary : colors.onSurface,
                      height: 1.45,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
