import React, { useEffect, useState } from "react";
import { useApp } from "../../context/AppContext";

export default function OwnerServices() {
  const { ownerServices, ownerVenues, attachServiceToVenue, loadAvailableServices, formatPricePair, serviceEmoji, arabicLabel } = useApp();
  const [available, setAvailable] = useState([]);
  const [venueId, setVenueId] = useState("");
  const [serviceId, setServiceId] = useState("");
  const [customPrice, setCustomPrice] = useState("");

  useEffect(() => { loadAvailableServices?.().then(setAvailable); }, []);

  const submit = async (e) => {
    e.preventDefault();
    if (!venueId || !serviceId) return alert("اختر الصالة والخدمة أولاً.");
    await attachServiceToVenue(venueId, serviceId, customPrice || null);
    setServiceId(""); setCustomPrice("");
    alert("✅ تم ربط الخدمة. ستظهر ضمن خدمات الصالة وفي تطبيق العملاء بعد اعتماد الصالة.");
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div><h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">🧩 خدمات الصالة</h1><p className="mt-2 text-sm text-slate-400">اربط الخدمات المجانية أو الإضافية أو خدمات مقدمي الخدمة بصالاتك. الخدمات المعتمدة تظهر في تطبيق العملاء.</p></div>

      <form onSubmit={submit} className="rounded-3xl border border-amber-400/20 bg-white/[.04] p-5">
        <h2 className="mb-4 text-lg font-black text-amber-200">➕ طلب/ربط خدمة بصالة</h2>
        <div className="grid gap-3 md:grid-cols-[1fr_1fr_180px_140px]">
          <select value={venueId} onChange={(e) => setVenueId(e.target.value)} className="field-surface">
            <option value="">اختر الصالة</option>
            {ownerVenues.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
          </select>
          <select value={serviceId} onChange={(e) => setServiceId(e.target.value)} className="field-surface">
            <option value="">اختر خدمة أو مقدم خدمة متاح</option>
            {available.map((s) => <option key={s.id} value={s.id}>{s.emoji || "🧩"} {s.name} — {arabicLabel(s.serviceType)}</option>)}
          </select>
          <input value={customPrice} onChange={(e) => setCustomPrice(e.target.value)} type="number" className="field-surface" placeholder="سعر مخصص بالدولار" />
          <button className="rounded-xl bg-amber-500 px-4 py-3 text-sm font-black text-slate-950 hover:bg-amber-400">ربط الخدمة</button>
        </div>
      </form>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
        {ownerServices.map((s) => <div key={s.id} className="rounded-3xl border border-white/10 bg-white/[.04] p-6">
          <div className="text-xs text-amber-300">{arabicLabel(s.serviceType)}</div>
          <h3 className="mt-1 text-lg font-black">{s.emoji || serviceEmoji?.[s.name] || "🧩"} {s.name}</h3>
          <p className="mt-1 text-sm text-slate-400">{s.provider} • {arabicLabel(s.category)}</p>
          <div className="mt-4 grid grid-cols-2 gap-3 text-sm"><div className="rounded-2xl bg-white/[.03] p-3"><div className="text-slate-500">السعر</div><b className="text-emerald-300">{s.price === 0 ? "مجاني ضمن السعر" : formatPricePair(s.price)}</b></div><div className="rounded-2xl bg-white/[.03] p-3"><div className="text-slate-500">الحالة</div><b>{arabicLabel(s.status)}</b></div></div>
          <div className="mt-4 flex flex-wrap gap-2">{(s.availableFor || []).map((t) => <span key={t} className="rounded-full bg-white/[.06] px-3 py-1 text-xs text-slate-300">{arabicLabel(t)}</span>)}</div>
        </div>)}
      </div>
    </div>
  );
}
