import React, { useMemo, useState } from "react";
import { useApp } from "../context/AppContext";

export default function ActivityLog() {
  const { activityLog } = useApp();
  const [type, setType] = useState("All");
  const types = ["All", ...new Set(activityLog.map((l) => l.type))];
  const filtered = useMemo(() => activityLog.filter((log) => type === "All" || log.type === type), [activityLog, type]);

  return (
    <div className="space-y-6 pb-12 text-white">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">🧾 Activity Log</h1><p className="mt-2 text-sm text-slate-400">سجل عمليات الأدمن والأونر: موافقات، دفع، حسابات، صالات وخدمات.</p></div><select className="field-surface sm:w-56" value={type} onChange={(e) => setType(e.target.value)}>{types.map((item) => <option key={item}>{item}</option>)}</select></div>
      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-4"><div className="space-y-3">{filtered.map((log) => <div key={log.id} className="grid gap-3 rounded-2xl border border-white/10 bg-slate-950/40 p-4 md:grid-cols-[160px_1fr_170px]"><div className="font-mono text-xs text-blue-300">{log.time}</div><div><b>{log.actor}</b> <span className="text-slate-400">{log.action}</span> <b className="text-white">{log.target}</b></div><div className="text-right text-xs uppercase tracking-widest text-slate-500">{log.type}</div></div>)}</div></div>
    </div>
  );
}
