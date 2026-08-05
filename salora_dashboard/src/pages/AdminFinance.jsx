import React, { useCallback, useEffect, useMemo, useState } from "react";
import { dashboardApi } from "../services/apiClient";

const money = (value, currency = "SYP") => {
  const number = Number(value || 0);
  return `${new Intl.NumberFormat("ar-SY", { maximumFractionDigits: currency === "USD" ? 2 : 0 }).format(number)} ${currency === "USD" ? "$" : "ل.س"}`;
};

const commissionLabels = {
  not_due: "غير مستحقة",
  due: "مستحقة",
  collected: "تم تحصيلها",
  waived: "معفاة",
  reversed: "معكوسة / مسترجعة",
};

const bookingLabels = {
  confirmed: "مؤكد",
  completed: "مكتمل",
  pending_payment: "بانتظار إثبات الدفع",
  payment_under_review: "الإثبات قيد مراجعة المالك",
  owner_rejected: "مرفوض من المالك",
  cancelled: "ملغي",
  expired: "منتهي",
};

const paymentLabels = {
  unpaid: "بانتظار الدفع",
  proof_uploaded: "الإثبات قيد المراجعة",
  approved: "مدفوع ومؤكد",
  rejected: "الإثبات مرفوض",
};

export default function AdminFinance() {
  const [summary, setSummary] = useState(null);
  const [venueRows, setVenueRows] = useState([]);
  const [serviceRows, setServiceRows] = useState([]);
  const [tab, setTab] = useState("venues");
  const [filter, setFilter] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [rates, setRates] = useState({ venue: 10, provider: 10 });
  const [savingRates, setSavingRates] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [summaryData, venueTransactions, serviceTransactions, settings] = await Promise.all([
        dashboardApi.admin.financeSummary(),
        dashboardApi.admin.financeTransactions({ per_page: 100, commission_status: filter }),
        dashboardApi.admin.financeServiceTransactions({ per_page: 100, commission_status: filter }),
        dashboardApi.admin.settings(),
      ]);
      setSummary(summaryData || {});
      setVenueRows(venueTransactions?.data || venueTransactions || []);
      setServiceRows(serviceTransactions?.data || serviceTransactions || []);
      const list = Array.isArray(settings) ? settings : [];
      const find = (key, fallback) => Number(list.find((item) => item.key === key)?.value ?? fallback);
      setRates({ venue: find("platform_commission_percent", 10), provider: find("provider_commission_percent", 10) });
    } catch (exception) {
      setError(exception?.message || "تعذر تحميل البيانات المالية.");
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => { load(); }, [load]);

  const cards = useMemo(() => summary ? [
    ["حجوزات الصالات المؤكدة", summary.confirmed_bookings || 0, "🏛️"],
    ["الخدمات المؤكدة", summary.confirmed_services || 0, "🧩"],
    ["مبيعات الصالات", money(summary.gross_sales_syp), "📈"],
    ["مبيعات الخدمات", money(summary.service_gross_sales_syp), "🧾"],
    ["عمولات الصالات", money(summary.platform_earnings_syp), "💰"],
    ["عمولات مقدمي الخدمة", money(summary.provider_platform_earnings_syp), "🤝"],
    ["إجمالي أرباح Salora", money(summary.total_platform_earnings_syp), "✨"],
    ["المحصل فعلياً", money(summary.collected_syp), "✅"],
    ["المستحق غير المحصل", money(summary.outstanding_syp), "⏳"],
  ] : [], [summary]);

  const updateVenueStatus = async (row, status) => {
    const notes = window.prompt("ملاحظة اختيارية على العملية:", row.commission_notes || "");
    if (notes === null) return;
    await dashboardApi.admin.updateBookingCommission(row.id, status, notes.trim() || null);
    await load();
  };

  const updateServiceStatus = async (row, status) => {
    const notes = window.prompt("ملاحظة اختيارية على عمولة مقدم الخدمة:", row.commission_notes || "");
    if (notes === null) return;
    await dashboardApi.admin.updateProviderCommission(row.id, status, notes.trim() || null);
    await load();
  };

  const saveRates = async () => {
    if (rates.venue < 0 || rates.venue > 100 || rates.provider < 0 || rates.provider > 100) return window.alert("النسبة يجب أن تكون بين 0 و100.");
    setSavingRates(true);
    try {
      await Promise.all([
        dashboardApi.admin.updateSettings({ key: "platform_commission_percent", value: String(rates.venue), type: "number" }),
        dashboardApi.admin.updateSettings({ key: "provider_commission_percent", value: String(rates.provider), type: "number" }),
      ]);
      window.alert("تم حفظ نسب العمولة. تُطبق النسبة الجديدة على العمليات التي ستتأكد بعد الآن.");
    } catch (exception) {
      window.alert(exception?.message || "تعذر حفظ النسب.");
    } finally {
      setSavingRates(false);
    }
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="bg-gradient-to-r from-emerald-300 to-white bg-clip-text text-3xl font-black text-transparent">💰 المالية والأرباح</h1>
          <p className="mt-2 text-sm text-slate-400">حساب منفصل لعمولات الصالات ومقدمي الخدمات. التحصيل يدوي وفق إثباتات الدفع المعتمدة.</p>
        </div>
        <button onClick={load} className="rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-2 text-sm font-black text-emerald-200">تحديث البيانات</button>
      </div>

      {error && <div className="rounded-2xl border border-red-400/30 bg-red-500/10 p-4 text-red-100">{error}</div>}
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {cards.map(([label, value, icon]) => <div key={label} className="rounded-3xl border border-white/10 bg-white/[.04] p-5"><div className="text-2xl">{icon}</div><div className="mt-3 text-xs font-bold text-slate-400">{label}</div><div className="mt-2 text-2xl font-black text-emerald-300">{value}</div></div>)}
      </div>

      <div className="rounded-3xl border border-emerald-400/20 bg-emerald-500/[.06] p-5">
        <h2 className="text-lg font-black text-emerald-200">ضبط نسب العمولة</h2>
        <p className="mt-1 text-xs text-slate-400">القيمة محفوظة في قاعدة البيانات وتُستخدم عند تأكيد دفعة جديدة. العمليات القديمة تحافظ على النسبة التي حُسبت بها.</p>
        <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
          <label className="text-sm font-bold">عمولة الصالات %<input type="number" min="0" max="100" step="0.5" value={rates.venue} onChange={(e) => setRates((r) => ({ ...r, venue: Number(e.target.value) }))} className="field-surface mt-2" /></label>
          <label className="text-sm font-bold">عمولة مقدمي الخدمة %<input type="number" min="0" max="100" step="0.5" value={rates.provider} onChange={(e) => setRates((r) => ({ ...r, provider: Number(e.target.value) }))} className="field-surface mt-2" /></label>
          <button disabled={savingRates} onClick={saveRates} className="self-end rounded-xl bg-emerald-500 px-5 py-3 font-black text-slate-950 disabled:opacity-50">{savingRates ? "جارٍ الحفظ..." : "حفظ النسب"}</button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-white/10 bg-white/[.03] p-3">
        <button onClick={() => setTab("venues")} className={`rounded-xl px-4 py-2 text-sm font-black ${tab === "venues" ? "bg-emerald-500 text-slate-950" : "bg-white/5"}`}>عمولات الصالات</button>
        <button onClick={() => setTab("services")} className={`rounded-xl px-4 py-2 text-sm font-black ${tab === "services" ? "bg-emerald-500 text-slate-950" : "bg-white/5"}`}>عمولات الخدمات</button>
        <span className="mr-auto text-xs font-bold text-slate-400">تصفية:</span>
        {["", "due", "collected", "waived", "reversed"].map((value) => <button key={value || "all"} onClick={() => setFilter(value)} className={`rounded-xl px-3 py-2 text-xs font-bold ${filter === value ? "bg-emerald-500/20 text-emerald-200" : "bg-white/5 text-slate-400"}`}>{value ? commissionLabels[value] : "الكل"}</button>)}
      </div>

      {tab === "venues" ? <VenueTable rows={venueRows} loading={loading} onStatus={updateVenueStatus} /> : <ServiceTable rows={serviceRows} loading={loading} onStatus={updateServiceStatus} />}
    </div>
  );
}

