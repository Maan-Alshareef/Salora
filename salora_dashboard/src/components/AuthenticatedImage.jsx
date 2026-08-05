import React, { useEffect, useState } from "react";
import { apiClient } from "../services/apiClient";

export default function AuthenticatedImage({ path, alt = "", className = "" }) {
  const [src, setSrc] = useState("");
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;
    let objectUrl = "";

    setSrc("");
    setError("");

    if (!path) {
      setError("لا يوجد ملف صورة مرتبط.");
      return () => {};
    }

    apiClient.getBlob(path)
      .then((blob) => {
        if (!active) return;
        objectUrl = URL.createObjectURL(blob);
        setSrc(objectUrl);
      })
      .catch((exception) => {
        if (active) setError(exception?.message || "تعذر تحميل الصورة.");
      });

    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [path]);

  if (error) {
    return <div className="rounded-2xl border border-red-400/20 bg-red-500/10 p-8 text-center text-sm text-red-200">{error}</div>;
  }

  if (!src) {
    return <div className="rounded-2xl border border-white/10 p-8 text-center text-sm text-slate-400">جارٍ تحميل الصورة...</div>;
  }

  return <img src={src} alt={alt} className={className} />;
}
