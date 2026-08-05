import React, { useMemo, useState } from "react";
import { useApp } from "../context/AppContext";
import { generateTemporaryPassword, strongPasswordPattern } from "../utils/passwords";

const statusStyle = {
  Pending: "border-amber-400/20 bg-amber-500/15 text-amber-300",
  Approved: "border-emerald-400/20 bg-emerald-500/15 text-emerald-300",
  Rejected: "border-red-400/20 bg-red-500/15 text-red-300"
};
const typeLabel = (type) => ({
  "Owner Request": "طلب انضمام مالك",
  "Provider Request": "طلب انضمام مقدم خدمة",
  "Venue Add": "إضافة صالة",
  "Service Add": "إضافة خدمة",
  "Offer": "عرض أو خصم",
  "Payment Proof": "إثبات دفع"
}[type] || type);

const roleLabel = (type) => type === "Provider Request" ? "مقدم خدمة" : "مالك صالة";

export default function ApprovalsCenter() {
  const { approvals, resolveApproval, venues, setGlobalViewVenue, arabicLabel } = useApp();
  const [type, setType] = useState("All");
  const [query, setQuery] = useState("");
  const [accountRequest, setAccountRequest] = useState(null);
  const [tempPassword, setTempPassword] = useState(() => generateTemporaryPassword());
  const [isCreatingAccount, setIsCreatingAccount] = useState(false);
  const types = ["All", ...new Set(approvals.map((a) => a.type))];
  const filtered = useMemo(() => approvals.filter((item) => {
    const text = [item.title, item.requester, item.type, item.details, item.email, item.phone].join(" ").toLowerCase();
    return (type === "All" || item.type === type) && text.includes(query.toLowerCase());
  }), [approvals, type, query]);

  const viewVenue = (item) => {
    const venue = venues.find((v) => String(v.id) === String(item.venueId));
    if (venue) setGlobalViewVenue(venue);
    else alert("لم يتم العثور على بيانات الصالة.");
  };

  const openAccountModal = (item) => {
    if (!item.email) {
      alert("لا يمكن إنشاء الحساب لأن صاحب الطلب لم يكتب بريدًا إلكترونيًا تجاريًا.");
      return;
    }
    if (!item.emailVerified) {
      alert("لا يمكن إنشاء الحساب قبل توثيق البريد التجاري بواسطة OTP.");
      return;
    }
    setAccountRequest(item);
    setTempPassword(generateTemporaryPassword());
  };

  const copyTemporaryPassword = async () => {
    try {
      await navigator.clipboard.writeText(tempPassword);
      alert("تم نسخ كلمة المرور المؤقتة.");
    } catch (_) {
      alert("تعذر النسخ التلقائي. انسخ كلمة المرور يدوياً.");
    }
  };

  const createAccountFromRequest = async () => {
    if (!accountRequest) return;
    if (!strongPasswordPattern.test(tempPassword)) {
      alert("كلمة المرور المؤقتة يجب أن تكون 8 أحرف على الأقل وتحوي حرفاً كبيراً وصغيراً ورقماً ورمزاً.");
      return;
    }
    setIsCreatingAccount(true);
    try {
      const result = await resolveApproval(accountRequest.id, "Approved", {
        temporary_password: tempPassword,
        temporary_password_confirmation: tempPassword,
      });
      if (!result) return;
      const mailStatus = result.mail_sent === false
        ? "تم إنشاء الحساب، لكن تعذر إرسال بيانات الدخول بالبريد. انسخ كلمة المرور وسلمها لصاحب الحساب بطريقة آمنة."
        : "تم إنشاء الحساب وإرسال بيانات الدخول إلى البريد التجاري.";
      alert(`${mailStatus} سيُطلب من المستخدم تغيير كلمة السر عند أول دخول.`);
      setAccountRequest(null);
      setTempPassword(generateTemporaryPassword());
    } catch (err) {
      alert(err?.message || "تعذر إنشاء الحساب من الطلب.");
    } finally {
      setIsCreatingAccount(false);
    }
  };

  const rejectItem = async (item) => {
    try { await resolveApproval(item.id, "Rejected"); }
    catch (err) { alert(err?.message || "تعذر رفض الطلب."); }
  };

  const approveNonJoinItem = async (item) => {
    try { await resolveApproval(item.id, "Approved"); }
    catch (err) { alert(err?.message || "تعذر قبول الطلب."); }
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">✅ مركز الموافقات</h1>
          <p className="mt-2 text-sm text-slate-400">العميل يرسل طلب الانضمام ببريد تجاري منفصل ويوثقه بواسطة OTP. بعد الموافقة ينشئ الأدمن حساب عمل جديدًا لا يختلط بحساب العميل، ويحدد كلمة سر مؤقتة يغيّرها صاحب الحساب عند أول دخول.</p>
        </div>
        <div className="grid w-full max-w-2xl gap-3 md:grid-cols-[1fr_220px]">
          <input className="field-surface" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="ابحث عن طلب، مالك، خدمة، بريد..." />
          <select className="field-surface" value={type} onChange={(e) => setType(e.target.value)}>
            {types.map((item) => <option key={item} value={item}>{item === "All" ? "الكل" : typeLabel(item)}</option>)}
          </select>
        </div>
      </div>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">قيد الانتظار</div><div className="mt-1 text-2xl font-black text-amber-300">{approvals.filter((a) => a.status === "Pending").length}</div></div>
        <div className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">مقبول</div><div className="mt-1 text-2xl font-black text-emerald-300">{approvals.filter((a) => a.status === "Approved").length}</div></div>
        <div className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">عدد النتائج</div><div className="mt-1 text-2xl font-black text-blue-300">{filtered.length}</div></div>
      </div>
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        {filtered.map((item) => {
          const isJoinRequest = item.type === "Owner Request" || item.type === "Provider Request";
          return (
            <div key={item.id} className="rounded-3xl border border-white/10 bg-white/[.04] p-6">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div><div className="text-xs font-bold tracking-widest text-blue-300">{typeLabel(item.type)}</div><h3 className="mt-1 text-xl font-black text-white">{item.title}</h3><p className="mt-1 text-sm text-slate-400">المرسل: {item.requester} • {item.createdAt}</p></div>
                <span className={`w-fit rounded-full border px-3 py-1 text-xs font-black ${statusStyle[item.status] || statusStyle.Pending}`}>{arabicLabel(item.status)}</span>
              </div>
              <p className="mt-4 rounded-2xl bg-slate-950/40 p-4 text-sm leading-7 text-slate-300">{item.details}</p>
              {isJoinRequest && (
                <div className="mt-3 grid gap-2 rounded-2xl border border-blue-400/20 bg-blue-500/10 p-4 text-sm text-blue-100 md:grid-cols-2">
                  <div>البريد التجاري للحساب الجديد: <b className="ltr inline-block">{item.email || "غير موجود"}</b></div>
                  <div>توثيق البريد: <b className={item.emailVerified ? "text-emerald-300" : "text-red-300"}>{item.emailVerified ? "موثق بواسطة OTP" : "غير موثق"}</b></div>
                  <div>حساب العميل المرسل: <b>{item.applicant?.email || "غير متاح"}</b></div>
                  <div>رقم الهاتف: <b>{item.phone || "غير محدد"}</b></div>
                  <div>نوع الحساب الجديد: <b>{roleLabel(item.type)}</b></div>
                  <div>المدينة: <b>{item.city || "غير محددة"}</b></div>
                </div>
              )}
              <div className="mt-5 flex flex-wrap gap-2">
                {item.type === "Venue Add" && <button type="button" onClick={() => viewVenue(item)} className="rounded-xl bg-blue-500/15 px-4 py-2 text-sm font-bold text-blue-300">مشاهدة الصالة</button>}
                {isJoinRequest ? (
                  <button disabled={item.status !== "Pending"} onClick={() => openAccountModal(item)} className="rounded-xl bg-emerald-500/15 px-4 py-2 text-sm font-bold text-emerald-300 disabled:opacity-40">إنشاء حساب</button>
                ) : (
                  <button disabled={item.status !== "Pending"} onClick={() => approveNonJoinItem(item)} className="rounded-xl bg-emerald-500/15 px-4 py-2 text-sm font-bold text-emerald-300 disabled:opacity-40">قبول</button>
                )}
                <button disabled={item.status !== "Pending"} onClick={() => rejectItem(item)} className="rounded-xl bg-red-500/15 px-4 py-2 text-sm font-bold text-red-300 disabled:opacity-40">رفض</button>
              </div>
            </div>
          );
        })}
      </div>

      {accountRequest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
          <div className="w-full max-w-xl rounded-3xl border border-white/10 bg-slate-950 p-6 shadow-2xl" dir="rtl">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h2 className="text-2xl font-black text-white">إنشاء حساب من طلب الانضمام</h2>
                <p className="mt-2 text-sm leading-7 text-slate-400">سيتم إنشاء حساب عمل مستقل بالبريد التجاري الموثق، ولن يتم تغيير دور حساب العميل الذي قدّم الطلب. عند أول تسجيل دخول سيُجبر صاحب الحساب الجديد على تغيير كلمة السر.</p>
              </div>
              <button onClick={() => setAccountRequest(null)} className="rounded-xl bg-white/10 px-3 py-2 text-sm text-slate-200">إغلاق</button>
            </div>

            <div className="mt-5 grid gap-3 rounded-2xl bg-white/[.04] p-4 text-sm text-slate-200 md:grid-cols-2">
              <div>نوع الحساب: <b className="text-blue-200">{roleLabel(accountRequest.type)}</b></div>
              <div>الاسم: <b>{accountRequest.requester}</b></div>
              <div>البريد التجاري: <b className="ltr inline-block">{accountRequest.email}</b></div>
              <div>حالة التوثيق: <b className="text-emerald-300">موثق بواسطة OTP</b></div>
              <div>الهاتف: <b>{accountRequest.phone || "غير محدد"}</b></div>
              <div>حساب العميل المرسل: <b className="ltr inline-block">{accountRequest.applicant?.email || "غير متاح"}</b></div>
              {accountRequest.serviceCategory && <div className="md:col-span-2">نوع الخدمة: <b>{accountRequest.serviceCategory}</b></div>}
            </div>

            <label className="mt-5 block text-sm font-bold text-slate-200">كلمة السر المؤقتة التي سيستلمها المستخدم</label>
            <div className="mt-2 flex flex-col gap-2 sm:flex-row">
              <input
                className="field-surface ltr min-w-0 flex-1"
                type="text"
                value={tempPassword}
                onChange={(e) => setTempPassword(e.target.value)}
                placeholder="مثال: Salora@12345"
                autoFocus
              />
              <button type="button" onClick={copyTemporaryPassword} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-slate-200">نسخ</button>
              <button type="button" onClick={() => setTempPassword(generateTemporaryPassword())} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-slate-200">توليد جديد</button>
            </div>
            <p className="mt-2 text-xs text-slate-500">تُولد كلمة قوية ومختلفة لكل حساب، ويمكن للأدمن تعديلها. سيُجبر المستخدم على تغييرها عند أول دخول.</p>

            <div className="mt-6 flex flex-wrap justify-end gap-2">
              <button onClick={() => setAccountRequest(null)} className="rounded-xl bg-white/10 px-4 py-2 text-sm font-bold text-slate-200">إلغاء</button>
              <button disabled={isCreatingAccount} onClick={createAccountFromRequest} className="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-black text-white disabled:opacity-50">
                {isCreatingAccount ? "جارٍ إنشاء الحساب..." : "إنشاء الحساب وتفعيل الطلب"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
