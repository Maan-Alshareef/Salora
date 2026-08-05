import React, { useMemo, useState } from "react";
import { useApp } from "../context/AppContext";

const statusClass = {
  "Pending Owner Review": "bg-amber-500/15 text-amber-300 border-amber-400/20",
  "Pending Payment": "bg-amber-500/15 text-amber-300 border-amber-400/20",
  "Owner Approved": "bg-blue-500/15 text-blue-300 border-blue-400/20",
  Confirmed: "bg-emerald-500/15 text-emerald-300 border-emerald-400/20",
  Completed: "bg-emerald-500/15 text-emerald-300 border-emerald-400/20",
  Expired: "bg-red-500/15 text-red-300 border-red-400/20",
  Rejected: "bg-red-500/15 text-red-300 border-red-400/20",
  Cancelled: "bg-red-500/15 text-red-300 border-red-400/20"
};
const displayStatus = (status) => status === "Completed" ? "مؤكد" : null;
const paymentClass = { Verified: "text-emerald-300", "Pending Admin Verification": "text-amber-300", "Payment Under Review": "text-amber-300", "Proof Uploaded": "text-amber-300", Unpaid: "text-slate-400", "Not Uploaded": "text-slate-400", "Rejected Proof": "text-red-300", "Re-upload Requested": "text-blue-300" };

export default function BookingsManagement() {
  const { bookings, BOOKING_STATUS, formatPricePair, arabicLabel } = useApp();
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("All");
  const filteredBookings = useMemo(() => bookings.filter((booking) => {
    const haystack = [booking.id, booking.customer, booking.venue, booking.email, booking.eventType, booking.eventName].join(" ").toLowerCase();
    return haystack.includes(query.toLowerCase()) && (status === "All" || booking.status === status);
  }), [bookings, query, status]);
  const totals = filteredBookings.reduce((sum, item) => sum + Number(item.invoiceTotal || item.amount || 0), 0);
  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div><h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">📋 مركز إدارة الحجوزات</h1><p className="mt-2 text-sm text-slate-400">الأدمن يراقب كل الحجوزات، ويظهر هنا تاريخ ووقت الحجز والعميل والصالة، بينما قبول الحجز النهائي يتم من المالك بعد قبول إثبات الدفع.</p></div>
        <div className="grid w-full max-w-2xl gap-3 md:grid-cols-[1fr_240px]"><input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="ابحث عن حجز، عميل، صالة، مناسبة..." className="field-surface" /><select value={status} onChange={(e) => setStatus(e.target.value)} className="field-surface"><option value="All">كل الحالات</option>{Object.values(BOOKING_STATUS).map((item) => <option key={item} value={item}>{arabicLabel(item)}</option>)}</select></div>
      </div>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-3"><div className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">عدد الحجوزات المعروضة</div><div className="mt-1 text-2xl font-black">{filteredBookings.length}</div></div><div className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">قيمة الفواتير المعروضة</div><div className="mt-1 text-2xl font-black text-blue-300">{formatPricePair(totals)}</div></div><div className="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">بانتظار مراجعة الدفع</div><div className="mt-1 text-2xl font-black text-amber-300">{bookings.filter((b) => ["Pending Admin Verification", "Payment Under Review", "Proof Uploaded"].includes(b.paymentStatus)).length}</div></div></div>
      <div className="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04] shadow-2xl"><div className="overflow-x-auto"><table className="w-full min-w-[1250px] text-right text-sm"><thead className="bg-slate-950/50 text-xs text-blue-300"><tr><th className="px-5 py-4">الحجز</th><th className="px-5 py-4">العميل</th><th className="px-5 py-4">الصالة / المالك</th><th className="px-5 py-4">المناسبة</th><th className="px-5 py-4">التاريخ</th><th className="px-5 py-4">الخدمات</th><th className="px-5 py-4">الفاتورة</th><th className="px-5 py-4 text-center">حالة الحجز</th><th className="px-5 py-4 text-center">حالة الدفع</th><th className="px-5 py-4 text-center">صلاحية الأدمن</th></tr></thead><tbody>{filteredBookings.map((booking) => (<tr key={booking.id} className="border-t border-white/5 hover:bg-white/[.03] align-top"><td className="px-5 py-4 font-mono font-black text-blue-300">{booking.id}</td><td className="px-5 py-4"><div className="font-bold">{booking.customer}</div><div className="text-xs text-slate-500">{booking.email}</div></td><td className="px-5 py-4"><div className="font-bold text-slate-200">{booking.venue}</div><div className="text-xs text-slate-500">معرّف المالك: {booking.ownerId}</div></td><td className="px-5 py-4"><div className="font-bold text-white">{arabicLabel(booking.eventType)}</div><div className="text-xs text-slate-500">{booking.eventName}</div></td><td className="px-5 py-4 text-slate-300">{booking.date}<div className="text-xs text-slate-500">{booking.time}{booking.endTime ? ` - ${booking.endTime}` : ""} • {booking.guests} ضيف</div></td><td className="px-5 py-4 text-xs text-slate-300"><div>المجانية: {(booking.includedServices || []).length}</div><div>الإضافية: {(booking.hallUpgrades || []).length}</div><div>مقدمو الخدمة: {(booking.externalServices || []).length}</div></td><td className="px-5 py-4 font-bold text-emerald-300">{formatPricePair(Number(booking.invoiceTotal || booking.amount))}</td><td className="px-5 py-4 text-center"><span className={`rounded-full border px-3 py-1 text-[11px] font-black ${statusClass[booking.status] || statusClass["Pending Owner Review"]}`}>{displayStatus(booking.status) || arabicLabel(booking.status)}</span></td><td className={`px-5 py-4 text-center font-bold ${paymentClass[booking.paymentStatus] || "text-slate-300"}`}>{arabicLabel(booking.paymentStatus)}</td><td className="px-5 py-4 text-center"><span className="inline-block rounded-xl border border-white/10 bg-white/[.04] px-3 py-2 text-xs font-bold text-slate-300">متابعة فقط؛ تعديل الحجز والدفع من مالك الصالة</span></td></tr>))}</tbody></table></div>{filteredBookings.length === 0 && <div className="p-10 text-center text-slate-400">لا توجد حجوزات مطابقة للبحث.</div>}</div>
    </div>
  );
}
