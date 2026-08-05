import React, { useMemo, useState } from "react";
import { Area, AreaChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import Card from "../../components/Card";
import { useApp } from "../../context/AppContext";

export default function OwnerDashboard() {
  const { ownerVenues, ownerBookings, reportData, formatPricePair, formatUsd, formatSyp, dataLoading, backendError, refreshData } = useApp();
  const [currency, setCurrency] = useState("SYP");
  const activeBookings = Number(reportData?.confirmed_bookings || 0) + Number(reportData?.completed_bookings || 0);
  const pendingRequests = ownerBookings.filter((booking) => ["Pending Owner Review", "Pending Payment", "Modification Requested", "Cancellation Requested"].includes(booking.status)).length;
  const revenue = currency === "USD" ? Number(reportData?.revenue_usd || 0) : Number(reportData?.revenue_syp || 0);
  const money = currency === "USD" ? formatUsd(revenue) : formatSyp(revenue);
  const chartData = useMemo(() => (reportData?.monthly || []).map((item) => ({
    name: item.month,
    revenue: Number(currency === "USD" ? item.revenue_usd : item.revenue_syp) || 0,
    bookings: Number(item.bookings || 0)
  })), [reportData, currency]);

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">📊 لوحة مالك الصالة</h1><p className="mt-2 text-sm text-slate-400">ملخص صالاتك وحجوزاتك وإيراداتك من قاعدة البيانات.</p></div><button onClick={refreshData} disabled={dataLoading} className="rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-2 text-xs font-bold text-amber-200 disabled:opacity-50">{dataLoading ? "جاري التحديث..." : "تحديث"}</button></div>
      {backendError && <div className="rounded-2xl border border-red-400/30 bg-red-500/10 p-4 text-sm text-red-200">{backendError}</div>}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card title="صالاتي المسجلة" value={reportData?.venues ?? ownerVenues.length} subtitle="ضمن حسابك فقط" />
        <Card title="الحجوزات المؤكدة/المكتملة" value={activeBookings} subtitle="لا تشمل الحجوزات المرفوضة" />
        <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6 shadow-xl"><div className="flex items-center justify-between gap-2"><div className="text-xs font-bold text-slate-400">الإيرادات المقبوضة</div><button type="button" onClick={() => setCurrency((value) => value === "SYP" ? "USD" : "SYP")} className="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-[11px] font-black text-amber-200">{currency === "SYP" ? "USD" : "SYP"}</button></div><div className="mt-2 text-xl font-black text-white">{money}</div><div className="mt-1 text-xs text-slate-500">دفعات معتمدة فقط</div></div>
        <div className="rounded-3xl border border-orange-400/20 bg-orange-500/10 p-6"><div className="mb-1 text-xs font-bold text-orange-300">طلبات تحتاج إجراء</div><div className="text-3xl font-black text-orange-300">{pendingRequests}</div></div>
      </div>
      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="mb-4 text-sm font-black text-amber-300">الإيرادات الشهرية</h3><div className="h-72"><ResponsiveContainer width="100%" height="100%"><AreaChart data={chartData}><XAxis dataKey="name" stroke="#94a3b8" fontSize={12} /><YAxis stroke="#94a3b8" fontSize={12} /><Tooltip contentStyle={{ background: "#0f172a", border: "1px solid rgba(255,255,255,.1)", borderRadius: 16 }} /><Area type="monotone" dataKey="revenue" stroke="#f59e0b" fill="rgba(245,158,11,.15)" strokeWidth={3} /></AreaChart></ResponsiveContainer></div></div>
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">{ownerVenues.map((venue) => <div key={venue.id} className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="text-xl font-black">{venue.name}</h3><p className="mt-1 text-sm text-slate-400">{venue.city} • {venue.capacity} ضيف</p><div className="mt-4 font-black text-emerald-300">{formatPricePair(venue.basePrice, "", venue.priceSyp)}</div>{venue.pendingRevision && <div className="mt-3 rounded-xl bg-amber-500/10 p-3 text-xs text-amber-200">يوجد تعديل بانتظار موافقة الإدارة.</div>}</div>)}{ownerVenues.length === 0 && <div className="rounded-3xl border border-white/10 bg-white/[.04] p-10 text-center text-slate-500 xl:col-span-2">لا توجد صالات بعد.</div>}</div>
    </div>
  );
}
