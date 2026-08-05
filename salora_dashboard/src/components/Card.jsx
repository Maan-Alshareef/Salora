import React from "react";

export default function Card({
  title,
  subtitle,
  value,
  children,
  className = "",
}) {
  const base = `glass-panel neon-edge-blue p-6 rounded-3xl ${className}`;

  if (children) {
    return (
      <div className={base}>
        {title && (
          <h3 className="text-lg font-bold text-white mb-4">{title}</h3>
        )}
        {children}
      </div>
    );
  }

  return (
    <div className={base}>
      {subtitle && (
        <div className="text-xs text-blue-300 mb-1 uppercase tracking-wider">
          {subtitle}
        </div>
      )}
      {title && <div className="text-sm text-slate-200 mb-2">{title}</div>}
      {value && <div className="text-3xl font-bold text-white">{value}</div>}
    </div>
  );
}
