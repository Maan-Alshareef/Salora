import { useEffect, useMemo, useState } from "react";
import { useApp } from "../../context/AppContext";
import { saloraV2 } from "../../lib/saloraBookingV2Api";

function Field({ label, hint = "", children, className = "" }) {
  return <label className={`block space-y-2 ${className}`}><span className="text-xs font-black text-slate-300">{label}</span>{children}{hint ? <span className="block text-[11px] leading-5 text-slate-500">{hint}</span> : null}</label>;
}

export default function OwnerBookingSettingsV2() {
  const { ownerVenues = [] } = useApp();
  const [venueId, setVenueId] = useState("");
  const [data, setData] = useState(null);
  const [price, setPrice] = useState("");
  const [maxHours, setMaxHours] = useState("5");
  const [title, setTitle] = useState("");
  const [discount, setDiscount] = useState("10");
  const [startsOn, setStartsOn] = useState("");
  const [endsOn, setEndsOn] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!venueId && ownerVenues[0]?.id) setVenueId(String(ownerVenues[0].id));
  }, [ownerVenues, venueId]);

  useEffect(() => { if (venueId) load(); }, [venueId]);
  const offers = useMemo(() => data?.offers || [], [data]);

  async function load() {
    setLoading(true); setError("");
    try {
      const result = await saloraV2(`/owner/venues/${venueId}`);
      const venue = result.venue || {};
      setData(result);
      setPrice(String(venue.hourly_price_syp || venue.price_syp || ""));
      setMaxHours(String(Number(venue.maximum_booking_minutes || 300) / 60));
    } catch (requestError) { setError(requestError.message); }
    finally { setLoading(false); }
  }

  async function savePricing(event) {
    event.preventDefault(); setError(""); setMessage("");
    try {
      await saloraV2(`/owner/venues/${venueId}/pricing`, {
        method: "PUT",
        body: JSON.stringify({
          hourly_price_syp: Number(price),
          maximum_booking_minutes: Math.round(Number(maxHours) * 60),
          cleanup_minutes: Number(data?.venue?.cleanup_minutes || 60),
        }),
      });
      setMessage("تم نشر سعر الساعة وحدود الحجز مباشرة في التطبيق.");
      await load();
    } catch (requestError) { setError(requestError.message); }
  }

  async function addOffer(event) {
    event.preventDefault(); setError(""); setMessage("");
    const percent = Number(discount);
    if (!Number.isFinite(percent) || percent < 1 || percent > 50) {
      setError("نسبة الخصم يجب أن تكون بين 1% و50% فقط."); return;
    }
    if (!startsOn || !endsOn) { setError("حدد تاريخ بداية العرض وتاريخ انتهائه."); return; }
    if (endsOn < startsOn) { setError("تاريخ انتهاء العرض لا يمكن أن يكون قبل تاريخ البداية."); return; }
    try {
      await saloraV2(`/owner/venues/${venueId}/offers`, {
        method: "POST",
        body: JSON.stringify({
          title,
          offer_type: "percentage",
          percentage: percent,
          starts_on: startsOn,
          ends_on: endsOn,
        }),
      });
      setTitle(""); setDiscount("10"); setStartsOn(""); setEndsOn("");
      setMessage("تم نشر العرض مباشرة في التطبيق.");
      await load();
    } catch (requestError) { setError(requestError.message); }
  }

  async function toggleOffer(offer) {
    setError(""); setMessage("");
    try {
      await saloraV2(`/owner/venues/${venueId}/offers/${offer.id}/toggle`, {
        method: "PATCH", body: JSON.stringify({ is_active: !offer.is_active }),
      });
      setMessage(offer.is_active ? "تم إيقاف العرض." : "تم نشر العرض.");
      await load();
    } catch (requestError) { setError(requestError.message); }
  }

  return <div className="space-y-6 pb-12" dir="rtl">
    <div><h1 className="text-3xl font-black text-white">⏱️ الساعات والعروض</h1><p className="mt-2 text-sm text-slate-400">السعر النظامي للساعة والعروض المنشورة مباشرة. لا توجد موافقة أدمن على العرض.</p></div>
    <Field label="الصالة المراد إدارتها"><select className="field-surface w-full" value={venueId} onChange={(event) => setVenueId(event.target.value)}>{ownerVenues.map((venue) => <option key={venue.id} value={venue.id}>{venue.name}</option>)}</select></Field>
    {loading && <div className="rounded-2xl border border-blue-400/20 bg-blue-500/10 p-4 text-blue-200">جاري التحميل...</div>}
    {message && <div className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4 font-bold text-emerald-200">✅ {message}</div>}
    {error && <div className="rounded-2xl border border-red-400/20 bg-red-500/10 p-4 font-bold text-red-200">⚠️ {error}</div>}

    <form onSubmit={savePricing} className="rounded-3xl border border-white/10 bg-white/[.04] p-6">
      <div className="mb-5"><h2 className="text-xl font-black text-white">تسعير الحجز بالساعة</h2><p className="mt-1 text-sm text-slate-500">بعد حذف العروض القديمة يظهر هذا السعر النظامي حتى ينشر المالك عرضاً جديداً.</p></div>
      <div className="grid gap-4 md:grid-cols-3">
        <Field label="سعر الساعة بالليرة السورية" hint="نصف الساعة الإضافية تُحسب بنصف سعر الساعة."><input required type="number" min="1" className="field-surface w-full" value={price} onChange={(event) => setPrice(event.target.value)} /></Field>
        <Field label="الحد الأدنى للحجز"><div className="field-surface flex min-h-[48px] items-center font-black text-slate-300">ساعتان</div></Field>
        <Field label="الحد الأقصى للحجز بالساعات"><input required type="number" min="2" step="0.5" className="field-surface w-full" value={maxHours} onChange={(event) => setMaxHours(event.target.value)} /></Field>
      </div>
      <button className="mt-5 w-full rounded-2xl bg-amber-500 py-3 font-black text-slate-950 hover:bg-amber-400">حفظ ونشر التسعير</button>
    </form>

    <form onSubmit={addOffer} className="rounded-3xl border border-white/10 bg-white/[.04] p-6">
      <div className="mb-5"><h2 className="text-xl font-black text-white">إضافة عرض جديد</h2><p className="mt-1 text-sm text-slate-500">اختر النسبة والمدة. الحد الأقصى 50%، والنشر فوري.</p></div>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Field label="عنوان العرض"><input required className="field-surface w-full" value={title} onChange={(event) => setTitle(event.target.value)} placeholder="مثال: خصم نهاية الأسبوع" /></Field>
        <Field label="نسبة الخصم" hint="من 1% إلى 50% فقط."><input required type="number" min="1" max="50" className="field-surface w-full" value={discount} onChange={(event) => setDiscount(event.target.value)} /></Field>
        <Field label="يبدأ بتاريخ"><input required type="date" className="field-surface w-full" value={startsOn} onChange={(event) => { setStartsOn(event.target.value); if (endsOn && endsOn < event.target.value) setEndsOn(event.target.value); }} /></Field>
        <Field label="ينتهي بتاريخ"><input required type="date" min={startsOn || undefined} className="field-surface w-full" value={endsOn} onChange={(event) => setEndsOn(event.target.value)} /></Field>
      </div>
      <button className="mt-5 w-full rounded-2xl bg-emerald-500 py-3 font-black text-slate-950 hover:bg-emerald-400">نشر العرض مباشرة</button>
    </form>

    <section className="space-y-4">
      <div><h2 className="text-xl font-black text-white">العروض الحالية</h2><p className="mt-1 text-sm text-slate-500">الأدمن يشاهدها فقط، والعميل يراها ويستفيد منها تلقائياً ضمن المدة.</p></div>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {offers.map((offer) => <article key={offer.id} className="rounded-3xl border border-white/10 bg-white/[.04] p-5">
          <div className="flex items-start justify-between gap-3"><div><h3 className="font-black text-white">{offer.title}</h3><p className="mt-2 text-3xl font-black text-amber-300">{offer.percentage || 0}%</p></div><span className={`rounded-full px-3 py-1 text-xs font-black ${offer.is_active ? "bg-emerald-500/15 text-emerald-300" : "bg-slate-500/15 text-slate-400"}`}>{offer.is_active ? "منشور" : "متوقف"}</span></div>
          <p className="mt-3 text-sm text-slate-400">من {String(offer.starts_on || "").slice(0, 10)} إلى {String(offer.ends_on || "").slice(0, 10)}</p>
          <button type="button" onClick={() => toggleOffer(offer)} className="mt-4 w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2 font-bold hover:bg-white/10">{offer.is_active ? "إيقاف العرض" : "إعادة النشر"}</button>
        </article>)}
        {!offers.length && <div className="rounded-3xl border border-dashed border-white/15 p-8 text-center text-slate-500 md:col-span-2 xl:col-span-3">لا توجد عروض. السعر الظاهر بالتطبيق هو السعر النظامي للساعة.</div>}
      </div>
    </section>
  </div>;
}
