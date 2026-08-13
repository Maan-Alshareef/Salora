import React, { useEffect, useMemo, useState } from "react";
import { useApp } from "../context/AppContext";
import { saloraV2 } from "../lib/saloraBookingV2Api";

const statusClass = {
  "Pending Owner Review": "bg-amber-500/15 text-amber-300 border-amber-400/20",
  "Pending Payment": "bg-amber-500/15 text-amber-300 border-amber-400/20",
  "Owner Approved": "bg-blue-500/15 text-blue-300 border-blue-400/20",
  Confirmed: "bg-emerald-500/15 text-emerald-300 border-emerald-400/20",
  Completed: "bg-emerald-500/15 text-emerald-300 border-emerald-400/20",
  Expired: "bg-red-500/15 text-red-300 border-red-400/20",
  Rejected: "bg-red-500/15 text-red-300 border-red-400/20",
  Cancelled: "bg-red-500/15 text-red-300 border-red-400/20",
};
const displayStatus = (status) => (status === "Completed" ? "مؤكد" : null);
const paymentClass = {
  Verified: "text-emerald-300",
  "Pending Admin Verification": "text-amber-300",
  "Payment Under Review": "text-amber-300",
  "Proof Uploaded": "text-amber-300",
  Unpaid: "text-slate-400",
  "Not Uploaded": "text-slate-400",
  "Rejected Proof": "text-red-300",
  "Re-upload Requested": "text-blue-300",
};

function slot(value) {
  const text = String(value || "").replace("T", " ");
  if (!text) return "-";
  const [date = "", time = ""] = text.split(" ");
  return `${date} ${time.slice(0, 5)}`;
}

function syp(value) {
  return `${Number(value || 0).toLocaleString("en-US")} ل.س`;
}

const changeStatusLabel = {
  pending: "قيد مراجعة المالك",
  awaiting_payment: "وافق المالك — بانتظار دفع فرق السعر",
  approved: "تمت الموافقة والتطبيق",
  rejected: "مرفوض — بقي الحجز القديم",
};

