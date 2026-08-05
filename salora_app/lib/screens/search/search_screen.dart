import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../providers/venue_provider.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/venue_card.dart';
import '../venue/venue_details_screen.dart';

class SearchScreen extends StatefulWidget {
  final String? initialEventType;

  const SearchScreen({super.key, this.initialEventType});

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  String query = '';
  String city = 'الكل';
  String eventType = 'الكل';
  String sort = 'الموصى بها';
  int? guests;
  int? maxPrice;
  double? minRating;
  bool hasOfferOnly = false;

  Timer? _searchDebounce;

  @override
  void initState() {
    super.initState();
    eventType = widget.initialEventType ?? 'الكل';
    WidgetsBinding.instance.addPostFrameCallback((_) => _initialize());
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    super.dispose();
  }

  Future<void> _initialize() async {
    final provider = context.read<VenueProvider>();
    if (provider.venues.isEmpty) {
      await provider.loadVenues();
    }
    if (!mounted) return;
    if (eventType == 'الكل') {
      provider.showAllLoadedVenues();
      return;
    }
    await _runSearch();
  }

  Future<void> _runSearch() {
    return context.read<VenueProvider>().searchVenues(
      query: query,
      city: city,
      eventType: eventType,
      minGuests: guests,
      maxPrice: maxPrice,
      minRating: minRating,
      hasOfferOnly: hasOfferOnly,
      sort: sort,
    );
  }

