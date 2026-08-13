import ProtectedPaymentProofButton from "../../components/ProtectedPaymentProof";
import React, { useEffect, useState } from "react";
import { apiClient } from "../../services/apiClient";
import { useApp } from "../../context/AppContext";

function syp(value) {
  return `${Number(value || 0).toLocaleString("en-US")} ل.س`;
}

export default function OwnerPaymentStatus() {
  const { refreshData } = useApp();
  const [items, setItems] = useState([]);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [actionId, setActionId] = useState("");

  const load = async () => {
    setBusy(true);
    try {
      setItems(await apiClient.get("/business/payments"));
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const approve = async (payment) => {
    const adjustment = payment.payment_adjustment;
    const label = adjustment?.type === "additional_payment"
      ? `فرق تعديل الحجز بقيمة ${syp(adjustment.amount_syp)}`
      : "الدفعة";
    if (!window.confirm(`تأكيد وصول ${label} وقبول الإثبات؟`)) return;
    setActionId(String(payment.id));
    setMessage("");
    try {
      await apiClient.post(`/business/payments/${payment.id}/approve`, {});
      setMessage(adjustment ? "تم قبول فرق الدفع واعتماد تعديل الحجز نهائياً." : "تم قبول الدفعة.");
      await load();
      await refreshData();
    } catch (error) {
      setMessage(error.message);
    } finally {
      setActionId("");
    }
  };

  const reject = async (payment) => {
    const reason = window.prompt("سبب الرفض:");
    if (!reason?.trim()) return;
    setActionId(String(payment.id));
    setMessage("");
    try {
      await apiClient.post(`/business/payments/${payment.id}/reject`, { reason });
      setMessage(payment.payment_adjustment ? "تم رفض إثبات فرق الدفع ويمكن للعميل إعادة رفع إثبات جديد في أي وقت." : "تم رفض الإثبات ويمكن للعميل إعادة الرفع.");
      await load();
    } catch (error) {
      setMessage(error.message);
    } finally {
      setActionId("");
    }
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div>
        <h1 className="text-3xl font-black">💳 مراجعة الدفعات</h1>
        <p className="mt-2 text-sm leading-7 text-slate-400">
          راجع دفعات الحجوزات ودفعات فرق تعديل الحجز. لا توجد مهلة لمراجعة الإثبات؛ يبقى معلقاً حتى تقبله أو ترفضه يدوياً.
        </p>
      </div>

      {message && <div className="rounded-2xl border border-white/10 bg-white/[.04] p-4 text-sm font-bold text-slate-200">{message}</div>}

      {busy ? (
        <div>جاري التحميل...</div>
      ) : (
        <div className="space-y-4">
          {items.map((payment) => {
            const adjustment = payment.payment_adjustment;
            const isAdjustment = adjustment?.type === "additional_payment";
            return (
              <div key={payment.id} className="grid gap-4 rounded-3xl border border-white/10 bg-white/[.04] p-5 lg:grid-cols-[1fr_auto]">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <div className="font-black">{payment.invoice?.booking?.venue?.name_ar || payment.invoice?.invoice_number}</div>
                    {isAdjustment && (
                      <span className="rounded-full border border-blue-400/20 bg-blue-500/10 px-3 py-1 text-[11px] font-black text-blue-200">
                        فرق تعديل حجز
                      </span>
                    )}
                  </div>
                  <div className="mt-1 text-sm text-slate-400">العميل: {payment.invoice?.customer?.name || "-"} • الطريقة: {payment.method?.name_ar || "-"}</div>
                  {isAdjustment && (
                    <div className="mt-2 rounded-xl border border-blue-400/15 bg-blue-500/[.06] p-3 text-sm text-blue-100">
                      الحجز #{adjustment.booking_id} • الفرق المطلوب: <b>{syp(adjustment.amount_syp)}</b>
                    </div>
                  )}
                  <div className="mt-2 text-sm">رقم العملية: <b>{payment.transaction_reference || "-"}</b> • المرسل: {payment.sender_name || "-"}</div>
                  <ProtectedPaymentProofButton paymentId={payment.id} label="عرض الإثبات" className="mt-3 inline-block rounded-xl bg-white/5 px-4 py-2 text-xs font-bold text-blue-200" />
                </div>
                <div className="flex items-center gap-2">
                  {payment.status === "pending" ? (
                    <>
                      <button disabled={actionId === String(payment.id)} onClick={() => approve(payment)} className="rounded-xl bg-emerald-500/15 px-4 py-3 font-bold text-emerald-300 disabled:opacity-50">قبول</button>
                      <button disabled={actionId === String(payment.id)} onClick={() => reject(payment)} className="rounded-xl bg-red-500/15 px-4 py-3 font-bold text-red-300 disabled:opacity-50">رفض</button>
                    </>
                  ) : (
                    <span className="rounded-xl bg-white/5 px-4 py-2">{payment.status}</span>
                  )}
                </div>
              </div>
            );
          })}
          {!items.length && <div className="rounded-3xl border border-white/10 p-10 text-center text-slate-400">لا توجد دفعات.</div>}
        </div>
      )}
    </div>
  );
}
