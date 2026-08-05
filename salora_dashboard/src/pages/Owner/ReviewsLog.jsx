import React, { useState } from "react";
import { useApp } from "../../context/AppContext";

export default function ReviewsLog() {
  const { ownerReviews, replyToReview } = useApp();
  const [drafts, setDrafts] = useState({});
  return (
    <div className="space-y-6 text-white font-sans pb-12" dir="rtl">
      <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">⭐ تقييمات وتعليقات صالاتي</h1>
      <p className="text-sm text-slate-400">هنا تظهر التقييمات التي يضيفها العملاء لصالاتك، ويمكنك الرد عليها من لوحة المالك.</p>
      <div className="space-y-4 max-w-3xl">
        {ownerReviews.map((r) => (
          <div key={r.id} className="rounded-3xl border border-white/10 bg-white/[.04] p-5">
            <div className="flex items-center justify-between text-xs mb-1"><span className="font-bold text-blue-300">{r.customer} <span className="text-yellow-400">⭐ {r.rating}</span></span><span className="text-slate-500">{r.createdAt}</span></div>
            <div className="text-sm font-bold text-white">{r.venue}</div>
            <p className="mt-2 text-sm leading-7 text-slate-300">{r.comment}</p>
            {r.ownerReply && <div className="mt-3 rounded-xl bg-blue-500/10 p-3 text-xs text-blue-200">ردك الحالي: {r.ownerReply}</div>}
            <div className="mt-4 flex gap-2"><input className="field-surface flex-1" value={drafts[r.id] ?? r.ownerReply ?? ""} onChange={(e) => setDrafts((prev) => ({ ...prev, [r.id]: e.target.value }))} placeholder="اكتب ردًا احترافيًا..." /><button onClick={() => replyToReview(r.id, drafts[r.id] ?? r.ownerReply ?? "")} className="rounded-xl bg-amber-500/20 px-4 text-xs font-bold text-amber-300">حفظ الرد</button></div>
          </div>
        ))}
        {ownerReviews.length === 0 && <div className="rounded-2xl border border-white/10 bg-white/[.04] p-8 text-center text-slate-400">لا توجد تقييمات لصالاتك حالياً.</div>}
      </div>
    </div>
  );
}
