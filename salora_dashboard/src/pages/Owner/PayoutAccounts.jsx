import React, { useEffect, useState } from "react";
import { apiClient } from "../../services/apiClient";
import { isValidSyrianPhone, normaliseSyrianPhone, syrianPhoneMessage } from "../../utils/syrianPhone";

const empty = {
  payment_method_id: "",
  account_name: "",
  account_number: "",
  phone: "",
  city: "",
  branch: "",
  instructions: "",
  is_default: true,
  is_active: true,
};

export default function PayoutAccounts() {
  const [methods, setMethods] = useState([]);
  const [items, setItems] = useState([]);
  const [form, setForm] = useState(empty);
  const [message, setMessage] = useState("");

  const load = async () => {
    try {
      const [methodsResponse, accountsResponse] = await Promise.all([
        apiClient.get("/business/payment-methods"),
        apiClient.get("/business/payout-accounts"),
      ]);
      setMethods(methodsResponse);
      setItems(accountsResponse);
      setForm((current) => current.payment_method_id || !methodsResponse[0]
        ? current
        : { ...current, payment_method_id: String(methodsResponse[0].id) });
    } catch (error) {
      setMessage(error.message);
    }
  };

  useEffect(() => { load(); }, []);

  const submit = async (event) => {
    event.preventDefault();
    setMessage("");
    const phone = normaliseSyrianPhone(form.phone);
    if (phone && !isValidSyrianPhone(phone)) {
      setMessage(syrianPhoneMessage);
      return;
    }
    try {
      await apiClient.post("/business/payout-accounts", {
        ...form,
        phone: phone || null,
        payment_method_id: Number(form.payment_method_id),
      });
      setForm({ ...empty, payment_method_id: String(methods[0]?.id || "") });
      setMessage("تمت إضافة حساب الاستلام.");
      await load();
    } catch (error) {
      setMessage(error.message);
    }
  };

  const disable = async (id) => {
    if (!window.confirm("تعطيل حساب الاستلام؟")) return;
    try {
      await apiClient.delete(`/business/payout-accounts/${id}`);
      await load();
    } catch (error) {
      setMessage(error.message);
    }
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div>
        <h1 className="text-3xl font-black">🏦 حسابات استلام الدفعات</h1>
        <p className="mt-2 text-sm text-slate-400">أضف حسابات الاستلام التي سيظهر منها الحساب المناسب للعميل عند الدفع.</p>
      </div>

      {message && <div className="rounded-2xl border border-amber-400/20 bg-amber-500/10 p-4 text-sm">{message}</div>}

      <form onSubmit={submit} className="grid gap-4 rounded-3xl border border-white/10 bg-white/[.04] p-6 md:grid-cols-2">
        <select className="field-surface" value={form.payment_method_id} onChange={(event) => setForm({ ...form, payment_method_id: event.target.value })} required>
          {methods.map((method) => <option key={method.id} value={method.id}>{method.name_ar}</option>)}
        </select>
        <input className="field-surface" placeholder="اسم صاحب الحساب" value={form.account_name} onChange={(event) => setForm({ ...form, account_name: event.target.value })} required />
        <input className="field-surface ltr" placeholder="رقم المحفظة / رقم الحوالة" value={form.account_number} onChange={(event) => setForm({ ...form, account_number: event.target.value })} />
        <input
          className="field-surface ltr"
          inputMode="numeric"
          maxLength={10}
          placeholder="رقم الهاتف - 10 أرقام"
          value={form.phone}
          onChange={(event) => setForm({ ...form, phone: normaliseSyrianPhone(event.target.value) })}
        />
        <input className="field-surface" placeholder="المدينة" value={form.city} onChange={(event) => setForm({ ...form, city: event.target.value })} />
        <input className="field-surface" placeholder="الفرع (للـهرم)" value={form.branch} onChange={(event) => setForm({ ...form, branch: event.target.value })} />
        <textarea className="field-surface md:col-span-2" placeholder="تعليمات إضافية" value={form.instructions} onChange={(event) => setForm({ ...form, instructions: event.target.value })} />
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.is_default} onChange={(event) => setForm({ ...form, is_default: event.target.checked })} /> الحساب الافتراضي</label>
        <button className="rounded-xl bg-amber-500 py-3 font-black text-slate-950">إضافة الحساب</button>
      </form>

      <div className="grid gap-4 lg:grid-cols-2">
        {items.map((item) => (
          <div key={item.id} className="min-w-0 rounded-2xl border border-white/10 bg-white/[.04] p-5">
            <div className="flex items-start justify-between gap-4">
              <div className="min-w-0"><b>{item.method?.name_ar}</b><div className="truncate text-sm text-slate-400">{item.account_name}</div></div>
              {item.is_default && <span className="shrink-0 rounded-full bg-emerald-500/15 px-3 py-1 text-xs text-emerald-300">افتراضي</span>}
            </div>
            <div className="mt-4 break-all text-sm" dir="ltr">{item.display_account || item.phone || item.branch || "-"}</div>
            <button onClick={() => disable(item.id)} className="mt-4 rounded-xl bg-red-500/10 px-4 py-2 text-xs font-bold text-red-300">تعطيل</button>
          </div>
        ))}
      </div>
    </div>
  );
}
