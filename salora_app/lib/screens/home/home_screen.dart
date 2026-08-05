import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/constants/app_constants.dart';
import '../../core/theme/app_colors.dart';
import '../../core/widgets/currency_toggle_chip.dart';
import '../../providers/notification_provider.dart';
import '../../providers/venue_provider.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/venue_card.dart';
import '../notifications/notifications_screen.dart';
import '../search/search_screen.dart';
import '../services/services_screen.dart';
import '../venue/venue_details_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final venueProvider = context.watch<VenueProvider>();
    final unread = context.watch<NotificationProvider>().unreadCount;
    return Scaffold(
      appBar: AppBar(
        centerTitle: false,
        title: const Text(AppConstants.appName, style: TextStyle(fontSize: 28, fontWeight: FontWeight.w900)),
        actions: [
          const CurrencyToggleChip(),
          Stack(children: [
            IconButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationsScreen())), icon: const Icon(Icons.notifications_none_rounded)),
            if (unread > 0) Positioned(right: 9, top: 9, child: CircleAvatar(radius: 8, backgroundColor: AppColors.danger, child: Text('$unread', style: const TextStyle(fontSize: 9, color: Colors.white))))
          ]),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => context.read<VenueProvider>().loadVenues(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            GestureDetector(
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SearchScreen())),
              child: AbsorbPointer(
                child: TextField(decoration: InputDecoration(hintText: 'ابحث عن صالة أو مدينة أو نوع مناسبة...', prefixIcon: const Icon(Icons.search_rounded), suffixIcon: IconButton(onPressed: () {}, icon: const Icon(Icons.tune_rounded)))),
              ),
            ),
            const SizedBox(height: 18),
            const _AdsCarousel(),
            const SizedBox(height: 20),
            const _HowItWorksCard(),
            const SizedBox(height: 20),
            const _ServiceProvidersHomeCard(),
            const SizedBox(height: 20),
            _SectionTitle(title: 'التصنيفات', onViewAll: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SearchScreen()))),
            const SizedBox(height: 10),
            const _CategoriesRow(),
            const SizedBox(height: 22),
            _SectionTitle(title: 'الصالات المميزة', onViewAll: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SearchScreen()))),
            const SizedBox(height: 10),
            if (venueProvider.isLoading)
              const Padding(padding: EdgeInsets.all(34), child: Center(child: CircularProgressIndicator()))
            else if (venueProvider.error != null)
              EmptyState(icon: Icons.wifi_off_rounded, title: 'تعذر تحميل الصالات', subtitle: venueProvider.error!, buttonText: 'إعادة المحاولة', onPressed: () => context.read<VenueProvider>().loadVenues())
            else
              ...venueProvider.venues.map((venue) => VenueCard(venue: venue, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => VenueDetailsScreen(venue: venue))))),
          ],
        ),
      ),
    );
  }
}


class _ServiceProvidersHomeCard extends StatelessWidget {
  const _ServiceProvidersHomeCard();

  @override
  Widget build(BuildContext context) {
    final items = const [
      _ServiceShortcut('📸', 'تصوير'),
      _ServiceShortcut('🍽️', 'ضيافة'),
      _ServiceShortcut('🎀', 'ديكور'),
      _ServiceShortcut('💡', 'إضاءة'),
    ];

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: Colors.white10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            const CircleAvatar(backgroundColor: AppColors.primary, child: Icon(Icons.room_service_rounded, color: Colors.white)),
            const SizedBox(width: 10),
            const Expanded(child: Text('مقدمو الخدمات', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900))),
            TextButton(
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ServicesScreen())),
              child: const Text('عرض الكل'),
            ),
          ]),
          const SizedBox(height: 6),
          const Text(
            'اختر خدمات خارجية مثل التصوير والضيافة والديكور. لكل خدمة فاتورة مستقلة، والدفع عبر شام كاش أو سيريتل كاش أو الهرم مع إثبات تحويل.',
            style: TextStyle(color: AppColors.textSecondary, height: 1.4),
          ),
          const SizedBox(height: 14),
          Row(
            children: items.map((item) => Expanded(child: _ServiceShortcutTile(item: item))).toList(),
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ServicesScreen())),
              icon: const Icon(Icons.storefront_rounded),
              label: const Text('فتح واجهة مقدمي الخدمات'),
            ),
          ),
        ],
      ),
    );
  }
}

class _ServiceShortcutTile extends StatelessWidget {
  final _ServiceShortcut item;
  const _ServiceShortcutTile({required this.item});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ServicesScreen())),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Column(children: [
          Text(item.emoji, style: const TextStyle(fontSize: 24)),
          const SizedBox(height: 5),
          Text(item.title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800)),
        ]),
      ),
    );
  }
}

class _ServiceShortcut {
  final String emoji;
  final String title;
  const _ServiceShortcut(this.emoji, this.title);
}

class _AdsCarousel extends StatefulWidget {
  const _AdsCarousel();

  @override
  State<_AdsCarousel> createState() => _AdsCarouselState();
}

