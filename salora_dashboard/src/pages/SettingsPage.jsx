import React, { useEffect, useState } from "react";
import { useApp } from "../context/AppContext";

export default function SettingsPage() {
  const { exchangeRate, updateExchangeRate } = useApp();
  const [rateInput, setRateInput] = useState(String(exchangeRate || 14000));
  const [savingRate, setSavingRate] = useState(false);

  useEffect(() => {
    setRateInput(String(exchangeRate || 14000));
  }, [exchangeRate]);

  const numericRate = Number(rateInput);
  const rateValid = Number.isFinite(numericRate) && numericRate >= 1 && numericRate <= 1000000000;

  const saveExchangeRate = async () => {
    if (!rateValid) {
      window.alert("أدخل سعر صرف صحيحاً أكبر من صفر.");
      return;
    }

    setSavingRate(true);
    const result = await updateExchangeRate(numericRate);
    setSavingRate(false);

    if (result) {
      window.alert("تم تحديث سعر الصرف بنجاح. سيُطبق السعر الجديد تلقائياً على القيم والفواتير المفتوحة.");
    }
  };

  return (
    <div className="pb-12 text-white" dir="rtl">
      <div className="mb-6">
        <h1 className="text-3xl font-black text-white">إعدادات النظام</h1>
        <p className="mt-2 text-sm text-slate-400">إدارة سعر الصرف المستخدم في التسعير والفواتير.</p>
      </div>

      <div className="max-w-2xl rounded-3xl border border-white/10 bg-white/[.04] p-6 shadow-xl shadow-black/10">
        <div className="mb-6 flex items-center justify-between gap-4 border-b border-white/10 pb-5">
          <div>
            <h2 className="text-xl font-black text-white">سعر صرف الدولار</h2>
            <p className="mt-1 text-sm text-slate-400">حدد قيمة الدولار بالليرة السورية.</p>
          </div>
          <div className="shrink-0 rounded-2xl bg-blue-500/10 px-4 py-3 text-center">
            <div className="text-xs text-slate-400">السعر الحالي</div>
            <div className="mt-1 font-black text-blue-300">
              {Number(exchangeRate || 0).toLocaleString("en-US")} ل.س
            </div>
          </div>
        </div>

        <label htmlFor="exchange-rate" className="mb-2 block text-sm font-bold text-slate-300">
          1 USD يساوي
        </label>

        <div className="flex flex-col gap-3 sm:flex-row">
          <div className="relative flex-1">
            <input
              id="exchange-rate"
              type="number"
              min="1"
              max="1000000000"
              step="1"
              inputMode="numeric"
              className="field-surface ltr w-full text-lg font-black"
              value={rateInput}
              onChange={(event) => setRateInput(event.target.value)}
              placeholder="14000"
            />
          </div>

          <button
            type="button"
            onClick={saveExchangeRate}
            disabled={!rateValid || savingRate}
            className="rounded-xl bg-blue-600 px-7 py-3 text-sm font-black text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {savingRate ? "جارٍ الحفظ..." : "حفظ"}
          </button>
        </div>

        <p className="mt-4 text-sm leading-7 text-slate-400">
          عند الحفظ يُستخدم السعر الجديد تلقائياً في التسعير والقيم المالية والفواتير المفتوحة.
        </p>
      </div>
    </div>
  );
}
