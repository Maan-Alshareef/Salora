import React, { useEffect, useMemo, useState } from "react";
import { useApp } from "../../context/AppContext";
import { dashboardApi } from "../../services/apiClient";
import { strongPasswordPattern } from "../../utils/passwords";
import { isValidSyrianPhone, normaliseSyrianPhone, syrianPhoneMessage } from "../../utils/syrianPhone";

export default function OwnerProfile() {
  const { userProfile, updateCurrentProfile, updateCurrentAvatar, arabicLabel } = useApp();
  const [form, setForm] = useState({ name: "", email: "", phone: "" });
  const [passwords, setPasswords] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [avatarFile, setAvatarFile] = useState(null);
  const [savingProfile, setSavingProfile] = useState(false);
  const [savingAvatar, setSavingAvatar] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [message, setMessage] = useState("");
  const [newEmail, setNewEmail] = useState("");
  const [emailOtp, setEmailOtp] = useState("");
  const [emailBusy, setEmailBusy] = useState(false);
  const [emailMessage, setEmailMessage] = useState({ text: "", success: false });

  const localPreview = useMemo(() => avatarFile ? URL.createObjectURL(avatarFile) : "", [avatarFile]);
  useEffect(() => () => { if (localPreview) URL.revokeObjectURL(localPreview); }, [localPreview]);

  useEffect(() => {
    setForm({
      name: userProfile?.name || "",
      email: userProfile?.email || "",
      phone: normaliseSyrianPhone(userProfile?.phone || "")
    });
  }, [userProfile]);

  const handleProfileUpdate = async (event) => {
    event.preventDefault();
    setMessage("");
    if (!isValidSyrianPhone(form.phone)) {
      setMessage(syrianPhoneMessage);
      return;
    }
    setSavingProfile(true);
    const saved = await updateCurrentProfile({ name: form.name.trim(), phone: normaliseSyrianPhone(form.phone) });
    setSavingProfile(false);
    if (saved) setMessage("تم حفظ الاسم ورقم الهاتف وتحديثهما في جميع الواجهات.");
  };

  const selectAvatar = (event) => {
    const file = event.target.files?.[0] || null;
    setMessage("");
    if (!file) return setAvatarFile(null);
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
      setMessage("الصورة يجب أن تكون JPG أو PNG أو WebP.");
      event.target.value = "";
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      setMessage("حجم الصورة يجب ألا يتجاوز 2 MB.");
      event.target.value = "";
      return;
    }
    setAvatarFile(file);
  };

  const saveAvatar = async () => {
    if (!avatarFile) return;
    setSavingAvatar(true);
    setMessage("");
    const saved = await updateCurrentAvatar(avatarFile);
    setSavingAvatar(false);
    if (saved) {
      setAvatarFile(null);
      setMessage("تم رفع الصورة الشخصية، وستظهر النسخة الجديدة في الملف والقوائم وشريط الحساب.");
    }
  };

  const handlePasswordUpdate = async (event) => {
    event.preventDefault();
    setMessage("");
    if (!strongPasswordPattern.test(passwords.password)) {
      setMessage("كلمة المرور يجب أن تكون 8 أحرف على الأقل وتحتوي أحرفاً كبيرة وصغيرة ورقماً ورمزاً.");
      return;
    }
    if (passwords.password !== passwords.password_confirmation) {
      setMessage("تأكيد كلمة المرور غير مطابق.");
      return;
    }
    setSavingPassword(true);
    try {
      await dashboardApi.auth.changePassword(passwords);
      setPasswords({ current_password: "", password: "", password_confirmation: "" });
      setMessage("تم تغيير كلمة المرور بنجاح.");
    } catch (error) {
      setMessage(error?.message || "تعذر تغيير كلمة المرور.");
    } finally {
      setSavingPassword(false);
    }
  };

  const updateStoredEmail = (email) => {
    try {
      const raw = localStorage.getItem("salora_user");
      if (!raw) return;
      const stored = JSON.parse(raw);
      localStorage.setItem("salora_user", JSON.stringify({ ...stored, email }));
    } catch (_) {
      // إعادة تحميل الصفحة ستجلب البيانات الصحيحة من الخادم.
    }
  };

  const requestEmailChange = async () => {
    const email = newEmail.trim().toLowerCase();
    setEmailMessage({ text: "", success: false });
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setEmailMessage({ text: "أدخل بريداً إلكترونياً جديداً صحيحاً.", success: false });
      return;
    }
    if (email === String(userProfile?.email || "").trim().toLowerCase()) {
      setEmailMessage({ text: "البريد الجديد مطابق للبريد الحالي.", success: false });
      return;
    }

    setEmailBusy(true);
    try {
      const response = await dashboardApi.auth.requestEmailChange(email);
      const destination = response?.masked_email || email;
      setEmailMessage({
        text: response?.message || `تم إرسال رمز OTP إلى ${destination}. تحقق من صندوق البريد، وصلاحيته 10 دقائق.`,
        success: true
      });
    } catch (error) {
      setEmailMessage({
        text: error?.message || "تعذر إرسال رمز تغيير البريد. تحقق من إعدادات البريد في الخادم.",
        success: false
      });
    } finally {
      setEmailBusy(false);
    }
  };

  const verifyEmailChange = async () => {
    const email = newEmail.trim().toLowerCase();
    const otp = emailOtp.trim();
    setEmailMessage({ text: "", success: false });

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setEmailMessage({ text: "أدخل البريد الجديد نفسه الذي أرسلت إليه الرمز.", success: false });
      return;
    }
    if (!/^\d{6}$/.test(otp)) {
      setEmailMessage({ text: "رمز OTP يجب أن يتكون من 6 أرقام.", success: false });
      return;
    }

    setEmailBusy(true);
    try {
      const response = await dashboardApi.auth.verifyEmailChange(email, otp);
      const confirmedEmail = response?.user?.email || response?.email || email;
      setForm((current) => ({ ...current, email: confirmedEmail }));
      updateStoredEmail(confirmedEmail);
      setNewEmail("");
      setEmailOtp("");
      setEmailMessage({
        text: response?.message || "تم تغيير البريد الإلكتروني وتأكيده بنجاح.",
        success: true
      });
      window.setTimeout(() => window.location.reload(), 1800);
    } catch (error) {
      setEmailMessage({
        text: error?.message || "تعذر تأكيد البريد الجديد.",
        success: false
      });
    } finally {
      setEmailBusy(false);
    }
  };

  return (
    <div className="max-w-2xl space-y-6 pb-12 font-sans text-white" dir="rtl">
      <div>
        <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">👤 الملف الشخصي</h1>
        <p className="mt-2 text-sm text-slate-400">البيانات الموحدة: الصورة، الاسم، البريد، الهاتف والدور. تغيير البريد يتم بواسطة رمز OTP يصل إلى البريد الجديد.</p>
      </div>

      {message && <div className="rounded-2xl border border-amber-400/20 bg-amber-500/10 p-4 text-sm leading-7 text-amber-100">{message}</div>}

      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6 shadow-2xl sm:p-8">
        <div className="mb-7 flex flex-col items-center gap-5 sm:flex-row sm:items-start">
          <div className="h-28 w-28 overflow-hidden rounded-3xl border border-amber-400/30 bg-slate-900 shadow-xl">
            {(localPreview || userProfile?.avatarUrl) ? <img src={localPreview || userProfile.avatarUrl} alt="الصورة الشخصية" className="h-full w-full object-cover" /> : <div className="grid h-full w-full place-items-center text-4xl font-black text-amber-300">{userProfile?.name?.[0] || "S"}</div>}
          </div>
          <div className="flex-1 text-center sm:text-right">
            <div className="text-xl font-black">{userProfile?.name || "المستخدم"}</div>
            <div className="mt-1 text-sm text-slate-400">{arabicLabel(userProfile?.role || "")}</div>
            <div className="mt-4 flex flex-wrap justify-center gap-2 sm:justify-start">
              <label className="cursor-pointer rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-xs font-bold text-slate-200 hover:bg-white/10">
                اختيار صورة
                <input type="file" accept="image/jpeg,image/png,image/webp" onChange={selectAvatar} className="hidden" />
              </label>
              {userProfile?.avatarUrl && <button type="button" onClick={async()=>{if(!confirm("حذف الصورة الشخصية؟"))return;try{await dashboardApi.auth.deleteAvatar();window.location.reload();}catch(e){setMessage(e.message)}}} className="rounded-xl bg-red-500/10 px-4 py-2 text-xs font-bold text-red-300">حذف الصورة</button>}
              {avatarFile && <button type="button" onClick={saveAvatar} disabled={savingAvatar} className="rounded-xl bg-amber-500 px-4 py-2 text-xs font-black text-slate-950 disabled:opacity-50">{savingAvatar ? "جارٍ الرفع..." : "رفع الصورة"}</button>}
            </div>
            <p className="mt-3 text-xs text-slate-500">JPG أو PNG أو WebP، بحد أقصى 2 MB.</p>
          </div>
        </div>

        <form onSubmit={handleProfileUpdate} className="space-y-5 text-sm">
          <div><label className="mb-1.5 block font-bold text-slate-400">الاسم الكامل</label><input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="field-surface w-full" required maxLength={120} /></div>
          <div><label className="mb-1.5 block font-bold text-slate-400">البريد الإلكتروني</label><input type="email" value={form.email} className="field-surface ltr w-full opacity-70" readOnly /></div>
          <div><label className="mb-1.5 block font-bold text-slate-400">رقم الهاتف</label><input type="text" inputMode="numeric" value={form.phone} onChange={(e) => setForm({ ...form, phone: normaliseSyrianPhone(e.target.value) })} className="field-surface ltr w-full" required maxLength={10} placeholder="10 أرقام" /></div>
          <div><label className="mb-1.5 block font-bold text-slate-400">الدور</label><input type="text" value={arabicLabel(userProfile?.role || "")} className="field-surface w-full opacity-70" readOnly /></div>
          <button type="submit" disabled={savingProfile} className="w-full rounded-xl bg-amber-500 py-3 font-black text-slate-950 hover:bg-amber-400 disabled:opacity-50">{savingProfile ? "جارٍ الحفظ..." : "حفظ بيانات الملف"}</button>
        </form>
      </div>

      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6 shadow-2xl sm:p-8">
        <h2 className="mb-2 text-xl font-black">تغيير البريد الإلكتروني</h2>
        <p className="mb-4 text-sm text-slate-400">يرسل النظام رمز OTP إلى البريد الجديد، ثم ينبه البريد القديم بعد نجاح التغيير.</p>
        <div className="grid gap-3 sm:grid-cols-2">
          <input className="field-surface ltr" type="email" placeholder="البريد الجديد" value={newEmail} onChange={(event)=>setNewEmail(event.target.value)} />
          <button type="button" disabled={emailBusy} onClick={requestEmailChange} className="rounded-xl bg-blue-600 py-3 font-bold disabled:opacity-50">{emailBusy ? "جارٍ الإرسال..." : "إرسال الرمز"}</button>
          <input className="field-surface ltr" inputMode="numeric" maxLength={6} placeholder="رمز OTP من 6 أرقام" value={emailOtp} onChange={(event)=>setEmailOtp(event.target.value.replace(/\D/g, "").slice(0, 6))} />
          <button type="button" disabled={emailBusy} onClick={verifyEmailChange} className="rounded-xl bg-emerald-600 py-3 font-bold disabled:opacity-50">{emailBusy ? "جارٍ التحقق..." : "تأكيد البريد"}</button>
        </div>
        {emailMessage.text && (
          <div className={`mt-4 rounded-2xl border p-4 text-sm font-bold leading-7 ${emailMessage.success ? "border-emerald-400/30 bg-emerald-500/10 text-emerald-200" : "border-red-400/30 bg-red-500/10 text-red-200"}`}>
            {emailMessage.text}
          </div>
        )}
      </div>

      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6 shadow-2xl sm:p-8">
        <h2 className="mb-5 text-xl font-black">تغيير كلمة المرور</h2>
        <form onSubmit={handlePasswordUpdate} className="space-y-5 text-sm">
          <div><label className="mb-1.5 block font-bold text-slate-400">كلمة المرور الحالية</label><input type="password" value={passwords.current_password} onChange={(e) => setPasswords({ ...passwords, current_password: e.target.value })} className="field-surface ltr w-full" required autoComplete="current-password" /></div>
          <div><label className="mb-1.5 block font-bold text-slate-400">كلمة المرور الجديدة</label><input type="password" value={passwords.password} onChange={(e) => setPasswords({ ...passwords, password: e.target.value })} className="field-surface ltr w-full" required autoComplete="new-password" /></div>
          <div><label className="mb-1.5 block font-bold text-slate-400">تأكيد كلمة المرور</label><input type="password" value={passwords.password_confirmation} onChange={(e) => setPasswords({ ...passwords, password_confirmation: e.target.value })} className="field-surface ltr w-full" required autoComplete="new-password" /></div>
          <button type="submit" disabled={savingPassword} className="w-full rounded-xl border border-amber-400/30 bg-amber-500/10 py-3 font-black text-amber-200 hover:bg-amber-500/20 disabled:opacity-50">{savingPassword ? "جارٍ التغيير..." : "تغيير كلمة المرور"}</button>
        </form>
      </div>
    </div>
  );
}