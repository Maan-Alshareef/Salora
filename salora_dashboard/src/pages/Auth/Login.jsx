import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import Button from "../../components/Button";
import { useApp } from "../../context/AppContext";
import { ROLES } from "../../config/permissions";
import { clearStoredSession, dashboardApi } from "../../services/apiClient";
import { strongPasswordPattern } from "../../utils/passwords";

const accounts = {
  [ROLES.ADMIN]: {
    title: "حساب مدير النظام",
    subtitle: "إدارة المنصة والموافقات والحسابات والمدفوعات."
  },
  [ROLES.OWNER]: {
    title: "حساب مالك صالة",
    subtitle: "إدارة الصالات والحجوزات والخدمات والتقييمات."
  }
};


export default function Login() {
  const navigate = useNavigate();
  const { switchRole, currentRole, currentUser, authLoading } = useApp();
  const [role, setRole] = useState(ROLES.ADMIN);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [loading, setLoading] = useState(false);
  const [mode, setMode] = useState("login");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [otp, setOtp] = useState("");
  const [resendAfter, setResendAfter] = useState(0);

  useEffect(() => {
    if (!authLoading && currentUser?.id && currentRole) {
      navigate(currentUser.mustChangePassword ? "/auth/change-required-password" : (currentRole === ROLES.ADMIN ? "/admin" : "/owner"), { replace: true });
    }
  }, [authLoading, currentRole, currentUser?.id, currentUser?.mustChangePassword, navigate]);

  useEffect(() => {
    if (resendAfter <= 0) return undefined;
    const timer = window.setInterval(() => {
      setResendAfter((value) => Math.max(0, value - 1));
    }, 1000);
    return () => window.clearInterval(timer);
  }, [resendAfter > 0]);

  const chooseRole = (nextRole) => {
    setRole(nextRole);
    setError("");
    setNotice("");
  };

  const finishLogin = (apiRole, user) => {
    switchRole(apiRole, user);
    navigate(apiRole === ROLES.ADMIN ? "/admin" : "/owner", { replace: true });
  };

  const handleSignIn = async () => {
    if (!email.trim() || !password) {
      setError("اكتب البريد وكلمة المرور.");
      return;
    }

    setLoading(true);
    setError("");
    setNotice("");
    try {
      const data = await dashboardApi.auth.login({ email: email.trim(), password });
      localStorage.setItem("salora_token", data.token);
      const apiRole = data.user?.role === "admin"
        ? ROLES.ADMIN
        : data.user?.role === "owner"
          ? ROLES.OWNER
          : null;

      if (!apiRole) {
        clearStoredSession();
        setError("لوحة الويب مخصصة لمدير النظام ومالك الصالة. حساب مقدم الخدمة يعمل من تطبيق الموبايل.");
        return;
      }
      if (apiRole !== role) {
        clearStoredSession();
        setError("الدور الفعلي للحساب لا يطابق نوع الدخول المحدد.");
        return;
      }
      if (data.user?.must_change_password) {
        switchRole(apiRole, data.user);
        navigate("/auth/change-required-password", { replace: true });
        return;
      }
      finishLogin(apiRole, data.user);
    } catch (err) {
      setError(err.message || "فشل تسجيل الدخول.");
    } finally {
      setLoading(false);
    }
  };

  const requestReset = async () => {
    if (!email.trim()) {
      setError("اكتب البريد الإلكتروني أولاً.");
      return;
    }
    setLoading(true);
    setError("");
    try {
      const result = await dashboardApi.auth.forgotPassword(email.trim());
      setMode("reset-password");
      setResendAfter(Number(result?.resend_after_seconds || 60));
      setNotice(result?.demo_otp
        ? `رمز بيئة التطوير: ${result.demo_otp}`
        : "إذا كان الحساب موجوداً، فقد تم إرسال رمز الاستعادة إلى بريده الإلكتروني.");
    } catch (err) {
      setError(err.message || "تعذر إنشاء رمز الاستعادة.");
    } finally {
      setLoading(false);
    }
  };

  const resetPassword = async () => {
    if (!/^\d{6}$/.test(otp)) {
      setError("رمز OTP يجب أن يتكون من 6 أرقام.");
      return;
    }
    if (!strongPasswordPattern.test(newPassword) || newPassword !== confirmPassword) {
      setError("تحقق من قوة كلمة المرور ومن تطابق التأكيد.");
      return;
    }

    setLoading(true);
    setError("");
    try {
      await dashboardApi.auth.resetPassword({
        email: email.trim(),
        otp,
        password: newPassword,
        password_confirmation: confirmPassword
      });
      setMode("login");
      setOtp("");
      setNewPassword("");
      setConfirmPassword("");
      setNotice("تم تغيير كلمة المرور. يمكنك تسجيل الدخول الآن.");
    } catch (err) {
      setError(err.message || "تعذر إعادة تعيين كلمة المرور.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 relative overflow-hidden font-sans" dir="rtl">
      <div className="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-blue-500/10 blur-[120px] rounded-full" />
      <div className="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-orange-500/10 blur-[120px] rounded-full" />

      <div className="hidden md:flex w-1/2 items-center justify-center text-white relative overflow-hidden border-l border-white/5">
        <div className="absolute inset-0 bg-indigo-950/40 backdrop-blur-sm" />
        <div className="relative z-10 text-center p-10 max-w-lg">
          <div className="mx-auto mb-6 grid h-20 w-20 place-items-center rounded-3xl border border-blue-400/20 bg-blue-500/10 text-4xl">✨</div>
          <h2 className="text-5xl font-extrabold mb-4 text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-300">لوحة Salora</h2>
          <p className="text-gray-300 opacity-90 mb-8 text-sm leading-relaxed">لوحة إدارة مرتبطة مباشرة بواجهة Laravel API، ولا تعرض بيانات تجريبية عند فشل الخادم.</p>
          <div className="space-y-3 text-sm text-gray-300 max-w-sm mx-auto text-right">
            <div>✓ التحقق من الجلسة والدور قبل فتح الصفحات</div>
            <div>✓ حفظ العمليات في قاعدة البيانات قبل تحديث الواجهة</div>
            <div>✓ تغيير كلمة المرور الإجباري للحسابات الجديدة</div>
          </div>
        </div>
      </div>

      <div className="flex-1 flex items-center justify-center p-8 z-10">
        <div className="w-full max-w-md bg-white/5 backdrop-blur-xl border border-white/10 p-8 rounded-3xl shadow-2xl">
          <h3 className="text-2xl font-bold mb-1 text-white text-center">الدخول إلى Salora</h3>
          <p className="text-xs text-gray-400 text-center mb-6">استخدم حساباً موجوداً في قاعدة البيانات.</p>

          {mode === "login" && (
            <div className="grid grid-cols-2 gap-2 p-1 bg-slate-950/60 border border-white/5 rounded-xl mb-6">
              <button type="button" onClick={() => chooseRole(ROLES.ADMIN)} className={`py-2.5 rounded-lg text-xs font-semibold ${role === ROLES.ADMIN ? "bg-blue-500/20 text-blue-300 border border-blue-500/30" : "text-gray-400 hover:text-white hover:bg-white/5"}`}>🛡️ مدير النظام</button>
              <button type="button" onClick={() => chooseRole(ROLES.OWNER)} className={`py-2.5 rounded-lg text-xs font-semibold ${role === ROLES.OWNER ? "bg-orange-500/20 text-orange-300 border border-orange-500/30" : "text-gray-400 hover:text-white hover:bg-white/5"}`}>🏛️ مالك صالة</button>
            </div>
          )}

          {mode === "login" && <div className="mb-4 rounded-2xl border border-white/10 bg-white/[.03] p-4"><div className="text-sm font-black text-white">{accounts[role].title}</div><div className="mt-1 text-xs text-slate-400">{accounts[role].subtitle}</div></div>}

          <div className="space-y-4">
            {(
              <div>
                <label className="block text-xs font-medium text-blue-300 mb-1.5">البريد الإلكتروني</label>
                <input className="field-surface ltr" type="email" value={email} onChange={(event) => setEmail(event.target.value)} autoComplete="email" />
              </div>
            )}

            {mode === "login" && (
              <>
                <div>
                  <label className="block text-xs font-medium text-blue-300 mb-1.5">كلمة المرور</label>
                  <input className="field-surface ltr" type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="current-password" />
                </div>
                <button type="button" onClick={requestReset} className="text-xs font-bold text-blue-300 hover:text-blue-200">نسيت كلمة المرور؟</button>
              </>
            )}

            {mode === "reset-password" && (
              <div>
                <label className="block text-xs font-medium text-blue-300 mb-1.5">رمز OTP</label>
                <input className="field-surface ltr" inputMode="numeric" maxLength={6} value={otp} onChange={(event) => setOtp(event.target.value.replace(/\D/g, ""))} />
              </div>
            )}

            {mode === "reset-password" && (
              <>
                <input className="field-surface ltr" type="password" value={newPassword} onChange={(event) => setNewPassword(event.target.value)} placeholder="كلمة المرور الجديدة" autoComplete="new-password" />
                <input className="field-surface ltr" type="password" value={confirmPassword} onChange={(event) => setConfirmPassword(event.target.value)} placeholder="تأكيد كلمة المرور" autoComplete="new-password" />
              </>
            )}

            {notice && <div className="text-xs text-emerald-200 bg-emerald-500/10 border border-emerald-500/20 py-2 px-3 rounded-lg">{notice}</div>}
            {error && <div className="text-xs text-red-300 bg-red-500/10 border border-red-500/20 py-2 px-3 rounded-lg">{error}</div>}

            {mode === "login" && <Button type="button" onClick={handleSignIn} disabled={loading} variant="primary" className="w-full justify-center py-3 bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-black rounded-xl text-sm">{loading ? "جاري الدخول..." : "تسجيل الدخول"}</Button>}
            {mode === "reset-password" && <button type="button" disabled={loading} onClick={resetPassword} className="w-full rounded-xl bg-blue-500 py-3 text-sm font-black text-white disabled:opacity-50">{loading ? "جاري الحفظ..." : "حفظ كلمة المرور الجديدة"}</button>}
            {mode === "reset-password" && (
              <button type="button" disabled={loading || resendAfter > 0} onClick={requestReset} className="w-full text-xs font-bold text-blue-300 disabled:text-slate-600">
                {resendAfter > 0 ? `إعادة إرسال الرمز بعد ${resendAfter} ثانية` : "إعادة إرسال رمز جديد"}
              </button>
            )}

            {mode !== "login" && <button type="button" onClick={() => { setMode("login"); setError(""); }} className="w-full text-xs font-bold text-slate-400 hover:text-white">العودة إلى تسجيل الدخول</button>}
          </div>
        </div>
      </div>
    </div>
  );
}
