import React, { useMemo } from "react";
import { ResponsiveContainer, BarChart, Bar, XAxis, YAxis, Tooltip, LineChart, Line, PieChart, Pie, Cell, AreaChart, Area } from "recharts";
import { useApp } from "../context/AppContext";

const colors = ["#22c55e", "#f59e0b", "#60a5fa", "#ef4444", "#a78bfa", "#14b8a6"];
const bookingStatusLabels = {
  pending_owner_review: "بانتظار المالك",
  owner_rejected: "مرفوض",
  pending_payment: "بانتظار الدفع",
  payment_under_review: "مراجعة الدفع",
  confirmed: "مؤكد",
  modification_requested: "طلب تعديل",
  cancellation_requested: "طلب إلغاء",
  cancelled: "ملغى",
  completed: "مكتمل"
};
const roleLabels = { admin: "إدارة", owner: "مالك صالة", provider: "مقدم خدمة", customer: "عميل" };

export default function ReportsPage() {
  const { reportData, bookings, venues, services, complaints, formatUsd, formatSyp, dataLoading } = useApp();

  const monthly = useMemo(() => (reportData?.monthly || []).map((item) => ({
    month: item.month,
    bookings: Number(item.bookings || 0),
    commissionUsd: Number(item.commission_usd ?? item.revenue_usd ?? 0),
    commissionSyp: Number(item.commission_syp ?? item.revenue_syp ?? 0),
    ownerCommissionUsd: Number(item.owner_commission_usd || 0),
    providerCommissionUsd: Number(item.provider_commission_usd || 0)
  })), [reportData]);

  const bookingStatus = useMemo(() => Object.entries(reportData?.booking_statuses || {}).map(([name, value]) => ({
    name: bookingStatusLabels[name] || name,
    value: Number(value || 0)
  })), [reportData]);

  const usersByRole = useMemo(() => Object.entries(reportData?.users_by_role || {}).map(([role, count]) => ({
    role: roleLabels[role] || role,
    count: Number(count || 0)
  })), [reportData]);

  const busiestTimes = useMemo(() => {
    const counts = new Map();
    bookings.forEach((booking) => {
      const time = String(booking.time || "غير محدد").slice(0, 5);
      counts.set(time, (counts.get(time) || 0) + 1);
    });
    return [...counts.entries()].map(([time, count]) => ({ time, bookings: count })).sort((a, b) => b.bookings - a.bookings).slice(0, 8);
  }, [bookings]);

  const topVenues = reportData?.top_venues?.length
    ? reportData.top_venues.map((venue) => ({ id: String(venue.id), name: venue.name_ar || venue.name_en, rating: Number(venue.rating_avg || 0), bookings: Number(venue.completed_bookings_count || 0) }))
    : [...venues].sort((a, b) => Number(b.rating || 0) - Number(a.rating || 0)).slice(0, 5);

  const pair = (syp, usd) => <><span className="block">{formatSyp(syp || 0)}</span><span className="mt-1 block text-sm text-slate-400">{formatUsd(usd || 0)}</span></>;

  if (dataLoading && !reportData) {
    return <div className="rounded-3xl border border-white/10 bg-white/[.04] p-10 text-center text-slate-300">جاري تحميل التقارير من الخادم...</div>;
  }

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div>
        <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">📈 التقارير والإحصائيات</h1>
        <p className="mt-2 text-sm text-slate-400">عمولة Salora أدناه محسوبة بنسبة 10% من الفواتير المدفوعة والمعتمدة، ومقسمة بين ملاك الصالات ومقدمي الخدمات.</p>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
        <Metric title="إجمالي عمولة Salora (10%)" value={pair(reportData?.commission_syp, reportData?.commission_usd)} tone="text-emerald-300" />
        <Metric title="عمولة ملاك الصالات" value={pair(reportData?.owner_commission_syp, reportData?.owner_commission_usd)} tone="text-blue-300" />
        <Metric title="عمولة مقدمي الخدمات" value={pair(reportData?.provider_commission_syp, reportData?.provider_commission_usd)} tone="text-violet-300" />
        <Metric title="إجمالي الدفعات المعتمدة" value={pair(reportData?.gross_revenue_syp, reportData?.gross_revenue_usd)} tone="text-amber-300" />
        <Metric title="الفواتير المدفوعة" value={reportData?.paid_invoice_count || 0} tone="text-cyan-300" />
        <Metric title="مدفوعات قيد المراجعة" value={reportData?.pending_payments || 0} tone="text-red-300" />
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <ChartCard title="عمولة Salora بالدولار خلال آخر ستة أشهر">
          <AreaChart data={monthly}><XAxis dataKey="month" stroke="#94a3b8" /><YAxis stroke="#94a3b8" /><Tooltip contentStyle={tooltipStyle} /><Area type="monotone" dataKey="commissionUsd" stroke="#34d399" fill="rgba(52,211,153,.18)" strokeWidth={3} /></AreaChart>
        </ChartCard>
        <ChartCard title="توزيع العمولة بين الصالات والخدمات بالدولار">
          <BarChart data={monthly}><XAxis dataKey="month" stroke="#94a3b8" /><YAxis stroke="#94a3b8" /><Tooltip contentStyle={tooltipStyle} /><Bar dataKey="ownerCommissionUsd" name="ملاك الصالات" fill="#60a5fa" radius={[8,8,0,0]} /><Bar dataKey="providerCommissionUsd" name="مقدمو الخدمات" fill="#a78bfa" radius={[8,8,0,0]} /></BarChart>
        </ChartCard>
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <ChartCard title="حالات الحجوزات" compact>
          <PieChart><Pie data={bookingStatus} dataKey="value" nameKey="name" innerRadius={55} outerRadius={88} paddingAngle={4}>{bookingStatus.map((entry, index) => <Cell key={entry.name} fill={colors[index % colors.length]} />)}</Pie><Tooltip /></PieChart>
        </ChartCard>
        <ChartCard title="المستخدمون حسب الدور" compact>
          <BarChart data={usersByRole}><XAxis dataKey="role" stroke="#94a3b8" /><YAxis stroke="#94a3b8" allowDecimals={false} /><Tooltip contentStyle={tooltipStyle} /><Bar dataKey="count" fill="#22c55e" radius={[10, 10, 0, 0]} /></BarChart>
        </ChartCard>
        <ChartCard title="عدد الحجوزات شهرياً" compact>
          <LineChart data={monthly}><XAxis dataKey="month" stroke="#94a3b8" /><YAxis stroke="#94a3b8" allowDecimals={false} /><Tooltip contentStyle={tooltipStyle} /><Line type="monotone" dataKey="bookings" stroke="#f59e0b" strokeWidth={3} dot={{ r: 4 }} /></LineChart>
        </ChartCard>
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <ChartCard title="أوقات الحجز الأكثر استخداماً">
          <BarChart data={busiestTimes}><XAxis dataKey="time" stroke="#94a3b8" /><YAxis stroke="#94a3b8" allowDecimals={false} /><Tooltip contentStyle={tooltipStyle} /><Bar dataKey="bookings" fill="#818cf8" radius={[10, 10, 0, 0]} /></BarChart>
        </ChartCard>
        <section className="rounded-3xl border border-white/10 bg-white/[.04] p-6">
          <h3 className="mb-4 text-sm font-black text-blue-300">ملخص النظام</h3>
          <div className="grid gap-3 sm:grid-cols-2">
            <Summary label="إجمالي المستخدمين" value={reportData?.users || 0} />
            <Summary label="إجمالي الصالات" value={reportData?.venues || 0} />
            <Summary label="الخدمات المعتمدة" value={reportData?.services ?? services.filter((service) => service.status === "Approved").length} />
            <Summary label="الشكاوى المفتوحة" value={reportData?.open_complaints ?? complaints.filter((item) => ["Open", "In Progress"].includes(item.status)).length} />
          </div>
        </section>
      </div>

      <section className="rounded-3xl border border-white/10 bg-white/[.04] p-6">
        <h3 className="mb-4 text-sm font-black text-blue-300">أفضل الصالات حسب الحجوزات المكتملة</h3>
        <div className="grid gap-3 md:grid-cols-2">
          {topVenues.map((venue) => <div key={venue.id} className="flex items-center justify-between rounded-2xl bg-slate-950/40 p-4"><div><b>{venue.name}</b><div className="text-xs text-slate-500">{venue.bookings ?? 0} حجوزات مؤكدة/مكتملة</div></div><div className="font-black text-amber-300">⭐ {venue.rating || 0}</div></div>)}
          {topVenues.length === 0 && <Empty />}
        </div>
      </section>
    </div>
  );
}

const tooltipStyle = { backgroundColor: "#0f172a", border: "1px solid rgba(255,255,255,.1)", borderRadius: 14 };
function Metric({ title, value, tone }) { return <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><div className="text-xs font-bold text-slate-500">{title}</div><div className={`mt-2 text-2xl font-black ${tone}`}>{value}</div></div>; }
function ChartCard({ title, children, compact = false }) { return <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><h3 className="mb-4 text-sm font-black text-blue-300">{title}</h3><div className={compact ? "h-64" : "h-72"}><ResponsiveContainer width="100%" height="100%">{children}</ResponsiveContainer></div></div>; }
function Summary({ label, value }) { return <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-4"><div className="text-xs text-slate-500">{label}</div><div className="mt-1 text-2xl font-black text-white">{value}</div></div>; }
function Empty() { return <div className="rounded-2xl border border-white/10 p-6 text-center text-sm text-slate-500">لا توجد بيانات كافية بعد.</div>; }