# Salora Flutter App — University Edition

تطبيق العميل ومقدم الخدمة. تم ربط الوظائف الأساسية بالـ Laravel API وإزالة البيانات الوهمية من المسارات الأساسية.

## التشغيل على Android Emulator

```bash
flutter pub get
flutter run --dart-define=SALORA_API_URL=http://10.0.2.2:8000/api
```

## التشغيل على جهاز حقيقي

استبدل `192.168.1.10` بعنوان الجهاز الذي يشغّل Laravel داخل الشبكة المحلية:

```bash
flutter run --dart-define=SALORA_API_URL=http://192.168.1.10:8000/api
```

في Production استخدم HTTPS:

```bash
flutter build apk --release \
  --dart-define=SALORA_API_URL=https://api.example.com/api
```

## فحوصات مطلوبة محلياً

```bash
flutter analyze
flutter test
```

## ملاحظات

- Token محفوظ عبر `flutter_secure_storage`.
- Events، Todo Lists، Bookings، Reviews، Complaints وNotifications تُحمّل من الخادم.
- `applicationId` هو `com.salora.university`.
- بناء Release موقّع يتطلب ملف `android/key.properties` وKeystore خاصاً بك؛ لا تضعهما في Git.
