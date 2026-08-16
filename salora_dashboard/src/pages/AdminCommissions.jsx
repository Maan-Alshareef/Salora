import React, { useCallback, useEffect, useMemo, useState } from "react";
import { dashboardApi } from "../services/apiClient";

const statusLabels = {
  uncollected: "غير محصلة",
  partial: "تحصيل جزئي",
  collected: "تم التحصيل",
  overdue: "متأخرة",
  waived: "معفاة",
  disputed: "عليها نزاع",
  cancelled: "ملغاة"
};

const statusStyles = {
  uncollected: "bg-amber-500/15 text-amber-300 border-amber-500/25",
  partial: "bg-blue-500/15 text-blue-300 border-blue-500/25",
  collected: "bg-emerald-500/15 text-emerald-300 border-emerald-500/25",
  overdue: "bg-orange-500/15 text-orange-300 border-orange-500/25",
  waived: "bg-slate-500/15 text-slate-300 border-slate-500/25",
  disputed: "bg-red-500/15 text-red-300 border-red-500/25",
  cancelled: "bg-zinc-500/15 text-zinc-400 border-zinc-500/25"
};

const initialEdit = {
  status: "uncollected",
  collected_syp: "",
  collected_usd: "",
  collection_method: "",
  collection_reference: "",
  notes: ""
};

