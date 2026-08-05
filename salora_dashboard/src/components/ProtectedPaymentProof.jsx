import React, { useEffect, useState } from "react";
import { API_BASE_URL } from "../services/apiClient";

async function fetchProtectedProof(paymentId) {
  const token = window.localStorage.getItem("salora_token");
  if (!token) throw new Error("انتهت جلسة الدخول. سجّل الدخول من جديد.");

  const response = await fetch(`${API_BASE_URL}/payment-proofs/${paymentId}/image`, {
    method: "GET",
    headers: {
      Accept: "application/json, image/*",
      Authorization: `Bearer ${token}`,
    },
  });

  if (!response.ok) {
    let message = "تعذر تحميل صورة إثبات الدفع.";
    try {
      const payload = await response.json();
      message = payload?.message || message;
    } catch (_) {
      // Keep the friendly fallback message.
    }
    if (response.status === 401) message = "انتهت جلسة الدخول. سجّل الدخول من جديد.";
    if (response.status === 403) message = "لا تملك صلاحية مشاهدة هذا الإثبات.";
    if (response.status === 404) message = "ملف إثبات الدفع غير موجود.";
    throw new Error(message);
  }

  const blob = await response.blob();
  return URL.createObjectURL(blob);
}

export function ProtectedPaymentProofImage({ paymentId, className = "" }) {
  const [url, setUrl] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    let objectUrl = "";

    setLoading(true);
    setError("");

    fetchProtectedProof(paymentId)
      .then((nextUrl) => {
        objectUrl = nextUrl;
        if (active) setUrl(nextUrl);
      })
      .catch((exception) => {
        if (active) setError(exception.message || "تعذر تحميل الإثبات.");
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [paymentId]);

  if (!paymentId) return <div className="rounded-2xl border border-white/10 p-8 text-center text-slate-400">لا يوجد إثبات دفع.</div>;
  if (loading) return <div className="rounded-2xl border border-white/10 p-8 text-center text-slate-300">جاري تحميل الإثبات...</div>;
  if (error) return <div className="rounded-2xl border border-red-400/20 bg-red-500/10 p-5 text-center text-red-200">{error}</div>;

  return <img src={url} alt="صورة إثبات الدفع" className={className} />;
}

export default function ProtectedPaymentProofButton({ paymentId, label = "عرض الإثبات", className = "" }) {
  const [open, setOpen] = useState(false);
  const [url, setUrl] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const close = () => {
    setOpen(false);
    setError("");
    if (url) URL.revokeObjectURL(url);
    setUrl("");
  };

  const show = async () => {
    if (!paymentId) {
      setError("لا يوجد معرّف إثبات دفع مرتبط بهذه العملية.");
      setOpen(true);
      return;
    }

    setOpen(true);
    setLoading(true);
    setError("");
    try {
      const nextUrl = await fetchProtectedProof(paymentId);
      setUrl(nextUrl);
    } catch (exception) {
      setError(exception.message || "تعذر تحميل الإثبات.");
    } finally {
      setLoading(false);
    }
  };

  const download = () => {
    if (!url) return;
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = `salora-payment-proof-${paymentId}.jpg`;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
  };

  return (
    <>
      <button type="button" onClick={show} className={className || "rounded-xl bg-blue-500/10 px-4 py-2 text-xs font-bold text-blue-300"}>
        📎 {label}
      </button>

      {open && (
        <div className="fixed inset-0 z-[99999] grid place-items-center bg-slate-950/90 p-4 backdrop-blur-xl" dir="rtl">
          <div className="w-full max-w-4xl rounded-3xl border border-blue-400/30 bg-slate-950 p-5 text-white shadow-2xl">
            <div className="mb-4 flex items-center justify-between gap-4">
              <h3 className="text-xl font-black">صورة إثبات الدفع</h3>
              <button type="button" onClick={close} className="rounded-xl bg-white/5 px-3 py-2">✕</button>
            </div>

            {loading && <div className="p-10 text-center text-slate-300">جاري تحميل الإثبات...</div>}
            {error && <div className="rounded-2xl bg-red-500/10 p-5 text-center text-red-200">{error}</div>}
            {url && !loading && !error && (
              <>
                <img src={url} alt="صورة إثبات الدفع" className="max-h-[72vh] w-full rounded-2xl border border-white/10 bg-black object-contain" />
                <div className="mt-4 flex justify-end">
                  <button type="button" onClick={download} className="rounded-xl bg-emerald-500/15 px-4 py-3 font-bold text-emerald-300">تنزيل الصورة</button>
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </>
  );
}