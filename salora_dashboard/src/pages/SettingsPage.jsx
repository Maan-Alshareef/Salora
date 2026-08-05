import React, { useState } from "react";
import { useApp } from "../context/AppContext";

export default function SettingsPage() {
  const { exchangeRate, sendBroadcastToOwner, dynamicPricingRules, activeRuleId, setActiveRuleId } = useApp();
  const [apiUrl, setApiUrl] = useState(import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000/api");
  const [broadcast, setBroadcast] = useState({ title: "تنبيه للمالكين", message: "" });
  const submitBroadcast = () => { sendBroadcastToOwner(broadcast.title, broadcast.message); setBroadcast({ title: "تنبيه للمالكين", message: "" }); alert("✅ تم إرسال التنبيه إلى مساحة المالك."); };
  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div><h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">⚙️ إعدادات النظام</h1><p className="mt-2 text-sm text-slate-400">صفحة ضبط عامة للعرض والتجربة، وكلها باللغة العربية ومهيأة للربط مع الواجهة الخلفية.</p></div>
      <div className="grid grid-cols-1 gap-5 xl:grid-cols-2"><div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="mb-3 font-black text-blue-300">🔗 رابط الواجهة الخلفية</h3><div className="flex gap-2"><input className="field-surface ltr flex-1" value={apiUrl} onChange={(e) => setApiUrl(e.target.value)} /><button className="rounded-xl bg-blue-600 px-5 text-sm font-black text-white">حفظ الرابط</button></div><div className="mt-4 grid gap-2 text-sm text-slate-300"><div className="rounded-2xl bg-white/[.03] p-4">✅ التوكن جاهز من خلال apiClient</div><div className="rounded-2xl bg-white/[.03] p-4">✅ فصل صلاحيات الأدمن والمالك</div><div className="rounded-2xl bg-white/[.03] p-4">✅ التطبيق يجلب الصالات والحجوزات والشكاوى من الواجهة الخلفية</div></div></div><div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="mb-3 font-black text-blue-300">💱 العملة</h3><div className="rounded-2xl bg-white/[.03] p-4 text-2xl font-black">1 دولار = {Number(exchangeRate).toLocaleString()} ليرة سورية</div></div></div>
      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="mb-4 font-black text-blue-300">📈 التسعير الديناميكي</h3><div className="flex flex-wrap gap-2">{dynamicPricingRules.map((rule) => <button key={rule.id} onClick={() => setActiveRuleId(rule.id)} className={`rounded-xl px-4 py-3 text-sm font-bold ${activeRuleId === rule.id ? "bg-blue-600 text-white" : "bg-white/[.04] text-slate-300"}`}>{rule.label}</button>)}</div></div>
      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="mb-2 font-black text-blue-300">🔔 إرسال تنبيه للمالكين</h3><input className="field-surface mb-3" value={broadcast.title} onChange={(e) => setBroadcast({ ...broadcast, title: e.target.value })} placeholder="عنوان التنبيه" /><textarea className="field-surface min-h-[120px]" value={broadcast.message} onChange={(e) => setBroadcast({ ...broadcast, message: e.target.value })} placeholder="اكتب الرسالة التي تريد إرسالها..." /><button onClick={submitBroadcast} className="mt-3 w-full rounded-xl bg-blue-600 py-3 font-black text-white">إرسال التنبيه</button></div>
    </div>
  );
}