function VenueTable({ rows, loading, onStatus }) {
  return <TableShell loading={loading} empty={!rows.length} headers={["الحجز / الفاتورة", "الصالة والمالك", "الإجمالي", "عمولة Salora", "صافي المالك", "الحالة", "حالة العمولة", "الإجراء"]}>
    {rows.map((row) => <tr key={row.id} className="border-t border-white/5 hover:bg-white/[.03]">
      <td className="px-4 py-4"><div className="font-mono font-bold text-emerald-300">{row.booking_number}</div><div className="text-xs text-slate-500">{row.invoice?.invoice_number || "لا توجد فاتورة"}</div></td>
      <td className="px-4 py-4"><div className="font-bold">{row.venue?.name_ar || row.venue?.name_en || "صالة"}</div><div className="text-xs text-slate-500">{row.owner?.name} • {row.owner?.email}</div></td>
      <td className="px-4 py-4 font-bold">{money(row.total_syp)}</td>
      <td className="px-4 py-4 font-black text-emerald-300">{money(row.platform_commission_syp)}<div className="text-[10px] text-slate-500">{Number(row.platform_commission_rate || 10)}%</div></td>
      <td className="px-4 py-4">{money(row.owner_net_syp)}</td>
      <td className="px-4 py-4 text-xs">{bookingLabels[row.booking_status] || row.booking_status}</td>
      <td className="px-4 py-4">{commissionLabels[row.commission_status] || row.commission_status}</td>
      <td className="px-4 py-4"><StatusSelect value={row.commission_status} disabled={!['confirmed','completed'].includes(row.booking_status)} onChange={(status) => onStatus(row, status)} /></td>
    </tr>)}
  </TableShell>;
}

