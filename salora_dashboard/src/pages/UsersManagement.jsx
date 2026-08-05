import React, { useEffect, useMemo, useState } from "react";
import { useApp } from "../context/AppContext";
import { dashboardApi } from "../services/apiClient";
import { resolveMediaUrl } from "../utils/mediaUrl";
import { generateTemporaryPassword, strongPasswordPattern } from "../utils/passwords";

const roleValue = (role) => role === "Owner" || role === "مالك صالة" ? "owner" : role === "Provider" || role === "مقدم خدمة" ? "provider" : role === "Admin" || role === "مدير النظام" ? "admin" : "customer";
const resolveAssetUrl = resolveMediaUrl;

const emptyCreateForm = () => ({ name: "", email: "", phone: "", role: "owner", password: generateTemporaryPassword() });

const statusClasses = (status) => {
  if (status === "Active") return "border-emerald-400/20 bg-emerald-500/15 text-emerald-300";
  if (status === "Suspended" || status === "Locked") return "border-amber-400/20 bg-amber-500/15 text-amber-300";
  if (status === "Deleted") return "border-slate-400/20 bg-slate-500/15 text-slate-300";
  return "border-red-400/20 bg-red-500/15 text-red-300";
};

const impactCountLabels = {
  future_customer_bookings: "حجوزات العميل المستقبلية",
  active_customer_bookings: "حجوزات العميل النشطة",
  active_owner_bookings: "حجوزات الصالات النشطة",
  owned_venues: "الصالات المملوكة",
  provider_services: "خدمات مقدم الخدمة",
  active_provider_requests: "طلبات الخدمة النشطة",
  events: "الأحداث",
  reviews: "التقييمات",
  complaints: "الشكاوى"
};

const blockerLabels = {
  self_account: "لا يمكن حذف الحساب المستخدم حالياً",
  last_admin: "لا يمكن حذف آخر مدير نظام فعال",
  active_customer_bookings: "توجد حجوزات عميل نشطة يجب معالجتها أولاً",
  active_owner_bookings: "توجد حجوزات صالات نشطة يجب معالجتها أولاً",
  owned_venues_require_transfer_or_disable: "يجب نقل الصالات أو تعطيلها قبل حذف المالك",
  provider_services_require_transfer_or_disable: "يجب نقل الخدمات أو تعطيلها قبل حذف مقدم الخدمة",
  active_provider_requests: "توجد طلبات خدمة نشطة يجب معالجتها أولاً"
};

