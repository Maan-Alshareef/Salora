import React from "react";
import { useApp } from "../context/AppContext";

const statusClass = {
  Active: "text-emerald-300 bg-emerald-500/15 border-emerald-400/20",
  Pending: "text-amber-300 bg-amber-500/15 border-amber-400/20",
  Expired: "text-slate-300 bg-slate-500/15 border-slate-400/20",
  Rejected: "text-red-300 bg-red-500/15 border-red-400/20"
};

export default function OffersDiscounts() {
  const { offers, venues, arabicLabel } = useApp();
  const venueName = (offer) => venues.find((v) => String(v.id) === String(offer.venueId))?.name || offer.target || "كل الصالات";

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div>
        <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">🏷️ العروض والخصومات</h1>
        <p className="mt-2 text-sm text-slate-400">هنا يشاهد مدير النظام العروض التي أنشأها مالك الصالة. العرض يصبح فعالاً مباشرة ويظهر للعميل في التطبيق بدون موافقة إضافية.</p>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {offers.map((offer) => (
          <div key={offer.id} className="salora-section-card p-6">
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="text-xs font-bold uppercase tracking-widest text-blue-300">عرض صالة</div>
                <h3 className="mt-2 text-xl font-black">{offer.title}</h3>
                <p className="mt-1 text-sm text-slate-400">الصالة: {venueName(offer)}</p>
              </div>
              <div className="rounded-2xl bg-blue-500/15 px-4 py-3 text-2xl font-black text-blue-200">{offer.discount}%</div>
            </div>
            <div className="mt-5 rounded-2xl bg-slate-950/40 p-4 text-sm text-slate-300">من {offer.startsAt || "غير محدد"} إلى {offer.endsAt || "غير محدد"}</div>
            <div className="mt-4 flex items-center justify-between">
              <span className={`rounded-full border px-3 py-1 text-xs font-black ${statusClass[offer.status] || statusClass.Active}`}>{arabicLabel(offer.status)}</span>
              <span className="text-xs text-slate-500">يظهر مباشرة بالتطبيق</span>
            </div>
          </div>
        ))}
      </div>
      {offers.length === 0 && <div className="rounded-2xl border border-white/10 bg-white/[.04] p-8 text-center text-slate-400">لا توجد عروض حالياً.</div>}
    </div>
  );
}
