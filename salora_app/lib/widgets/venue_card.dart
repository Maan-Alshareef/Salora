import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../core/widgets/price_text.dart';
import '../models/venue_model.dart';
import '../providers/favorite_provider.dart';

class VenueCard extends StatelessWidget {
  final VenueModel venue;
  final VoidCallback onTap;
  final bool compact;

  const VenueCard({
    super.key,
    required this.venue,
    required this.onTap,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final isFav = context.watch<FavoriteProvider>().isFavorite(venue.id);
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: EdgeInsets.only(bottom: compact ? 12 : 18),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(22),
        ),
        clipBehavior: Clip.antiAlias,
        child: compact ? _compact(context, isFav) : _full(context, isFav),
      ),
    );
  }

  Widget _full(BuildContext context, bool isFav) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Stack(
          children: [
            _VenueImage(
              height: 170,
              imagePath: venue.mainImage,
              text: venue.name,
            ),
            if (venue.hasOffer)
              Positioned(
                top: 12,
                left: 12,
                child: _Badge(
                  text: venue.discountPercentage == null
                      ? 'عرض'
                      : '${venue.discountPercentage}% خصم',
                  color: AppColors.danger,
                ),
              ),
            if (venue.badge?.trim().isNotEmpty ?? false)
              Positioned(
                bottom: 12,
                left: 12,
                child: _Badge(text: venue.badge!, color: AppColors.primary),
              ),
            Positioned(top: 12, right: 12, child: _heart(context, isFav)),
          ],
        ),
        Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      venue.name,
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      if (venue.hasDiscount)
                        PriceText(
                          priceSyp: venue.displayOriginalPrice,
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 12,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                      PriceText(
                        priceSyp: venue.finalPrice,
                        style: const TextStyle(
                          color: AppColors.success,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(
                    Icons.location_on_outlined,
                    size: 16,
                    color: AppColors.textSecondary,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    venue.city,
                    style: const TextStyle(color: AppColors.textSecondary),
                  ),
                  const Spacer(),
                  const Icon(Icons.star_rounded, size: 17, color: Colors.amber),
                  Text(' ${venue.rating} (${venue.reviewsCount})'),
                  const SizedBox(width: 10),
                  const Icon(
                    Icons.people_outline,
                    size: 16,
                    color: AppColors.textSecondary,
                  ),
                  Text(' ${venue.capacity}'),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: venue.eventTypes
                    .take(3)
                    .map((e) => _Chip(text: e))
                    .toList(),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _compact(BuildContext context, bool isFav) {
    return Row(
      children: [
        SizedBox(
          width: 112,
          height: 112,
          child: _VenueImage(
            height: 112,
            imagePath: venue.mainImage,
            text: venue.city,
          ),
        ),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  venue.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  '${venue.city} • ${venue.capacity} ضيف',
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
                if (venue.badge?.trim().isNotEmpty ?? false) ...[
                  const SizedBox(height: 5),
                  Text(
                    venue.badge!,
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
                if (venue.hasOffer) ...[
                  const SizedBox(height: 4),
                  Text(
                    venue.discountPercentage == null
                        ? 'عرض فعال'
                        : '${venue.discountPercentage}% خصم',
                    style: const TextStyle(
                      color: AppColors.danger,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
                const SizedBox(height: 6),
                Row(
                  children: [
                    const Icon(
                      Icons.star_rounded,
                      color: Colors.amber,
                      size: 16,
                    ),
                    Text(' ${venue.rating}'),
                    const Spacer(),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        if (venue.hasDiscount)
                          PriceText(
                            priceSyp: venue.displayOriginalPrice,
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 10,
                              decoration: TextDecoration.lineThrough,
                            ),
                          ),
                        PriceText(
                          priceSyp: venue.finalPrice,
                          style: const TextStyle(
                            color: AppColors.success,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        _heart(context, isFav),
        const SizedBox(width: 8),
      ],
    );
  }

  Widget _heart(BuildContext context, bool isFav) => CircleAvatar(
    radius: 18,
    backgroundColor: Colors.black45,
    child: IconButton(
      padding: EdgeInsets.zero,
      icon: Icon(
        isFav ? Icons.favorite_rounded : Icons.favorite_border_rounded,
        size: 18,
        color: isFav ? Colors.redAccent : Colors.white,
      ),
      onPressed: () => context.read<FavoriteProvider>().toggleFavorite(venue),
    ),
  );
}

class _VenueImage extends StatelessWidget {
  final double height;
  final String imagePath;
  final String text;
  const _VenueImage({
    required this.height,
    required this.imagePath,
    required this.text,
  });

  @override
  Widget build(BuildContext context) {
    if (imagePath.isEmpty) return _fallback();
    final isNetwork =
        imagePath.startsWith('http://') || imagePath.startsWith('https://');
    if (isNetwork) {
      return Image.network(
        imagePath,
        height: height,
        width: double.infinity,
        fit: BoxFit.cover,
        cacheWidth: 900,
        filterQuality: FilterQuality.low,
        gaplessPlayback: true,
        loadingBuilder: (context, child, progress) =>
            progress == null ? child : _fallback(),
        errorBuilder: (_, __, ___) => _fallback(),
      );
    }
    return Image.asset(
      imagePath,
      height: height,
      width: double.infinity,
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) => _fallback(),
    );
  }

  Widget _fallback() => Container(
    height: height,
    width: double.infinity,
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        colors: [AppColors.primary, AppColors.secondary],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
    ),
    child: Center(
      child: Text(
        text,
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontSize: 18,
          fontWeight: FontWeight.w900,
          color: Colors.white,
        ),
      ),
    ),
  );
}

class _Badge extends StatelessWidget {
  final String text;
  final Color color;
  const _Badge({required this.text, required this.color});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(30),
    ),
    child: Text(
      text,
      style: const TextStyle(
        color: Colors.white,
        fontWeight: FontWeight.w800,
        fontSize: 12,
      ),
    ),
  );
}

class _Chip extends StatelessWidget {
  final String text;
  const _Chip({required this.text});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: AppColors.surface2,
      borderRadius: BorderRadius.circular(30),
    ),
    child: Text(
      text,
      style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
    ),
  );
}
