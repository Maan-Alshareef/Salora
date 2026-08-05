import React, { useMemo, useState } from "react";
import Card from "../components/Card";
import { useApp } from "../context/AppContext";
import { ResponsiveContainer, AreaChart, Area, XAxis, YAxis, Tooltip, PieChart, Pie, Cell, BarChart, Bar } from "recharts";

const colors = ["#22c55e", "#f59e0b", "#ef4444"];

export default function AdminDashboard() {
  const { metrics, venues, users, providers, reportData, formatUsd, formatSyp, arabicLabel, dataLoading, backendError, refreshData } = useApp();
  const [currency, setCurrency] = useState("SYP");
  const valueFor = (sypKey, usdKey) => currency === "USD" ? Number(reportData?.[usdKey] || 0) : Number(reportData?.[sypKey] || 0);
  const moneyFor = (sypKey, usdKey) => currency === "USD" ? formatUsd(valueFor(sypKey, usdKey)) : formatSyp(valueFor(sypKey, usdKey));

  const commissionMoney = moneyFor("commission_syp", "commission_usd");
  const grossMoney = moneyFor("gross_revenue_syp", "gross_revenue_usd");
  const ownerCommissionMoney = moneyFor("owner_commission_syp", "owner_commission_usd");
  const providerCommissionMoney = moneyFor("provider_commission_syp", "provider_commission_usd");

  const revenueData = useMemo(() => (reportData?.monthly || []).map((item) => ({
    name: item.month,
    commission: Number(currency === "USD" ? (item.commission_usd ?? item.revenue_usd) : (item.commission_syp ?? item.revenue_syp)) || 0,
    bookings: Number(item.bookings || 0)
  })), [reportData, currency]);

  const venueStatusData = [
    { name: "مقبولة", value: venues.filter((venue) => venue.status === "Approved").length },
    { name: "قيد المراجعة", value: venues.filter((venue) => venue.status === "Pending").length },
    { name: "مرفوضة", value: venues.filter((venue) => venue.status === "Rejected").length }
  ];

  const topVenues = reportData?.top_venues?.length
    ? reportData.top_venues.map((venue) => ({ id: String(venue.id), name: venue.name_ar || venue.name_en, rating: Number(venue.rating_avg || 0), bookings: Number(venue.completed_bookings_count || 0) }))
    : [...venues].sort((a, b) => Number(b.rating || 0) - Number(a.rating || 0)).slice(0, 5);

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-white to-blue-300">📊 لوحة تحكم الإدارة</h1><p className="mt-2 text-sm text-slate-400">ملخص مباشر من قاعدة البيانات، ويتضمن عمولة Salora المحسوبة بنسبة 10% على الدفعات المعتمدة.</p></div>
        <button onClick={refreshData} disabled={dataLoading} className="rounded-xl border border-blue-400/20 bg-blue-500/10 px-4 py-2 text-xs font-bold text-blue-200 disabled:opacity-50">{dataLoading ? "جاري التحديث..." : "تحديث البيانات"}</button>
      </div>
      {backendError && <div className="rounded-2xl border border-red-400/30 bg-red-500/10 p-4 text-sm text-red-200">{backendError}</div>}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-8">
        <Card title="الصالات" value={reportData?.venues ?? metrics.totalVenues} subtitle={`${reportData?.approved_venues ?? metrics.activeVenues} مقبولة`} />
        <Card title="الحجوزات" value={reportData?.bookings ?? metrics.totalBookings} subtitle={`${reportData?.pending_payments ?? metrics.pendingPayments} دفعات للمراجعة`} />
        <Card title="المستخدمون" value={reportData?.users ?? users.length} subtitle="كل الأدوار" />
        <Card title="مقدمو الخدمة" value={providers.length} subtitle="حسابات مسجلة" />

        <div className="rounded-3xl border border-emerald-400/25 bg-emerald-500/10 p-6 shadow-xl">
          <div className="flex items-center justify-between gap-2"><div className="text-xs font-bold text-emerald-300">عمولة Salora المستحقة (10%)</div><button type="button" onClick={() => setCurrency((value) => value === "SYP" ? "USD" : "SYP")} className="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-[11px] font-black text-emerald-200">{currency === "SYP" ? "USD" : "SYP"}</button></div>
          <div className="mt-2 text-xl font-black text-white">{commissionMoney}</div>
          <div className="mt-1 text-xs text-emerald-100/60">إجمالي الدفعات المعتمدة: {grossMoney}</div>
        </div>

        <div className="rounded-3xl border border-blue-400/20 bg-blue-500/10 p-6"><div className="text-xs font-bold text-blue-300">من ملاك الصالات</div><div className="mt-2 text-xl font-black text-blue-100">{ownerCommissionMoney}</div><div className="mt-1 text-xs text-slate-500">حجوزات الصالات المدفوعة</div></div>
        <div className="rounded-3xl border border-violet-400/20 bg-violet-500/10 p-6"><div className="text-xs font-bold text-violet-300">من مقدمي الخدمات</div><div className="mt-2 text-xl font-black text-violet-100">{providerCommissionMoney}</div><div className="mt-1 text-xs text-slate-500">طلبات الخدمات المدفوعة</div></div>
        <div className="rounded-3xl border border-amber-400/20 bg-amber-400/10 p-6"><div className="text-xs font-bold text-amber-300">تحتاج مراجعة</div><div className="mt-2 text-3xl font-black text-amber-200">{metrics.pendingApprovals + metrics.pendingPayments}</div></div>
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div className="xl:col-span-2 rounded-3xl border border-white/10 bg-white/[.04] p-6"><div className="mb-4 flex items-center justify-between"><h3 className="text-sm font-black text-emerald-300">عمولة Salora والحجوزات خلال آخر ستة أشهر</h3><span className="text-xs text-slate-500">{currency}</span></div><div className="h-72"><ResponsiveContainer width="100%" height="100%"><AreaChart data={revenueData}><XAxis dataKey="name" stroke="#94a3b8" fontSize={12} /><YAxis stroke="#94a3b8" fontSize={12} /><Tooltip contentStyle={{ background: "#0f172a", border: "1px solid rgba(255,255,255,.1)", borderRadius: 16 }} /><Area type="monotone" dataKey="commission" stroke="#34d399" fill="rgba(52,211,153,.15)" strokeWidth={3} /></AreaChart></ResponsiveContainer></div></div>
        <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="mb-4 text-sm font-black text-blue-300">حالات الصالات</h3><div className="h-56"><ResponsiveContainer width="100%" height="100%"><PieChart><Pie data={venueStatusData} dataKey="value" innerRadius={55} outerRadius={82} paddingAngle={4}>{venueStatusData.map((item, index) => <Cell key={item.name} fill={colors[index]} />)}</Pie><Tooltip /></PieChart></ResponsiveContainer></div><div className="space-y-2 text-xs">{venueStatusData.map((item, index) => <div key={item.name} className="flex justify-between text-slate-300"><span style={{ color: colors[index] }}>● {item.name}</span><b>{item.value}</b></div>)}</div></div>
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="mb-4 text-sm font-black text-blue-300">أفضل الصالات</h3><div className="space-y-3">{topVenues.map((venue) => <div key={venue.id} className="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-950/30 p-4"><div><div className="font-bold">{venue.name}</div><div className="text-xs text-slate-400">{venue.bookings ?? 0} حجوزات مؤكدة/مكتملة</div></div><div className="text-right"><div className="font-black text-amber-300">⭐ {venue.rating || 0}</div></div></div>)}{topVenues.length === 0 && <div className="p-8 text-center text-slate-500">لا توجد بيانات.</div>}</div></div>
        <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="mb-4 text-sm font-black text-blue-300">حجم الحجوزات</h3><div className="h-64"><ResponsiveContainer width="100%" height="100%"><BarChart data={revenueData}><XAxis dataKey="name" stroke="#94a3b8" fontSize={12} /><YAxis stroke="#94a3b8" fontSize={12} allowDecimals={false} /><Tooltip /><Bar dataKey="bookings" fill="#818cf8" radius={[10,10,0,0]} /></BarChart></ResponsiveContainer></div></div>
      </div>

      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="mb-4 text-sm font-black text-blue-300">أحدث الصالات المسجلة</h3><div className="grid gap-3 md:grid-cols-2">{venues.slice(0, 6).map((venue) => <div key={venue.id} className="rounded-2xl bg-slate-950/40 p-4"><div className="font-bold">{venue.name}</div><div className="mt-1 text-xs text-slate-400">{venue.city} • {venue.capacity} ضيف • {arabicLabel(venue.status)}</div></div>)}{venues.length === 0 && <div className="p-8 text-center text-slate-500">لا توجد صالات مسجلة.</div>}</div></div>
    </div>
  );
}