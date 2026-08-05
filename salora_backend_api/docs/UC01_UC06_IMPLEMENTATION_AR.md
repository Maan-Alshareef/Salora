# تقرير تنفيذ UC-01 إلى UC-06 — Salora

## النتيجة

تم تنفيذ وربط الحزمة الخاصة بالمصادقة والحسابات والملف الشخصي بين:

- Laravel Backend API
- React Dashboard
- Flutter Application

التنفيذ مبني على القرارات المعتمدة للحالات UC-01 إلى UC-06، مع حذف تاريخ الميلاد من التسجيل والملف الشخصي، واعتماد OTP بالبريد، والصورة الموحدة، والتجميد والحذف الآمن، وكلمة المرور المؤقتة الإلزامية لحسابات الأعمال.

## هل كان OTP موجوداً سابقاً؟

كان موجوداً **جزئياً فقط**:

- يوجد جدول/Model قديم خاص باستعادة كلمة المرور.
- كان الـBackend يولد الرمز ويتحقق منه.
- لم يكن يرسل الرمز إلى البريد فعلياً.
- لم يكن هناك توثيق بريد عند التسجيل.
- كان التسجيل يصدر Token مباشرة.

تم استبدال الأساس القديم بدورة موحدة كاملة: إنشاء الرمز، تخزين Hash، إرسال Email، شاشة إدخال، تحقق، انتهاء صلاحية، حد محاولات، إعادة إرسال، وتوثيق حساب أو إعادة تعيين كلمة المرور.

## UC-01 — تسجيل الدخول والاستعادة

### المنفذ

- تسجيل دخول لجميع الأدوار مع Token من Laravel Sanctum.
- 5 محاولات خاطئة ثم قفل أمني لمدة 10 دقائق.
- تصفير المحاولات بعد نجاح الدخول أو انتهاء القفل.
- رسائل عامة تمنع كشف الحسابات قدر الإمكان.
- OTP عبر البريد لاستعادة كلمة المرور.
- الرمز 6 أرقام، صالح 10 دقائق، 5 محاولات، وإعادة الإرسال بعد 60 ثانية.
- سحب الجلسات بعد إعادة تعيين كلمة المرور.
- إصلاح Dashboard بعد Refresh: حالة `must_change_password` تُستعاد من `/auth/me` وتوجه لصفحة مستقلة.
- منع مالك الصالة ومقدم الخدمة من جميع الـEndpoints المحمية قبل تغيير كلمة المرور، باستثناء `/auth/me` و`/auth/change-password` و`/auth/logout`.

## UC-02 — تسجيل حساب عميل جديد

### الحقول النهائية

- الاسم الكامل.
- رقم الهاتف.
- البريد الإلكتروني.
- كلمة المرور.
- تأكيد كلمة المرور.

لا يوجد تاريخ ميلاد.

### التدفق

1. ينشئ Flutter حساب العميل.
2. الـBackend يحفظه مع `email_verified_at = null`.
3. لا يصدر Token.
4. يرسل OTP إلى البريد المدخل.
5. تظهر شاشة توثيق البريد.
6. بعد الرمز الصحيح يتم توثيق البريد وإصدار Token.
7. عند فشل الإرسال يمكن إعادة الإرسال من الشاشة من دون إنشاء حساب جديد.

## UC-03 — عرض الملف الشخصي

تم توحيد البيانات المعروضة في جميع الأدوار:

- الصورة.
- الاسم.
- البريد.
- الهاتف.
- الدور.

لا يظهر تاريخ الميلاد أو تاريخ التسجيل.

## UC-04 — تعديل الملف الشخصي

- تعديل الاسم والهاتف من Flutter وDashboard.
- البريد للقراءة فقط؛ تغييره يحتاج دورة OTP مستقلة غير داخلة في القرار الحالي.
- رفع الصورة بصيغة Multipart إلى الخادم.
- JPG/PNG/WebP حتى 2 MB.
- اسم ملف جديد في كل رفع لتجنب Cache قديم.
- حذف الصورة القديمة بعد نجاح رفع الجديدة.
- إرجاع `avatar_url` موحد.
- تحديث Auth Provider/Context فوراً، لذلك تتغير الصورة في الملف والشريط والقوائم التي تجلب المستخدم الحالي.
- علاقات السجلات التاريخية بالمستخدم تستخدم `withTrashed` حتى لا تنكسر بعد الحذف الآمن.

## UC-05 — إدارة الحسابات

### الإجراءات المتاحة للأدمن

- إنشاء حساب.
- تعديل الاسم والبريد والهاتف والدور وكلمة المرور.
- تنشيط الحساب وفك القفل.
- تجميد مؤقت حتى تاريخ مع سبب.
- تعطيل غير محدد مع سبب.
- فحص أثر الحذف.
- Soft Delete آمن.
- استعادة الحساب المحذوف.

### حماية الحذف

قبل الحذف يعرض Dashboard أعداد الارتباطات ويمنع العملية عند وجود:

- حجوزات عميل نشطة.
- حجوزات صالات نشطة.
- صالات مملوكة تحتاج نقل/معالجة.
- خدمات مقدم خدمة تحتاج نقل/معالجة.
- طلبات خدمة نشطة.
- محاولة حذف الحساب الحالي.
- محاولة حذف آخر Admin فعال.

