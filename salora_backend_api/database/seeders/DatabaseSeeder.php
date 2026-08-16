<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\BookingStatusHistory;
use App\Models\Complaint;
use App\Models\Event;
use App\Models\EventTodoItem;
use App\Models\EventType;
use App\Models\InvitationTemplate;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\PaymentProof;
use App\Models\PaymentTransaction;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\TodoTemplate;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueImage;
use App\Support\SaloraStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Salora@2026';

    public function run(): void
    {
        $users = $this->seedUsers();
        [$eventTypes, $todoTemplates] = $this->seedEventTypes();
        $categories = $this->seedServiceCategories();
        [$venues, $services] = $this->seedVenuesAndServices($users, $eventTypes, $categories);
        $this->seedAcademicWorkflow($users, $eventTypes, $todoTemplates, $venues, $services);
        $this->seedSettings();
    }

    private function seedUsers(): array
    {
        $password = Hash::make(self::DEMO_PASSWORD);

        return [
            'admin' => User::create([
                'name' => 'مدير النظام',
                'email' => 'admin@salora.test',
                'phone' => '0900000001',
                'password' => $password,
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]),
            'owner' => User::create([
                'name' => 'مدير قاعة الياقوت',
                'email' => 'owner@salora.test',
                'phone' => '0900000002',
                'password' => $password,
                'role' => 'owner',
                'status' => 'active',
                'email_verified_at' => now(),
            ]),
            'owner2' => User::create([
                'name' => 'مدير تراس لونا',
                'email' => 'owner2@salora.test',
                'phone' => '0900000003',
                'password' => $password,
                'role' => 'owner',
                'status' => 'active',
                'email_verified_at' => now(),
            ]),
            'provider' => User::create([
                'name' => 'Moments Photography',
                'email' => 'provider@salora.test',
                'phone' => '0900000004',
                'password' => $password,
                'role' => 'provider',
                'status' => 'active',
                'email_verified_at' => now(),
            ]),
            'customer' => User::create([
                'name' => 'عميل تجريبي',
                'email' => 'customer@salora.test',
                'phone' => '0900000005',
                'birth_date' => '1998-05-12',
                'password' => $password,
                'role' => 'customer',
                'status' => 'active',
                'email_verified_at' => now(),
            ]),
            'customer2' => User::create([
                'name' => 'عميلة تجريبية',
                'email' => 'customer2@salora.test',
                'phone' => '0900000006',
                'birth_date' => '2000-09-21',
                'password' => $password,
                'role' => 'customer',
                'status' => 'active',
                'email_verified_at' => now(),
            ]),
        ];
    }

    private function seedEventTypes(): array
    {
        $definitions = [
            ['name_ar' => 'زفاف', 'name_en' => 'Wedding', 'emoji' => '💍', 'tasks' => ['حجز الصالة', 'حجز التصوير', 'تأكيد الضيافة', 'إرسال الدعوات']],
            ['name_ar' => 'خطوبة', 'name_en' => 'Engagement', 'emoji' => '💞', 'tasks' => ['تحديد الموعد', 'حجز الصالة', 'اختيار الديكور', 'تأكيد قائمة الضيوف']],
            ['name_ar' => 'تخرج', 'name_en' => 'Graduation', 'emoji' => '🎓', 'tasks' => ['حجز الصالة', 'تجهيز الصوتيات', 'تأكيد قائمة الضيوف']],
            ['name_ar' => 'عيد ميلاد', 'name_en' => 'Birthday', 'emoji' => '🎂', 'tasks' => ['اختيار المكان', 'طلب الكعكة', 'إرسال الدعوات']],
            ['name_ar' => 'عزاء', 'name_en' => 'Condolence', 'emoji' => '🕊️', 'tasks' => ['اختيار الصالة', 'تأكيد الضيافة', 'إنشاء النعوة الإلكترونية']],
            ['name_ar' => 'مؤتمر', 'name_en' => 'Conference', 'emoji' => '🧑‍💼', 'tasks' => ['حجز المكان', 'تجهيز جهاز العرض', 'تأكيد الحضور']],
        ];

        $eventTypes = collect();
        $templates = collect();

        foreach ($definitions as $index => $definition) {
            $eventType = EventType::create([
                'name_ar' => $definition['name_ar'],
                'name_en' => $definition['name_en'],
                'emoji' => $definition['emoji'],
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
            $eventTypes->put($definition['name_en'], $eventType);

            foreach ($definition['tasks'] as $taskIndex => $task) {
                $template = TodoTemplate::create([
                    'event_type_id' => $eventType->id,
                    'task_ar' => $task,
                    'task_en' => $task,
                    'sort_order' => $taskIndex + 1,
                    'is_active' => true,
                ]);
                $templates->push($template);
            }

            InvitationTemplate::create([
                'event_type_id' => $eventType->id,
                'title_ar' => 'قالب '.$definition['name_ar'],
                'title_en' => $definition['name_en'].' invitation',
                'body_ar' => 'نتشرف بدعوتكم إلى {event_name} بتاريخ {event_date} في {venue_name}.',
                'body_en' => 'You are invited to {event_name} on {event_date} at {venue_name}.',
                'theme' => Str::slug($definition['name_en']),
                'is_active' => true,
            ]);
        }

        return [$eventTypes, $templates];
    }

    private function seedServiceCategories(): array
    {
        return [
            'hall' => ServiceCategory::create(['name_ar' => 'خدمات الصالة', 'name_en' => 'Hall services', 'applies_to' => 'hall', 'sort_order' => 1]),
            'photo' => ServiceCategory::create(['name_ar' => 'التصوير', 'name_en' => 'Photography', 'applies_to' => 'provider', 'sort_order' => 2]),
            'hospitality' => ServiceCategory::create(['name_ar' => 'الضيافة', 'name_en' => 'Hospitality', 'applies_to' => 'both', 'sort_order' => 3]),
            'equipment' => ServiceCategory::create(['name_ar' => 'التجهيزات', 'name_en' => 'Equipment', 'applies_to' => 'both', 'sort_order' => 4]),
        ];
    }

    private function seedVenuesAndServices(array $users, $eventTypes, array $categories): array
    {
        $grand = Venue::create([
            'owner_id' => $users['owner']->id,
            'name_ar' => 'قاعة الياقوت الكبرى',
            'name_en' => 'Grand Sapphire Hall',
            'description_ar' => 'صالة داخلية مناسبة للأعراس والخطوبة والتخرج.',
            'description_en' => 'Indoor hall suitable for weddings, engagements and graduations.',
            'city' => 'Damascus',
            'address' => 'Mezzeh Highway',
            'map_url' => 'https://maps.google.com/?q=Mezzeh+Damascus',
            'capacity' => 600,
            'price_usd' => 250,
            'price_syp' => 3500000,
            'currency_base' => 'SYP',
            'status' => 'approved',
            'rating_avg' => 5,
            'reviews_count' => 1,
            'amenities' => ['Parking', 'Air conditioning', 'Bride room'],
            'policies' => ['Payment is required after owner approval', 'No overlapping bookings'],
        ]);

        $luna = Venue::create([
            'owner_id' => $users['owner2']->id,
            'name_ar' => 'تراس لونا',
            'name_en' => 'Luna Terrace',
            'description_ar' => 'تراس مفتوح للمناسبات المتوسطة والمؤتمرات الصغيرة.',
            'description_en' => 'Open-air terrace for medium events and small conferences.',
            'city' => 'Homs',
            'address' => 'Al-Waer',
            'map_url' => 'https://maps.google.com/?q=Al+Waer+Homs',
            'capacity' => 220,
            'price_usd' => 120,
            'price_syp' => 1680000,
            'currency_base' => 'SYP',
            'status' => 'approved',
            'rating_avg' => 4,
            'reviews_count' => 1,
            'amenities' => ['Open air', 'WiFi', 'Stage area'],
            'policies' => ['Outdoor events depend on weather'],
        ]);

        foreach ([[$grand, 'Grand Sapphire Hall'], [$luna, 'Luna Terrace']] as [$venue, $label]) {
            VenueImage::create([
                'venue_id' => $venue->id,
                'image_url' => 'https://placehold.co/900x520?text='.urlencode($label),
                'is_main' => true,
                'sort_order' => 1,
            ]);
        }

        $grand->eventTypes()->sync([
            $eventTypes['Wedding']->id,
            $eventTypes['Engagement']->id,
            $eventTypes['Graduation']->id,
            $eventTypes['Birthday']->id,
        ]);
        $luna->eventTypes()->sync([
            $eventTypes['Graduation']->id,
            $eventTypes['Birthday']->id,
            $eventTypes['Conference']->id,
        ]);

        $lighting = Service::create([
            'name_ar' => 'إضاءة أساسية',
            'name_en' => 'Basic lighting',
            'emoji' => '💡',
            'type' => 'included',
            'category' => 'Hall services',
            'category_id' => $categories['hall']->id,
            'price_usd' => 0,
            'price_syp' => 0,
            'pricing_unit' => 'per_event',
            'is_active' => true,
            'approval_status' => 'approved',
            'available_for' => ['Wedding', 'Engagement', 'Graduation'],
        ]);
        $hospitality = Service::create([
            'name_ar' => 'ضيافة مميزة',
            'name_en' => 'Premium hospitality',
            'emoji' => '☕',
            'type' => 'hall_upgrade',
            'category' => 'Hospitality',
            'category_id' => $categories['hospitality']->id,
            'price_usd' => 90,
            'price_syp' => 1260000,
            'pricing_unit' => 'per_event',
            'is_active' => true,
            'approval_status' => 'approved',
            'available_for' => ['Wedding', 'Engagement'],
        ]);
        $photography = Service::create([
            'name_ar' => 'تصوير مناسبات',
            'name_en' => 'Moments Photography',
            'description_ar' => 'تغطية فوتوغرافية للمناسبات.',
            'description_en' => 'Event photography coverage.',
            'emoji' => '📸',
            'type' => 'external_vendor',
            'category' => 'Photography',
            'category_id' => $categories['photo']->id,
            'price_usd' => 100,
            'price_syp' => 1400000,
            'pricing_unit' => 'per_event',
            'duration_minutes' => 300,
            'provider_id' => $users['provider']->id,
            'is_active' => true,
            'approval_status' => 'approved',
            'available_for' => ['Wedding', 'Graduation', 'Birthday'],
        ]);
        $projector = Service::create([
            'name_ar' => 'جهاز عرض',
            'name_en' => 'Projector',
            'emoji' => '📽️',
            'type' => 'hall_upgrade',
            'category' => 'Equipment',
            'category_id' => $categories['equipment']->id,
            'price_usd' => 30,
            'price_syp' => 420000,
            'pricing_unit' => 'per_event',
            'is_active' => true,
            'approval_status' => 'approved',
            'available_for' => ['Graduation', 'Conference'],
        ]);

        $grand->services()->sync([
            $lighting->id => ['custom_price_usd' => 0, 'custom_price_syp' => 0, 'is_available' => true],
            $hospitality->id => ['custom_price_usd' => 85, 'custom_price_syp' => 1190000, 'is_available' => true],
            $photography->id => ['custom_price_usd' => 100, 'custom_price_syp' => 1400000, 'is_available' => true],
        ]);
        $luna->services()->sync([
            $lighting->id => ['custom_price_usd' => 0, 'custom_price_syp' => 0, 'is_available' => true],
            $photography->id => ['custom_price_usd' => 95, 'custom_price_syp' => 1330000, 'is_available' => true],
            $projector->id => ['custom_price_usd' => 30, 'custom_price_syp' => 420000, 'is_available' => true],
        ]);

        return [
            ['grand' => $grand, 'luna' => $luna],
            ['lighting' => $lighting, 'hospitality' => $hospitality, 'photography' => $photography, 'projector' => $projector],
        ];
    }

    private function seedAcademicWorkflow(array $users, $eventTypes, $todoTemplates, array $venues, array $services): void
    {
        $pastEvent = Event::create([
            'customer_id' => $users['customer']->id,
            'event_type_id' => $eventTypes['Wedding']->id,
            'name' => 'حفل زفاف تجريبي مكتمل',
            'event_date' => now()->subMonth()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '23:00',
            'guests_count' => 350,
            'budget_syp' => 6000000,
            'city' => 'Damascus',
            'status' => 'completed',
        ]);
        foreach ($todoTemplates->where('event_type_id', $eventTypes['Wedding']->id) as $template) {
            EventTodoItem::create([
                'event_id' => $pastEvent->id,
                'todo_template_id' => $template->id,
                'title' => $template->task_ar ?: $template->task_en,
                'is_completed' => true,
                'sort_order' => $template->sort_order,
                'completed_at' => now()->subMonth(),
                'updated_by' => $users['customer']->id,
            ]);
        }

        $completedBooking = Booking::create([
            'booking_number' => 'BK-DEMO-COMPLETED',
            'customer_id' => $users['customer']->id,
            'venue_id' => $venues['grand']->id,
            'owner_id' => $users['owner']->id,
            'event_type_id' => $eventTypes['Wedding']->id,
            'event_id' => $pastEvent->id,
            'event_name' => $pastEvent->name,
            'host_name' => $users['customer']->name,
            'event_date' => $pastEvent->event_date,
            'start_time' => '18:00',
            'end_time' => '23:00',
            'guests_count' => 350,
            'booking_status' => SaloraStatus::BOOKING_COMPLETED,
            'payment_status' => SaloraStatus::PAYMENT_APPROVED,
            'subtotal_usd' => 335,
            'subtotal_syp' => 4690000,
            'total_usd' => 335,
            'total_syp' => 4690000,
            'currency' => 'SYP',
            'owner_decision_at' => now()->subMonths(2),
            'admin_payment_decision_at' => now()->subMonths(2)->addDay(),
        ]);
        BookingService::create([
            'booking_id' => $completedBooking->id,
            'service_id' => $services['hospitality']->id,
            'service_name' => $services['hospitality']->name_ar,
            'service_type' => $services['hospitality']->type,
            'quantity' => 1,
            'unit_price_usd' => 85,
            'unit_price_syp' => 1190000,
            'total_usd' => 85,
            'total_syp' => 1190000,
        ]);
        BookingStatusHistory::create([
            'booking_id' => $completedBooking->id,
            'from_status' => SaloraStatus::BOOKING_CONFIRMED,
            'to_status' => SaloraStatus::BOOKING_COMPLETED,
            'changed_by' => $users['owner']->id,
            'reason' => 'Seeded completed booking for the review workflow.',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-DEMO-0001',
            'booking_id' => $completedBooking->id,
            'customer_id' => $users['customer']->id,
            'subtotal_syp' => 4690000,
            'subtotal_usd' => 335,
            'total_syp' => 4690000,
            'total_usd' => 335,
            'currency' => 'SYP',
            'status' => 'paid',
            'due_at' => now()->subMonths(2),
            'paid_at' => now()->subMonths(2)->addDay(),
        ]);

        $proofPath = 'payment-proofs/demo-payment-proof.png';
        Storage::disk('local')->put($proofPath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));
        $proof = PaymentProof::create([
            'booking_id' => $completedBooking->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $users['customer']->id,
            'owner_id' => $users['owner']->id,
            'image_url' => $proofPath,
            'amount_usd' => 335,
            'amount_syp' => 4690000,
            'payment_method' => 'manual_transfer',
            'status' => 'approved',
            'admin_id' => $users['admin']->id,
            'uploaded_at' => now()->subMonths(2),
            'reviewed_at' => now()->subMonths(2)->addDay(),
        ]);
        PaymentTransaction::create([
            'invoice_id' => $invoice->id,
            'payment_proof_id' => $proof->id,
            'method' => 'manual_transfer',
            'reference' => 'TX-DEMO-0001',
            'amount' => 4690000,
            'currency' => 'SYP',
            'status' => 'paid',
            'metadata' => ['seeded' => true],
            'processed_at' => now()->subMonths(2)->addDay(),
        ]);

        Review::create([
            'customer_id' => $users['customer']->id,
            'venue_id' => $venues['grand']->id,
            'booking_id' => $completedBooking->id,
            'rating' => 5,
            'comment' => 'تنظيم جيد والتزام بالموعد.',
            'status' => 'visible',
        ]);

        $upcomingEvent = Event::create([
            'customer_id' => $users['customer2']->id,
            'event_type_id' => $eventTypes['Graduation']->id,
            'name' => 'حفل تخرج تجريبي',
            'event_date' => now()->addMonths(2)->toDateString(),
            'start_time' => '17:00',
            'end_time' => '21:00',
            'guests_count' => 160,
            'budget_syp' => 3000000,
            'city' => 'Homs',
        ]);
        foreach ($todoTemplates->where('event_type_id', $eventTypes['Graduation']->id) as $template) {
            EventTodoItem::create([
                'event_id' => $upcomingEvent->id,
                'todo_template_id' => $template->id,
                'title' => $template->task_ar ?: $template->task_en,
                'sort_order' => $template->sort_order,
                'updated_by' => $users['customer2']->id,
            ]);
        }

        $pendingBooking = Booking::create([
            'booking_number' => 'BK-DEMO-PENDING',
            'customer_id' => $users['customer2']->id,
            'venue_id' => $venues['luna']->id,
            'owner_id' => $users['owner2']->id,
            'event_type_id' => $eventTypes['Graduation']->id,
            'event_id' => $upcomingEvent->id,
            'event_name' => $upcomingEvent->name,
            'host_name' => $users['customer2']->name,
            'event_date' => $upcomingEvent->event_date,
            'start_time' => '17:00',
            'end_time' => '21:00',
            'guests_count' => 160,
            'booking_status' => SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
            'payment_status' => SaloraStatus::PAYMENT_UNPAID,
            'subtotal_usd' => 150,
            'subtotal_syp' => 2100000,
            'total_usd' => 150,
            'total_syp' => 2100000,
            'currency' => 'SYP',
        ]);
        BookingStatusHistory::create([
            'booking_id' => $pendingBooking->id,
            'to_status' => SaloraStatus::BOOKING_PENDING_OWNER_REVIEW,
            'changed_by' => $users['customer2']->id,
            'reason' => 'Seeded pending booking.',
        ]);

        Complaint::create([
            'reference_number' => 'CMP-DEMO-0001',
            'customer_id' => $users['customer']->id,
            'booking_id' => $completedBooking->id,
            'venue_id' => $venues['grand']->id,
            'owner_id' => $users['owner']->id,
            'category' => 'service',
            'subject' => 'ملاحظة على وقت الاستجابة',
            'message' => 'تم حل المشكلة، وهذه الشكوى موجودة لاختبار دورة الدعم.',
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        Offer::create([
            'created_by' => $users['owner']->id,
            'scope' => 'specific_venue',
            'venue_id' => $venues['grand']->id,
            'owner_id' => $users['owner']->id,
            'title_ar' => 'خصم تجريبي',
            'title_en' => 'Demo discount',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'status' => 'approved',
        ]);

        Notification::create([
            'user_id' => $users['owner2']->id,
            'title' => 'طلب حجز جديد',
            'body' => 'يوجد طلب حجز تجريبي بانتظار المراجعة.',
            'type' => 'booking_created',
            'data_json' => ['booking_id' => $pendingBooking->id],
        ]);
    }

    private function seedSettings(): void
    {
        Setting::insert([
            ['key' => 'exchange_rate_usd_to_syp', 'value' => '14000', 'type' => 'number', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'platform_commission_percentage', 'value' => '10', 'type' => 'number', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'payment_review_message', 'value' => 'Manual payment proofs are reviewed by the administrator.', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