export default function AdminCommissions() {
  const [payload, setPayload] = useState({ summary: {}, items: [] });
  const [filters, setFilters] = useState({ status: "", source_type: "", search: "" });
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState(null);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [editing, setEditing] = useState(null);
  const [editForm, setEditForm] = useState(initialEdit);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const result = await dashboardApi.admin.commissions(filters);
      setPayload({ summary: result?.summary || {}, items: result?.items || [] });
    } catch (requestError) {
      setError(requestError?.message || "تعذر تحميل سجل العمولات.");
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [load]);

  const summary = payload.summary || {};
  const items = payload.items || [];

  const collect = async (item) => {
    if (!window.confirm(`تأكيد تحصيل عمولة ${item.source_reference || `#${item.id}`} كاملة؟`)) return;
    setBusyId(item.id);
    setMessage("");
    setError("");
    try {
      await dashboardApi.admin.collectCommission(item.id, {});
      setMessage("تم التحصيل، وأضيفت العمولة مباشرة إلى أرباح Salora المحصلة.");
      await load();
    } catch (requestError) {
      setError(requestError?.message || "تعذر تسجيل التحصيل.");
    } finally {
      setBusyId(null);
    }
  };

  const openEdit = (item) => {
    setEditing(item);
    setEditForm({
      status: item.status || "uncollected",
      collected_syp: numberOrEmpty(item.collected_syp),
      collected_usd: numberOrEmpty(item.collected_usd),
      collection_method: item.collection_method || "",
      collection_reference: item.collection_reference || "",
      notes: item.notes || ""
    });
  };

  const saveEdit = async (event) => {
    event.preventDefault();
    if (!editing) return;
    setBusyId(editing.id);
    setMessage("");
    setError("");
    try {
      await dashboardApi.admin.updateCommission(editing.id, {
        ...editForm,
        collected_syp: editForm.collected_syp === "" ? 0 : Number(editForm.collected_syp),
        collected_usd: editForm.collected_usd === "" ? 0 : Number(editForm.collected_usd)
      });
      setEditing(null);
      setMessage("تم تحديث حالة العمولة.");
      await load();
    } catch (requestError) {
      setError(requestError?.message || "تعذر تحديث العمولة.");
    } finally {
      setBusyId(null);
    }
  };

  const cards = useMemo(() => [
    { title: "الربح المحصل فعلياً", value: moneyPair(summary.collected_syp, summary.collected_usd), tone: "text-emerald-300", hint: `${englishInteger(summary.collected_records)} عمليات محصلة` },
    { title: "عمولة غير محصلة", value: moneyPair(summary.uncollected_syp, summary.uncollected_usd), tone: "text-amber-300", hint: `${englishInteger(summary.uncollected_records)} عمليات بانتظار التحصيل` },
    { title: "إجمالي العمولة المستحقة", value: moneyPair(summary.commission_syp, summary.commission_usd), tone: "text-blue-300", hint: "10% من قيمة العمليات المعتمدة" },
    { title: "عمولة ملاك الصالات", value: moneyPair(summary.owner_commission_syp, summary.owner_commission_usd), tone: "text-orange-300", hint: "حجوزات الصالات" },
    { title: "عمولة مقدمي الخدمات", value: moneyPair(summary.provider_commission_syp, summary.provider_commission_usd), tone: "text-violet-300", hint: "طلبات الخدمات المقبولة" },
    { title: "قيمة العمليات", value: moneyPair(summary.gross_syp, summary.gross_usd), tone: "text-slate-100", hint: `${englishInteger(summary.active_records)} سجلات فعالة` }
  ], [summary]);

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-emerald-300 to-blue-300">💰 أرباح وعمولات Salora</h1>
          <p className="mt-2 max-w-3xl text-sm leading-7 text-slate-400">كل حجز صالة يثبته المالك بعد قبول إثبات الدفع، وكل طلب خدمة يقبله مقدم الخدمة، ينزل هنا تلقائياً بعمولة 10%. الربح المحصل يزيد فقط عند تسجيل «تم التحصيل».</p>
        </div>
        <button onClick={load} disabled={loading} className="rounded-xl border border-white/10 bg-white/5 px-5 py-3 text-sm font-bold hover:bg-white/10 disabled:opacity-50">↻ تحديث</button>
      </div>

      {message && <div className="rounded-2xl border border-emerald-400/25 bg-emerald-500/10 p-4 text-sm font-bold text-emerald-200">✅ {message}</div>}
      {error && <div className="rounded-2xl border border-red-400/25 bg-red-500/10 p-4 text-sm font-bold text-red-200">⚠️ {error}</div>}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {cards.map((card) => <MetricCard key={card.title} {...card} />)}
      </div>

      <section className="rounded-3xl border border-white/10 bg-white/[.04] p-5">
        <div className="grid gap-3 md:grid-cols-4">
          <input value={filters.search} onChange={(event) => setFilters((old) => ({ ...old, search: event.target.value }))} placeholder="بحث بالرقم أو الاسم..." className="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm outline-none focus:border-blue-400/50" />
          <select value={filters.source_type} onChange={(event) => setFilters((old) => ({ ...old, source_type: event.target.value }))} className="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm outline-none">
            <option value="">كل الأنواع</option>
            <option value="booking">حجوزات الصالات</option>
            <option value="provider_service_request">خدمات مقدمي الخدمات</option>
          </select>
          <select value={filters.status} onChange={(event) => setFilters((old) => ({ ...old, status: event.target.value }))} className="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm outline-none">
            <option value="">كل حالات التحصيل</option>
            {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
          <button onClick={() => setFilters({ status: "", source_type: "", search: "" })} className="rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-bold hover:bg-white/10">مسح الفلاتر</button>
        </div>
      </section>

      <section className="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04]">
        <div className="overflow-x-auto">
          <table className="min-w-[1180px] w-full text-sm">
            <thead className="bg-slate-950/70 text-xs text-slate-400">
              <tr>
                <th className="px-4 py-4 text-right">العملية</th>
                <th className="px-4 py-4 text-right">المالك / مقدم الخدمة</th>
                <th className="px-4 py-4 text-right">العميل</th>
                <th className="px-4 py-4 text-right">قيمة العملية</th>
                <th className="px-4 py-4 text-right">عمولة 10%</th>
                <th className="px-4 py-4 text-right">الصافي 90%</th>
                <th className="px-4 py-4 text-right">المحصل</th>
                <th className="px-4 py-4 text-center">الحالة</th>
                <th className="px-4 py-4 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {items.map((item) => (
                <tr key={item.id} className="align-top hover:bg-white/[.025]">
                  <td className="px-4 py-4"><div className="font-black text-white">{item.source_reference || `#${item.id}`}</div><div className="mt-1 text-xs text-slate-400">{item.source_title || sourceLabel(item.source_type)}</div><div className="mt-1 text-[11px] text-slate-500">اعتماد: {dateTime(item.approved_at)}</div><div className="mt-1 text-[11px] text-blue-300/80">آخر تحديث مالي: {dateTime(item.updated_at)}</div></td>
                  <td className="px-4 py-4"><div className="font-bold">{item.business_user?.name || "حساب محذوف"}</div><div className="mt-1 text-xs text-slate-500">{item.business_role === "owner" ? "مالك صالة" : "مقدم خدمة"}</div></td>
                  <td className="px-4 py-4"><div className="font-bold text-slate-200">{item.customer?.name || "غير محدد"}</div><div className="mt-1 text-xs text-slate-500">{item.booking?.venue?.name_ar || item.booking?.venue?.name_en || ""}</div></td>
                  <td className="px-4 py-4 font-bold text-slate-200">{moneyPair(item.gross_syp, item.gross_usd)}</td>
                  <td className="px-4 py-4 font-black text-blue-300">{moneyPair(item.commission_syp, item.commission_usd)}</td>
                  <td className="px-4 py-4 font-bold text-slate-300">{moneyPair(item.net_syp, item.net_usd)}</td>
                  <td className="px-4 py-4 font-black text-emerald-300">{moneyPair(item.collected_syp, item.collected_usd)}</td>
                  <td className="px-4 py-4 text-center"><span className={`inline-flex rounded-full border px-3 py-1 text-xs font-black ${statusStyles[item.status] || statusStyles.uncollected}`}>{statusLabels[item.status] || item.status}</span></td>
                  <td className="px-4 py-4"><div className="flex justify-center gap-2">{item.status !== "collected" && !["cancelled", "waived"].includes(item.status) && <button disabled={busyId === item.id} onClick={() => collect(item)} className="rounded-xl bg-emerald-500/15 px-3 py-2 text-xs font-black text-emerald-300 hover:bg-emerald-500/25 disabled:opacity-50">تم التحصيل</button>}<button disabled={busyId === item.id} onClick={() => openEdit(item)} className="rounded-xl bg-blue-500/15 px-3 py-2 text-xs font-black text-blue-300 hover:bg-blue-500/25 disabled:opacity-50">تعديل الحالة</button></div></td>
                </tr>
              ))}
              {!loading && items.length === 0 && <tr><td colSpan="9" className="px-4 py-14 text-center text-slate-500">لا توجد عمولات مطابقة. تظهر عمولة الصالة بعد تثبيت الحجز بقبول الدفع، وعمولة الخدمة بعد قبول مقدم الخدمة.</td></tr>}
              {loading && <tr><td colSpan="9" className="px-4 py-14 text-center text-slate-300">جاري تحميل العمولات...</td></tr>}
            </tbody>
          </table>
        </div>
      </section>

      {editing && (
        <div className="fixed inset-0 z-[99999] grid place-items-center overflow-y-auto bg-slate-950/85 p-4 backdrop-blur-xl">
          <form onSubmit={saveEdit} className="my-6 w-full max-w-2xl rounded-3xl border border-blue-400/25 bg-slate-950 p-6 shadow-2xl">
            <div className="mb-5 flex items-start justify-between"><div><h2 className="text-2xl font-black">تعديل حالة العمولة</h2><p className="mt-1 text-sm text-slate-400">{editing.source_reference} • العمولة {moneyPair(editing.commission_syp, editing.commission_usd)}</p></div><button type="button" onClick={() => setEditing(null)} className="rounded-xl bg-white/5 px-3 py-2">✕</button></div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="الحالة"><select value={editForm.status} onChange={(event) => setEditForm((old) => ({ ...old, status: event.target.value }))} className="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none focus:border-blue-400/50"><option value="uncollected">غير محصلة</option><option value="partial">تحصيل جزئي</option><option value="collected">تم التحصيل</option><option value="overdue">متأخرة</option><option value="waived">معفاة</option><option value="disputed">عليها نزاع</option><option value="cancelled">ملغاة</option></select></Field>
              <Field label="طريقة التحصيل"><input value={editForm.collection_method} onChange={(event) => setEditForm((old) => ({ ...old, collection_method: event.target.value }))} className="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none focus:border-blue-400/50" placeholder="تحويل / نقدي / غيره" /></Field>
              <Field label="المبلغ المحصل بالليرة"><input type="number" min="0" step="0.01" value={editForm.collected_syp} onChange={(event) => setEditForm((old) => ({ ...old, collected_syp: event.target.value }))} className="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none focus:border-blue-400/50" /></Field>
              <Field label="المبلغ المحصل بالدولار"><input type="number" min="0" step="0.01" value={editForm.collected_usd} onChange={(event) => setEditForm((old) => ({ ...old, collected_usd: event.target.value }))} className="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none focus:border-blue-400/50" /></Field>
              <Field label="رقم المرجع"><input value={editForm.collection_reference} onChange={(event) => setEditForm((old) => ({ ...old, collection_reference: event.target.value }))} className="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none focus:border-blue-400/50" /></Field>
              <Field label="ملاحظات"><input value={editForm.notes} onChange={(event) => setEditForm((old) => ({ ...old, notes: event.target.value }))} className="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none focus:border-blue-400/50" /></Field>
            </div>
            <div className="mt-6 flex gap-3"><button disabled={busyId === editing.id} className="flex-1 rounded-xl bg-blue-600 px-4 py-3 font-black hover:bg-blue-500 disabled:opacity-50">حفظ</button><button type="button" onClick={() => setEditing(null)} className="rounded-xl bg-white/5 px-5 py-3 font-bold hover:bg-white/10">إلغاء</button></div>
          </form>
        </div>
      )}
    </div>
  );
}

