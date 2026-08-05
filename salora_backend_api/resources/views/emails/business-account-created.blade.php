<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Salora</title></head>
<body style="margin:0;background:#f1f5f9;font-family:Tahoma,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:30px 12px;background:#f1f5f9;"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #e2e8f0;">
<tr><td style="padding:26px 28px;background:#0f172a;color:#fff;text-align:center;"><div style="font-size:28px;font-weight:800;">✨ Salora</div><div style="margin-top:6px;color:#cbd5e1;">تم قبول طلب الانضمام</div></td></tr>
<tr><td style="padding:30px 28px;line-height:1.9;">
<h1 style="font-size:22px;margin:0 0 12px;">مرحباً {{ $name }}</h1>
<p style="color:#475569;">وافق مدير النظام على طلبك وتم إنشاء حساب مستقل بدور <strong>{{ $role === 'provider' ? 'مقدم خدمة' : 'مدير صالة' }}</strong>.</p>
<div style="padding:16px;border-radius:14px;background:#f8fafc;border:1px solid #cbd5e1;direction:ltr;text-align:left;">
<div><strong>Email:</strong> {{ $email }}</div>
<div style="margin-top:8px;"><strong>Temporary password:</strong> {{ $temporaryPassword }}</div>
</div>
<p style="color:#b45309;font-weight:bold;">يجب تغيير كلمة المرور فور أول تسجيل دخول. لا تشارك كلمة المرور المؤقتة مع أي شخص.</p>
<p style="color:#475569;">حساب العميل السابق، إن وجد، يبقى منفصلاً ولا يتغير.</p>
</td></tr>
<tr><td style="padding:18px 28px;background:#f8fafc;color:#64748b;font-size:12px;text-align:center;border-top:1px solid #e2e8f0;">هذه رسالة آلية من Salora.</td></tr>
</table></td></tr></table>
</body></html>
