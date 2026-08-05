import React, { useEffect, useState } from "react";
import { NavLink, useLocation, useNavigate } from "react-router-dom";
import { useApp } from "../context/AppContext";

const adminLinks = [
  { path: "/admin", label: "الرئيسية", icon: "📊" },
  { path: "/admin/approvals", label: "مركز الموافقات", icon: "✅" },
  { path: "/admin/venues", label: "الصالات", icon: "🏛️" },
  { path: "/admin/venue-revisions", label: "تعديلات الصالات", icon: "📝" },
  { path: "/admin/service-categories", label: "تصنيفات الخدمات", icon: "🗂️" },
  { path: "/admin/services", label: "الخدمات والمزودون", icon: "🧩" },
  { path: "/admin/bookings", label: "الحجوزات", icon: "📋" },
  { path: "/admin/payments", label: "المدفوعات", icon: "💳" },
  { path: "/admin/finance", label: "المالية والأرباح", icon: "💰" },
  { path: "/admin/offers", label: "العروض", icon: "🏷️" },
  { path: "/admin/support", label: "الشكاوى والدعم", icon: "🎧" },
  { path: "/admin/calendar", label: "التقويم", icon: "📅" },
  { path: "/admin/event-types", label: "أنواع المناسبات", icon: "🗂️" },
  { path: "/admin/users", label: "المستخدمون والحسابات", icon: "👥" },
  { path: "/admin/reviews", label: "التقييمات", icon: "⭐" },
  { path: "/admin/reports", label: "التقارير", icon: "📈" },
  { path: "/admin/activity", label: "سجل النشاط", icon: "🧾" },
  { path: "/admin/notifications", label: "الإشعارات", icon: "🔔" },
  { path: "/admin/profile", label: "الملف الشخصي", icon: "👤" }
];

const ownerLinks = [
  { path: "/owner", label: "نظرة عامة", icon: "📊" },
  { path: "/owner/halls", label: "صالاتي", icon: "🏛️" },
  { path: "/owner/add-hall", label: "إضافة صالة", icon: "➕" },
  { path: "/owner/bookings", label: "حجوزات صالاتي", icon: "🧾" },
  { path: "/owner/calendar", label: "تقويم الحجوزات", icon: "📅" },
  { path: "/owner/payments", label: "مراجعة الدفع", icon: "💳" },
  { path: "/owner/services", label: "خدمات الصالة", icon: "🧩" },
  { path: "/owner/offers", label: "عروضي", icon: "🏷️" },
  { path: "/owner/reviews", label: "التقييمات والتعليقات", icon: "⭐" },
  { path: "/owner/complaints", label: "الشكاوى", icon: "🎧" },
  { path: "/owner/profile", label: "الملف الشخصي", icon: "👤" }
];

