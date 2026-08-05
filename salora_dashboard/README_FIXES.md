# VenueHub Dashboard - Improved Prototype

تم تحسين نسخة لوحة التحكم لتكون أقوى كـ Frontend Prototype وجاهزة للربط مع Backend لاحقاً.

## أهم التعديلات
- توحيد بيانات النظام داخل `AppContext.jsx` وإضافة: users, providers, metrics, payments, notifications.
- تحسين Layout ليصبح Responsive مع Sidebar للموبايل والديسكتوب.
- تطوير Admin Dashboard مع إحصائيات وتشارتات أوضح.
- تطوير إدارة الصالات مع بحث، فلترة، View، Approve، Reject، Delete.
- تطوير إدارة الحجوزات مع بحث، فلترة، تحديث حالة الحجز، ومراجعة حالة الدفع.
- تطوير إدارة المستخدمين ومقدمي الخدمات.
- تطوير التقارير مع Revenue, Platform Fee, Booking Distribution.
- تطوير الإشعارات ومركز الإعدادات وتجهيز نقطة API Base URL.

## ملاحظات ربط الباك لاحقاً
- استبدل البيانات التجريبية في `AppContext.jsx` بطبقة API services.
- اربط عمليات approve/reject/delete مع Laravel endpoints.
- اجعل auth حقيقي باستخدام token من الـ backend.
