import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/favorite_provider.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/venue_card.dart';
import '../venue/venue_details_screen.dart';

class FavoritesScreen extends StatelessWidget {
  const FavoritesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final favorites = context.watch<FavoriteProvider>().favorites;
    return Scaffold(
      appBar: AppBar(title: const Text('المفضلة')),
      body: favorites.isEmpty
          ? const EmptyState(icon: Icons.favorite_border_rounded, title: 'لا توجد صالات مفضلة بعد', subtitle: 'اضغط على القلب في أي صالة لحفظها هنا.')
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: favorites.length,
              itemBuilder: (context, index) => VenueCard(compact: true, venue: favorites[index], onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => VenueDetailsScreen(venue: favorites[index])))),
            ),
    );
  }
}
