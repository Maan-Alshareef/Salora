import React from "react";

export default function Button({
  children,
  variant = "glass",
  className = "",
  ...props
}) {
  const baseClass =
    "inline-flex items-center gap-2 px-6 py-2.5 rounded-lg font-semibold transition-all duration-300 border";

  let variantClass = "btn-glass";
  if (variant === "primary") variantClass = "btn-primary";
  else if (variant === "danger") variantClass = "btn-danger";
  else if (variant === "success") variantClass = "btn-success";

  return (
    <button {...props} className={`${baseClass} ${variantClass} ${className}`}>
      {children}
    </button>
  );
}