export default function BookingsManagement() {
  const { bookings, BOOKING_STATUS, formatPricePair, arabicLabel } = useApp();
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("All");
  const [changeRequests, setChangeRequests] = useState([]);
  const [changeError, setChangeError] = useState("");

  useEffect(() => {
    let mounted = true;
    saloraV2("/admin/change-requests?status=all")
      .then((items) => {
        if (mounted) setChangeRequests(Array.isArray(items) ? items : []);
      })
      .catch((error) => {
        if (mounted) setChangeError(error.message || "تعذر تحميل سجل تعديلات الحجوزات.");
      });
    return () => {
      mounted = false;
    };
  }, [bookings]);

  const latestChangeByBooking = useMemo(() => {
    const map = new Map();
    for (const item of changeRequests) {
      const key = String(item.booking_id);
      if (!map.has(key)) map.set(key, item);
    }
    return map;
  }, [changeRequests]);

  const filteredBookings = useMemo(
    () =>
      bookings.filter((booking) => {
        const haystack = [
          booking.id,
          booking.customer,
          booking.venue,
          booking.email,
          booking.eventType,
          booking.eventName,
        ]
          .join(" ")
          .toLowerCase();
        return (
          haystack.includes(query.toLowerCase()) &&
          (status === "All" || booking.status === status)
        );
      }),
    [bookings, query, status]
  );
  const totals = filteredBookings.reduce(
    (sum, item) => sum + Number(item.invoiceTotal || item.amount || 0),
    0
  );
  const pendingChangesCount = changeRequests.filter((item) => item.status === "pending").length;

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <h1 className="bg-gradient-to-r from-blue-300 to-white bg-clip-text text-3xl font-black text-transparent">📋 مركز إدارة الحجوزات</h1>
          <p className="mt-2 text-sm text-slate-400">الأدمن يراقب الحجز الحالي وسجل طلبات التعديل. قرار التعديل للمالك، وبعد الموافقة تظهر البيانات الجديدة هنا مباشرة مع الاحتفاظ بمقارنة القديم والجديد.</p>
        </div>
        <div className="grid w-full max-w-2xl gap-3 md:grid-cols-[1fr_240px]">
          <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="ابحث عن حجز، عميل، صالة، مناسبة..." className="field-surface" />
          <select value={status} onChange={(e) => setStatus(e.target.value)} className="field-surface">
            <option value="All">كل الحالات</option>
            {Object.values(BOOKING_STATUS).map((item) => (
              <option key={item} value={item}>{arabicLabel(item)}</option>
            ))}
          </select>
        </div>
      </div>

      {changeError && <div className="rounded-2xl border border-red-400/20 bg-red-500/10 p-4 text-sm font-bold text-red-200">⚠️ {changeError}</div>}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">عدد الحجوزات المعروضة</div><div className="mt-1 text-2xl font-black">{filteredBookings.length}</div></div>
        <div className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">قيمة الفواتير المعروضة</div><div className="mt-1 text-2xl font-black text-blue-300">{formatPricePair(totals)}</div></div>
        <div className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">بانتظار مراجعة الدفع</div><div className="mt-1 text-2xl font-black text-amber-300">{bookings.filter((b) => ["Pending Admin Verification", "Payment Under Review", "Proof Uploaded"].includes(b.paymentStatus)).length}</div></div>
        <div className="rounded-2xl border border-amber-400/20 bg-amber-500/[.06] p-5"><div className="text-xs text-amber-200/70">طلبات تعديل معلقة</div><div className="mt-1 text-2xl font-black text-amber-300">{pendingChangesCount}</div></div>
      </div>

      <div className="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04] shadow-2xl">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1500px] text-right text-sm">
            <thead className="bg-slate-950/50 text-xs text-blue-300">
              <tr>
                <th className="px-5 py-4">الحجز</th>
                <th className="px-5 py-4">العميل</th>
                <th className="px-5 py-4">الصالة / المالك</th>
                <th className="px-5 py-4">المناسبة</th>
                <th className="px-5 py-4">التاريخ الحالي</th>
                <th className="px-5 py-4">آخر طلب تعديل</th>
                <th className="px-5 py-4">الخدمات</th>
                <th className="px-5 py-4">الفاتورة</th>
                <th className="px-5 py-4 text-center">حالة الحجز</th>
                <th className="px-5 py-4 text-center">حالة الدفع</th>
                <th className="px-5 py-4 text-center">صلاحية الأدمن</th>
              </tr>
            </thead>
            <tbody>
              {filteredBookings.map((booking) => {
                const change = latestChangeByBooking.get(String(booking.id));
                const adjustment = change?.payment_adjustment;
                return (
                  <tr key={booking.id} className="border-t border-white/5 align-top hover:bg-white/[.03]">
                    <td className="px-5 py-4 font-mono font-black text-blue-300">{booking.id}</td>
                    <td className="px-5 py-4"><div className="font-bold">{booking.customer}</div><div className="text-xs text-slate-500">{booking.email}</div></td>
                    <td className="px-5 py-4"><div className="font-bold text-slate-200">{booking.venue}</div><div className="text-xs text-slate-500">معرّف المالك: {booking.ownerId}</div></td>
                    <td className="px-5 py-4"><div className="font-bold text-white">{arabicLabel(booking.eventType)}</div><div className="text-xs text-slate-500">{booking.eventName}</div></td>
                    <td className="px-5 py-4 text-slate-300">{booking.date}<div className="text-xs text-slate-500">{booking.time}{booking.endTime ? ` - ${booking.endTime}` : ""} • {booking.guests} ضيف</div></td>
                    <td className="px-5 py-4 text-xs">
                      {!change ? (
                        <span className="text-slate-500">لا يوجد تعديل</span>
                      ) : (
                        <div className="min-w-[270px] space-y-2 rounded-xl border border-white/10 bg-white/[.03] p-3">
                          <div className={change.status === "approved" ? "font-black text-emerald-300" : change.status === "rejected" ? "font-black text-red-300" : "font-black text-amber-300"}>{changeStatusLabel[change.status] || change.status}</div>
                          <div className="text-slate-400">قديم: {slot(change.old?.start_at)} → {slot(change.old?.end_at)} • {change.old?.guests_count || 0} ضيف</div>
                          <div className="text-blue-200">مطلوب: {slot(change.requested?.start_at)} → {slot(change.requested?.end_at)} • {change.requested?.guests_count || 0} ضيف</div>
                          <div className="text-slate-400">السعر المثبت: {syp(change.quote_snapshot?.final_price_syp)}{change.quote_snapshot?.offer ? ` • عرض: ${change.quote_snapshot.offer.title || "عرض"}` : ""}</div>
                          {adjustment && ["pending_payment", "proof_uploaded", "pending_refund", "pending"].includes(String(adjustment.status || "")) && (
                            <div className="font-bold text-amber-200">{adjustment.type === "additional_payment" ? "فرق دفع مطلوب" : "استرداد فرق مستحق"}: {syp(adjustment.amount_syp)}</div>
                          )}
                        </div>
                      )}
                    </td>
                    <td className="px-5 py-4 text-xs text-slate-300"><div>المجانية: {(booking.includedServices || []).length}</div><div>الإضافية: {(booking.hallUpgrades || []).length}</div><div>مقدمو الخدمة: {(booking.externalServices || []).length}</div></td>
                    <td className="px-5 py-4 font-bold text-emerald-300">{formatPricePair(Number(booking.invoiceTotal || booking.amount))}</td>
                    <td className="px-5 py-4 text-center"><span className={`rounded-full border px-3 py-1 text-[11px] font-black ${statusClass[booking.status] || statusClass["Pending Owner Review"]}`}>{displayStatus(booking.status) || arabicLabel(booking.status)}</span></td>
                    <td className={`px-5 py-4 text-center font-bold ${paymentClass[booking.paymentStatus] || "text-slate-300"}`}>{arabicLabel(booking.paymentStatus)}</td>
                    <td className="px-5 py-4 text-center"><span className="inline-block rounded-xl border border-white/10 bg-white/[.04] px-3 py-2 text-xs font-bold text-slate-300">متابعة فقط؛ قرار طلب التعديل من المالك</span></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {filteredBookings.length === 0 && <div className="p-10 text-center text-slate-400">لا توجد حجوزات مطابقة للبحث.</div>}
      </div>
    </div>
  );
}
