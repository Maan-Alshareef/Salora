import React, { useMemo, useState } from "react";
import { useApp } from "../../context/AppContext";

const pad = (n) => String(n).padStart(2, "0");
const monthNames = ["كانون الثاني", "شباط", "آذار", "نيسان", "أيار", "حزيران", "تموز", "آب", "أيلول", "تشرين الأول", "تشرين الثاني", "كانون الأول"];

const terminalStatuses = new Set(["Cancelled", "Rejected", "Expired", "Owner Rejected"]);
const confirmedStatuses = new Set(["Confirmed", "Completed"]);

function bookingTone(booking) {
  if (booking.cancellationStatus === "cancelled") return "cancelled";
  if (booking.cancellationStatus === "waiting_refund") return "pending";
  if (terminalStatuses.has(booking.status) || ["Rejected Proof", "Expired"].includes(booking.paymentStatus)) return "cancelled";
  if (confirmedStatuses.has(booking.status) || booking.paymentStatus === "Verified") return "confirmed";
  return "pending";
}

const toneCard = {
  confirmed: "border-emerald-400/30 bg-emerald-500/15 text-emerald-100",
  pending: "border-amber-400/30 bg-amber-500/15 text-amber-100",
  cancelled: "border-red-400/30 bg-red-500/15 text-red-100",
};

const toneDay = {
  confirmed: "border-emerald-400/30 bg-emerald-500/10 hover:bg-emerald-500/15",
  pending: "border-amber-400/30 bg-amber-500/10 hover:bg-amber-500/15",
  cancelled: "border-red-400/30 bg-red-500/10 hover:bg-red-500/15",
};

const toneBadge = {
  confirmed: "bg-emerald-500/20 text-emerald-200",
  pending: "bg-amber-500/20 text-amber-200",
  cancelled: "bg-red-500/20 text-red-200",
};

function bookingLabel(booking, arabicLabel) {
  if (booking.cancellationStatus === "waiting_refund") return "بانتظار تنفيذ الاسترداد";
  if (booking.cancellationStatus === "cancelled") return "ملغي";
  const tone = bookingTone(booking);
  if (tone === "confirmed") return "مؤكد";
  if (tone === "cancelled") return arabicLabel(booking.status);
  if (booking.paymentStatus === "Payment Under Review" || booking.paymentStatus === "Proof Uploaded") return "قيد مراجعة الدفع";
  if (booking.paymentStatus === "Unpaid" || booking.status === "Pending Payment") return "بانتظار الدفع";
  return arabicLabel(booking.status);
}

function monthDays(year, month) {
  const count = new Date(year, month + 1, 0).getDate();
  return Array.from({ length: count }, (_, i) => `${year}-${pad(month + 1)}-${pad(i + 1)}`);
}

function cleanDate(value = "") {
  const text = String(value || "");
  const iso = text.match(/\d{4}-\d{2}-\d{2}/);
  return iso ? iso[0] : text.slice(0, 10);
}

function dayTone(bookings) {
  if (bookings.some((booking) => bookingTone(booking) === "confirmed")) return "confirmed";
  if (bookings.some((booking) => bookingTone(booking) === "pending")) return "pending";
  return bookings.length ? "cancelled" : null;
}

