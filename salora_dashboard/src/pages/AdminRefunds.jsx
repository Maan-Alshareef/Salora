import React, { useEffect, useMemo, useState } from "react";
import { apiClient } from "../services/apiClient";

const labels = {
  pending_transfer: "بانتظار تنفيذ الاسترداد",
  overdue: "متأخر",
  transferred: "تم التحويل",
  confirmed: "تم التأكيد",
  no_refund: "لا يوجد استرداد",
  disputed: "نزاع",
  rejected: "مرفوض",
};

const tone = {
  pending_transfer: "bg-amber-500/15 text-amber-200",
  overdue: "bg-red-500/15 text-red-200",
  transferred: "bg-blue-500/15 text-blue-200",
  confirmed: "bg-emerald-500/15 text-emerald-200",
  no_refund: "bg-slate-500/15 text-slate-300",
  disputed: "bg-red-500/15 text-red-200",
};

export default function AdminRefunds() {
  const [items, setItems] = useState([]);
  const [status, setStatus] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true); setError("");
    try { setItems(await apiClient.get(`/admin/payment-refunds${status ? `?status=${status}` : ""}`)); }
    catch (requestError) { setError(requestError.message); }
    finally { setLoading(false); }
  }
  useEffect(() => { load(); }, [status]);

  const totals = useMemo(() => items.reduce((sum, item) => sum + Number(item.amount_syp || 0), 0), [items]);

  return <div className="space-y-6 pb-12 text-white" dir="rtl">
    <div><h1 className="text-3xl font-black">↩️ الاستردادات</h1><p className="mt-2 text-sm text-slate-400">سجل مركزي لكل استرداد ناتج عن إلغاء الحجز. الأدمن يراقب، والمالك ينفذ الاسترداد المستحق.</p></div>
    <div className="grid gap-4 md:grid-cols-3"><div className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">عدد الطلبات</div><div className="mt-2 text-3xl font-black">{items.length}</div></div><div className="rounded-2xl border border-white/10 bg-white/[.04] p-5 md:col-span-2"><div className="text-xs text-slate-500">إجمالي المبالغ المعروضة</div><div className="mt-2 text-3xl font-black text-amber-300">{totals.toLocaleString("en-US")} ل.س</div></div></div>
    <select className="field-surface w-full max-w-sm" value={status} onChange={(event) => setStatus(event.target.value)}><option value="">كل الحالات</option>{Object.entries(labels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
    {error && <div className="rounded-xl bg-red-500/10 p-4 text-red-200">{error}</div>}
    {loading ? <div className="rounded-2xl border border-white/10 p-8 text-center text-slate-400">جاري التحميل...</div> : <div className="space-y-3">{items.map((item) => <article key={item.id} className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="flex flex-wrap items-start justify-between gap-3"><div><div className="font-black">استرداد #{item.id} • حجز #{item.booking_id || "-"}</div><div className="mt-2 text-sm text-slate-400">العميل: {item.customer?.name || "-"} • المالك/المستفيد السابق: {item.payee?.name || "-"}</div></div><span className={`rounded-full px-3 py-1 text-xs font-black ${tone[item.status] || "bg-slate-500/15 text-slate-300"}`}>{labels[item.status] || item.status}</span></div><div className="mt-4 grid gap-3 text-sm md:grid-cols-2"><div>النسبة: <b>{Number(item.refund_percent || 0).toLocaleString("en-US")}%</b></div><div>المبلغ: <b>{Number(item.amount_syp || 0).toLocaleString("en-US")} ل.س</b></div></div>{item.reason && <div className="mt-3 rounded-xl bg-slate-950/40 p-3 text-sm text-slate-300">السبب: {item.reason}</div>}</article>)}{!items.length && <div className="rounded-2xl border border-white/10 p-8 text-center text-slate-400">لا توجد استردادات.</div>}</div>}
  </div>;
}
