import React from "react";

export default function StatusPill({ status, size = "md" }) {
  const sizeClass =
    size === "sm"
      ? "px-2.5 py-1 text-xs"
      : size === "lg"
        ? "px-4 py-2.5 text-base"
        : "px-3.5 py-1.5 text-sm";

  const statusClasses = {
    Confirmed: "status-confirmed",
    Pending: "status-pending",
    Cancelled: "status-cancelled",
    Paid: "status-paid",
    Unpaid: "status-unpaid",
    Refunded: "bg-slate-500/30 text-slate-300 border border-slate-500/50",
  };

  return (
    <span
      className={`status-pill inline-block rounded-full font-semibold ${sizeClass} ${statusClasses[status] || "bg-slate-500/20 text-slate-300 border border-slate-500/30"}`}
    >
      {status}
    </span>
  );
}
