import { useState } from "react";
import AdminCommissions from "./AdminCommissions";
import AdminBookingFinancialsV2 from "./AdminBookingFinancialsV2";

export default function AdminFinanceHub() {
  const [tab, setTab] = useState("profits");
  return <div className="space-y-5" dir="rtl">
    <div className="rounded-3xl border border-white/10 bg-white/[.04] p-3">
      <div className="grid gap-2 sm:grid-cols-2">
        <button type="button" onClick={() => setTab("profits")} className={`rounded-2xl px-4 py-3 text-sm font-black transition ${tab === "profits" ? "bg-emerald-500 text-slate-950" : "bg-white/5 text-slate-300 hover:bg-white/10"}`}>💰 الأرباح والعمولات</button>
        <button type="button" onClick={() => setTab("bookings")} className={`rounded-2xl px-4 py-3 text-sm font-black transition ${tab === "bookings" ? "bg-blue-500 text-white" : "bg-white/5 text-slate-300 hover:bg-white/10"}`}>🧮 تفاصيل حسابات الحجوزات والتسويات</button>
      </div>
    </div>
    {tab === "profits" ? <AdminCommissions /> : <AdminBookingFinancialsV2 />}
  </div>;
}