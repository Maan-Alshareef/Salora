# إعداد OTP المجاني عبر Gmail — Salora

## ما الذي تم ربطه؟

أصبح OTP جزءاً فعلياً من الـBackend وواجهات Flutter وDashboard في مسارين:

1. **تسجيل عميل جديد:** يُنشأ الحساب من دون Token، ويُرسل رمز من 6 أرقام إلى البريد الذي أدخله العميل. لا يدخل إلى التطبيق إلا بعد نجاح التوثيق.
2. **نسيان كلمة المرور:** يُرسل رمز إلى البريد المسجل، ثم يضع المستخدم الرمز وكلمة المرور الجديدة.

حسابات مالك الصالة ومقدم الخدمة التي ينشئها الأدمن تُعد موثقة إدارياً، وتُجبر بدلاً من ذلك على تغيير كلمة المرور المؤقتة عند أول دخول.

## هل Gmail مجاني؟

يمكن استخدام حساب Gmail شخصي مخصص للمشروع بلا شراء دومين. هذا مناسب للعرض الجامعي والاختبارات والإطلاق الصغير، لكنه يخضع لحدود Google وسياسات مكافحة الرسائل المزعجة، لذلك لا يُعامل كخدمة إرسال جماعي أو كحل إنتاجي واسع النطاق.

## إعداد حساب Gmail

1. أنشئ حساباً مخصصاً للمشروع، مثل `salora.notifications@gmail.com`.
2. فعّل **التحقق بخطوتين** لحساب Google.
3. من أمان حساب Google أنشئ **App Password** باسم `Salora Backend`.
4. انسخ كلمة مرور التطبيق ذات 16 رمزاً. يمكن حذف المسافات عند وضعها في `.env`.
5. لا تستخدم كلمة مرور Gmail العادية نهائياً.

## إعداد Laravel

انسخ `.env.example` إلى `.env`، ثم ضع القيم التالية في ملف `.env` على الخادم فقط:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=salora.notifications@gmail.com
MAIL_PASSWORD=ضع_هنا_App_Password_بدون_مشاركة_او_رفع
MAIL_FROM_ADDRESS=salora.notifications@gmail.com
MAIL_FROM_NAME="Salora"
MAIL_EHLO_DOMAIN=example.com

OTP_EXPOSE_IN_LOCAL=false
```

> لا ترسل App Password ضمن المحادثات، ولا تضعه في Git، ولا تضمه إلى ZIP التسليم. يبقى داخل `.env` على الخادم فقط.

بعد تعديل الإعدادات:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan salora:test-email your-address@example.com
```

يجب أن تصل رسالة الاختبار قبل اختبار شاشات التسجيل والاستعادة.

## إعداد المجدول لتنظيف رموز OTP القديمة

المشروع يحتوي أمراً يومياً يحذف الرموز المستخدمة أو المنتهية القديمة. على Linux أضف Cron واحداً:

```cron
* * * * * cd /path/to/salora_backend_api && php artisan schedule:run >> /dev/null 2>&1
```

يمكن تنفيذ التنظيف يدوياً أيضاً:

```bash
php artisan salora:prune-email-otps
```

## قواعد الأمان المطبقة

- الرمز 6 أرقام ويُولد بواسطة `random_int`.
- مدة الصلاحية 10 دقائق.
- الحد الأقصى 5 محاولات إدخال.
- إعادة الإرسال بعد 60 ثانية.
- الرمز محفوظ كـHash، وليس كنص صريح.
- إصدار رمز جديد يلغي الرمز السابق لنفس البريد والغرض.
- يوجد تقييد طلبات على Routes العامة، إضافة إلى Cooldown لكل بريد.
- استجابة «نسيت كلمة المرور» عامة ولا تكشف إن كان البريد مسجلاً.
- `demo_otp` لا يظهر إلا في بيئة `local/testing` عند تفعيل `OTP_EXPOSE_IN_LOCAL=true` عمداً.
- أخطاء الإرسال تُسجل في Laravel Log من دون تسجيل الرمز نفسه.
- جدول OTP موحد لغرضي `verify_email` و`password_reset`.

## نقاط الاختبار اليدوي

### تسجيل حساب جديد

1. افتح Flutter وسجل اسماً وهاتفاً وبريداً وكلمة مرور قوية.
2. يجب ألا يدخل التطبيق مباشرة.
3. افتح البريد وخذ الرمز.
4. أدخل الرمز في شاشة التوثيق.
5. بعد النجاح يصدر Token ويُفتح حساب العميل.
6. جرّب رمزاً خاطئاً خمس مرات، ورمزاً منتهياً، وإعادة الإرسال قبل وبعد 60 ثانية.

### نسيان كلمة المرور

1. اطلب الرمز من Flutter أو Dashboard.
2. أدخل الرمز وكلمة مرور جديدة قوية.
3. يجب إلغاء الجلسات السابقة.
4. يجب أن ينجح الدخول بالكلمة الجديدة ويفشل بالكلمة القديمة.

## عند عدم وصول الرسالة

تحقق من الآتي بالترتيب:

1. `MAIL_USERNAME` هو البريد الكامل.
2. `MAIL_PASSWORD` هو App Password، وليس كلمة المرور العادية.
3. التحقق بخطوتين مفعّل.
4. شغّل `php artisan optimize:clear` بعد تعديل `.env`.
5. افحص `storage/logs/laravel.log`.
6. افحص Spam/Junk.
7. جرّب `php artisan salora:test-email`.
8. تأكد أن الخادم يسمح باتصال خارجي إلى `smtp.gmail.com:587`.

## الانتقال لاحقاً إلى مزود احترافي

منطق OTP مستقل عن Gmail ويستخدم Laravel Mail؛ لذلك يمكن الانتقال لاحقاً إلى Resend أو Brevo أو Amazon SES بتعديل إعدادات البريد فقط، من دون تغيير Flutter أو Dashboard أو قواعد OTP.
