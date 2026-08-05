import React, { useMemo, useState } from "react";
import Card from "../components/Card";
import GoogleMapPicker from "../components/GoogleMapPicker";
import ImageGalleryEditor from "../components/ImageGalleryEditor";
import VideoGalleryEditor from "../components/VideoGalleryEditor";
import { useApp } from "../context/AppContext";
// SALORA_VISIBLE_FIELD_LABELS

function ToggleChip({ active, children, onClick }) {
  return <button type="button" onClick={onClick} className={`rounded-2xl border px-3 py-2 text-xs font-bold transition ${active ? "border-blue-400/50 bg-blue-500/20 text-blue-100" : "border-white/10 bg-white/[.04] text-slate-300 hover:bg-white/[.08]"}`}>{children}</button>;
}

const fallbackEvents = [
  ["Wedding", "زفاف", "💍"], ["Engagement", "خطوبة", "💞"], ["Graduation", "تخرج", "🎓"],
  ["Birthday", "عيد ميلاد", "🎂"], ["Condolence", "عزاء", "🕊️"], ["Conference", "مؤتمر", "🧑‍💼"],
];
const amenities = ["مدخل خاص", "غرفة عروس", "موقف سيارات", "تكييف", "إنترنت", "منصة", "أقسام منفصلة", "مصعد", "ركن أطفال"];
const policies = ["يتطلب الحجز إثبات دفع", "منع تداخل المواعيد", "الساعة الإضافية مدفوعة", "الطعام الخارجي يحتاج موافقة", "تنتهي الموسيقى قبل منتصف الليل"];
const includedDefaults = ["طاولات وكراسي", "إضاءة أساسية", "صوت أساسي", "تنظيف", "موقف سيارات", "مياه", "تكييف"];
const paidDefaults = ["إضاءة VIP", "ضيافة مميزة", "ساعة إضافية", "بروجكتور", "ركن تصوير", "ديكور"];

const initialForm = () => ({
  name: "", city: "دمشق", address: "", latitude: null, longitude: null, mapUrl: "", googlePlaceId: "",
  capacity: "", price: "", currency: "SYP", description: "", supportedEventTypes: ["Wedding"],
  includedServices: ["طاولات وكراسي", "تنظيف"], paidUpgrades: [], amenities: ["موقف سيارات"],
  policies: ["يتطلب الحجز إثبات دفع", "منع تداخل المواعيد"], vendorCategories: [],
});