function MetricCard({ title, value, tone, hint }) { return <div className="min-w-0 overflow-hidden rounded-3xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs font-bold text-slate-500">{title}</div><div className={`mt-2 max-w-full break-words text-[clamp(1.05rem,2vw,1.5rem)] font-black leading-tight [overflow-wrap:anywhere] ${tone}`}>{value}</div><div className="mt-2 text-xs text-slate-500">{hint}</div></div>; }
function Field({ label, children }) { return <label className="space-y-2"><span className="text-xs font-bold text-slate-400">{label}</span>{children}</label>; }
function sourceLabel(value) { return value === "booking" ? "حجز صالة" : "طلب خدمة"; }
function numberOrEmpty(value) { const number = Number(value || 0); return number > 0 ? String(number) : ""; }
function englishInteger(value) { return `\u2066${new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 }).format(Number(value || 0))}\u2069`; }
function dateTime(value) { if (!value) return ""; try { return `\u2066${new Intl.DateTimeFormat("ar-SY-u-nu-latn", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value))}\u2069`; } catch (_) { return String(value); } }
function moneyPair(syp, usd) { const parts = []; const sypValue = Number(syp || 0); const usdValue = Number(usd || 0); const isolate = (value) => `\u2066${value}\u2069`; if (sypValue || !usdValue) parts.push(isolate(`${new Intl.NumberFormat("en-US", { maximumFractionDigits: 2 }).format(sypValue)} ل.س`)); if (usdValue) parts.push(isolate(`$${new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(usdValue)}`)); return parts.join(" • "); }