export default function Layout({ children }) {
  const context = useApp();
  const navigate = useNavigate();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [themePreference, setThemePreference] = useState(() => localStorage.getItem("salora_dashboard_theme") || "system");

  useEffect(() => {
    const media = window.matchMedia("(prefers-color-scheme: dark)");
    const apply = () => {
      const resolved = themePreference === "system" ? (media.matches ? "dark" : "light") : themePreference;
      document.documentElement.dataset.theme = resolved;
      document.documentElement.dataset.themePreference = themePreference;
    };
    apply();
    media.addEventListener?.("change", apply);
    return () => media.removeEventListener?.("change", apply);
  }, [themePreference]);

  const cycleTheme = () => {
    const next = themePreference === "system" ? "light" : themePreference === "light" ? "dark" : "system";
    localStorage.setItem("salora_dashboard_theme", next);
    setThemePreference(next);
  };
  const themeLabel = themePreference === "light" ? "فاتح" : themePreference === "dark" ? "داكن" : "حسب الجهاز";
  const themeIcon = themePreference === "light" ? "☀️" : themePreference === "dark" ? "🌙" : "🖥️";

  if (!context) return <div className="min-h-screen grid place-items-center bg-[#020617] text-white">جاري تحميل لوحة Salora...</div>;

  const { userProfile, adminNotifications = [], ownerNotifications = [], logout, dataLoading, backendError, refreshData } = context;
  const isOwner = location.pathname.startsWith("/owner");
  const isProvider = false;
  const links = isOwner ? ownerLinks : adminLinks;
  const unreadCount = (isOwner ? ownerNotifications : adminNotifications).filter((n) => !n.read).length;
  const brandColor = isProvider ? "from-violet-300 to-fuchsia-400" : isOwner ? "from-amber-400 to-orange-500" : "from-blue-400 to-indigo-400";

  const Sidebar = (
    <aside className="salora-sidebar h-full w-72 p-5 bg-slate-950/80 border-l border-white/10 backdrop-blur-xl flex flex-col">
      <div className={`text-2xl font-black mb-8 bg-clip-text text-transparent bg-gradient-to-r ${brandColor}`}>
        ✨ Salora {isProvider ? "مقدم خدمة" : isOwner ? "مالك الصالة" : "مدير النظام"}
      </div>
      <nav className="flex-1 space-y-1 overflow-y-auto">
        {links.map((item) => (
          <NavLink
            key={item.path}
            to={item.path}
            end={item.path === "/admin" || item.path === "/owner"}
            onClick={() => setSidebarOpen(false)}
            className={({ isActive }) =>
              `flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-sm font-bold transition-all ${
                isActive
                  ? isProvider
                    ? "bg-violet-500/15 text-violet-300 border-violet-400/30"
                    : isOwner
                    ? "bg-amber-500/15 text-amber-300 border-amber-400/30"
                    : "bg-blue-500/15 text-blue-300 border-blue-400/30"
                  : "text-slate-400 border-transparent hover:bg-white/5 hover:text-white"
              }`
            }
          >
            <span className="flex items-center gap-3"><span>{item.icon}</span>{item.label}</span>
            {item.path.includes("notifications") && unreadCount > 0 && <span className="rounded-full bg-red-500 px-2 py-0.5 text-[10px] text-white">{unreadCount}</span>}
          </NavLink>
        ))}
      </nav>
      <button onClick={async () => { await logout?.(); navigate('/auth/login', { replace: true }); }} className="mt-5 w-full rounded-xl border border-red-500/20 bg-red-500/10 py-3 text-sm font-bold text-red-300 hover:bg-red-500/20">
        🚪 تسجيل الخروج
      </button>
    </aside>
  );

  return (
    <div className="salora-dashboard-shell min-h-screen bg-[#020617] text-slate-100" dir="rtl">
      <div className="fixed inset-0 pointer-events-none bg-[radial-gradient(circle_at_15%_20%,rgba(59,130,246,.12),transparent_28%),radial-gradient(circle_at_85%_10%,rgba(245,158,11,.10),transparent_26%)]" />
      <div className="hidden lg:fixed lg:inset-y-0 lg:right-0 lg:z-30 lg:block">{Sidebar}</div>
      {sidebarOpen && <div className="fixed inset-0 z-50 lg:hidden"><button className="absolute inset-0 bg-black/60" onClick={() => setSidebarOpen(false)} /><div className="relative h-full">{Sidebar}</div></div>}
      <div className="relative z-10 lg:pr-72">
        <header className="salora-header sticky top-0 z-20 flex h-16 items-center justify-between border-b border-white/10 bg-slate-950/60 px-4 backdrop-blur-xl sm:px-8">
          <div className="flex items-center gap-3">
            <button className="rounded-xl border border-white/10 bg-white/5 px-3 py-2 lg:hidden" onClick={() => setSidebarOpen(true)}>☰</button>
            <div>
              <div className="text-xs uppercase tracking-[0.25em] text-slate-500">مركز تحكم Salora</div>
              <div className="text-sm font-bold text-slate-200">{isProvider ? "مساحة مقدم الخدمة" : isOwner ? "مساحة مالك الصالة" : "مساحة مدير النظام"}</div>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <button type="button" onClick={cycleTheme} title={`المظهر: ${themeLabel}`} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-bold hover:bg-white/10">
              {themeIcon} <span className="hidden sm:inline">{themeLabel}</span>
            </button>
            <button onClick={() => navigate(isOwner ? "/owner/notifications" : "/admin/notifications")} className="relative rounded-xl border border-white/10 bg-white/5 px-3 py-2 hover:bg-white/10">
              🔔 {unreadCount > 0 && <span className="absolute -right-1 -top-1 grid h-5 w-5 place-items-center rounded-full bg-red-500 text-[10px] font-bold">{unreadCount}</span>}
            </button>
            <button type="button" onClick={() => navigate(isOwner ? "/owner/profile" : "/admin/profile")} title="فتح الملف الشخصي" className={`h-10 w-10 overflow-hidden rounded-full border border-white/15 bg-gradient-to-tr ${brandColor} text-sm font-black text-slate-950 shadow-lg`}>
              {userProfile?.avatarUrl ? <img src={userProfile.avatarUrl} alt="الصورة الشخصية" className="h-full w-full object-cover" /> : <span className="grid h-full w-full place-items-center">{userProfile?.name?.[0] || "S"}</span>}
            </button>
          </div>
        </header>
        {backendError && <div className="mx-4 mt-4 flex flex-col gap-3 rounded-2xl border border-red-400/30 bg-red-500/10 p-4 text-sm text-red-100 sm:mx-8 sm:flex-row sm:items-center sm:justify-between"><span>{backendError}</span><button onClick={refreshData} className="rounded-xl bg-red-500/20 px-4 py-2 font-bold hover:bg-red-500/30">إعادة المحاولة</button></div>}
        {dataLoading && <div className="mx-4 mt-4 rounded-xl border border-blue-400/20 bg-blue-500/10 px-4 py-2 text-xs font-bold text-blue-200 sm:mx-8">جاري مزامنة البيانات مع الخادم...</div>}
        <main className="salora-main p-4 sm:p-8">{children}</main>
        <GlobalVenueInspector />
      </div>
    </div>
  );
}

