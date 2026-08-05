# Salora Backend API — University Edition

REST API مبني باستخدام **Laravel 11 / PHP 8.2+** لخدمة تطبيق Flutter ولوحة React. تحتفظ النسخة بنطاق مناسب لمشروع جامعي، مع تأجيل التكاملات الإنتاجية المتقدمة.

## الوظائف الأساسية

- Authentication عبر Laravel Sanctum، ملف شخصي، تغيير واستعادة كلمة المرور.
- Roles: `admin`, `owner`, `provider`, `customer` مع Ownership checks.
- إدارة الصالات والخدمات والموافقات الإدارية.
- Events وTodo Lists محفوظة في قاعدة البيانات.
- حجز الصالات وطلبات الخدمات، منع التعارض، وسجل حالات الحجز.
- طلبات تعديل وإلغاء رسمية.
- Invoice وPayment Transaction لطريقة التحويل اليدوي مع إثبات دفع خاص وغير عام.
- Notifications داخل النظام، Offers، Reviews، Complaints، وتقارير أساسية.

## التشغيل

```bash
composer install
cp .env.example .env
php artisan key:generate
```

أنشئ قاعدة MySQL باسم `salora_db`، ثم عدّل بيانات الاتصال في `.env` ونفّذ:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

فحص الصحة:

```text
GET http://127.0.0.1:8000/api/health
```

## حسابات العرض

جميع الحسابات تستخدم كلمة المرور:

```text
Salora@2026
```

| Role | Email |
|---|---|
| Admin | `admin@salora.test` |
| Hall Manager | `owner@salora.test` |
| Hall Manager 2 | `owner2@salora.test` |
| Service Provider | `provider@salora.test` |
| Customer | `customer@salora.test` |
| Customer 2 | `customer2@salora.test` |

## الاختبارات

بعد تثبيت dependencies:

```bash
php artisan test
```

الاختبارات تستخدم SQLite in-memory وتشمل المصادقة، تغيير كلمة المرور الإجباري، إنشاء الأحداث والمهام، منع تداخل الحجوزات، خصوصية إثبات الدفع، وشروط التقييم.

## ملاحظات النطاق

- الدفع الحالي هو `manual_transfer` مع مراجعة إثبات الدفع، وليس بوابة بطاقات حقيقية.
- OTP مربوط فعلياً بالبريد لتوثيق الحساب واستعادة كلمة المرور. راجع `docs/EMAIL_OTP_SETUP_AR.md`. ولا يظهر الرمز في استجابة API إلا إذا تم تفعيل `OTP_EXPOSE_IN_LOCAL=true` في بيئة محلية خاضعة للرقابة.
- Push Notifications، Real-time collaboration، Print-shop network و2FA خارج نطاق النسخة الجامعية الحالية.
