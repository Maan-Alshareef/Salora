<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Salora</title></head>
<body style="margin:0;background:#f1f5f9;font-family:Tahoma,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:30px 12px;background:#f1f5f9;"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #e2e8f0;">
<tr><td style="padding:26px 28px;background:#0f172a;color:#fff;text-align:center;"><div style="font-size:28px;font-weight:800;">✨ Salora</div></td></tr>
<tr><td style="padding:30px 28px;line-height:1.9;"><h1 style="font-size:22px;margin:0 0 12px;">مرحباً {{ $name }}</h1>
<p>تمت مراجعة طلب الانضمام كـ{{ $requestType === 'provider' ? 'مقدم خدمة' : 'مدير صالة' }}، ولم تتم الموافقة عليه حالياً.</p>
<div style="padding:16px;border-radius:14px;background:#fff7ed;border:1px solid #fed7aa;"><strong>سبب الرفض:</strong><br>{{ $reason }}</div>
<p style="color:#475569;">يمكنك تصحيح المعلومات وإرسال طلب جديد من التطبيق.</p></td></tr>
</table></td></tr></table>
</body></html>
