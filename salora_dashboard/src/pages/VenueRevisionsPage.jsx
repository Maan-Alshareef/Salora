import React, { useEffect, useState } from "react";
import { dashboardApi } from "../services/apiClient";
import { resolveMediaUrl } from "../utils/mediaUrl";

const assetUrl = resolveMediaUrl;
const labels = { name_ar: "الاسم", name_en: "الاسم الإنكليزي", description_ar: "الوصف", description_en: "الوصف الإنكليزي", city: "المدينة", address: "العنوان", map_url: "رابط الخريطة", google_place_id: "معرّف المكان", latitude: "خط العرض", longitude: "خط الطول", capacity: "السعة", price_syp: "السعر بالليرة", price_usd: "السعر بالدولار", currency_base: "عملة التسعير", opening_hours: "أوقات العمل", amenities: "المزايا", policies: "السياسات", vendor_categories: "فئات الخدمات", event_types: "أنواع المناسبات", services: "الخدمات", images: "الصور", videos: "الفيديوهات" };
const formatValue = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  if (Array.isArray(value)) return value.map((item) => typeof item === "object" ? item.name_ar || item.name_en || item.image_url || JSON.stringify(item) : item).join("، ") || "—";
  if (typeof value === "object") return Object.entries(value).map(([key, item]) => `${key}: ${typeof item === "object" ? `${item.enabled ? `${item.open}-${item.close}` : "مغلق"}` : item}`).join(" | ");
  return String(value);
};

export default function VenueRevisionsPage() {
  const [status, setStatus] = useState("pending");
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState("");
  const load = async () => { setLoading(true); setError(""); try { setItems(await dashboardApi.admin.venueRevisions(status) || []); } catch (e) { setError(e.message); } finally { setLoading(false); } };
  useEffect(() => { load(); }, [status]);

  const decide = async (item, decision) => {
    let reason = "";
    if (decision === "reject") { reason = window.prompt("اكتب سبب رفض التعديل بالتفصيل:") || ""; if (!reason.trim()) return; }
    setBusyId(item.id);
    try { decision === "approve" ? await dashboardApi.admin.approveVenueRevision(item.id) : await dashboardApi.admin.rejectVenueRevision(item.id, reason.trim()); await load(); }
    catch (e) { setError(e.message || "تعذر تنفيذ القرار."); }
    finally { setBusyId(null); }
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h1 className="bg-gradient-to-r from-cyan-300 to-white bg-clip-text text-3xl font-black text-transparent">🔄 مراجعة تعديلات الصالات</h1><p className="mt-2 text-sm text-slate-400">قارن النسخة المنشورة بالنسخة المقترحة؛ لا تتغير بيانات العملاء قبل القبول.</p></div><select value={status} onChange={(e) => setStatus(e.target.value)} className="field-surface max-w-xs"><option value="pending">قيد المراجعة</option><option value="approved">مقبولة سابقاً</option><option value="rejected">مرفوضة سابقاً</option></select></div>
      {error && <div className="rounded-2xl border border-red-400/30 bg-red-500/10 p-4 text-red-200">{error}</div>}
      {loading ? <div className="p-10 text-center text-slate-400">جاري التحميل...</div> : !items.length ? <div className="rounded-3xl border border-dashed border-white/15 p-10 text-center text-slate-500">لا توجد تعديلات بهذه الحالة.</div> : <div className="space-y-6">{items.map((item) => {
        const current = item.current_snapshot || {};
        const proposed = item.proposed_snapshot || {};
        const fields = item.changed_fields || [];
        return <article key={item.id} className="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04]">
          <div className="flex flex-col gap-3 border-b border-white/10 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="text-xl font-black">{item.venue?.name_ar || item.venue?.name_en || `صالة #${item.venue_id}`}</h2><p className="mt-1 text-xs text-slate-400">المالك: {item.owner?.name || item.venue?.owner?.name || "—"} • أُرسل: {String(item.created_at || "").slice(0, 16)}</p></div><span className="rounded-full bg-cyan-500/10 px-3 py-1 text-xs font-black text-cyan-200">{fields.length} {fields.length === 1 ? "حقل متغير" : "حقول متغيرة"}</span></div>
          <div className="space-y-4 p-5">
            {fields.filter((field) => !["images", "videos"].includes(field)).map((field) => <div key={field} className="grid gap-3 md:grid-cols-[170px_1fr_1fr]"><div className="rounded-xl bg-white/[.04] p-3 text-sm font-black text-cyan-200">{labels[field] || field}</div><div className="rounded-xl border border-red-400/15 bg-red-500/[.05] p-3 text-sm"><span className="mb-1 block text-[10px] font-bold text-red-300">الحالي</span>{formatValue(current[field])}</div><div className="rounded-xl border border-emerald-400/15 bg-emerald-500/[.05] p-3 text-sm"><span className="mb-1 block text-[10px] font-bold text-emerald-300">المقترح</span>{formatValue(proposed[field])}</div></div>)}
            {fields.includes("images") && <div><h3 className="mb-3 font-black text-cyan-200">الصور</h3><div className="grid gap-4 md:grid-cols-2"><div><div className="mb-2 text-xs text-red-300">الصور الحالية</div><div className="grid grid-cols-3 gap-2">{(current.images || []).map((img, i) => <img key={i} src={assetUrl(img.image_url)} alt="" className="h-28 w-full rounded-xl object-cover" />)}</div></div><div><div className="mb-2 text-xs text-emerald-300">الصور المقترحة</div><div className="grid grid-cols-3 gap-2">{(proposed.images || []).map((img, i) => <img key={i} src={assetUrl(img.image_url)} alt="" className="h-28 w-full rounded-xl object-cover" />)}</div></div></div></div>}
            {fields.includes("videos") && <div><h3 className="mb-3 font-black text-cyan-200">الفيديوهات</h3><div className="grid gap-4 md:grid-cols-2"><div><div className="mb-2 text-xs text-red-300">الفيديوهات الحالية</div><div className="grid gap-2">{(current.videos || []).map((video, i) => <video key={i} src={assetUrl(video.resolved_url || video.video_url || video.url)} controls preload="metadata" className="h-56 w-full rounded-xl bg-black object-contain" />)}</div></div><div><div className="mb-2 text-xs text-emerald-300">الفيديوهات المقترحة</div><div className="grid gap-2">{(proposed.videos || []).map((video, i) => <video key={i} src={assetUrl(video.resolved_url || video.video_url || video.url)} controls preload="metadata" className="h-56 w-full rounded-xl bg-black object-contain" />)}</div></div></div></div>}
            {item.status === "pending" && <div className="flex gap-3 pt-2"><button disabled={busyId === item.id} onClick={() => decide(item, "reject")} className="flex-1 rounded-2xl bg-red-500/15 py-3 font-black text-red-200 disabled:opacity-50">رفض مع سبب</button><button disabled={busyId === item.id} onClick={() => decide(item, "approve")} className="flex-1 rounded-2xl bg-emerald-500 py-3 font-black text-slate-950 disabled:opacity-50">قبول ونشر التعديل</button></div>}
            {item.decision_reason && <div className="rounded-xl bg-red-500/10 p-3 text-sm text-red-200">سبب القرار: {item.decision_reason}</div>}
          </div>
        </article>;
      })}</div>}
    </div>
  );
}
