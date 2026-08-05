import { useEffect, useState } from "react";
import { useApp } from "../../context/AppContext";
import { saloraV2 } from "../../lib/saloraBookingV2Api";

const dayNames = ["الأحد", "الاثنين", "الثلاثاء", "الأربعاء", "الخميس", "الجمعة", "السبت"];

function normaliseDays(value) {
  const incoming = Array.isArray(value) ? value : [];
  return dayNames.map((_, index) => {
    const found = incoming.find((item) => Number(item.day_of_week) === index) || {};
    return {
      day_of_week: index,
      is_closed: Boolean(found.is_closed),
      open_time: String(found.open_time || "10:00").slice(0, 5),
      close_time: String(found.close_time || "23:00").slice(0, 5),
    };
  });
}

function Field({ label, hint = "", children }) {
  return <label className="block space-y-2"><span className="text-xs font-black text-slate-300">{label}</span>{children}{hint ? <span className="block text-[11px] leading-5 text-slate-500">{hint}</span> : null}</label>;
}

export default function OwnerWorkingHours() {
  const { ownerVenues = [] } = useApp();
  const [venueId, setVenueId] = useState("");
  const [days, setDays] = useState(normaliseDays([]));
  const [cleanup, setCleanup] = useState("60");
  const [pricingSnapshot, setPricingSnapshot] = useState({ price: 0, maximum: 300 });
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");

  useEffect(() => {
    if (!venueId && ownerVenues[0]?.id) setVenueId(String(ownerVenues[0].id));
  }, [ownerVenues, venueId]);

  useEffect(() => {
    if (venueId) load();
  }, [venueId]);

  async function load() {
    setLoading(true);
    setError("");
    try {
      const result = await saloraV2(`/owner/venues/${venueId}`);
      const venue = result.venue || {};
      setDays(normaliseDays(result.working_hours));
      setCleanup(String(venue.cleanup_minutes ?? 60));
      setPricingSnapshot({
        price: Number(venue.hourly_price_syp || venue.price_syp || 0),
        maximum: Number(venue.maximum_booking_minutes || 300),
      });
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }

  const setDay = (index, key, value) => setDays((current) => current.map((day, dayIndex) => dayIndex === index ? { ...day, [key]: value } : day));

  async function save() {
    setSaving(true);
    setError("");
    setMessage("");
    try {
      if (pricingSnapshot.price <= 0) throw new Error("حدد سعر الساعة أولاً من صفحة الساعات والعروض.");
      await saloraV2(`/owner/venues/${venueId}/pricing`, {
        method: "PUT",
        body: JSON.stringify({
          hourly_price_syp: pricingSnapshot.price,
          maximum_booking_minutes: pricingSnapshot.maximum,
          cleanup_minutes: Number(cleanup),
        }),
      });
      await saloraV2(`/owner/venues/${venueId}/working-hours`, {
        method: "PUT",
        body: JSON.stringify({ days }),
      });
      setMessage("تم نشر أوقات العمل والتوافر مباشرة في التطبيق بدون انتظار موافقة الأدمن.");
      await load();
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  return <div className="space-y-6 pb-12" dir="rtl">
    <div>
      <h1 className="text-3xl font-black text-white">🗓️ التوافر وأوقات العمل</h1>
      <p className="mt-2 text-sm leading-6 text-slate-400">هذه الإعدادات تشغيلية ويعدلها المالك مباشرة. لا تُرسل إلى مراجعة الأدمن.</p>
    </div>

    <div className="grid gap-4 rounded-3xl border border-white/10 bg-white/[.04] p-6 md:grid-cols-2">
      <Field label="الصالة"><select className="field-surface w-full" value={venueId} onChange={(event) => setVenueId(event.target.value)}>{ownerVenues.map((venue) => <option key={venue.id} value={venue.id}>{venue.name}</option>)}</select></Field>
      <Field label="مدة التنظيف بين الحجوزات بالدقائق" hint="تمنع حجز الفترة التالية أثناء تجهيز الصالة، ولا تُحسب على العميل."><input type="number" min="0" step="30" className="field-surface w-full" value={cleanup} onChange={(event) => setCleanup(event.target.value)} /></Field>
    </div>

    {loading && <div className="rounded-2xl border border-blue-400/20 bg-blue-500/10 p-4 text-blue-200">جاري تحميل الأوقات...</div>}
    {message && <div className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4 font-bold text-emerald-200">✅ {message}</div>}
    {error && <div className="rounded-2xl border border-red-400/20 bg-red-500/10 p-4 font-bold text-red-200">⚠️ {error}</div>}

    <section className="rounded-3xl border border-white/10 bg-white/[.04] p-6">
      <div className="mb-5"><h2 className="text-xl font-black text-white">الجدول الأسبوعي</h2><p className="mt-1 text-sm text-slate-500">يمكن أن يكون وقت الإغلاق بعد منتصف الليل؛ الخادم يحسبه ضمن اليوم التالي.</p></div>
      <div className="space-y-3">
        {days.map((day, index) => <div key={day.day_of_week} className="grid gap-3 rounded-2xl border border-white/10 bg-slate-950/35 p-4 md:grid-cols-[1.2fr_1fr_1fr_auto] md:items-end">
          <label className="flex min-h-[48px] items-center gap-3 font-black text-slate-100"><input type="checkbox" checked={!day.is_closed} onChange={(event) => setDay(index, "is_closed", !event.target.checked)} className="h-5 w-5" /><span>{dayNames[index]}</span></label>
          <Field label="وقت الفتح"><input type="time" className="field-surface w-full" disabled={day.is_closed} value={day.open_time} onChange={(event) => setDay(index, "open_time", event.target.value)} /></Field>
          <Field label="وقت الإغلاق"><input type="time" className="field-surface w-full" disabled={day.is_closed} value={day.close_time} onChange={(event) => setDay(index, "close_time", event.target.value)} /></Field>
          <span className={`mb-1 rounded-full px-3 py-2 text-center text-xs font-black ${day.is_closed ? "bg-red-500/10 text-red-300" : "bg-emerald-500/10 text-emerald-300"}`}>{day.is_closed ? "مغلق" : "مفتوح"}</span>
        </div>)}
      </div>
      <button type="button" disabled={saving || loading || !venueId} onClick={save} className="mt-5 w-full rounded-2xl bg-emerald-500 py-3 font-black text-slate-950 hover:bg-emerald-400 disabled:opacity-50">{saving ? "جاري الحفظ..." : "حفظ ونشر أوقات العمل فوراً"}</button>
    </section>
  </div>;
}