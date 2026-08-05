import React, { useState } from "react";
import { useApp } from "../../context/AppContext";

export default function OwnerComplaints() {
  const { ownerComplaints, updateComplaint, arabicLabel } = useApp();
  const [drafts, setDrafts] = useState({});

  const saveReply = async (complaint) => {
    const reply = (drafts[complaint.id] ?? complaint.ownerReply ?? "").trim();
    if (!reply) return;
    await updateComplaint(complaint.id, { reply, status: "Resolved" });
    setDrafts((prev) => ({ ...prev, [complaint.id]: reply }));
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div>
        <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">🎧 شكاوى صالاتي</h1>
        <p className="mt-2 text-sm text-slate-400">تظهر هنا شكاوى العملاء المتعلقة بصالاتك فقط. عند حفظ الرد يصل للباك ويظهر للعميل في تطبيق Salora.</p>
      </div>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {ownerComplaints.map((c) => {
          const draft = drafts[c.id] ?? c.ownerReply ?? "";
          return <div key={c.id} className="rounded-3xl border border-white/10 bg-white/[.04] p-6">
            <div className="flex items-start justify-between gap-4"><div><h3 className="font-black text-white">{c.subject}</h3><p className="mt-1 text-xs text-slate-500">{c.user} • {c.createdAt}</p></div><span className="rounded-full border border-white/10 px-3 py-1 text-xs font-bold">{arabicLabel(c.status)}</span></div>
            <p className="mt-4 rounded-2xl bg-slate-950/40 p-4 text-sm leading-7 text-slate-300">{c.message}</p>
            {c.reply && <div className="mt-4 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4 text-sm leading-7 text-emerald-100 whitespace-pre-line">{c.reply}</div>}
            <textarea value={draft} onChange={(e) => setDrafts((prev) => ({ ...prev, [c.id]: e.target.value }))} className="field-surface mt-4 min-h-[90px]" placeholder="اكتب رد مالك الصالة..." />
            <button onClick={() => saveReply(c)} disabled={!draft.trim()} className="mt-3 rounded-xl bg-emerald-500 px-5 py-3 text-sm font-black text-slate-950 disabled:opacity-40">حفظ الرد وإظهاره للعميل</button>
          </div>;
        })}
      </div>
      {ownerComplaints.length === 0 && <div className="rounded-2xl border border-white/10 bg-white/[.04] p-8 text-center text-slate-400">لا توجد شكاوى حالياً.</div>}
    </div>
  );
}
