import React, { useState } from "react";
import { useApp } from "../../context/AppContext";

export default function OwnerOffers() {
  const { ownerOffers, ownerVenues, addOffer, arabicLabel } = useApp();
  const [form, setForm] = useState({ title: "", discount: 10, venueId: "", startsAt: "", endsAt: "" });

  const submit = async (e) => {
    e.preventDefault();
    if (!form.title.trim()) return alert("اكتب اسم العرض.");
    if (!form.venueId) return alert("اختر الصالة التي سيطبق عليها العرض.");
    await addOffer({ ...form, target: "specific_venue", type: "percentage", status: "Active" });
    setForm({ title: "", discount: 10, venueId: "", startsAt: "", endsAt: "" });
  };

  const targetName = (offer) => ownerVenues.find((v) => String(v.id) === String(offer.venueId))?.name || "كل صالاتي";

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div><h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">🏷️ عروضي وخصوماتي</h1><p className="mt-2 text-sm text-slate-400">أنشئ عرضاً لصالة من صالاتك وسيظهر مباشرة في التطبيق بدون موافقة الأدمن، مع إرسال إشعار Firebase للعملاء لفتح الصالة والعرض.</p></div>
      <form onSubmit={submit} className="rounded-3xl border border-amber-400/20 bg-white/[.04] p-5">
        <h2 className="mb-4 text-lg font-black text-amber-200">➕ نشر عرض جديد</h2>
        <div className="grid gap-3 md:grid-cols-[1fr_140px_1fr_170px_170px]">
          <input className="field-surface" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="اسم العرض" />
          <input className="field-surface" type="number" min="1" max="50" value={form.discount} onChange={(e) => setForm({ ...form, discount: e.target.value })} placeholder="نسبة الخصم" />
          <select required className="field-surface" value={form.venueId} onChange={(e) => setForm({ ...form, venueId: e.target.value })}><option value="">اختر الصالة</option>{ownerVenues.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}</select>
          <input className="field-surface" type="date" value={form.startsAt} onChange={(e) => setForm({ ...form, startsAt: e.target.value })} />
          <input className="field-surface" type="date" value={form.endsAt} onChange={(e) => setForm({ ...form, endsAt: e.target.value })} />
        </div>
        <button className="mt-4 rounded-xl bg-emerald-500 px-5 py-3 text-sm font-black text-slate-950 hover:bg-emerald-400">نشر العرض الآن</button>
      </form>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {ownerOffers.map((offer) => <div key={offer.id} className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="font-black text-white">{offer.title}</h3><p className="mt-1 text-sm text-slate-400">{targetName(offer)}</p><div className="mt-4 text-3xl font-black text-amber-300">{offer.discount}%</div><p className="mt-2 text-xs text-slate-500">{offer.startsAt || "بلا تاريخ"} ← {offer.endsAt || "بلا تاريخ"}</p><span className="mt-4 inline-block rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">{arabicLabel(offer.status || "Active")}</span></div>)}
      </div>
      {ownerOffers.length === 0 && <div className="rounded-2xl border border-white/10 bg-white/[.04] p-8 text-center text-slate-400">لا توجد عروض بعد.</div>}
    </div>
  );
}
