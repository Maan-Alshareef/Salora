import React, { useEffect, useState } from "react";
import { isValidSyrianPhone, normaliseSyrianPhone, syrianPhoneMessage } from "../../utils/syrianPhone";
import { useApp } from "../../context/AppContext";
import { dashboardApi } from "../../services/apiClient";

const strongPassword = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

export default function OwnerProfile() {
  const { userProfile, updateCurrentProfile } = useApp();
  const [form, setForm] = useState({ name: "", email: "", phone: "" });
  const [passwords, setPasswords] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [savingProfile, setSavingProfile] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [message, setMessage] = useState("");

  useEffect(() => {
    setForm({
      name: userProfile?.name || "",
      email: userProfile?.email || "",
      phone: userProfile?.phone || ""
    });
  }, [userProfile]);

  const handleProfileUpdate = async (event) => {
    event.preventDefault();
    setMessage("");
    if (!isValidSyrianPhone(form.phone)) return window.alert(syrianPhoneMessage);
    setSavingProfile(true);
    const saved = await updateCurrentProfile({ name: form.name.trim(), phone: normaliseSyrianPhone(form.phone) });
    setSavingProfile(false);
    if (saved) setMessage("تم حفظ بيانات الملف الشخصي في الخادم.");
  };

  const handlePasswordUpdate = async (event) => {
    event.preventDefault();
    setMessage("");
    if (!strongPassword.test(passwords.password)) {
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

  return (
    <div className="max-w-xl space-y-6 pb-12 font-sans text-white" dir="rtl">
      <div>
        <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">👤 الملف الشخصي</h1>
        <p className="mt-2 text-sm text-slate-400">تُحفظ التغييرات مباشرة في قاعدة البيانات. البريد الإلكتروني ثابت لتجنب تعارض هوية الحساب.</p>
      </div>

      {message && <div className="rounded-2xl border border-amber-400/20 bg-amber-500/10 p-4 text-sm text-amber-100">{message}</div>}

      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-8 shadow-2xl">
        <form onSubmit={handleProfileUpdate} className="space-y-5 text-sm">
          <div><label className="mb-1.5 block font-bold text-slate-400">الاسم الكامل</label><input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="field-surface w-full" required maxLength={120} /></div>
          <div><label className="mb-1.5 block font-bold text-slate-400">البريد الإلكتروني</label><input type="email" value={form.email} className="field-surface w-full opacity-70" readOnly /></div>
          <div><label className="mb-1.5 block font-bold text-slate-400">رقم الهاتف</label><input type="text" value={form.phone} onChange={(e) => setForm({ ...form, phone: normaliseSyrianPhone(e.target.value) })} className="field-surface w-full" required maxLength={10} inputMode="numeric" pattern="09[0-9]{8}" title={syrianPhoneMessage} /></div>
          <button type="submit" disabled={savingProfile} className="w-full rounded-xl bg-amber-500 py-3 font-black text-slate-950 hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50">{savingProfile ? "جارٍ الحفظ..." : "حفظ بيانات الملف"}</button>
        </form>
      </div>

      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-8 shadow-2xl">
        <h2 className="mb-5 text-xl font-black">تغيير كلمة المرور</h2>
        <form onSubmit={handlePasswordUpdate} className="space-y-5 text-sm">
          <div><label className="mb-1.5 block font-bold text-slate-400">كلمة المرور الحالية</label><input type="password" value={passwords.current_password} onChange={(e) => setPasswords({ ...passwords, current_password: e.target.value })} className="field-surface w-full" required autoComplete="current-password" /></div>
          <div><label className="mb-1.5 block font-bold text-slate-400">كلمة المرور الجديدة</label><input type="password" value={passwords.password} onChange={(e) => setPasswords({ ...passwords, password: e.target.value })} className="field-surface w-full" required autoComplete="new-password" /></div>
          <div><label className="mb-1.5 block font-bold text-slate-400">تأكيد كلمة المرور</label><input type="password" value={passwords.password_confirmation} onChange={(e) => setPasswords({ ...passwords, password_confirmation: e.target.value })} className="field-surface w-full" required autoComplete="new-password" /></div>
          <button type="submit" disabled={savingPassword} className="w-full rounded-xl border border-amber-400/30 bg-amber-500/10 py-3 font-black text-amber-200 hover:bg-amber-500/20 disabled:cursor-not-allowed disabled:opacity-50">{savingPassword ? "جارٍ التغيير..." : "تغيير كلمة المرور"}</button>
        </form>
      </div>
    </div>
  );
}