export function GlobalVenueInspector() {
  const { globalViewVenue, setGlobalViewVenue, formatPricePair, arabicLabel } = useApp();
  if (!globalViewVenue) return null;

  const details = [
    ["الاسم", globalViewVenue.name],
    ["المالك", globalViewVenue.owner],
    ["المدينة", globalViewVenue.city],
    ["العنوان", globalViewVenue.address || "غير محدد"],
    ["رابط الخريطة", globalViewVenue.mapUrl || "غير محدد"],
    ["السعة", `${globalViewVenue.capacity || 0} ضيف`],
    ["السعر", formatPricePair(globalViewVenue.finalPrice || globalViewVenue.basePrice || globalViewVenue.price || 0, "", globalViewVenue.finalPriceSyp || globalViewVenue.priceSyp || 0)],
    ["الحالة", arabicLabel(globalViewVenue.status)],
    ["أنواع المناسبات", (globalViewVenue.supportedEventTypes || []).map(arabicLabel).join(" • ") || "غير محدد"],
    ["الخدمات المجانية", (globalViewVenue.includedServices || []).join(" • ") || "لا يوجد"],
    ["الخدمات المدفوعة", (globalViewVenue.paidUpgrades || []).join(" • ") || "لا يوجد"],
    ["مقدمو الخدمات", (globalViewVenue.vendorCategories || []).join(" • ") || "لا يوجد"],
    ["المزايا", (globalViewVenue.amenities || []).join(" • ") || "لا يوجد"],
    ["السياسات", (globalViewVenue.policies || []).join(" • ") || "لا يوجد"],
    ["الوصف", globalViewVenue.description || "لا يوجد"]
  ];

  return (
    <div className="fixed inset-0 z-[99999] overflow-y-auto bg-slate-950/85 p-3 backdrop-blur-xl sm:p-6" dir="rtl">
      <div className="mx-auto my-4 w-full max-w-5xl rounded-3xl border border-blue-400/30 bg-slate-950 p-5 shadow-[0_0_70px_rgba(59,130,246,.25)] sm:p-6">
        <div className="sticky -top-5 z-10 mb-5 flex items-start justify-between border-b border-white/10 bg-slate-950/95 pb-4 pt-1 backdrop-blur">
          <div>
            <h3 className="text-2xl font-black text-white">🏛️ تفاصيل الصالة كاملة</h3>
            <p className="text-sm text-slate-400">الأدمن يستطيع مشاهدة كل بيانات الصالة والنزول لآخر التفاصيل قبل القبول أو الرفض.</p>
          </div>
          <button onClick={() => setGlobalViewVenue(null)} className="rounded-xl bg-white/5 px-3 py-2 text-slate-300 hover:bg-white/10">✕</button>
        </div>

        {(globalViewVenue.imageUrls || []).length > 0 ? (
          <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {(globalViewVenue.imageUrls || []).map((url, index) => <img key={index} src={url} alt={`صورة الصالة ${index + 1}`} className="h-36 w-full rounded-2xl border border-white/10 object-cover" />)}
          </div>
        ) : (
          <div className="mb-5 rounded-2xl border border-white/10 bg-white/[.03] p-6 text-center text-slate-400">لا توجد صور مرفوعة لهذه الصالة.</div>
        )}

        {(globalViewVenue.videoUrls || []).length > 0 && (
          <div className="mb-5">
            <h4 className="mb-3 text-lg font-black text-violet-200">🎬 فيديوهات الصالة</h4>
            <div className="grid gap-3 md:grid-cols-2">
              {(globalViewVenue.videoUrls || []).map((url, index) => <video key={index} src={url} controls preload="metadata" className="h-64 w-full rounded-2xl border border-white/10 bg-black object-contain" />)}
            </div>
          </div>
        )}

        <div className="grid gap-4 sm:grid-cols-2">
          {details.map(([label, value]) => <div key={label} className="rounded-2xl border border-white/10 bg-white/[.03] p-4"><div className="text-[10px] font-bold uppercase tracking-widest text-slate-500">{label}</div><div className="mt-2 whitespace-pre-wrap break-words font-bold leading-7 text-slate-100">{value}</div></div>)}
        </div>
        <button onClick={() => setGlobalViewVenue(null)} className="mt-6 w-full rounded-2xl bg-blue-600 py-3 font-black text-white hover:bg-blue-500">إغلاق</button>
      </div>
    </div>
  );
}
