import React, { useEffect, useMemo, useState } from "react";
import { dashboardApi } from "../services/apiClient";
import { resolveMediaUrl } from "../utils/mediaUrl";

const assetUrl = resolveMediaUrl;
const emptyForm = { id: null, parentId: "", nameAr: "", nameEn: "", description: "", appliesTo: "both", isActive: true, sortOrder: 0, image: null, imageUrl: "", removeImage: false };

export default function ServiceCategoriesManagement() {
  const [categories, setCategories] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const load = async () => {
    setLoading(true); setError("");
    try { setCategories(await dashboardApi.admin.serviceCategories() || []); }
    catch (exception) { setError(exception.message || "تعذر تحميل التصنيفات."); }
    finally { setLoading(false); }
  };
  useEffect(() => { load(); }, []);

  const roots = useMemo(() => categories.filter((category) => !category.parent_id), [categories]);
  const childrenByParent = useMemo(() => categories.reduce((map, category) => {
    const key = String(category.parent_id || "root");
    map[key] = [...(map[key] || []), category];
    return map;
  }, {}), [categories]);
  const ordered = useMemo(() => {
    const output = [];
    const visit = (category, depth = 0) => {
      output.push({ category, depth });
      (childrenByParent[String(category.id)] || []).forEach((child) => visit(child, depth + 1));
    };
    roots.forEach((root) => visit(root));
    return output;
  }, [roots, childrenByParent]);

  const edit = (category) => setForm({
    id: category.id, parentId: category.parent_id ? String(category.parent_id) : "", nameAr: category.name_ar || "",
    nameEn: category.name_en || "", description: category.description || "", appliesTo: category.applies_to || "both",
    isActive: category.is_active !== false, sortOrder: Number(category.sort_order || 0), image: null,
    imageUrl: assetUrl(category.image_url), removeImage: false,
  });
  const reset = () => setForm(emptyForm);

  const save = async (event) => {
    event.preventDefault();
    if (!form.nameAr.trim() || !form.nameEn.trim()) return window.alert("الاسم العربي والإنكليزي مطلوبان.");
    setSaving(true); setError("");
    try {
      const data = new FormData();
      if (form.parentId) data.append("parent_id", form.parentId);
      data.append("name_ar", form.nameAr.trim());
      data.append("name_en", form.nameEn.trim());
      data.append("description", form.description.trim());
      data.append("applies_to", form.appliesTo);
      data.append("is_active", form.isActive ? "1" : "0");
      data.append("sort_order", String(form.sortOrder || 0));
      if (form.image) data.append("image", form.image);
      if (form.removeImage) data.append("remove_image", "1");
      if (form.id) await dashboardApi.admin.updateServiceCategory(form.id, data);
      else await dashboardApi.admin.createServiceCategory(data);
      reset(); await load();
    } catch (exception) { setError(exception.message || "تعذر حفظ التصنيف."); }
    finally { setSaving(false); }
  };

  const remove = async (category) => {
    if (!window.confirm(`حذف/تعطيل التصنيف «${category.name_ar || category.name_en}»؟ إذا كان مرتبطاً بخدمات سيُعطّل بدلاً من الحذف.`)) return;
    try { await dashboardApi.admin.deleteServiceCategory(category.id); await load(); }
    catch (exception) { setError(exception.message || "تعذر حذف التصنيف."); }
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div><h1 className="bg-gradient-to-r from-violet-300 to-white bg-clip-text text-3xl font-black text-transparent">🧩 تصنيفات الخدمات</h1><p className="mt-2 text-sm leading-7 text-slate-400">التصنيفات الرئيسية والفرعية تظهر في التطبيق، بينما خيار «الكل» افتراضي في الواجهة ولا يحتاج سجلاً في قاعدة البيانات.</p></div>
      {error && <div className="rounded-2xl border border-red-400/30 bg-red-500/10 p-4 text-red-200">{error}</div>}
      <div className="grid gap-6 xl:grid-cols-[1fr_420px]">
        <section className="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04]">
          <div className="border-b border-white/10 p-5"><h2 className="font-black">التصنيفات الموجودة</h2></div>
          {loading ? <div className="p-8 text-center text-slate-400">جاري التحميل...</div> : !ordered.length ? <div className="p-8 text-center text-slate-500">لا توجد تصنيفات بعد.</div> : <div className="divide-y divide-white/10">{ordered.map(({ category, depth }) => (
            <div key={category.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between" style={{ paddingRight: `${16 + depth * 28}px` }}>
              <div className="flex items-center gap-3">
                {category.image_url ? <img src={assetUrl(category.image_url)} alt="" className="h-12 w-12 rounded-xl object-cover" /> : <div className="grid h-12 w-12 place-items-center rounded-xl bg-violet-500/10">🧩</div>}
                <div><div className="font-black">{depth ? "↳ " : ""}{category.name_ar || category.name_en}</div><div className="mt-1 text-xs text-slate-500">{category.name_en} • {category.applies_to === "provider" ? "مقدمو الخدمات" : category.applies_to === "hall" ? "الصالات" : "الاثنان"} • {category.services_count || 0} خدمة</div></div>
              </div>
              <div className="flex items-center gap-2"><span className={`rounded-full px-3 py-1 text-[11px] font-black ${category.is_active ? "bg-emerald-500/10 text-emerald-300" : "bg-slate-500/10 text-slate-400"}`}>{category.is_active ? "فعال" : "معطل"}</span><button onClick={() => edit(category)} className="rounded-xl bg-blue-500/10 px-3 py-2 text-xs font-bold text-blue-200">تعديل</button><button onClick={() => remove(category)} className="rounded-xl bg-red-500/10 px-3 py-2 text-xs font-bold text-red-200">حذف</button></div>
            </div>
          ))}</div>}
        </section>

        <form onSubmit={save} className="h-fit space-y-4 rounded-3xl border border-violet-400/20 bg-violet-500/[.06] p-5 xl:sticky xl:top-24">
          <div className="flex items-center justify-between"><h2 className="text-xl font-black">{form.id ? "تعديل التصنيف" : "إضافة تصنيف"}</h2>{form.id && <button type="button" onClick={reset} className="text-xs text-slate-400">إلغاء التعديل</button>}</div>
          <input required value={form.nameAr} onChange={(e) => setForm((v) => ({ ...v, nameAr: e.target.value }))} placeholder="الاسم بالعربية *" className="field-surface" />
          <input required value={form.nameEn} onChange={(e) => setForm((v) => ({ ...v, nameEn: e.target.value }))} placeholder="الاسم بالإنكليزية *" className="field-surface" />
          <textarea value={form.description} onChange={(e) => setForm((v) => ({ ...v, description: e.target.value }))} placeholder="وصف اختياري" className="field-surface min-h-24" />
          <select value={form.parentId} onChange={(e) => setForm((v) => ({ ...v, parentId: e.target.value }))} className="field-surface"><option value="">تصنيف رئيسي</option>{categories.filter((c) => String(c.id) !== String(form.id)).map((c) => <option key={c.id} value={c.id}>{c.name_ar || c.name_en}</option>)}</select>
          <select value={form.appliesTo} onChange={(e) => setForm((v) => ({ ...v, appliesTo: e.target.value }))} className="field-surface"><option value="both">للصالات ومقدمي الخدمات</option><option value="provider">لمقدمي الخدمات</option><option value="hall">للصالات</option></select>
          <input type="number" min="0" value={form.sortOrder} onChange={(e) => setForm((v) => ({ ...v, sortOrder: Number(e.target.value) }))} placeholder="ترتيب العرض" className="field-surface" />
          <label className="block rounded-2xl border border-dashed border-white/15 p-4 text-sm text-slate-300"><span className="mb-2 block font-bold">صورة التصنيف (JPG/PNG/WebP/SVG)</span><input type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml" onChange={(e) => setForm((v) => ({ ...v, image: e.target.files?.[0] || null, removeImage: false }))} /></label>
          {form.imageUrl && !form.removeImage && <div className="flex items-center gap-3"><img src={form.imageUrl} alt="" className="h-16 w-16 rounded-xl object-cover" /><button type="button" onClick={() => setForm((v) => ({ ...v, removeImage: true }))} className="text-xs font-bold text-red-300">إزالة الصورة الحالية</button></div>}
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.isActive} onChange={(e) => setForm((v) => ({ ...v, isActive: e.target.checked }))} /> التصنيف فعال ويظهر في التطبيق</label>
          <button disabled={saving} className="w-full rounded-2xl bg-violet-500 py-3 font-black text-white disabled:opacity-50">{saving ? "جاري الحفظ..." : form.id ? "حفظ التعديل" : "إضافة التصنيف"}</button>
        </form>
      </div>
    </div>
  );
}
