import React, { useMemo, useState } from "react";
import { isValidSyrianPhone, normaliseSyrianPhone, syrianPhoneMessage } from "../utils/syrianPhone";
import { useApp } from "../context/AppContext";

const roleValue = (role) => role === "مالك صالة" ? "owner" : role === "مقدم خدمة" ? "provider" : role === "مدير النظام" ? "admin" : "customer";

export default function UsersManagement() {
  const { users, providers, updateUserStatus, updateProviderStatus, createUserAccount, arabicLabel } = useApp();
  const [tab, setTab] = useState("users");
  const [query, setQuery] = useState("");
  const [form, setForm] = useState({ name: "", email: "", phone: "", role: "owner", password: "Salora12345" });
  const [message, setMessage] = useState("");

  const filteredUsers = useMemo(() => users.filter((u) => [u.name, u.email, u.role, u.status].join(" ").toLowerCase().includes(query.toLowerCase())), [users, query]);
  const filteredProviders = useMemo(() => providers.filter((p) => [p.name, p.category, p.status].join(" ").toLowerCase().includes(query.toLowerCase())), [providers, query]);

  const submit = async (e) => {
    e.preventDefault();
    if (!form.name || !form.email || !form.password) return setMessage("املأ الاسم والبريد وكلمة السر المؤقتة.");
    if (!isValidSyrianPhone(form.phone)) return setMessage(syrianPhoneMessage);
    await createUserAccount({ ...form, phone: normaliseSyrianPhone(form.phone) });
    setMessage("تم إنشاء الحساب. عند أول دخول سيُطلب من المستخدم تغيير كلمة السر.");
    setForm({ name: "", email: "", phone: "", role: "owner", password: "Salora12345" });
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">👥 إدارة الحسابات</h1>
          <p className="mt-2 text-sm text-slate-400">الأدمن ينشئ حسابات المالكين ومقدمي الخدمة، ويمكن للأدمن إنشاء الحسابات أو إلغاء تنشيطها. الحساب غير النشط يخرج من التطبيق ولا يستطيع الدخول حتى يتم تنشيطه من جديد.</p>
        </div>
        <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="ابحث بالاسم أو البريد أو الدور..." className="field-surface lg:w-80" />
      </div>

      <div className="rounded-3xl border border-emerald-400/20 bg-emerald-500/10 p-5 text-sm leading-7 text-emerald-100">
        ✅ السيناريو المعتمد: العميل يرسل طلب الانضمام من التطبيق، الأدمن يوافق وينشئ حساب مالك صالة، ثم يدخل المالك للداشبورد ويستكمل بيانات صالاته. كذلك حساب مقدم الخدمة ينشئه الأدمن من هنا، وبعدها يضيف مقدم الخدمة خدماته وترسل للمراجعة قبل ظهورها في التطبيق.
      </div>

      <form onSubmit={submit} className="rounded-3xl border border-blue-400/20 bg-white/[.04] p-5 space-y-4">
        <h2 className="text-xl font-black text-blue-200">➕ إنشاء حساب جديد</h2>
        <div className="grid gap-3 md:grid-cols-5">
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="اسم المستخدم" />
          <input className="ltr" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="email@example.com" />
          <input value={form.phone} onChange={(e) => setForm({ ...form, phone: normaliseSyrianPhone(e.target.value) })} placeholder="09xxxxxxxx" inputMode="numeric" maxLength={10} pattern="09[0-9]{8}" title={syrianPhoneMessage} />
          <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
            <option value="owner">مالك صالة</option>
            <option value="provider">مقدم خدمة</option>
            <option value="admin">مدير النظام</option>
          </select>
          <input className="ltr" type="text" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} placeholder="كلمة سر مؤقتة" />
        </div>
        <button className="rounded-xl bg-blue-600 px-5 py-3 text-sm font-black text-white hover:bg-blue-500">إنشاء الحساب</button>
        {message && <span className="ms-3 text-sm text-emerald-300">{message}</span>}
      </form>

      <div className="flex gap-2 rounded-2xl border border-white/10 bg-white/[.04] p-2 w-fit">
        <button onClick={() => setTab("users")} className={`rounded-xl px-5 py-2 text-sm font-bold ${tab === "users" ? "bg-blue-500 text-white" : "text-slate-400 hover:bg-white/5"}`}>المستخدمون</button>
        <button onClick={() => setTab("providers")} className={`rounded-xl px-5 py-2 text-sm font-bold ${tab === "providers" ? "bg-blue-500 text-white" : "text-slate-400 hover:bg-white/5"}`}>مقدمو الخدمة</button>
      </div>

      {tab === "users" ? (
        <div className="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04]"><div className="overflow-x-auto"><table className="w-full min-w-[900px] text-sm"><thead className="bg-slate-950/50 text-xs text-blue-300"><tr><th className="px-5 py-4">الحساب</th><th className="px-5 py-4">الدور</th><th className="px-5 py-4">الهاتف</th><th className="px-5 py-4">تاريخ الإنشاء</th><th className="px-5 py-4 text-center">الحالة</th><th className="px-5 py-4 text-center">الإجراءات</th></tr></thead><tbody>{filteredUsers.map((user) => <tr key={user.id} className="border-t border-white/5"><td className="px-5 py-4"><div className="font-black">{user.name}</div><div className="text-xs text-slate-500 ltr">{user.email}</div></td><td className="px-5 py-4 font-bold text-blue-300">{arabicLabel(user.role)}</td><td className="px-5 py-4 text-slate-300">{user.phone}</td><td className="px-5 py-4 text-slate-400">{user.joinedAt}</td><td className="px-5 py-4 text-center"><span className={`rounded-full border px-3 py-1 text-[11px] font-black ${user.status === "Active" ? "border-emerald-400/20 bg-emerald-500/15 text-emerald-300" : "border-amber-400/20 bg-amber-500/15 text-amber-300"}`}>{arabicLabel(user.status)}</span></td><td className="px-5 py-4"><div className="flex justify-center gap-2"><button onClick={() => updateUserStatus(user.id, user.status === "Active" ? "Inactive" : "Active")} className={`${user.status === "Active" ? "bg-red-500/15 text-red-300" : "bg-emerald-500/15 text-emerald-300"} rounded-lg px-3 py-2 text-xs font-bold`}>{user.status === "Active" ? "إلغاء التنشيط" : "تنشيط الحساب"}</button></div></td></tr>)}</tbody></table></div></div>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">{filteredProviders.map((provider) => <div key={provider.id} className="rounded-3xl border border-white/10 bg-white/[.04] p-6"><div className="flex justify-between gap-4"><div><div className="text-lg font-black">{provider.name}</div><div className="text-sm text-slate-400">{provider.category || provider.email}</div></div><span className="text-xs font-bold text-blue-300">{provider.orders || 0} طلب</span></div><div className="mt-5 flex items-center justify-between"><span className={`rounded-full border px-3 py-1 text-[11px] font-black ${provider.status === "Active" ? "border-emerald-400/20 bg-emerald-500/15 text-emerald-300" : "border-amber-400/20 bg-amber-500/15 text-amber-300"}`}>{arabicLabel(provider.status)}</span><button onClick={() => updateProviderStatus(provider.id, provider.status === "Active" ? "Pending" : "Active")} className="rounded-xl bg-blue-500/15 px-4 py-2 text-xs font-bold text-blue-300">تبديل الحالة</button></div></div>)}</div>
      )}
    </div>
  );
}
