import React, { useMemo, useState } from "react";
import { useApp } from "../context/AppContext";

const statusStyle = {
  Approved: "border-emerald-400/20 bg-emerald-500/15 text-emerald-300",
  Pending: "border-amber-400/20 bg-amber-500/15 text-amber-300",
  Rejected: "border-red-400/20 bg-red-500/15 text-red-300",
  Disabled: "border-slate-400/20 bg-slate-500/15 text-slate-300"
};

export default function ServicesManagement() {
  const { services, updateServiceStatus, SERVICE_TYPES, formatPricePair, serviceEmoji, arabicLabel } = useApp();
  const [status, setStatus] = useState("All");
  const [type, setType] = useState("All");
  const [query, setQuery] = useState("");

  const filtered = useMemo(() => services.filter((service) => {
    const text = [service.name, service.provider, service.category, service.city, service.serviceType].join(" ").toLowerCase();
    return (status === "All" || service.status === status) && (type === "All" || service.serviceType === type) && text.includes(query.toLowerCase());
  }), [services, status, type, query]);

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div><h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">🧩 الخدمات ومقدمو الخدمات</h1><p className="mt-2 text-sm text-slate-400">الخدمات مقسمة إلى خدمات مجانية ضمن الصالة، وخدمات مدفوعة إضافية، وخدمات يقدمها مزود خارجي.</p></div>
        <div className="grid w-full max-w-3xl gap-3 md:grid-cols-[1fr_220px_220px]"><input className="field-surface" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="ابحث عن خدمة أو مقدم خدمة أو تصنيف..." /><select className="field-surface" value={type} onChange={(e) => setType(e.target.value)}><option value="All">الكل</option>{Object.values(SERVICE_TYPES).map((item) => <option key={item} value={item}>{arabicLabel(item)}</option>)}</select><select className="field-surface" value={status} onChange={(e) => setStatus(e.target.value)}><option value="All">كل الحالات</option><option value="Approved">مقبولة</option><option value="Pending">قيد المراجعة</option><option value="Rejected">مرفوضة</option></select></div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        {Object.values(SERVICE_TYPES).map((item) => <div key={item} className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">{arabicLabel(item)}</div><div className="mt-1 text-2xl font-black">{services.filter((s) => s.serviceType === item).length}</div></div>)}
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
        {filtered.map((service) => (
          <div key={service.id} className="rounded-3xl border border-white/10 bg-white/[.04] p-6">
            <div className="flex items-start justify-between gap-4"><div><div className="text-xs font-bold text-blue-300">{arabicLabel(service.serviceType || service.category)}</div><h3 className="mt-1 text-lg font-black">{serviceEmoji?.[service.name] || "🧩"} {service.name}</h3><p className="mt-1 text-sm text-slate-400">{service.provider} • {service.city}</p></div><span className={`rounded-full border px-3 py-1 text-xs font-black ${statusStyle[service.status] || statusStyle.Disabled}`}>{arabicLabel(service.status)}</span></div>
            <div className="mt-5 grid grid-cols-3 gap-3 text-center text-sm"><div className="rounded-2xl bg-white/[.03] p-3"><div className="text-slate-500">السعر</div><b className="text-emerald-300">{service.price === 0 && service.priceSyp === 0 ? "مجاني ضمن السعر" : formatPricePair(service.price, "", service.priceSyp)}</b></div><div className="rounded-2xl bg-white/[.03] p-3"><div className="text-slate-500">التقييم</div><b className="text-amber-300">⭐ {service.rating}</b></div><div className="rounded-2xl bg-white/[.03] p-3"><div className="text-slate-500">الطلبات</div><b>{service.orders}</b></div></div>
            <div className="mt-4 flex flex-wrap gap-2">{(service.availableFor || []).map((eventType) => <span key={eventType} className="rounded-full bg-white/[.06] px-3 py-1 text-xs text-slate-300">{arabicLabel(eventType)}</span>)}</div>
            {service.status === "Pending" && <div className="mt-5 flex gap-2"><button onClick={() => updateServiceStatus(service.id, "Approved")} className="flex-1 rounded-xl bg-emerald-500/15 py-2 text-xs font-bold text-emerald-300">قبول</button><button onClick={() => updateServiceStatus(service.id, "Rejected")} className="flex-1 rounded-xl bg-red-500/15 py-2 text-xs font-bold text-red-300">رفض</button></div>}
          </div>
        ))}
      </div>
    </div>
  );
}
