import '../models/venue.dart';

final List<Venue> venues = [
  Venue(
    id: '1',
    name: 'الصالة الملكية',
    location: 'دمشق',
    image: 'assets/images/hall1.jpg',
    price: 500000,
    rating: 4.8,
    capacity: 300,
    services: const ['تصوير', 'ديكور', 'خدمة ضيافة'],
    eventTypes: const ['زفاف', 'تخرج'],
  ),
  Venue(
    id: '2',
    name: 'القصر الذهبي',
    location: 'حلب',
    image: 'assets/images/hall2.jpg',
    price: 750000,
    rating: 4.9,
    capacity: 500,
    services: const ['تصوير', 'خدمة دي جي', 'ديكور'],
    eventTypes: const ['زفاف', 'مؤتمر'],
  ),
  Venue(
    id: '3',
    name: 'صالة دايموند',
    location: 'حمص',
    image: 'assets/images/hall3.jpg',
    price: 600000,
    rating: 4.7,
    capacity: 400,
    services: const ['خدمة ضيافة', 'ديكور'],
    eventTypes: const ['عيد ميلاد', 'تخرج'],
  ),
];
