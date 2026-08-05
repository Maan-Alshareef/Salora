import ProtectedPaymentProofButton from "../../components/ProtectedPaymentProof";
import React, { useState } from "react";
import { useApp } from "../../context/AppContext";
import { saloraV2 } from "../../lib/saloraBookingV2Api";

const terminalStatuses = new Set(["Cancelled", "Rejected", "Expired", "Owner Rejected"]);
const confirmedStatuses = new Set(["Confirmed", "Completed"]);

function tone(booking) {
  if (booking.cancellationStatus === "cancelled") return "cancelled";
  if (booking.cancellationStatus === "waiting_refund") return "pending";
  if (terminalStatuses.has(booking.status) || ["Rejected Proof", "Expired"].includes(booking.paymentStatus)) return "cancelled";
  if (confirmedStatuses.has(booking.status) || booking.paymentStatus === "Verified") return "confirmed";
  return "pending";
}

const badgeClass = {
  confirmed: "border-emerald-400/30 bg-emerald-500/15 text-emerald-200",
  pending: "border-amber-400/30 bg-amber-500/15 text-amber-200",
  cancelled: "border-red-400/30 bg-red-500/15 text-red-200",
};

function statusLabel(booking, arabicLabel) {
  if (booking.cancellationStatus === "waiting_refund") return "بانتظار تنفيذ الاسترداد";
  if (booking.cancellationStatus === "cancelled") return "ملغي";
  const value = tone(booking);
  if (value === "confirmed") return "مؤكد";
  if (booking.paymentStatus === "Payment Under Review" || booking.paymentStatus === "Proof Uploaded") return "قيد مراجعة الدفع";
  if (booking.paymentStatus === "Unpaid" || booking.status === "Pending Payment") return "بانتظار الدفع";
  return arabicLabel(booking.status);
}

