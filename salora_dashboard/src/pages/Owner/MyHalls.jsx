import React, { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import GoogleMapPicker from "../../components/GoogleMapPicker";
import ImageGalleryEditor from "../../components/ImageGalleryEditor";
import VideoGalleryEditor from "../../components/VideoGalleryEditor";
import { useApp } from "../../context/AppContext";

const listText = (value) => (value || []).join("، ");
const parseList = (value) => String(value || "").split(/[،,\n]/).map((item) => item.trim()).filter(Boolean);
const hourlySyp = (venue) => Number(venue.hourlyPriceSyp ?? venue.hourly_price_syp ?? venue.priceSyp ?? venue.finalPriceSyp ?? 0);
const hourlyUsd = (venue) => Number(venue.hourlyPrice ?? venue.hourly_price_usd ?? venue.basePrice ?? venue.finalPrice ?? 0);

function Field({ label, className = "", children, hint = "" }) {
  return (
    <label className={`block space-y-2 ${className}`}>
      <span className="text-xs font-black text-slate-300">{label}</span>
      {children}
      {hint ? <span className="block text-[11px] leading-5 text-slate-500">{hint}</span> : null}
    </label>
  );
}

function Editor({ venue, onClose }) {
  const { updateVenue } = useApp();
  const originalImages = useMemo(() => (venue.imageObjects || []).map((image) => ({ ...image, local: false })), [venue]);
  const originalVideos = useMemo(() => (venue.videoObjects || []).map((video) => ({ ...video, local: false })), [venue]);
  const [gallery, setGallery] = useState(originalImages);
  const [videoGallery, setVideoGallery] = useState(originalVideos);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    name: venue.name,
    city: venue.city,
    address: venue.address,
    latitude: venue.latitude,
    longitude: venue.longitude,
    mapUrl: venue.mapUrl,
    googlePlaceId: venue.googlePlaceId,
    capacity: venue.capacity,
    hourlyPriceSyp: hourlySyp(venue),
    description: venue.description,
    supportedEventTypes: listText(venue.supportedEventTypes),
    includedServices: listText(venue.includedServices),
    paidUpgrades: listText(venue.paidUpgrades),
    vendorCategories: listText(venue.vendorCategories),
    amenities: listText(venue.amenities),
    policies: listText(venue.policies),
  });
  const setField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

  const save = async (event) => {
    event.preventDefault();
    if (!gallery.length) return window.alert("يجب إبقاء صورة واحدة على الأقل للصالة.");
    if (!Number.isFinite(Number(form.latitude)) || !Number.isFinite(Number(form.longitude))) return window.alert("حدد الموقع بدقة على الخريطة.");
    if (!Number.isFinite(Number(form.hourlyPriceSyp)) || Number(form.hourlyPriceSyp) <= 0) return window.alert("أدخل سعر ساعة صحيحاً أكبر من صفر.");

    const retained = gallery.filter((image) => !image.local);
    const retainedIds = new Set(retained.map((image) => String(image.id)));
    const removedImageIds = originalImages.filter((image) => !retainedIds.has(String(image.id))).map((image) => image.id);
    const localImages = gallery.filter((image) => image.local && image.file);
    const retainedVideos = videoGallery.filter((video) => !video.local);
    const retainedVideoIds = new Set(retainedVideos.map((video) => String(video.id)));
    const removedVideoIds = originalVideos.filter((video) => !retainedVideoIds.has(String(video.id))).map((video) => video.id);
    const localVideos = videoGallery.filter((video) => video.local && video.file);

    setSaving(true);
    try {
      const result = await updateVenue(venue.id, {
        name: form.name.trim(),
        city: form.city.trim(),
        address: form.address.trim(),
        latitude: form.latitude,
        longitude: form.longitude,
        mapUrl: form.mapUrl,
        googlePlaceId: form.googlePlaceId,
        capacity: Number(form.capacity),
        priceSyp: Number(form.hourlyPriceSyp),
        hourlyPriceSyp: Number(form.hourlyPriceSyp),
        description: form.description.trim(),
        supportedEventTypes: parseList(form.supportedEventTypes),
        includedServices: parseList(form.includedServices),
        paidUpgrades: parseList(form.paidUpgrades),
        vendorCategories: parseList(form.vendorCategories),
        amenities: parseList(form.amenities),
        policies: parseList(form.policies),
        imageFiles: localImages.map((image) => image.file),
        removedImageIds,
        retainedRawImageUrls: retained.map((image) => image.rawUrl).filter(Boolean),
        imageOrder: gallery.map((image) => image.local
          ? { kind: "new", index: localImages.indexOf(image) }
          : { kind: "existing", url: image.rawUrl }),
        coverImageId: !gallery[0]?.local ? gallery[0]?.id : null,
        makeNewImagesCover: Boolean(gallery[0]?.local),
        videoFiles: localVideos.map((video) => video.file),
        removedVideoIds,
        retainedRawVideoUrls: retainedVideos.map((video) => video.rawUrl).filter(Boolean),
        videoOrder: videoGallery.map((video) => video.local
          ? { kind: "new", index: localVideos.indexOf(video) }
          : { kind: "existing", url: video.rawUrl }),
      });
      if (!result) return;
      onClose();
      window.alert(venue.status === "Approved"
        ? "تم إرسال تعديل بيانات الصالة وسعر الساعة للمراجعة. أوقات العمل تبقى مستقلة ويمكن تعديلها فوراً من صفحتها."
        : "تم حفظ بيانات الصالة وسعر الساعة.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[9999] overflow-y-auto bg-slate-950/90 p-4 backdrop-blur-md" dir="rtl">
      <form onSubmit={save} className="mx-auto my-5 max-w-5xl space-y-5 rounded-3xl border border-amber-500/30 bg-slate-950 p-5 shadow-2xl sm:p-7">
        <div className="flex items-start justify-between border-b border-white/10 pb-4">
          <div>
            <h2 className="text-2xl font-black text-amber-200">تعديل {venue.name}</h2>
            <p className="mt-1 text-sm leading-6 text-slate-400">بيانات الصالة والصور وسعر الساعة تُرسل للمراجعة. أوقات العمل تُدار مباشرة من صفحة مستقلة ولا تنتظر الأدمن.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-xl bg-white/5 px-4 py-2">✕</button>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <Field label="اسم الصالة"><input required value={form.name} onChange={(e) => setField("name", e.target.value)} placeholder="اسم الصالة" className="field-surface w-full" /></Field>
          <Field label="المدينة"><input required value={form.city} onChange={(e) => setField("city", e.target.value)} placeholder="المدينة" className="field-surface w-full" /></Field>
          <Field label="العنوان المكتوب" className="md:col-span-2"><input required value={form.address} onChange={(e) => setField("address", e.target.value)} placeholder="العنوان" className="field-surface w-full" /></Field>
          <Field label="السعة القصوى"><input required type="number" min="1" value={form.capacity} onChange={(e) => setField("capacity", e.target.value)} placeholder="عدد الضيوف" className="field-surface w-full" /></Field>
          <Field label="سعر الساعة بالليرة السورية" hint="هذا هو السعر الأساسي الذي تُحسب منه مدة الحجز والعروض."><input required type="number" min="1" step="1" value={form.hourlyPriceSyp} onChange={(e) => setField("hourlyPriceSyp", e.target.value)} placeholder="سعر الساعة" className="field-surface w-full" /></Field>
          <Field label="وصف الصالة" className="md:col-span-2"><textarea value={form.description} onChange={(e) => setField("description", e.target.value)} placeholder="وصف الصالة" className="field-surface min-h-24 w-full" /></Field>
          <Field label="أنواع المناسبات المناسبة"><textarea value={form.supportedEventTypes} onChange={(e) => setField("supportedEventTypes", e.target.value)} placeholder="مثال: زفاف، خطوبة" className="field-surface min-h-24 w-full" /></Field>
          <Field label="الخدمات المجانية المشمولة"><textarea value={form.includedServices} onChange={(e) => setField("includedServices", e.target.value)} placeholder="الخدمات المشمولة" className="field-surface min-h-24 w-full" /></Field>
          <Field label="الخدمات الإضافية المدفوعة"><textarea value={form.paidUpgrades} onChange={(e) => setField("paidUpgrades", e.target.value)} placeholder="الخدمات المدفوعة" className="field-surface min-h-24 w-full" /></Field>
          <Field label="فئات مقدمي الخدمات"><textarea value={form.vendorCategories} onChange={(e) => setField("vendorCategories", e.target.value)} placeholder="فئات مقدمي الخدمات" className="field-surface min-h-24 w-full" /></Field>
          <Field label="المزايا"><textarea value={form.amenities} onChange={(e) => setField("amenities", e.target.value)} placeholder="المزايا" className="field-surface min-h-24 w-full" /></Field>
          <Field label="السياسات"><textarea value={form.policies} onChange={(e) => setField("policies", e.target.value)} placeholder="السياسات" className="field-surface min-h-24 w-full" /></Field>
        </div>

        <section className="rounded-3xl border border-white/10 bg-white/[.025] p-4">
          <h3 className="mb-3 text-lg font-black text-slate-100">الموقع على الخريطة</h3>
          <GoogleMapPicker latitude={form.latitude} longitude={form.longitude} address={form.address} onChange={(location) => setForm((current) => ({ ...current, ...location }))} />
        </section>
        <ImageGalleryEditor images={gallery} onChange={setGallery} maxImages={10} label="صور الصالة" />
        <VideoGalleryEditor videos={videoGallery} onChange={setVideoGallery} maxVideos={5} label="فيديوهات الصالة" />

        <div className="flex gap-3">
          <button type="button" onClick={onClose} className="flex-1 rounded-2xl bg-white/5 py-3 font-bold">إلغاء</button>
          <button disabled={saving} className="flex-1 rounded-2xl bg-amber-500 py-3 font-black text-slate-950 disabled:opacity-50">{saving ? "جاري الحفظ..." : "حفظ وإرسال للمراجعة"}</button>
        </div>
      </form>
    </div>
  );
}

export default function MyHalls() {
  const { ownerVenues, formatPricePair, arabicLabel, setGlobalViewVenue } = useApp();
  const navigate = useNavigate();
  const [editing, setEditing] = useState(null);

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 className="bg-gradient-to-r from-amber-300 to-white bg-clip-text text-3xl font-black text-transparent">🏛️ صالاتي</h1>
          <p className="mt-2 text-sm text-slate-400">صورة غلاف واضحة، سعر الساعة، والحالة والإجراءات الأساسية بدون ازدحام.</p>
        </div>
        <button onClick={() => navigate("/owner/add-hall")} className="rounded-2xl bg-amber-500 px-5 py-3 font-black text-slate-950 hover:bg-amber-400">＋ إضافة صالة</button>
      </div>

      {!ownerVenues.length && <div className="rounded-3xl border border-dashed border-white/15 p-10 text-center text-slate-500">لم تضف صالة بعد.</div>}

      <div className="grid gap-6 md:grid-cols-2 2xl:grid-cols-3">
        {ownerVenues.map((venue) => {
          const cover = venue.imageUrls?.[0];
          const price = formatPricePair(hourlyUsd(venue), "", hourlySyp(venue));
          return (
            <article key={venue.id} className="group overflow-hidden rounded-3xl border border-white/10 bg-white/[.045] shadow-xl transition hover:-translate-y-1 hover:border-amber-400/30">
              <div className="relative h-64 overflow-hidden bg-slate-950/70">
                {cover ? <img src={cover} alt={venue.name} className="h-full w-full object-cover transition duration-500 group-hover:scale-105" /> : <div className="grid h-full place-items-center text-7xl">🏛️</div>}
                <div className="absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-slate-950 via-slate-950/70 to-transparent" />
                <span className="absolute right-4 top-4 rounded-full border border-white/15 bg-slate-950/75 px-3 py-1.5 text-xs font-black backdrop-blur">{arabicLabel(venue.status)}</span>
                <div className="absolute bottom-4 right-4 left-4">
                  <h3 className="truncate text-2xl font-black text-white">{venue.name}</h3>
                  <p className="mt-1 truncate text-sm text-slate-300">📍 {venue.city} • {venue.address}</p>
                </div>
              </div>

              <div className="space-y-4 p-5">
                <div className="grid grid-cols-2 gap-3">
                  <div className="rounded-2xl border border-white/10 bg-slate-950/45 p-3"><div className="text-[10px] font-bold text-slate-500">سعر الساعة</div><div className="mt-1 text-base font-black text-emerald-300">{price} <span className="text-xs text-slate-500">/ ساعة</span></div></div>
                  <div className="rounded-2xl border border-white/10 bg-slate-950/45 p-3"><div className="text-[10px] font-bold text-slate-500">السعة</div><div className="mt-1 text-base font-black text-slate-100">{venue.capacity || 0} ضيف</div></div>
                  <div className="rounded-2xl border border-white/10 bg-slate-950/45 p-3"><div className="text-[10px] font-bold text-slate-500">الصور</div><div className="mt-1 font-black">{venue.imageUrls?.length || 0}</div></div>
                  <div className="rounded-2xl border border-white/10 bg-slate-950/45 p-3"><div className="text-[10px] font-bold text-slate-500">الفيديوهات</div><div className="mt-1 font-black">{venue.videoUrls?.length || 0}</div></div>
                </div>

                {venue.pendingRevision && <div className="rounded-2xl border border-amber-400/25 bg-amber-500/10 p-3 text-xs font-bold leading-6 text-amber-200">⏳ يوجد تعديل بيانات بانتظار مراجعة الأدمن. أوقات العمل لا تدخل ضمن هذه المراجعة.</div>}

                <div className="grid gap-2 sm:grid-cols-2">
                  <button onClick={() => setGlobalViewVenue(venue)} className="rounded-xl border border-violet-400/25 bg-violet-500/10 py-2.5 text-sm font-black text-violet-200 hover:bg-violet-500/20">👁️ عرض الصالة</button>
                  <button onClick={() => setEditing(venue)} className="rounded-xl bg-amber-500 py-2.5 text-sm font-black text-slate-950 hover:bg-amber-400">تعديل البيانات والصور</button>
                  <button onClick={() => navigate("/owner/booking-settings-v2")} className="rounded-xl border border-blue-400/25 bg-blue-500/10 py-2.5 text-sm font-black text-blue-200 hover:bg-blue-500/20">الساعات والعروض</button>
                  <button onClick={() => navigate("/owner/working-hours")} className="rounded-xl border border-emerald-400/25 bg-emerald-500/10 py-2.5 text-sm font-black text-emerald-200 hover:bg-emerald-500/20">التوافر وأوقات العمل</button>
                  <button onClick={() => venue.mapUrl && window.open(venue.mapUrl, "_blank", "noopener,noreferrer")} disabled={!venue.mapUrl} className="rounded-xl border border-white/10 bg-white/5 py-2.5 text-sm font-black text-slate-200 hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40 sm:col-span-2">فتح الخريطة</button>
                </div>
              </div>
            </article>
          );
        })}
      </div>

      {editing && <Editor venue={editing} onClose={() => setEditing(null)} />}
    </div>
  );
}