import React, { useState } from "react";
import { useLocation } from "react-router-dom";
import { useApp } from "../context/AppContext";

export default function NotificationsPage() {
  const location = useLocation();
  const isOwner = location.pathname.startsWith("/owner");
  const { adminNotifications, ownerNotifications, markAdminRead, markOwnerRead, clearAllAdminNotifications, clearAllOwnerNotifications } = useApp();
  const notifications = isOwner ? ownerNotifications : adminNotifications;
  const markRead = isOwner ? markOwnerRead : markAdminRead;
  const markAllRead = isOwner ? clearAllOwnerNotifications : clearAllAdminNotifications;
  const [active, setActive] = useState(null);

  const openNotification = async (item) => {
    setActive(item);
    if (!item.read) await markRead(item.id);
  };

  return (
    <div className="max-w-5xl space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">🔔 مركز الإشعارات</h1><p className="mt-2 text-sm text-slate-400">{isOwner ? "تنبيهات صالاتك وحجوزاتك" : "تنبيهات الإدارة ومراجعات النظام"}</p></div><button onClick={markAllRead} className="rounded-xl border border-blue-400/20 bg-blue-500/10 px-4 py-2 text-sm font-bold text-blue-200 hover:bg-blue-500/20">تعليم الكل كمقروء</button></div>
      <div className="space-y-3">{notifications.map((notification) => <button key={notification.id} onClick={() => openNotification(notification)} className={`w-full rounded-3xl border p-5 text-right transition-all ${notification.read ? "border-white/10 bg-white/[.03] opacity-65" : "border-blue-400/25 bg-blue-500/10 shadow-[0_0_28px_rgba(59,130,246,.12)]"}`}><div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div className="font-black text-white">{notification.title}</div><div className="text-xs text-slate-500">{notification.time}</div></div><p className="mt-2 line-clamp-2 text-sm text-slate-300">{notification.message}</p></button>)}{notifications.length === 0 && <div className="rounded-3xl border border-white/10 bg-white/[.04] p-10 text-center text-slate-400">لا توجد إشعارات.</div>}</div>
      {active && <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/80 p-4 backdrop-blur-xl"><div className="w-full max-w-lg rounded-3xl border border-blue-400/30 bg-slate-950 p-6 shadow-2xl"><div className="mb-4 flex justify-between border-b border-white/10 pb-3"><h3 className="font-black text-blue-300">{active.title}</h3><button onClick={() => setActive(null)} className="text-slate-400 hover:text-white">✕</button></div><p className="rounded-2xl bg-white/[.04] p-4 text-sm leading-7 text-slate-200">{active.message || "لا توجد تفاصيل إضافية."}</p><button onClick={() => setActive(null)} className="mt-5 w-full rounded-2xl bg-blue-600 py-3 font-black text-white hover:bg-blue-500">إغلاق</button></div></div>}
    </div>
  );
}