export default function VenueForm() {
  const { addVenue, eventTypes, serviceCategories, services, SERVICE_TYPES } = useApp();
  const [form, setForm] = useState(initialForm);
  const [gallery, setGallery] = useState([]);
  const [videos, setVideos] = useState([]);
  const [submitting, setSubmitting] = useState(false);

  const eventOptions = useMemo(() => {
    const api = (eventTypes || []).filter((item) => item.status !== "Disabled").map((item) => ({ value: item.nameEn || item.name, label: item.name, emoji: item.emoji || "🎯" })).filter((item) => item.value);
    return api.length ? api : fallbackEvents.map(([value, label, emoji]) => ({ value, label, emoji }));
  }, [eventTypes]);

  const providerCategories = useMemo(() => (serviceCategories || [])
    .filter((item) => item.isActive !== false && ["provider", "both"].includes(item.appliesTo || "both"))
    .map((item) => item.name), [serviceCategories]);

  const includedOptions = useMemo(() => Array.from(new Set([
    ...(services || []).filter((service) => service.serviceType === SERVICE_TYPES.INCLUDED).map((service) => service.name),
    ...includedDefaults,
  ])), [services, SERVICE_TYPES]);
  const paidOptions = useMemo(() => Array.from(new Set([
    ...(services || []).filter((service) => service.serviceType === SERVICE_TYPES.HALL_UPGRADE).map((service) => service.name),
    ...paidDefaults,
  ])), [services, SERVICE_TYPES]);

  const setField = (key, value) => setForm((current) => ({ ...current, [key]: value }));
  const toggle = (key, value) => setForm((current) => ({ ...current, [key]: current[key].includes(value) ? current[key].filter((item) => item !== value) : [...current[key], value] }));

  const submit = async () => {
    if (submitting) return;
    if (!form.name.trim() || !form.city.trim() || !form.address.trim() || !form.capacity || !form.price) return window.alert("أكمل اسم الصالة والمدينة والعنوان والسعة والسعر.");
    if (!Number.isFinite(Number(form.latitude)) || !Number.isFinite(Number(form.longitude))) return window.alert("حدد موقع الصالة بدقة على خريطة Google.");
    if (!form.supportedEventTypes.length) return window.alert("اختر نوع مناسبة واحداً على الأقل.");
    if (gallery.length < 1) return window.alert("أضف صورة واحدة على الأقل للصالة.");

    setSubmitting(true);
    try {
      const saved = await addVenue({
        name: form.name.trim(), city: form.city.trim(), address: form.address.trim(), latitude: form.latitude,
        longitude: form.longitude, mapUrl: form.mapUrl, googlePlaceId: form.googlePlaceId, capacity: Number(form.capacity),
        ...(form.currency === "SYP" ? { priceSyp: Number(form.price) } : { basePrice: Number(form.price) }),
        description: form.description.trim(), supportedEventTypes: form.supportedEventTypes, includedServices: form.includedServices,
        paidUpgrades: form.paidUpgrades, amenities: form.amenities, policies: form.policies, vendorCategories: form.vendorCategories,
        videoFiles: videos.map((video) => video.file).filter(Boolean),
      });
      if (!saved) return;
      gallery.forEach((image) => image.local && image.url && URL.revokeObjectURL(image.url));
      videos.forEach((video) => video.local && video.url && URL.revokeObjectURL(video.url));
      setGallery([]);
      setVideos([]);
      setForm(initialForm());
      window.alert(saved.imageUploadFailed || saved.videoUploadFailed ? "تم إنشاء الصالة، لكن تعذر رفع بعض الصور أو الفيديوهات. افتح صالاتي وأعد الرفع." : "تم إرسال الصالة وصورها وفيديوهاتها وموقعها لمراجعة الأدمن.");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div>
        <h1 className="bg-gradient-to-r from-amber-300 to-white bg-clip-text text-3xl font-black text-transparent">🏛️ إضافة صالة / فرع قابل للحجز</h1>
        <p className="mt-2 max-w-4xl text-sm leading-7 text-slate-400">كل موقع قابل للحجز يُسجل كصالة مستقلة تحت حسابك، حتى تكون الصور والسعر والمواعيد والتقييمات مستقلة وواضحة.</p>
      </div>

      <Card title="📋 البيانات الأساسية">
        <div className="grid gap-4 md:grid-cols-2">
          <input value={form.name} onChange={(event) => setField("name", event.target.value)} placeholder="اسم الصالة *" className="field-surface" />
          <input value={form.city} onChange={(event) => setField("city", event.target.value)} placeholder="المدينة *" className="field-surface" />
          <input value={form.address} onChange={(event) => setField("address", event.target.value)} placeholder="العنوان المكتوب *" className="field-surface md:col-span-2" />
          <input type="number" min="1" value={form.capacity} onChange={(event) => setField("capacity", event.target.value)} placeholder="السعة *" className="field-surface" />
          <div className="grid grid-cols-[1fr_105px] gap-2">
            <input type="number" min="0" value={form.price} onChange={(event) => setField("price", event.target.value)} placeholder="سعر الساعة *" className="field-surface" />
            <select value={form.currency} onChange={(event) => setField("currency", event.target.value)} className="field-surface"><option value="SYP">ل.س</option><option value="USD">USD</option></select>
          </div>
          <textarea value={form.description} onChange={(event) => setField("description", event.target.value)} placeholder="وصف الصالة" className="field-surface min-h-28 md:col-span-2" />
        </div>
      </Card>

      <GoogleMapPicker latitude={form.latitude} longitude={form.longitude} address={form.address} onChange={(location) => setForm((current) => ({ ...current, ...location }))} />
      
      <ImageGalleryEditor images={gallery} onChange={setGallery} maxImages={10} label="صور الصالة" />
      <VideoGalleryEditor videos={videos} onChange={setVideos} maxVideos={5} label="فيديوهات الصالة" />

      <Card title="🎯 أنواع المناسبات">
        <div className="flex flex-wrap gap-2">{eventOptions.map((item) => <ToggleChip key={item.value} active={form.supportedEventTypes.includes(item.value)} onClick={() => toggle("supportedEventTypes", item.value)}>{item.emoji} {item.label}</ToggleChip>)}</div>
      </Card>

      <div className="grid gap-6 xl:grid-cols-2">
        <Card title="✅ خدمات مشمولة بالسعر"><div className="flex flex-wrap gap-2">{includedOptions.map((item) => <ToggleChip key={item} active={form.includedServices.includes(item)} onClick={() => toggle("includedServices", item)}>{item}</ToggleChip>)}</div></Card>
        <Card title="💰 خدمات إضافية مدفوعة"><div className="flex flex-wrap gap-2">{paidOptions.map((item) => <ToggleChip key={item} active={form.paidUpgrades.includes(item)} onClick={() => toggle("paidUpgrades", item)}>{item}</ToggleChip>)}</div></Card>
        <Card title="✨ المزايا"><div className="flex flex-wrap gap-2">{amenities.map((item) => <ToggleChip key={item} active={form.amenities.includes(item)} onClick={() => toggle("amenities", item)}>{item}</ToggleChip>)}</div></Card>
        <Card title="📜 السياسات"><div className="flex flex-wrap gap-2">{policies.map((item) => <ToggleChip key={item} active={form.policies.includes(item)} onClick={() => toggle("policies", item)}>{item}</ToggleChip>)}</div></Card>
      </div>

      <Card title="🤝 فئات مقدمي الخدمات المتاحة حول الصالة">
        <div className="flex flex-wrap gap-2">{providerCategories.length ? providerCategories.map((item) => <ToggleChip key={item} active={form.vendorCategories.includes(item)} onClick={() => toggle("vendorCategories", item)}>{item}</ToggleChip>) : <span className="text-sm text-slate-500">أضف الأدمن تصنيفات الخدمات أولاً.</span>}</div>
      </Card>

      <div className="rounded-3xl border border-amber-400/20 bg-amber-500/10 p-5 text-sm leading-7 text-amber-100">لن تظهر الصالة للعملاء قبل موافقة الأدمن. بعد الاعتماد، أي تعديل جديد يبقى كنسخة معلقة بينما تستمر النسخة القديمة بالظهور.</div>
      <button onClick={submit} disabled={submitting} className="w-full rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 py-4 text-lg font-black text-slate-950 shadow-lg disabled:opacity-50">{submitting ? "جاري الحفظ ورفع الوسائط..." : "حفظ وإرسال للموافقة"}</button>
    </div>
  );
}