export default function OwnerBookings() {
  const {
    ownerBookings,
    formatPricePair,
    arabicLabel,
    refreshData,
  } = useApp();
  const [busyBookingId, setBusyBookingId] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  async function confirmRefund(booking) {
    const amount = Number(booking.refundedSyp || 0).toLocaleString("en-US");
    const approved = window.confirm(
      `هل تؤكد أنك أعدت للعميل ${amount} ل.س؟\nبعد التأكيد يصبح الحجز ملغياً نهائياً ويظهر الاسترداد مؤكداً لدى الأدمن.`
    );
    if (!approved) return;

    setBusyBookingId(String(booking.id));
    setMessage("");
    setError("");
    try {
      await saloraV2(`/bookings/${booking.id}/confirm-refund`, {
        method: "POST",
        body: JSON.stringify({}),
      });
      setMessage(`تم تأكيد تنفيذ الاسترداد للحجز #${booking.id}.`);
      refreshData();
    } catch (requestError) {
      setError(requestError.message || "تعذر تأكيد الاسترداد.");
    } finally {
      setBusyBookingId("");
    }
  }

  return (
    <div className="space-y-6 text-white font-sans pb-12" dir="rtl">
      <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">📋 حجوزات صالاتي</h1>
      <p className="text-sm leading-7 text-slate-400">العميل ينشئ الحجز ثم يرفع إثبات الدفع مباشرة. يراجع المالك الإثبات من صفحة الدفعات، وعند قبوله يصبح الحجز مؤكداً تلقائياً. لا توجد موافقة مبدئية ولا زر «منجز».</p>

      <div className="flex flex-wrap gap-3 text-xs font-bold">
        <span className="rounded-full bg-emerald-500/15 px-3 py-2 text-emerald-200">● مؤكد</span>
        <span className="rounded-full bg-amber-500/15 px-3 py-2 text-amber-200">● قيد الدفع أو المراجعة أو الاسترداد</span>
        <span className="rounded-full bg-red-500/15 px-3 py-2 text-red-200">● ملغي أو مرفوض</span>
      </div>

      {message && <div className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4 font-bold text-emerald-200">✅ {message}</div>}
      {error && <div className="rounded-2xl border border-red-400/20 bg-red-500/10 p-4 font-bold text-red-200">⚠️ {error}</div>}

      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1120px] text-sm text-right">
            <thead className="bg-slate-950/50 text-xs text-amber-300">
              <tr className="border-b border-white/10">
                <th className="py-3 px-4">العميل</th>
                <th className="py-3 px-4">الصالة / المناسبة</th>
                <th className="py-3 px-4">التاريخ والوقت</th>
                <th className="py-3 px-4 text-center">الضيوف</th>
                <th className="py-3 px-4">الفاتورة</th>
                <th className="py-3 px-4 text-center">الحالة</th>
                <th className="py-3 px-4 text-center">الدفع</th>
                <th className="py-3 px-4 text-center">إثبات الدفع</th>
                <th className="py-3 px-4 text-center">المطلوب</th>
              </tr>
            </thead>
            <tbody>
              {ownerBookings.map((booking) => {
                const bookingTone = tone(booking);
                const waitingRefund = booking.cancellationStatus === "waiting_refund";
                return (
                  <tr key={booking.id} className="border-b border-white/5 hover:bg-white/5 transition-all">
                    <td className="py-4 px-4"><div className="font-bold text-white">{booking.customer}</div><div className="text-xs text-slate-500">{booking.email}</div></td>
                    <td className="py-4 px-4"><div className="text-slate-300 font-bold">{booking.venue}</div><div className="text-xs text-slate-500">{arabicLabel(booking.eventType)} • {booking.eventName}</div></td>
                    <td className="py-4 px-4 text-slate-400 text-xs">{booking.date} • {booking.time}{booking.endTime ? ` - ${booking.endTime}` : ""}</td>
                    <td className="py-4 px-4 text-center font-bold text-slate-300">{booking.guests} 👥</td>
                    <td className="py-4 px-4 font-bold text-green-400">{formatPricePair(booking.amount, "", booking.amountSyp)}</td>
                    <td className="py-4 px-4 text-center"><span className={`inline-block rounded-full border px-3 py-1 text-[11px] font-bold ${badgeClass[bookingTone]}`}>{statusLabel(booking, arabicLabel)}</span></td>
                    <td className="py-4 px-4 text-center text-xs text-slate-300">{arabicLabel(booking.paymentStatus)}</td>
                    <td className="py-4 px-4 text-center">
                      {booking.paymentProofId ? <ProtectedPaymentProofButton paymentId={booking.paymentProofId} label="عرض الإثبات" className="rounded-lg bg-blue-500/15 px-3 py-2 text-xs font-bold text-blue-300" /> : <span className="text-xs text-slate-500">لم يرفع بعد</span>}
                    </td>
                    <td className="py-4 px-4 text-center text-xs font-bold">
                      {waitingRefund ? (
                        <div className="space-y-2">
                          <div className="text-amber-300">المستحق: {Number(booking.refundedSyp || 0).toLocaleString("en-US")} ل.س ({Number(booking.refundPercentage || 0).toLocaleString("en-US")}%)</div>
                          <button
                            type="button"
                            disabled={busyBookingId === String(booking.id)}
                            onClick={() => confirmRefund(booking)}
                            className="rounded-xl bg-amber-500 px-3 py-2 text-slate-950 disabled:opacity-60"
                          >
                            {busyBookingId === String(booking.id) ? "جاري التأكيد..." : "تأكيد تنفيذ الاسترداد"}
                          </button>
                        </div>
                      ) : bookingTone === "confirmed" ? <span className="text-emerald-300">لا يوجد إجراء — الحجز مؤكد</span> : bookingTone === "cancelled" ? <span className="text-red-300">لا يوجد إجراء</span> : booking.paymentProofId ? <span className="text-amber-300">راجع الإثبات من صفحة الدفعات</span> : <span className="text-amber-300">بانتظار دفع العميل</span>}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {ownerBookings.length === 0 && <div className="p-8 text-center text-slate-400">لا توجد حجوزات لصالاتك حالياً.</div>}
      </div>
    </div>
  );
}