export default function BookingCalendar() {
  const { ownerBookings, ownerVenues, arabicLabel } = useApp();
  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth());
  const [selectedVenue, setSelectedVenue] = useState("All");
  const [selectedDay, setSelectedDay] = useState(null);

  const visibleBookings = useMemo(() => ownerBookings
    .map((booking) => ({ ...booking, date: cleanDate(booking.date) }))
    .filter((booking) => {
      const inMonth = String(booking.date || "").startsWith(`${year}-${pad(month + 1)}`);
      const venueOk = selectedVenue === "All" || String(booking.venueId) === String(selectedVenue) || booking.venue === selectedVenue;
      return inMonth && venueOk;
    })
    .sort((a, b) => `${a.date} ${a.time || ""}`.localeCompare(`${b.date} ${b.time || ""}`)), [ownerBookings, year, month, selectedVenue]);

  const byDate = useMemo(() => visibleBookings.reduce((map, booking) => {
    const key = cleanDate(booking.date);
    map[key] = [...(map[key] || []), booking];
    return map;
  }, {}), [visibleBookings]);

  const moveMonth = (step) => {
    const next = new Date(year, month + step, 1);
    setYear(next.getFullYear());
    setMonth(next.getMonth());
    setSelectedDay(null);
  };

  const days = monthDays(year, month);
  const selectedBookings = selectedDay ? byDate[selectedDay] || [] : [];

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="rounded-3xl border border-amber-400/20 bg-amber-500/10 p-6">
        <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">📅 تقويم حجوزات صالاتي</h1>
        <p className="mt-3 max-w-4xl text-sm leading-7 text-slate-300">الأخضر للحجز المؤكد بعد قبول الدفع، الأصفر للحجز الذي ما زال بانتظار الدفع أو المراجعة، والأحمر للحجز الملغي أو المرفوض أو المنتهي.</p>
      </div>

      <div className="flex flex-wrap gap-3 text-xs font-bold">
        <span className="rounded-full bg-emerald-500/15 px-3 py-2 text-emerald-200">● مؤكد</span>
        <span className="rounded-full bg-amber-500/15 px-3 py-2 text-amber-200">● قيد الدفع أو التحقق</span>
        <span className="rounded-full bg-red-500/15 px-3 py-2 text-red-200">● ملغي أو مرفوض</span>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div className="rounded-3xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">كل حجوزاتي</div><div className="mt-2 text-3xl font-black">{ownerBookings.length}</div></div>
        <div className="rounded-3xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">حجوزات هذا الشهر</div><div className="mt-2 text-3xl font-black text-amber-300">{visibleBookings.length}</div></div>
        <div className="rounded-3xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">الشهر المعروض</div><div className="mt-2 text-lg font-black text-slate-100">{monthNames[month]} {year}</div></div>
        <div className="rounded-3xl border border-white/10 bg-white/[.04] p-5"><div className="text-xs text-slate-500">الصالة</div><div className="mt-2 text-lg font-black text-slate-100">{selectedVenue === "All" ? "كل الصالات" : ownerVenues.find((v) => String(v.id) === String(selectedVenue))?.name || "صالة محددة"}</div></div>
      </div>

      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-5">
        <div className="grid gap-3 md:grid-cols-[140px_1fr_140px_160px_180px]">
          <button onClick={() => moveMonth(-1)} className="rounded-xl border border-white/10 bg-white/[.05] px-4 py-3 text-sm font-bold text-slate-200 hover:bg-white/10">الشهر السابق</button>
          <select className="field-surface" value={selectedVenue} onChange={(e) => { setSelectedVenue(e.target.value); setSelectedDay(null); }}>
            <option value="All">كل صالاتي</option>
            {ownerVenues.map((venue) => <option key={venue.id} value={venue.id}>{venue.name}</option>)}
          </select>
          <button onClick={() => moveMonth(1)} className="rounded-xl border border-white/10 bg-white/[.05] px-4 py-3 text-sm font-bold text-slate-200 hover:bg-white/10">الشهر التالي</button>
          <input className="field-surface" type="number" value={year} onChange={(e) => { setYear(Number(e.target.value) || year); setSelectedDay(null); }} />
          <select className="field-surface" value={month} onChange={(e) => { setMonth(Number(e.target.value)); setSelectedDay(null); }}>{monthNames.map((name, index) => <option key={name} value={index}>{name}</option>)}</select>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
        {days.map((day) => {
          const dayBookings = byDate[day] || [];
          const isSelected = selectedDay === day;
          const tone = dayTone(dayBookings);
          const confirmedCount = dayBookings.filter((booking) => bookingTone(booking) === "confirmed").length;
          return <button key={day} onClick={() => setSelectedDay(day)} className={`min-h-[135px] rounded-2xl border p-3 text-right transition ${isSelected ? "border-white/60 bg-white/15" : tone ? toneDay[tone] : "border-white/5 bg-white/[.03] hover:border-white/20"}`}>
            <div className="flex items-center justify-between"><span className="text-lg font-black">{Number(day.slice(-2))}</span>{dayBookings.length > 0 && <span className={`rounded-full px-2 py-1 text-[10px] font-bold ${toneBadge[tone]}`}>{dayBookings.length} حجز</span>}</div>
            <div className="mt-2 text-[10px] text-slate-400">{confirmedCount ? `${confirmedCount} مؤكد` : dayBookings.length ? bookingLabel(dayBookings[0], arabicLabel) : "متاح"}</div>
            <div className="mt-2 space-y-1">{dayBookings.slice(0, 3).map((booking) => { const itemTone = bookingTone(booking); return <div key={booking.id} className={`truncate rounded-lg px-2 py-1 text-[10px] ${toneBadge[itemTone]}`}>{booking.time || "--:--"} • {booking.venue}</div>; })}</div>
          </button>;
        })}
      </div>

      {selectedDay && <div className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/80 p-4 backdrop-blur-xl">
        <div className="max-h-[88vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-amber-400/30 bg-slate-950 p-6">
          <div className="flex items-start justify-between border-b border-white/10 pb-4"><div><h3 className="text-xl font-black">حجوزات يوم {selectedDay}</h3><p className="mt-1 text-xs text-slate-400">كل حالة مرتبطة بالدفع والتحقق الفعلي.</p></div><button onClick={() => setSelectedDay(null)} className="rounded-xl bg-white/5 px-3 py-2">✕</button></div>
          <div className="mt-4 space-y-3">{selectedBookings.length ? selectedBookings.map((booking) => { const tone = bookingTone(booking); return <div key={booking.id} className={`rounded-2xl border p-4 ${toneCard[tone]}`}>
            <div className="flex items-start justify-between gap-3"><div className="text-lg font-black">{booking.venue}</div><span className={`rounded-full px-3 py-1 text-xs font-black ${toneBadge[tone]}`}>{bookingLabel(booking, arabicLabel)}</span></div>
            <div className="mt-2 grid gap-2 text-sm sm:grid-cols-2"><span>👤 العميل: {booking.customer}</span><span>🎉 المناسبة: {arabicLabel(booking.eventType)}</span><span>📅 التاريخ: {booking.date}</span><span>⏰ الوقت: {booking.time || "غير محدد"}{booking.endTime ? ` - ${booking.endTime}` : ""}</span><span>👥 الضيوف: {booking.guests}</span><span>💳 الدفع: {arabicLabel(booking.paymentStatus)}</span></div>
          </div>; }) : <div className="rounded-2xl bg-white/[.04] p-6 text-center text-slate-400">لا توجد حجوزات في هذا اليوم.</div>}</div>
        </div>
      </div>}
    </div>
  );
}
