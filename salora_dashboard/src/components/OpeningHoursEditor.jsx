import React from "react";

export const WEEK_DAYS = [
  ["saturday", "السبت"],
  ["sunday", "الأحد"],
  ["monday", "الاثنين"],
  ["tuesday", "الثلاثاء"],
  ["wednesday", "الأربعاء"],
  ["thursday", "الخميس"],
  ["friday", "الجمعة"],
];

export const defaultOpeningHours = () => Object.fromEntries(
  WEEK_DAYS.map(([key]) => [key, { enabled: true, open: "09:00", close: "23:00" }]),
);

export function normaliseOpeningHours(value) {
  const source = value && typeof value === "object" ? value : {};
  return Object.fromEntries(WEEK_DAYS.map(([key]) => {
    const entry = source[key] || {};
    return [key, {
      enabled: entry.enabled !== false,
      open: entry.open || "09:00",
      close: entry.close || "23:00",
    }];
  }));
}

export default function OpeningHoursEditor({ value, onChange, disabled = false }) {
  const hours = normaliseOpeningHours(value);
  const update = (day, patch) => onChange?.({ ...hours, [day]: { ...hours[day], ...patch } });

  return (
    <div className="space-y-3 rounded-3xl border border-violet-400/20 bg-violet-500/[.06] p-4">
      <div>
        <h3 className="font-black text-violet-100">🕒 أوقات عمل الصالة</h3>
        <p className="mt-1 text-xs text-slate-400">الحجز يجب أن يقع ضمن هذه الأوقات، والمواعيد المتعارضة تُرفض تلقائياً.</p>
      </div>
      <div className="space-y-2">
        {WEEK_DAYS.map(([key, label]) => {
          const entry = hours[key];
          return (
            <div key={key} className="grid items-center gap-2 rounded-2xl border border-white/10 bg-white/[.03] p-3 sm:grid-cols-[110px_100px_1fr_1fr]">
              <div className="font-bold text-slate-100">{label}</div>
              <label className="flex items-center gap-2 text-xs text-slate-300">
                <input type="checkbox" checked={entry.enabled} disabled={disabled} onChange={(event) => update(key, { enabled: event.target.checked })} />
                {entry.enabled ? "مفتوح" : "مغلق"}
              </label>
              <label className="flex items-center gap-2 text-xs text-slate-400">من
                <input type="time" value={entry.open} disabled={disabled || !entry.enabled} onChange={(event) => update(key, { open: event.target.value })} className="field-surface" />
              </label>
              <label className="flex items-center gap-2 text-xs text-slate-400">إلى
                <input type="time" value={entry.close} disabled={disabled || !entry.enabled} onChange={(event) => update(key, { close: event.target.value })} className="field-surface" />
              </label>
            </div>
          );
        })}
      </div>
    </div>
  );
}
