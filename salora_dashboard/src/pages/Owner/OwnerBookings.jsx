import ProtectedPaymentProofButton from "../../components/ProtectedPaymentProof";
import React, { useEffect, useMemo, useState } from "react";
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

function slotLabel(value) {
  const text = String(value || "").replace("T", " ");
  if (!text) return "-";
  const [date = "", clock = ""] = text.split(" ");
  return `${date} • ${clock.slice(0, 5)}`;
}

function syp(value) {
  return `${Number(value || 0).toLocaleString("en-US")} ل.س`;
}

export default function OwnerBookings() {
  const {
    ownerBookings,
    formatPricePair,
    arabicLabel,
    refreshData,
  } = useApp();
  const [busyBookingId, setBusyBookingId] = useState("");
  const [busyChangeId, setBusyChangeId] = useState("");
  const [changeRequests, setChangeRequests] = useState([]);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const pendingChanges = useMemo(
    () => changeRequests.filter((item) => item.status === "pending"),
    [changeRequests]
  );
  const latestChangeByBooking = useMemo(() => {
    const map = new Map();
    for (const item of changeRequests) {
      const key = String(item.booking_id);
      if (!map.has(key)) map.set(key, item);
    }
    return map;
  }, [changeRequests]);

  async function loadChangeRequests() {
    try {
      const items = await saloraV2("/owner/change-requests?status=all");
      setChangeRequests(Array.isArray(items) ? items : []);
    } catch (requestError) {
      setError(requestError.message || "تعذر تحميل طلبات تعديل الحجوزات.");
    }
  }

  useEffect(() => {
    loadChangeRequests();
  }, []);

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
      await loadChangeRequests();
    } catch (requestError) {
      setError(requestError.message || "تعذر تأكيد الاسترداد.");
    } finally {
      setBusyBookingId("");
    }
  }

  async function confirmAdjustmentRefund(booking, adjustment) {
    const amount = Number(adjustment?.amount_syp || 0).toLocaleString("en-US");
    const approved = window.confirm(`هل تؤكد أنك أعدت فرق السعر ${amount} ل.س للعميل؟`);
    if (!approved) return;

    setBusyBookingId(String(booking.id));
    setMessage("");
    setError("");
    try {
      await saloraV2(`/bookings/${booking.id}/payment-adjustments/${adjustment.id}/confirm-refund`, {
        method: "POST",
        body: JSON.stringify({}),
      });
      setMessage(`تم تأكيد رد فرق السعر للحجز #${booking.id}.`);
      refreshData();
      await loadChangeRequests();
    } catch (requestError) {
      setError(requestError.message || "تعذر تأكيد رد فرق السعر.");
    } finally {
      setBusyBookingId("");
    }
  }

  async function decideChange(item, decision) {
    let reason = "";
    if (decision === "approve") {
      const approved = window.confirm(
        "تأكيد قبول طلب التعديل؟ سيعيد النظام فحص التوفر والسعر المثبت. إذا زاد السعر سيُطلب من العميل دفع الفرق أولاً ولن يعتمد الموعد الجديد نهائياً إلا بعد قبول إثبات الفرق. إذا كان السعر نفسه أو أقل يطبق التعديل مباشرة."
      );
      if (!approved) return;
    } else {
      reason = window.prompt("سبب رفض طلب التعديل (اختياري):") || "";
    }

    setBusyChangeId(String(item.id));
    setMessage("");
    setError("");
    try {
      const response = await saloraV2(
        `/bookings/${item.booking_id}/change-requests/${item.id}/${decision === "approve" ? "approve" : "reject"}`,
        {
          method: "POST",
          body: JSON.stringify(decision === "reject" && reason.trim() ? { reason: reason.trim() } : {}),
        }
      );
      setMessage(response?.message || (decision === "approve" ? "تم قبول طلب التعديل." : "تم رفض طلب التعديل."));
      await loadChangeRequests();
      refreshData();
    } catch (requestError) {
      setError(requestError.message || "تعذر معالجة طلب التعديل.");
    } finally {
      setBusyChangeId("");
    }
  }

  return (
    <div className="space-y-6 text-white font-sans pb-12" dir="rtl">
      <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-300 to-white">📋 حجوزات صالاتي</h1>
      <p className="text-sm leading-7 text-slate-400">تعديلات التاريخ والوقت وعدد الضيوف تمر بطلب رسمي. إذا زاد السعر بعد موافقتك يبقى الحجز القديم فعالاً ويحجز الموعد الجديد للطلب حتى يدفع العميل الفرق ويُقبل إثباته. إذا لم يزد السعر يطبق التعديل مباشرة بعد فحص التوفر.</p>

      <div className="flex flex-wrap gap-3 text-xs font-bold">
        <span className="rounded-full bg-emerald-500/15 px-3 py-2 text-emerald-200">● مؤكد</span>
        <span className="rounded-full bg-amber-500/15 px-3 py-2 text-amber-200">● قيد الدفع أو المراجعة أو طلب تعديل أو الاسترداد</span>
        <span className="rounded-full bg-red-500/15 px-3 py-2 text-red-200">● ملغي أو مرفوض</span>
      </div>

      {message && <div className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4 font-bold text-emerald-200">✅ {message}</div>}
      {error && <div className="rounded-2xl border border-red-400/20 bg-red-500/10 p-4 font-bold text-red-200">⚠️ {error}</div>}

      {pendingChanges.length > 0 && (
        <section className="space-y-4 rounded-3xl border border-amber-400/20 bg-amber-500/[.06] p-5">
          <div>
            <h2 className="text-xl font-black text-amber-200">📝 طلبات تعديل بانتظار قرارك</h2>
            <p className="mt-1 text-xs leading-6 text-slate-400">الموعد القديم لا يزال فعالاً. عند الموافقة يعاد فحص الموعد؛ وإذا كان هناك فرق دفع إضافي يُحمى الموعد الجديد حتى اكتمال دفع الفرق بدون أي مهلة زمنية.</p>
          </div>
          <div className="grid gap-4 xl:grid-cols-2">
            {pendingChanges.map((item) => {
              const quote = item.quote_snapshot || {};
              const offer = quote.offer;
              return (
                <article key={item.id} className="rounded-2xl border border-white/10 bg-slate-950/40 p-5">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <div className="font-black">الحجز #{item.booking_id} • {item.venue?.name || "الصالة"}</div>
                      <div className="mt-1 text-xs text-slate-400">العميل: {item.customer?.name || "-"}</div>
                    </div>
                    <span className="rounded-full bg-amber-500/15 px-3 py-1 text-xs font-black text-amber-200">قيد المراجعة</span>
                  </div>

                  <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <div className="rounded-xl bg-white/[.04] p-3">
                      <div className="text-[11px] font-black text-slate-500">الحجز الحالي</div>
                      <div className="mt-1 text-sm font-bold">{slotLabel(item.old?.start_at)}</div>
                      <div className="text-xs text-slate-400">إلى {slotLabel(item.old?.end_at)} • {item.old?.guests_count || 0} ضيف</div>
                    </div>
                    <div className="rounded-xl bg-blue-500/[.08] p-3">
                      <div className="text-[11px] font-black text-blue-300">المطلوب</div>
                      <div className="mt-1 text-sm font-bold">{slotLabel(item.requested?.start_at)}</div>
                      <div className="text-xs text-slate-300">إلى {slotLabel(item.requested?.end_at)} • {item.requested?.guests_count || 0} ضيف</div>
                    </div>
                  </div>

                  <div className="mt-3 rounded-xl border border-white/10 p-3 text-xs leading-6 text-slate-300">
                    <div>السعر الجديد: <b className="text-emerald-300">{syp(quote.final_price_syp)}</b></div>
                    <div>السعر قبل العرض: {syp(quote.price_before_discount_syp)}</div>
                    <div>العرض المثبت: {offer ? `${offer.title || "عرض"} — خصم ${syp(quote.discount_syp)}` : "لا يوجد عرض"}</div>
                    {item.reason && <div>ملاحظة العميل: {item.reason}</div>}
                  </div>

                  <div className="mt-4 flex gap-3">
                    <button
                      type="button"
                      disabled={busyChangeId === String(item.id)}
                      onClick={() => decideChange(item, "reject")}
                      className="flex-1 rounded-xl bg-red-500/15 py-3 font-black text-red-200 disabled:opacity-50"
                    >
                      رفض
                    </button>
                    <button
                      type="button"
                      disabled={busyChangeId === String(item.id)}
                      onClick={() => decideChange(item, "approve")}
                      className="flex-1 rounded-xl bg-emerald-500 py-3 font-black text-slate-950 disabled:opacity-50"
                    >
                      {busyChangeId === String(item.id) ? "جاري المعالجة..." : "موافقة على التعديل"}
                    </button>
                  </div>
                </article>
              );
            })}
          </div>
        </section>
      )}

      <div className="rounded-3xl border border-white/10 bg-white/[.04] p-6 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1240px] text-sm text-right">
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
                <th className="py-3 px-4 text-center">التعديل / المطلوب</th>
              </tr>
            </thead>
            <tbody>
              {ownerBookings.map((booking) => {
                const bookingTone = tone(booking);
                const waitingRefund = booking.cancellationStatus === "waiting_refund";
                const change = latestChangeByBooking.get(String(booking.id));
                const adjustment = change?.payment_adjustment;
                return (
                  <tr key={booking.id} className="border-b border-white/5 hover:bg-white/5 transition-all align-top">
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
                      {change?.status === "pending" ? (
                        <span className="text-amber-300">يوجد طلب تعديل بانتظار قرارك</span>
                      ) : adjustment && ["pending_payment", "proof_uploaded", "pending_refund", "pending"].includes(String(adjustment.status || "")) ? (
                        adjustment.type === "refund_due" && adjustment.status === "pending_refund" ? (
                          <div className="space-y-2">
                            <div className="text-amber-300">استرداد فرق للعميل: {syp(adjustment.amount_syp)}</div>
                            <button
                              type="button"
                              disabled={busyBookingId === String(booking.id)}
                              onClick={() => confirmAdjustmentRefund(booking, adjustment)}
                              className="rounded-xl bg-amber-500 px-3 py-2 text-slate-950 disabled:opacity-60"
                            >
                              {busyBookingId === String(booking.id) ? "جاري التأكيد..." : "تأكيد رد فرق السعر"}
                            </button>
                          </div>
                        ) : (
                          <span className="text-blue-300">
                            {adjustment.status === "proof_uploaded" ? "إثبات فرق الدفع قيد المراجعة: " : "فرق دفع مطلوب من العميل: "}{syp(adjustment.amount_syp)}
                          </span>
                        )
                      ) : waitingRefund ? (
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
