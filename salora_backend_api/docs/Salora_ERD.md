# Salora ERD — University Edition

```mermaid
erDiagram
    USERS ||--o{ EVENTS : creates
    EVENT_TYPES ||--o{ EVENTS : classifies
    EVENTS ||--o{ EVENT_TODO_ITEMS : contains
    TODO_TEMPLATES ||--o{ EVENT_TODO_ITEMS : copied_from

    USERS ||--o{ VENUES : owns
    VENUES ||--o{ VENUE_REVISIONS : has_pending_changes
    VENUES ||--o{ VENUE_IMAGES : has
    VENUES }o--o{ EVENT_TYPES : supports

    SERVICE_CATEGORIES ||--o{ SERVICES : classifies
    USERS ||--o{ SERVICES : provides
    VENUES }o--o{ SERVICES : offers

    USERS ||--o{ BOOKINGS : makes
    EVENTS ||--o{ BOOKINGS : groups
    VENUES ||--o{ BOOKINGS : receives
    BOOKINGS ||--o{ BOOKING_SERVICES : contains
    BOOKINGS ||--o{ PROVIDER_SERVICE_REQUESTS : requests
    SERVICES ||--o{ PROVIDER_SERVICE_REQUESTS : targets
    BOOKINGS ||--o{ BOOKING_STATUS_HISTORIES : records
    BOOKINGS ||--o{ BOOKING_CHANGE_REQUESTS : receives

    BOOKINGS ||--|| INVOICES : billed_by
    INVOICES ||--o{ PAYMENT_TRANSACTIONS : paid_by
    BOOKINGS ||--o{ PAYMENT_PROOFS : supports
    PAYMENT_PROOFS ||--o| PAYMENT_TRANSACTIONS : verifies

    USERS ||--o{ OFFERS : creates
    VENUES ||--o{ OFFERS : targeted_by
    USERS ||--o{ REVIEWS : writes
    BOOKINGS ||--o| REVIEWS : validates
    USERS ||--o{ COMPLAINTS : submits
    USERS ||--o{ NOTIFICATIONS : receives
    USERS ||--o{ ACTIVITY_LOGS : performs
```

## ملاحظات

- `EventTodoItem` نسخة مستقلة من `TodoTemplate` كي يستطيع العميل تخصيصها دون تغيير القالب الإداري.
- تعديل الصالة المنشورة يُحفظ في `VenueRevision` حتى موافقة Admin.
- `Invoice` واحدة لكل Booking في النطاق الجامعي الحالي.
- `PaymentTransaction` يسجل قرار إثبات التحويل اليدوي، مع إمكانية إضافة Gateway Adapter مستقبلاً.
