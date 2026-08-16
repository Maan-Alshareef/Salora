import React, { useState } from "react";
import { useApp } from "../context/AppContext";

export default function ComplaintsSupport() {
  const { complaints, updateComplaint, arabicLabel } = useApp();
  const [active, setActive] = useState(null);
  const [reply, setReply] = useState("");
  const statusClass = {
    Open: "text-red-300 bg-red-500/15 border-red-400/20",
    "In Progress": "text-amber-300 bg-amber-500/15 border-amber-400/20",
    Answered: "text-blue-300 bg-blue-500/15 border-blue-400/20",
    Resolved: "text-emerald-300 bg-emerald-500/15 border-emerald-400/20",
    Closed: "text-slate-300 bg-slate-500/15 border-slate-400/20"
  };

  const openTicket = (ticket) => {
    setActive(ticket);
    setReply(ticket.adminReply || "");
  };

  const saveReply = async () => {
    if (!active || !reply.trim()) return;
    await updateComplaint(active.id, { reply: reply.trim(), status: "Resolved" });
    setActive(null);
    setReply("");
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div>
        <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">🎧 الشكاوى والدعم</h1>
        <p className="mt-2 text-sm text-slate-400">أي رد تكتبه الإدارة هنا يُحفظ في الباك ويظهر مباشرة للعميل داخل تطبيق Salora في مركز الدعم.</p>
      </div>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
        {["Open", "In Progress", "Resolved", "Closed"].map((status) => <div key={status} className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">{arabicLabel(status)}</div><div className="mt-1 text-2xl font-black">{complaints.filter((c) => c.status === status).length}</div></div>)}
      </div>
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        {complaints.map((ticket) => <button key={ticket.id} onClick={() => openTicket(ticket)} className="rounded-3xl border border-white/10 bg-white/[.04] p-6 text-right transition hover:bg-white/[.07]"><div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><div className="text-xs font-bold tracking-widest text-blue-300">{arabicLabel(ticket.role)} • {arabicLabel(ticket.priority)}</div><h3 className="mt-1 text-lg font-black text-white">{ticket.subject}</h3><p className="mt-1 text-sm text-slate-400">{ticket.user} • {ticket.createdAt}</p></div><span className={`w-fit rounded-full border px-3 py-1 text-xs font-black ${statusClass[ticket.status] || statusClass.Open}`}>{arabicLabel(ticket.status)}</span></div><p className="mt-4 line-clamp-2 rounded-2xl bg-slate-950/40 p-4 text-sm text-slate-300">{ticket.message}</p>{ticket.reply && <div className="mt-3 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-3 text-xs text-emerald-200 whitespace-pre-line">{ticket.reply}</div>}</button>)}
      </div>
      {complaints.length === 0 && <div className="rounded-2xl border border-white/10 bg-white/[.04] p-8 text-center text-slate-400">لا توجد شكاوى حالياً.</div>}
      {active && <div className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/85 p-4 backdrop-blur-xl"><div className="max-h-[88vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-blue-400/30 bg-slate-950 p-6 shadow-2xl"><div className="flex justify-between border-b border-white/10 pb-4"><div><h3 className="text-xl font-black text-white">{active.subject}</h3><p className="text-sm text-slate-400">{active.user} • {active.createdAt}</p></div><button onClick={() => setActive(null)} className="text-slate-400 hover:text-white">✕</button></div><p className="mt-4 rounded-2xl bg-white/[.04] p-4 text-sm leading-7 text-slate-300">{active.message}</p>{active.reply && <div className="mt-4 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4 text-sm leading-7 text-emerald-100 whitespace-pre-line">{active.reply}</div>}<textarea className="field-surface mt-4" value={reply} onChange={(e) => setReply(e.target.value)} placeholder="اكتب رد الإدارة ليظهر للعميل..." rows={4} /><div className="mt-4 grid gap-2 sm:grid-cols-3"><button onClick={saveReply} className="rounded-xl bg-blue-600 py-3 font-bold text-white disabled:opacity-40" disabled={!reply.trim()}>حفظ الرد وإظهاره للعميل</button><button onClick={() => { updateComplaint(active.id, { status: "In Progress" }); setActive(null); }} className="rounded-xl bg-amber-500/15 py-3 font-bold text-amber-300">قيد المعالجة</button><button onClick={() => { updateComplaint(active.id, { status: "Closed" }); setActive(null); }} className="rounded-xl bg-slate-500/15 py-3 font-bold text-slate-300">إغلاق الشكوى</button></div></div></div>}
    </div>
  );
}
