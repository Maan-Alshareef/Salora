import React, { useState } from "react";
import { useApp } from "../../context/AppContext";

export default function OwnerNotifications() {
  const { notifications, markAsRead, clearAllOwnerNotifications } = useApp();
  const [active, setActive] = useState(null);

  const open = async (notification) => {
    setActive(notification);
    if (!notification.read) await markAsRead(notification.id);
  };

  return (
    <div className="max-w-4xl space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex items-end justify-between gap-3"><div><h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">🔔 إشعارات صالاتي</h1><p className="mt-1 text-sm text-slate-400">حالات الحجوزات والدفع والشكاوى المرتبطة بحسابك.</p></div><button onClick={clearAllOwnerNotifications} className="rounded-xl bg-amber-500/15 px-4 py-2 text-xs font-bold text-amber-200">تعليم الكل كمقروء</button></div>
      <div className="space-y-3">{(notifications || []).map((notification) => <button key={notification.id} onClick={() => open(notification)} className={`flex w-full items-center justify-between rounded-2xl border p-5 text-right transition ${notification.read ? "border-white/5 bg-slate-950/20 opacity-60" : "border-amber-500/30 bg-amber-500/10"}`}><div><h4 className={`text-sm font-black ${notification.read ? "text-slate-400" : "text-amber-300"}`}>{notification.title}</h4><p className="mt-1 max-w-xl truncate text-xs text-slate-300">{notification.message}</p></div><span className="whitespace-nowrap text-[10px] text-slate-500">{notification.time}</span></button>)}{notifications.length === 0 && <div className="rounded-3xl border border-white/10 p-10 text-center text-slate-500">لا توجد إشعارات.</div>}</div>
      {active && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-md"><div className="w-full max-w-md rounded-3xl border border-amber-500/40 bg-slate-900 p-6"><div className="mb-4 flex items-center justify-between border-b border-white/5 pb-3"><h3 className="font-black text-amber-300">{active.title}</h3><button onClick={() => setActive(null)} className="text-slate-400">✕</button></div><p className="rounded-xl bg-slate-950/40 p-4 text-sm leading-7 text-slate-200">{active.message || "لا توجد تفاصيل إضافية."}</p><button onClick={() => setActive(null)} className="mt-5 w-full rounded-xl bg-amber-500 py-3 font-bold text-slate-950">إغلاق</button></div></div>}
    </div>
  );
}