  void _scheduleTextSearch(String value) {
    setState(() => query = value);
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) _runSearch();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<VenueProvider>();
    final results = provider.filteredVenues;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          eventType == 'الكل' ? 'البحث عن صالات' : 'صالات $eventType',
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    autofocus: true,
                    onChanged: _scheduleTextSearch,
                    decoration: const InputDecoration(
                      hintText: 'اسم الصالة أو المدينة...',
                      prefixIcon: Icon(Icons.search_rounded),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                IconButton.filled(
                  onPressed: provider.isSearching ? null : _openFilterSheet,
                  icon: const Icon(Icons.tune_rounded),
                ),
              ],
            ),
          ),
          if (provider.isSearching) const LinearProgressIndicator(minHeight: 2),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Row(
              children: [
                Text(
                  '${results.length} نتيجة',
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
                const Spacer(),
                Text(
                  sort,
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Expanded(
            child: provider.isLoading && provider.venues.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : provider.error != null && provider.venues.isEmpty
                ? _ErrorState(message: provider.error!, onRetry: _initialize)
                : provider.searchError != null && results.isEmpty
                ? _ErrorState(
                    message: provider.searchError!,
                    onRetry: _runSearch,
                  )
                : results.isEmpty
                ? const EmptyState(
                    icon: Icons.search_off_rounded,
                    title: 'لا توجد نتائج',
                    subtitle: 'جرّب تعديل الفلاتر أو كلمات البحث.',
                  )
                : RefreshIndicator(
                    onRefresh: _runSearch,
                    child: ListView.builder(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      itemCount: results.length,
                      itemBuilder: (context, index) => VenueCard(
                        compact: true,
                        venue: results[index],
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                VenueDetailsScreen(venue: results[index]),
                          ),
                        ),
                      ),
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  void _openFilterSheet() {
    final venueProvider = context.read<VenueProvider>();
    final cities = <String>{
      'الكل',
      ...venueProvider.venues
          .map((venue) => venue.city.trim())
          .where((value) => value.isNotEmpty),
    }.toList();
    final eventTypes = <String>{
      'الكل',
      ...venueProvider.venues
          .expand((venue) => venue.eventTypes)
          .where((value) => value.trim().isNotEmpty),
    }.toList();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) {
        var tempCity = cities.contains(city) ? city : 'الكل';
        var tempEvent = eventTypes.contains(eventType) ? eventType : 'الكل';
        var tempSort = sort;
        var tempGuests = guests;
        var tempMaxPrice = maxPrice;
        var tempRating = minRating;
        var tempHasOffer = hasOfferOnly;

        return StatefulBuilder(
          builder: (context, setModal) {
            return Padding(
              padding: EdgeInsets.only(
                left: 18,
                right: 18,
                bottom: MediaQuery.of(context).viewInsets.bottom + 18,
              ),
              child: ListView(
                shrinkWrap: true,
                children: [
                  const Text(
                    'الفلاتر',
                    style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 16),
                  _dropdown(
                    'المدينة',
                    tempCity,
                    cities,
                    (value) => setModal(() => tempCity = value!),
                  ),
                  _dropdown(
                    'نوع المناسبة',
                    tempEvent,
                    eventTypes,
                    (value) => setModal(() => tempEvent = value!),
                  ),
                  _dropdown(
                    'ترتيب حسب',
                    tempSort,
                    const [
                      'الموصى بها',
                      'الأقل سعرًا',
                      'الأعلى تقييمًا',
                      'الأكبر سعة',
                    ],
                    (value) => setModal(() => tempSort = value!),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    initialValue: tempGuests?.toString() ?? '',
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'أقل عدد ضيوف',
                      prefixIcon: Icon(Icons.people_outline),
                    ),
                    onChanged: (value) => tempGuests = int.tryParse(value),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    initialValue: tempMaxPrice?.toString() ?? '',
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'أعلى سعر للساعة (ل.س)',
                      prefixIcon: Icon(Icons.payments_outlined),
                    ),
                    onChanged: (value) => tempMaxPrice = int.tryParse(value),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<double?>(
                    initialValue: tempRating,
                    items: const [
                      DropdownMenuItem<double?>(
                        value: null,
                        child: Text('أي تقييم'),
                      ),
                      DropdownMenuItem<double?>(
                        value: 4.0,
                        child: Text('4.0+'),
                      ),
                      DropdownMenuItem<double?>(
                        value: 4.5,
                        child: Text('4.5+'),
                      ),
                    ],
                    onChanged: (value) => setModal(() => tempRating = value),
                    decoration: const InputDecoration(labelText: 'التقييم'),
                  ),
                  const SizedBox(height: 10),
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    value: tempHasOffer,
                    onChanged: (value) => setModal(() => tempHasOffer = value),
                    title: const Text('العروض والخصومات فقط'),
                    subtitle: const Text(
                      'إظهار الصالات التي لديها عرض فعال حالياً',
                    ),
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () {
                      setState(() {
                        city = tempCity;
                        eventType = tempEvent;
                        sort = tempSort;
                        guests = tempGuests;
                        maxPrice = tempMaxPrice;
                        minRating = tempRating;
                        hasOfferOnly = tempHasOffer;
                      });
                      Navigator.pop(context);
                      _runSearch();
                    },
                    child: const Text('تطبيق الفلاتر'),
                  ),
                  TextButton(
                    onPressed: () {
                      setState(() {
                        city = 'الكل';
                        eventType = 'الكل';
                        sort = 'الموصى بها';
                        guests = null;
                        maxPrice = null;
                        minRating = null;
                        hasOfferOnly = false;
                      });
                      Navigator.pop(context);
                      _runSearch();
                    },
                    child: const Text('إعادة ضبط'),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _dropdown(
    String label,
    String value,
    List<String> values,
    ValueChanged<String?> onChanged,
  ) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<String>(
        initialValue: value,
        items: values
            .map((item) => DropdownMenuItem(value: item, child: Text(item)))
            .toList(),
        onChanged: onChanged,
        decoration: InputDecoration(labelText: label),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const Icon(
          Icons.cloud_off_outlined,
          size: 64,
          color: AppColors.textSecondary,
        ),
        const SizedBox(height: 12),
        Text(message, textAlign: TextAlign.center),
        const SizedBox(height: 12),
        ElevatedButton(onPressed: onRetry, child: const Text('إعادة المحاولة')),
      ],
    );
  }
}
