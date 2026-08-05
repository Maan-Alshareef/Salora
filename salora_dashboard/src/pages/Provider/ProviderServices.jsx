import React, { useState } from "react";
import { useApp } from "../../context/AppContext";

const eventOptions = [
  ["Wedding", "💍 زفاف"],
  ["Engagement", "💞 خطوبة"],
  ["Graduation", "🎓 تخرج"],
  ["Birthday", "🎂 عيد ميلاد"],
  ["Family Event", "👨‍👩‍👧 مناسبة عائلية"],
  ["Condolence", "🕊️ عزاء"]
];
const categories = ["📸 تصوير", "🍽️ ضيافة ومأكولات", "🌸 ديكور", "💡 إضاءة وصوت", "🎂 كيك وحلويات", "📖 قارئ / شيخ", "🧑‍💼 تنظيم مناسبات"];

export default function ProviderServices() {
  const { currentUser, providerServices, createProviderService, formatPricePair, arabicLabel } = useApp();
  const [form, setForm] = useState({ name_ar: "", emoji: "📸", category: "📸 تصوير", price_syp: "", price_usd: "", description_ar: "", available_for: ["Wedding"] });
  const [message, setMessage] = useState("");

  const toggleEvent = (value) => setForm((prev) => ({ ...prev, available_for: prev.available_for.includes(value) ? prev.available_for.filter((x) => x !== value) : [...prev.available_for, value] }));
  const submit = async (e) => {
    e.preventDefault();
    if (!form.name_ar.trim()) return setMessage("اكتب اسم الخدمة أولاً.");
    await createProviderService({ ...form, price_syp: Number(form.price_syp || 0), price_usd: Number(form.price_usd || 0) });
    setMessage("تم إرسال الخدمة للأدمن. تظهر في التطبيق بعد موافقة الإدارة.");
    setForm({ name_ar: "", emoji: "📸", category: "📸 تصوير", price_syp: "", price_usd: "", description_ar: "", available_for: ["Wedding"] });
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="rounded-3xl border border-violet-400/20 bg-violet-500/10 p-6">
        <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-violet-300 to-white">🧩 خدماتي كمقدم خدمة</h1>
        <p className="mt-2 text-sm leading-7 text-slate-300">مرحباً {currentUser?.name}. أضف خدماتك وأسعارك وصورك الوصفية. الخدمة تبقى قيد المراجعة حتى يوافق عليها مدير النظام، وبعدها تظهر فوراً في تطبيق العملاء ضمن تبويب الخدمات.</p>
      </div>

      <form onSubmit={submit} className="rounded-3xl border border-white/10 bg-white/[.04] p-5 space-y-4">
        <h2 className="text-xl font-black text-violet-200">➕ إضافة خدمة جديدة</h2>
        <div className="grid gap-3 md:grid-cols-[90px_1fr_1fr_180px_180px]">
          <input className="field-surface text-center" value={form.emoji} onChange={(e) => setForm({ ...form, emoji: e.target.value })} placeholder="📸" />
          <input className="field-surface" value={form.name_ar} onChange={(e) => setForm({ ...form, name_ar: e.target.value })} placeholder="اسم الخدمة بالعربي" />
          <select className="field-surface" value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}>{categories.map((c) => <option key={c} value={c}>{c}</option>)}</select>
          <input className="field-surface" type="number" value={form.price_syp} onChange={(e) => setForm({ ...form, price_syp: e.target.value })} placeholder="السعر ل.س" />
          <input className="field-surface" type="number" value={form.price_usd} onChange={(e) => setForm({ ...form, price_usd: e.target.value })} placeholder="السعر $" />
        </div>
        <textarea className="field-surface min-h-[100px]" value={form.description_ar} onChange={(e) => setForm({ ...form, description_ar: e.target.value })} placeholder="وصف الخدمة والباقات وطريقة العمل..." />
        <div className="flex flex-wrap gap-2">{eventOptions.map(([value, label]) => <button type="button" key={value} onClick={() => toggleEvent(value)} className={`rounded-full border px-3 py-2 text-xs font-bold ${form.available_for.includes(value) ? "border-violet-400/40 bg-violet-500/20 text-violet-100" : "border-white/10 bg-white/[.03] text-slate-300"}`}>{label}</button>)}</div>
        <button className="rounded-xl bg-violet-500 px-5 py-3 text-sm font-black text-white hover:bg-violet-400">إرسال الخدمة للمراجعة</button>
        {message && <span className="ms-3 text-sm text-emerald-300">{message}</span>}
      </form>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
        {providerServices.map((s) => <div key={s.id} className="rounded-3xl border border-white/10 bg-white/[.04] p-6">
          <div className="flex items-start justify-between gap-4">
            <div><div className="text-xs text-violet-300">{arabicLabel(s.serviceType)}</div><h3 className="mt-1 text-xl font-black">{s.emoji || "🧩"} {s.name}</h3><p className="mt-1 text-sm text-slate-400">{s.category}</p></div>
            <span className={`rounded-full border px-3 py-1 text-xs font-black ${s.status === "Approved" ? "border-emerald-400/20 bg-emerald-500/15 text-emerald-300" : "border-amber-400/20 bg-amber-500/15 text-amber-300"}`}>{arabicLabel(s.status)}</span>
          </div>
          <div className="mt-4 rounded-2xl bg-slate-950/40 p-4 text-sm text-emerald-300">{s.priceSyp ? `${Number(s.priceSyp).toLocaleString()} ل.س` : formatPricePair(s.price)}</div>
          <div className="mt-4 flex flex-wrap gap-2">{(s.availableFor || []).map((t) => <span key={t} className="rounded-full bg-white/[.06] px-3 py-1 text-xs text-slate-300">{arabicLabel(t)}</span>)}</div>
        </div>)}
      </div>
      {providerServices.length === 0 && <div className="rounded-2xl border border-white/10 bg-white/[.04] p-8 text-center text-slate-400">لا توجد خدمات بعد. أضف أول خدمة ليتم إرسالها للمراجعة.</div>}
    </div>
  );
}