export default function UsersManagement() {
  const {
    users,
    createUserAccount,
    updateUserAccount,
    activateUser,
    deactivateUser,
    suspendUser,
    getUserDeletionImpact,
    deleteUser,
    restoreUser,
    arabicLabel
  } = useApp();

  const [tab, setTab] = useState("all");
  const [query, setQuery] = useState("");
  const [form, setForm] = useState(emptyCreateForm);
  const [message, setMessage] = useState("");
  const [busyId, setBusyId] = useState("");
  const [editUser, setEditUser] = useState(null);
  const [suspension, setSuspension] = useState(null);
  const [deletePreview, setDeletePreview] = useState(null);
  const [deleteConfirmation, setDeleteConfirmation] = useState("");
  const [deletedUsers, setDeletedUsers] = useState([]);
  const [loadingDeleted, setLoadingDeleted] = useState(false);

  useEffect(() => {
    if (tab !== "deleted") return;
    let cancelled = false;
    setLoadingDeleted(true);
    dashboardApi.admin.users({ status: "deleted", include_deleted: 1, per_page: 100 })
      .then((value) => {
        if (cancelled) return;
        const rows = Array.isArray(value) ? value : Array.isArray(value?.data) ? value.data : [];
        setDeletedUsers(rows.map((u) => ({
          id: String(u.id),
          name: u.name,
          email: u.email,
          phone: u.phone || "",
          role: u.role === "admin" ? "Admin" : u.role === "owner" ? "Owner" : u.role === "provider" ? "Provider" : "Customer",
          status: "Deleted",
          avatarUrl: resolveAssetUrl(u.avatar_url || u.avatar || ""),
          deletedAt: u.deleted_at
        })));
      })
      .catch((error) => setMessage(error?.message || "تعذر تحميل الحسابات المحذوفة."))
      .finally(() => { if (!cancelled) setLoadingDeleted(false); });
    return () => { cancelled = true; };
  }, [tab]);

  const displayed = useMemo(() => {
    const source = tab === "deleted" ? deletedUsers : users.filter((user) => tab === "all" || roleValue(user.role) === tab);
    const needle = query.trim().toLowerCase();
    if (!needle) return source;
    return source.filter((u) => [u.name, u.email, u.phone, u.role, u.status].join(" ").toLowerCase().includes(needle));
  }, [users, deletedUsers, tab, query]);

  const copyPassword = async (password = form.password) => {
    try {
      await navigator.clipboard.writeText(password);
      setMessage("تم نسخ كلمة المرور المؤقتة.");
    } catch (_) {
      setMessage("تعذر النسخ التلقائي. انسخ كلمة المرور يدوياً.");
    }
  };

  const submit = async (event) => {
    event.preventDefault();
    setMessage("");
    if (!form.name.trim() || !form.email.trim() || !form.phone.trim()) {
      setMessage("املأ الاسم والبريد ورقم الهاتف.");
      return;
    }
    if (!strongPasswordPattern.test(form.password)) {
      setMessage("كلمة المرور المؤقتة يجب أن تكون 8 أحرف على الأقل وتحوي حرفاً كبيراً وصغيراً ورقماً ورمزاً.");
      return;
    }

    const created = await createUserAccount({ ...form, password_confirmation: form.password });
    if (!created) return;
    setMessage(`تم إنشاء الحساب. احفظ كلمة المرور المؤقتة الآن: ${form.password}`);
    setForm(emptyCreateForm());
  };

  const saveEdit = async (event) => {
    event.preventDefault();
    if (!editUser) return;
    if (editUser.password && !strongPasswordPattern.test(editUser.password)) {
      setMessage("كلمة المرور الجديدة لا تحقق سياسة الأمان.");
      return;
    }
    setBusyId(editUser.id);
    const saved = await updateUserAccount(editUser.id, {
      ...editUser,
      password_confirmation: editUser.password
    });
    setBusyId("");
    if (saved) {
      setEditUser(null);
      setMessage("تم تحديث بيانات الحساب.");
    }
  };

  const activate = async (user) => {
    setBusyId(user.id);
    const saved = await activateUser(user.id);
    setBusyId("");
    if (saved) setMessage("تم تنشيط الحساب وفك أي قفل أمني.");
  };

  const deactivate = async (user) => {
    const reason = window.prompt("سبب التعطيل الإداري (اختياري):", "");
    if (reason === null) return;
    setBusyId(user.id);
    const saved = await deactivateUser(user.id, reason.trim());
    setBusyId("");
    if (saved) setMessage("تم تعطيل الحساب إلى أجل غير محدد.");
  };

  const submitSuspension = async (event) => {
    event.preventDefault();
    if (!suspension?.until || !suspension?.reason?.trim()) {
      setMessage("حدد موعد انتهاء التجميد واكتب السبب.");
      return;
    }
    setBusyId(suspension.user.id);
    const saved = await suspendUser(suspension.user.id, new Date(suspension.until).toISOString(), suspension.reason.trim());
    setBusyId("");
    if (saved) {
      setSuspension(null);
      setMessage("تم تجميد الحساب مؤقتاً وسحب جلساته الحالية.");
    }
  };

  const inspectDelete = async (user) => {
    setBusyId(user.id);
    const impact = await getUserDeletionImpact(user.id);
    setBusyId("");
    if (impact) {
      setDeleteConfirmation("");
      setDeletePreview({ user, impact });
    }
  };

  const closeDeletePreview = () => {
    setDeletePreview(null);
    setDeleteConfirmation("");
  };

  const deleteEmailMatches = Boolean(
    deletePreview?.user?.email &&
    deleteConfirmation.trim().toLowerCase() === deletePreview.user.email.trim().toLowerCase()
  );

  const confirmDelete = async () => {
    if (!deletePreview?.impact?.can_delete || !deleteEmailMatches) return;
    setBusyId(deletePreview.user.id);
    const deleted = await deleteUser(deletePreview.user.id);
    setBusyId("");
    if (deleted) {
      closeDeletePreview();
      setMessage("تم حذف الحساب حذفاً آمناً مع إبقاء السجلات التاريخية.");
    }
  };

  const restore = async (user) => {
    setBusyId(user.id);
    const restored = await restoreUser(user.id);
    setBusyId("");
    if (restored) {
      setDeletedUsers((prev) => prev.filter((item) => item.id !== user.id));
      setMessage("تمت استعادة الحساب وتنشيطه.");
    }
  };

  return (
    <div className="space-y-6 pb-12 text-white" dir="rtl">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">👥 إدارة الحسابات</h1>
          <p className="mt-2 max-w-4xl text-sm leading-7 text-slate-400">يمكن إنشاء الحساب وتعديل بياناته، تجميده حتى موعد محدد، تعطيله إلى أجل غير محدد، أو حذفه بأمان بعد فحص الحجوزات والصالات والخدمات المرتبطة.</p>
        </div>
        <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="ابحث بالاسم أو البريد أو الهاتف..." className="field-surface lg:w-80" />
      </div>

      {message && <div className="rounded-2xl border border-blue-400/20 bg-blue-500/10 p-4 text-sm leading-7 text-blue-100">{message}</div>}

      <form onSubmit={submit} className="space-y-4 rounded-3xl border border-blue-400/20 bg-white/[.04] p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div><h2 className="text-xl font-black text-blue-200">➕ إنشاء حساب جديد</h2><p className="mt-1 text-xs text-slate-500">يُوثّق البريد إدارياً، ويُجبر مالك الصالة أو مقدم الخدمة على تغيير كلمة المرور عند أول دخول.</p></div>
          <button type="button" onClick={() => setForm({ ...form, password: generateTemporaryPassword() })} className="rounded-xl border border-white/10 px-4 py-2 text-xs font-bold text-slate-300 hover:bg-white/5">توليد كلمة جديدة</button>
        </div>
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="اسم المستخدم" required />
          <input className="ltr" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="email@example.com" required />
          <input className="ltr" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="رقم الهاتف" required />
          <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
            <option value="owner">مالك صالة</option><option value="provider">مقدم خدمة</option><option value="customer">عميل</option><option value="admin">مدير النظام</option>
          </select>
          <div className="flex gap-2"><input className="ltr min-w-0 flex-1" type="text" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} placeholder="كلمة سر مؤقتة" /><button type="button" onClick={() => copyPassword()} className="rounded-xl border border-white/10 px-3 text-xs">نسخ</button></div>
        </div>
        <button className="rounded-xl bg-blue-600 px-5 py-3 text-sm font-black text-white hover:bg-blue-500">إنشاء الحساب</button>
      </form>

      <div className="flex flex-wrap gap-2 rounded-2xl border border-white/10 bg-white/[.04] p-2 w-fit">
        {[['all','الكل'],['owner','مالكو الصالات'],['provider','مقدمو الخدمة'],['customer','العملاء'],['admin','المدراء'],['deleted','المحذوفة']].map(([value,label]) => <button key={value} onClick={() => setTab(value)} className={`rounded-xl px-4 py-2 text-sm font-bold ${tab === value ? "bg-blue-500 text-white" : "text-slate-400 hover:bg-white/5"}`}>{label}</button>)}
      </div>

      <div className="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04]">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1050px] text-sm">
            <thead className="bg-slate-950/50 text-xs text-blue-300"><tr><th className="px-5 py-4">الحساب</th><th className="px-5 py-4">الدور</th><th className="px-5 py-4">الهاتف</th><th className="px-5 py-4 text-center">الحالة</th><th className="px-5 py-4 text-center">الإجراءات</th></tr></thead>
            <tbody>
              {loadingDeleted ? <tr><td colSpan="5" className="p-10 text-center text-slate-400">جاري تحميل الحسابات المحذوفة...</td></tr> : displayed.map((user) => (
                <tr key={user.id} className="border-t border-white/5">
                  <td className="px-5 py-4"><div className="flex items-center gap-3"><div className="h-11 w-11 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">{user.avatarUrl ? <img src={user.avatarUrl} alt="" className="h-full w-full object-cover" /> : <div className="grid h-full w-full place-items-center font-black text-blue-300">{user.name?.[0]}</div>}</div><div><div className="font-black">{user.name}</div><div className="text-xs text-slate-500 ltr">{user.email}</div>{user.suspendedUntil && <div className="mt-1 text-[10px] text-amber-300">حتى {String(user.suspendedUntil).slice(0,16).replace('T',' ')}</div>}</div></div></td>
                  <td className="px-5 py-4 font-bold text-blue-300">{arabicLabel(user.role)}</td>
                  <td className="px-5 py-4 text-slate-300 ltr">{user.phone || "—"}</td>
                  <td className="px-5 py-4 text-center"><span className={`rounded-full border px-3 py-1 text-[11px] font-black ${statusClasses(user.status)}`}>{arabicLabel(user.status)}</span></td>
                  <td className="px-5 py-4"><div className="flex flex-wrap justify-center gap-2">
                    {tab === "deleted" ? <button disabled={busyId === user.id} onClick={() => restore(user)} className="rounded-lg bg-emerald-500/15 px-3 py-2 text-xs font-bold text-emerald-300 disabled:opacity-50">استعادة</button> : <>
                      <button onClick={() => setEditUser({ ...user, role: roleValue(user.role), password: "" })} className="rounded-lg bg-blue-500/15 px-3 py-2 text-xs font-bold text-blue-300">تعديل</button>
                      {user.status !== "Active" ? <button disabled={busyId === user.id} onClick={() => activate(user)} className="rounded-lg bg-emerald-500/15 px-3 py-2 text-xs font-bold text-emerald-300 disabled:opacity-50">تنشيط</button> : <button disabled={busyId === user.id} onClick={() => deactivate(user)} className="rounded-lg bg-red-500/15 px-3 py-2 text-xs font-bold text-red-300 disabled:opacity-50">تعطيل</button>}
                      <button onClick={() => setSuspension({ user, until: "", reason: "" })} className="rounded-lg bg-amber-500/15 px-3 py-2 text-xs font-bold text-amber-300">تجميد مؤقت</button>
                      <button disabled={busyId === user.id} onClick={() => inspectDelete(user)} className="rounded-lg border border-red-400/20 px-3 py-2 text-xs font-bold text-red-300 disabled:opacity-50">حذف آمن</button>
                    </>}
                  </div></td>
                </tr>
              ))}
              {!loadingDeleted && displayed.length === 0 && <tr><td colSpan="5" className="p-10 text-center text-slate-500">لا توجد حسابات مطابقة.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      {editUser && <div className="fixed inset-0 z-[100] grid place-items-center bg-slate-950/80 p-4 backdrop-blur"><form onSubmit={saveEdit} className="w-full max-w-xl space-y-4 rounded-3xl border border-blue-400/20 bg-slate-950 p-6"><div className="flex justify-between"><h3 className="text-xl font-black">تعديل الحساب</h3><button type="button" onClick={() => setEditUser(null)}>✕</button></div><input value={editUser.name} onChange={(e) => setEditUser({ ...editUser, name: e.target.value })} placeholder="الاسم" required /><input className="ltr" type="email" value={editUser.email} onChange={(e) => setEditUser({ ...editUser, email: e.target.value })} required /><input className="ltr" value={editUser.phone} onChange={(e) => setEditUser({ ...editUser, phone: e.target.value })} required /><select value={editUser.role} onChange={(e) => setEditUser({ ...editUser, role: e.target.value })}><option value="owner">مالك صالة</option><option value="provider">مقدم خدمة</option><option value="customer">عميل</option><option value="admin">مدير النظام</option></select><input className="ltr" type="password" value={editUser.password} onChange={(e) => setEditUser({ ...editUser, password: e.target.value })} placeholder="كلمة مرور جديدة (اختياري)" /><button disabled={busyId === editUser.id} className="w-full rounded-xl bg-blue-600 py-3 font-black disabled:opacity-50">حفظ التعديلات</button></form></div>}

      {suspension && <div className="fixed inset-0 z-[100] grid place-items-center bg-slate-950/80 p-4 backdrop-blur"><form onSubmit={submitSuspension} className="w-full max-w-lg space-y-4 rounded-3xl border border-amber-400/20 bg-slate-950 p-6"><div className="flex justify-between"><div><h3 className="text-xl font-black">تجميد {suspension.user.name}</h3><p className="mt-1 text-xs text-slate-500">سيتم سحب الجلسات ومنع الدخول حتى الموعد المحدد.</p></div><button type="button" onClick={() => setSuspension(null)}>✕</button></div><input type="datetime-local" value={suspension.until} onChange={(e) => setSuspension({ ...suspension, until: e.target.value })} min={new Date(Date.now()+60000).toISOString().slice(0,16)} required /><textarea value={suspension.reason} onChange={(e) => setSuspension({ ...suspension, reason: e.target.value })} placeholder="سبب التجميد" rows="4" required /><button disabled={busyId === suspension.user.id} className="w-full rounded-xl bg-amber-500 py-3 font-black text-slate-950 disabled:opacity-50">تأكيد التجميد</button></form></div>}

      {deletePreview && <div className="fixed inset-0 z-[100] grid place-items-center bg-slate-950/80 p-4 backdrop-blur"><div className="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-3xl border border-red-400/20 bg-slate-950 p-6"><div className="flex justify-between"><div><h3 className="text-xl font-black">فحص حذف {deletePreview.user.name}</h3><p className="mt-1 text-xs text-slate-500">الحذف آمن (Soft Delete) ويحافظ على السجلات التاريخية ولا يمسح الحجوزات أو الفواتير القديمة.</p></div><button onClick={closeDeletePreview}>✕</button></div><div className="mt-5 grid grid-cols-2 gap-3 text-sm">{Object.entries(deletePreview.impact.counts || {}).map(([key,value]) => <div key={key} className="rounded-xl border border-white/10 bg-white/[.04] p-3"><div className="text-xs text-slate-500">{impactCountLabels[key] || key}</div><div className="mt-1 text-lg font-black">{value}</div></div>)}</div>{deletePreview.impact.can_delete ? <div className="mt-5 rounded-xl border border-emerald-400/20 bg-emerald-500/10 p-4 text-sm text-emerald-200">لا توجد ارتباطات نشطة تمنع الحذف. سيبقى سجل العمليات التاريخي محفوظاً.</div> : <div className="mt-5 rounded-xl border border-red-400/20 bg-red-500/10 p-4 text-sm leading-7 text-red-200"><div className="font-black">الحذف ممنوع حالياً:</div><ul className="mt-2 list-disc space-y-1 pr-5">{(deletePreview.impact.blockers || []).map((item) => <li key={item}>{blockerLabels[item] || item}</li>)}</ul></div>}{deletePreview.impact.can_delete && <div className="mt-5 rounded-2xl border border-red-400/20 bg-red-500/[.06] p-4"><label className="text-sm font-black text-red-100">اكتب بريد الحساب للتأكيد</label><p className="mt-1 text-xs text-slate-500 ltr text-left">{deletePreview.user.email}</p><input className="mt-3 ltr w-full" type="email" value={deleteConfirmation} onChange={(e) => setDeleteConfirmation(e.target.value)} placeholder={deletePreview.user.email} autoComplete="off" /></div>}<div className="mt-5 flex gap-3"><button onClick={closeDeletePreview} className="flex-1 rounded-xl border border-white/10 py-3">إلغاء</button><button onClick={confirmDelete} disabled={!deletePreview.impact.can_delete || !deleteEmailMatches || busyId === deletePreview.user.id} className="flex-1 rounded-xl bg-red-600 py-3 font-black disabled:cursor-not-allowed disabled:opacity-40">حذف الحساب</button></div></div></div>}
    </div>
  );
}
