# Salora React Dashboard

لوحة إدارة ومالك صالة مبنية باستخدام React وVite.

## التشغيل

```bash
cp .env.example .env
npm ci
npm run dev
```

القيمة الافتراضية للـ API:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

## Production build

```bash
npm run build
```

تُكتب الملفات الناتجة داخل `dist/`. لا تستخدم اللوحة Mock Data عند فشل الـ Backend؛ تعرض Loading/Empty/Error states ولا تعتبر العملية ناجحة إلا بعد نجاح API.
