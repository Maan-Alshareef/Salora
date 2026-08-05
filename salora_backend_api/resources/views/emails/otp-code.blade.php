<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Salora OTP</title>
</head>
<body style="margin:0;background:#f1f5f9;font-family:Tahoma,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:30px 12px;background:#f1f5f9;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e2e8f0;">
                <tr>
                    <td style="padding:26px 28px;background:#0f172a;color:#ffffff;text-align:center;">
                        <div style="font-size:28px;font-weight:800;">✨ Salora</div>
                        <div style="margin-top:6px;font-size:14px;color:#cbd5e1;">منصة إدارة وحجز المناسبات</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px 28px;text-align:right;line-height:1.8;">
                        <h1 style="font-size:22px;margin:0 0 10px;">
                            {{ $purpose === \App\Models\EmailOtp::PURPOSE_VERIFY_EMAIL ? 'توثيق البريد الإلكتروني' : ($purpose === \App\Models\EmailOtp::PURPOSE_JOIN_REQUEST ? 'توثيق بريد طلب الانضمام' : 'استعادة كلمة المرور') }}
                        </h1>
                        <p style="margin:0 0 18px;color:#475569;">
                            استخدم الرمز التالي لإكمال العملية. لا تشاركه مع أي شخص.
                        </p>
                        <div style="direction:ltr;text-align:center;letter-spacing:10px;font-size:34px;font-weight:900;padding:18px 12px;border-radius:14px;background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;">
                            {{ $code }}
                        </div>
                        <p style="margin:18px 0 0;color:#64748b;font-size:13px;">
                            صلاحية الرمز {{ $expiresInMinutes }} دقائق. إذا لم تطلب هذه العملية، تجاهل الرسالة.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 28px;background:#f8fafc;color:#64748b;font-size:12px;text-align:center;border-top:1px solid #e2e8f0;">
                        هذه رسالة آلية من Salora، يرجى عدم الرد عليها.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