عند السماح بالحذف يجب على الأدمن كتابة بريد الحساب للتأكيد. لا تُحذف السجلات التاريخية أو الحجوزات والفواتير القديمة.

### أثر التجميد والتعطيل

- تُسحب Tokens فوراً.
- يُمنع تسجيل الدخول.
- انتهاء التجميد يعيد الحساب فعالاً تلقائياً.
- لا تقبل الصالة حجوزات جديدة إن كان حساب مالكها غير متاح.
- لا تقبل خدمة خارجية طلبات جديدة إن كان مقدمها غير متاح.
- السجلات والحجوزات السابقة تبقى محفوظة.

## UC-06 — إنشاء حساب مالك/مقدم خدمة

- Dashboard يولد كلمة مؤقتة قوية ومختلفة لكل حساب باستخدام Web Crypto.
- الأدمن يستطيع تعديلها قبل الحفظ.
- الـBackend يطبق نفس سياسة القوة.
- الحساب يُنشأ فعالاً وموثق البريد إدارياً.
- `must_change_password = true` للمالك والمقدم.
- تظهر كلمة المرور في نموذج الأدمن فقط ولا تُخزن كنص صريح.
- عند أول دخول تُعرض صفحة إجبارية منفصلة وتبقى بعد Refresh وإغلاق المتصفح.
- إعادة تعيين كلمة مرور حساب أعمال بواسطة الأدمن تعيد تفعيل شرط التغيير الإجباري.

## أهم Routes الجديدة أو المعدلة

### Public Auth

- `POST /api/auth/register`
- `POST /api/auth/verify-email`
- `POST /api/auth/resend-verification`
- `POST /api/auth/login`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`

### Authenticated Profile

- `GET /api/auth/me`
- `PUT /api/auth/profile`
- `POST /api/auth/profile/avatar`
- `POST /api/auth/change-password`
- `POST /api/auth/logout`

### Admin Accounts

- `GET /api/admin/users`
- `POST /api/admin/users`
- `PUT /api/admin/users/{user}`
- `GET /api/admin/users/{user}/deletion-impact`
- `POST /api/admin/users/{user}/suspend`
- `POST /api/admin/users/{user}/deactivate`
- `POST /api/admin/users/{user}/activate`
- `DELETE /api/admin/users/{user}`
- `POST /api/admin/users/{id}/restore`

## قاعدة البيانات

Migration الجديد:

`database/migrations/2026_01_01_000018_harden_accounts_and_create_email_otps.php`

يضيف:

- `failed_login_attempts`
- `locked_until`
- `suspended_until`
- `suspension_reason`
- `suspended_by`
- `deleted_at`
- جدول `email_otps`

كما يستبدل مخزن OTP القديم الخاص باستعادة كلمة المرور بالجدول الموحد.

## ملفات رئيسية مضافة

### Backend

- `app/Models/EmailOtp.php`
- `app/Services/EmailOtpService.php`
- `app/Mail/OtpCodeMail.php`
- `app/Http/Middleware/AccountStatusMiddleware.php`
- `resources/views/emails/otp-code.blade.php`
- `tests/Feature/AuthAndAccountHardeningTest.php`
- `docs/EMAIL_OTP_SETUP_AR.md`

### Dashboard

- `src/pages/Auth/RequiredPasswordChange.jsx`
- `src/utils/passwords.js`

### Flutter

- `lib/screens/auth/email_verification_screen.dart`
- `lib/core/widgets/user_avatar.dart`

## نتائج الفحص

- فحص Syntax لجميع ملفات PHP في `app/config/database/routes/tests`: ناجح.
- Laravel Route List: 145 Route، منها 142 API Route.
- تحليل Babel لجميع ملفات JavaScript/JSX: 45 ملفاً، بلا أخطاء Parsing.
- فحص المراجع المحلية في Flutter: 87 ملف Dart، ولا يوجد Import محلي مفقود.
- تمت إضافة اختبارات Feature لدورة OTP، القفل، تغيير كلمة المرور الإجباري، الصورة، التجميد، التعطيل، الحذف والاستعادة.

### حدود بيئة الفحص

- تشغيل PHPUnit الكامل متوقف في بيئة الفحص بسبب غياب PHP Extensions: `dom`, `mbstring`, `xml`, `xmlwriter`.
- تشغيل Vite Build متوقف لأن `node_modules` الأصلي من Windows ويحوي `@esbuild/win32-x64`؛ ملف التسليم النظيف لا يتضمن `node_modules`، ويجب تشغيل `npm ci` على جهاز البناء.
- Flutter/Dart SDK غير مثبت في بيئة الفحص، لذلك لم يتم تشغيل `flutter analyze` أو بناء APK.
- إرسال بريد حقيقي يحتاج Gmail Username وApp Password في `.env`; لم تُضمّن أي بيانات اعتماد في الملفات.

## خطوات التشغيل بعد فك الضغط

### Backend

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan optimize:clear
php artisan serve
```

أضف إعداد Gmail في `.env` قبل اختبار OTP.

### Dashboard

```bash
cp .env.example .env
npm ci
npm run dev
```

### Flutter

```bash
flutter pub get
flutter analyze
flutter run
```

تأكد من ضبط عنوان API في `lib/core/network/api_config.dart` بما يناسب الجهاز أو المحاكي.