class _AdsCarouselState extends State<_AdsCarousel> {
  int index = 0;
  final ads = const [
    _AdData('assets/images/offer1.jpg', 'العروض الخاصة', 'قارن بين الصالات واحصل على أفضل باقة'),
    _AdData('assets/images/offer2.jpg', 'باقة الزفاف', 'صالة وديكور وتصوير ضمن طلب واحد'),
    _AdData('assets/images/offer3.jpg', 'مقدمو خدمات جدد', 'احجز خدمات الضيافة والإضاءة بسهولة'),
  ];

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      SizedBox(
        height: 156,
        child: PageView.builder(
          itemCount: ads.length,
          onPageChanged: (value) => setState(() => index = value),
          itemBuilder: (context, i) => _AdCard(ad: ads[i]),
        ),
      ),
      const SizedBox(height: 8),
      Row(mainAxisAlignment: MainAxisAlignment.center, children: List.generate(ads.length, (i) => AnimatedContainer(duration: const Duration(milliseconds: 250), margin: const EdgeInsets.symmetric(horizontal: 3), height: 6, width: index == i ? 18 : 6, decoration: BoxDecoration(color: index == i ? AppColors.primary : AppColors.surface2, borderRadius: BorderRadius.circular(20))))),
    ]);
  }
}

class _AdCard extends StatelessWidget {
  final _AdData ad;
  const _AdCard({required this.ad});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 1),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(borderRadius: BorderRadius.circular(24)),
      child: Stack(fit: StackFit.expand, children: [
        Image.asset(ad.image, fit: BoxFit.cover, errorBuilder: (_, __, ___) => Container(decoration: const BoxDecoration(gradient: LinearGradient(colors: [AppColors.primary, AppColors.secondary])))),
        Container(decoration: BoxDecoration(gradient: LinearGradient(colors: [Colors.black.withValues(alpha: .65), Colors.black.withValues(alpha: .15)], begin: Alignment.centerLeft, end: Alignment.centerRight))),
        Padding(
          padding: const EdgeInsets.all(20),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(ad.label, style: const TextStyle(color: Colors.white70, fontWeight: FontWeight.w700)),
            const SizedBox(height: 7),
            Text(ad.title, style: const TextStyle(fontSize: 23, height: 1.15, color: Colors.white, fontWeight: FontWeight.w900)),
          ]),
        ),
      ]),
    );
  }
}

class _AdData {
  final String image;
  final String label;
  final String title;
  const _AdData(this.image, this.label, this.title);
}

class _SectionTitle extends StatelessWidget {
  final String title;
  final VoidCallback onViewAll;
  const _SectionTitle({required this.title, required this.onViewAll});
  @override
  Widget build(BuildContext context) => Row(children: [Expanded(child: Text(title, style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900))), TextButton(onPressed: onViewAll, child: const Text('عرض الكل'))]);
}

class _CategoriesRow extends StatelessWidget {
  const _CategoriesRow();
  @override
  Widget build(BuildContext context) {
    const items = <_CategoryItem>[
      _CategoryItem(Icons.favorite_rounded, 'زفاف'),
      _CategoryItem(Icons.favorite_border_rounded, 'خطوبة'),
      _CategoryItem(Icons.school_rounded, 'تخرج'),
      _CategoryItem(Icons.cake_rounded, 'عيد ميلاد'),
      _CategoryItem(Icons.local_florist_outlined, 'عزاء'),
    ];
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: items.map((item) => InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SearchScreen(initialEventType: item.title))),
        child: Padding(
          padding: const EdgeInsets.all(2),
          child: Column(children: [
            CircleAvatar(radius: 28, backgroundColor: AppColors.surface, child: Icon(item.icon, color: AppColors.primary)),
            const SizedBox(height: 8),
            Text(item.title, style: const TextStyle(fontSize: 12)),
          ]),
        ),
      )).toList(),
    );
  }
}

class _CategoryItem {
  final IconData icon;
  final String title;
  const _CategoryItem(this.icon, this.title);
}


class _HowItWorksCard extends StatelessWidget {
  const _HowItWorksCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: Colors.white10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Text(
            'كيف يعمل Salora',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          SizedBox(height: 12),
          _HowStep(emoji: '🎯', title: 'اختر نوع المناسبة', subtitle: 'زفاف أو خطوبة أو تخرج أو عيد ميلاد أو عزاء.'),
          SizedBox(height: 10),
          _HowStep(emoji: '🏛️', title: 'احجز الصالة المناسبة', subtitle: 'تظهر لك الصالات المناسبة لنوع مناسبتك فقط.'),
          SizedBox(height: 10),
          _HowStep(emoji: '🧾', title: 'أضف الخدمات وأكّد الحجز', subtitle: 'الخدمات المجانية ضمن سعر الصالة، والإضافات تظهر بوضوح في الفاتورة.'),
        ],
      ),
    );
  }
}

class _HowStep extends StatelessWidget {
  final String emoji;
  final String title;
  final String subtitle;

  const _HowStep({
    required this.emoji,
    required this.title,
    required this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(emoji, style: const TextStyle(fontSize: 22)),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: const TextStyle(color: AppColors.textSecondary, height: 1.35),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