function ServiceTable({ rows, loading, onStatus }) {
  return <TableShell loading={loading} empty={!rows.length} headers={["فاتورة الخدمة", "الخدمة ومقدمها", "الحجز والصالة", "المبلغ", "عمولة Salora", "صافي المقدم", "الدفع", "حالة العمولة", "الإجراء"]}>
    {rows.map((row) => <tr key={row.id} className="border-t border-white/5 hover:bg-white/[.03]">
      <td className="px-4 py-4 font-mono font-bold text-emerald-300">{row.invoice_number || `SRV-${row.id}`}</td>
      <td className="px-4 py-4"><div className="font-bold">{row.service_name}</div><div className="text-xs text-slate-500">{row.provider?.name} • {row.provider?.email}</div></td>
      <td className="px-4 py-4"><div>{row.booking?.booking_number}</div><div className="text-xs text-slate-500">{row.booking?.venue?.name_ar || row.booking?.venue?.name_en}</div></td>
      <td className="px-4 py-4 font-bold">{money(row.price_syp)}</td>
      <td className="px-4 py-4 font-black text-emerald-300">{money(row.provider_commission_syp)}<div className="text-[10px] text-slate-500">{Number(row.provider_commission_rate || 10)}%</div></td>
      <td className="px-4 py-4">{money(row.provider_net_syp)}</td>
      <td className="px-4 py-4 text-xs">{paymentLabels[row.payment_status] || row.payment_status}</td>
      <td className="px-4 py-4">{commissionLabels[row.commission_status] || row.commission_status}</td>
      <td className="px-4 py-4"><StatusSelect value={row.commission_status} disabled={row.payment_status !== 'approved'} onChange={(status) => onStatus(row, status)} /></td>
    </tr>)}
  </TableShell>;
}

function StatusSelect({ value, disabled, onChange }) {
  return <select value={value || "not_due"} onChange={(event) => onChange(event.target.value)} className="field-surface min-w-40 text-xs" disabled={disabled}>
    <option value="not_due">غير مستحقة</option><option value="due">مستحقة</option><option value="collected">تم تحصيلها</option><option value="waived">معفاة</option><option value="reversed">معكوسة / مسترجعة</option>
  </select>;
}

function TableShell({ headers, children, loading, empty }) {
  return <div className="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04]">
    <div className="overflow-x-auto"><table className="w-full min-w-[1250px] text-right text-sm"><thead className="bg-slate-950/60 text-xs text-emerald-300"><tr>{headers.map((header) => <th key={header} className="px-4 py-4">{header}</th>)}</tr></thead><tbody>{children}</tbody></table></div>
    {!loading && empty && <div className="p-10 text-center text-slate-400">لا توجد حركات مالية مطابقة.</div>}
    {loading && <div className="p-10 text-center text-slate-400">جاري تحميل البيانات المالية...</div>}
  </div>;
}
