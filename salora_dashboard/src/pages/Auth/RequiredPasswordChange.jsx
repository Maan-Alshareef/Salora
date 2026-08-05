import React, { useState } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { useApp } from "../../context/AppContext";
import { ROLES } from "../../config/permissions";
import { dashboardApi } from "../../services/apiClient";
import { strongPasswordPattern } from "../../utils/passwords";

export default function RequiredPasswordChange() {
  const navigate = useNavigate();
  const { currentUser, currentRole, authLoading, refreshCurrentUser, logout } = useApp();
  const [form, setForm] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);

  if (authLoading) {
    return <div className="min-h-screen grid place-items-center bg-slate-950 text-white">جاري التحقق من الجلسة...</div>;
  }
  if (!localStorage.getItem("salora_token") || !currentUser?.id) {
    return <Navigate to="/auth/login" replace />;
  }
  if (!currentUser.mustChangePassword) {
    return <Navigate to={currentRole === ROLES.ADMIN ? "/admin" : "/owner"} replace />;
  }

  const submit = async (event) => {
    event.preventDefault();
    setMessage("");
    if (!strongPasswordPattern.test(form.password)) {
      setMessage("كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل وتحتوي حرفاً كبيراً وصغيراً ورقماً ورمزاً.");
      return;
    }
    if (form.password !== form.password_confirmation) {
      setMessage("تأكيد كلمة المرور غير مطابق.");
      return;
    }

    setLoading(true);
    try {
      await dashboardApi.auth.changePassword(form);
      const updated = await refreshCurrentUser();
      if (updated) navigate(currentRole === ROLES.ADMIN ? "/admin" : "/owner", { replace: true });
    } catch (error) {
      setMessage(error?.message || "تعذر تغيير كلمة المرور.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen grid place-items-center bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 p-5 text-white" dir="rtl">
      <div className="w-full max-w-lg rounded-3xl border border-amber-400/20 bg-white/[.05] p-7 shadow-2xl backdrop-blur-xl">
        <div className="mb-6 text-center">
          <div className="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-2xl bg-amber-500/15 text-3xl">🔐</div>
          <h1 className="text-2xl font-black">تغيير كلمة المرور المؤقتة</h1>
          <p className="mt-2 text-sm leading-7 text-slate-400">هذه الخطوة إلزامية قبل فتح لوحة العمل، وتبقى فعالة حتى بعد تحديث الصفحة أو إعادة فتح المتصفح.</p>
        </div>

        {message && <div className="mb-4 rounded-2xl border border-red-400/20 bg-red-500/10 p-4 text-sm text-red-100">{message}</div>}

        <form onSubmit={submit} className="space-y-4">
          <input className="field-surface ltr w-full" type="password" value={form.current_password} onChange={(e) => setForm({ ...form, current_password: e.target.value })} placeholder="كلمة المرور المؤقتة الحالية" autoComplete="current-password" required />
          <input className="field-surface ltr w-full" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} placeholder="كلمة المرور الجديدة" autoComplete="new-password" required />
          <input className="field-surface ltr w-full" type="password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} placeholder="تأكيد كلمة المرور الجديدة" autoComplete="new-password" required />
          <button disabled={loading} className="w-full rounded-xl bg-amber-500 py-3 font-black text-slate-950 hover:bg-amber-400 disabled:opacity-50">{loading ? "جارٍ التغيير..." : "حفظ ومتابعة"}</button>
        </form>

        <button type="button" onClick={logout} className="mt-4 w-full rounded-xl border border-white/10 py-3 text-sm font-bold text-slate-300 hover:bg-white/5">تسجيل الخروج</button>
      </div>
    </div>
  );
}
