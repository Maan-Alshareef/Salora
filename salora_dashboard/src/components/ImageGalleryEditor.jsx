import React, { useEffect, useRef } from "react";

const SUPPORTED_TYPES = new Set(["image/jpeg", "image/png", "image/webp"]);
const MAX_BYTES = 4 * 1024 * 1024;

export function createLocalImages(files) {
  return Array.from(files || []).map((file) => ({
    id: `local-${file.name}-${file.size}-${file.lastModified}-${Math.random()}`,
    file,
    url: URL.createObjectURL(file),
    local: true,
  }));
}

export default function ImageGalleryEditor({ images, onChange, maxImages = 6, label = "الصور", disabled = false }) {
  const inputRef = useRef(null);
  const latestRef = useRef(images || []);
  latestRef.current = images || [];

  useEffect(() => () => {
    latestRef.current.filter((item) => item.local && item.url).forEach((item) => URL.revokeObjectURL(item.url));
  }, []);

  const selectFiles = (event) => {
    const files = Array.from(event.target.files || []);
    event.target.value = "";
    if (!files.length) return;
    const invalid = files.find((file) => !SUPPORTED_TYPES.has(file.type));
    if (invalid) return window.alert(`الصورة ${invalid.name} ليست JPG أو PNG أو WebP.`);
    const oversized = files.find((file) => file.size > MAX_BYTES);
    if (oversized) return window.alert(`الصورة ${oversized.name} أكبر من 4 MB.`);
    if ((images?.length || 0) + files.length > maxImages) return window.alert(`يمكن إضافة ${maxImages} صور كحد أقصى.`);
    onChange?.([...(images || []), ...createLocalImages(files)]);
  };

  const remove = (index) => {
    const next = [...(images || [])];
    const [removed] = next.splice(index, 1);
    if (removed?.local && removed.url) URL.revokeObjectURL(removed.url);
    onChange?.(next);
  };

  const move = (index, direction) => {
    const target = index + direction;
    if (target < 0 || target >= (images?.length || 0)) return;
    const next = [...images];
    [next[index], next[target]] = [next[target], next[index]];
    onChange?.(next);
  };

  const makeCover = (index) => {
    if (index === 0) return;
    const next = [...images];
    const [selected] = next.splice(index, 1);
    next.unshift(selected);
    onChange?.(next);
  };

  return (
    <div className="space-y-4 rounded-3xl border border-amber-400/20 bg-amber-500/[.06] p-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="font-black text-amber-100">🖼️ {label}</h3>
          <p className="mt-1 text-xs text-slate-400">من صورة واحدة إلى {maxImages}. أول صورة هي الغلاف ويمكن ترتيب الصور.</p>
        </div>
        <button type="button" disabled={disabled || (images?.length || 0) >= maxImages} onClick={() => inputRef.current?.click()} className="rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-2 text-xs font-black text-amber-200 disabled:opacity-40">
          إضافة صور ({images?.length || 0}/{maxImages})
        </button>
        <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" multiple hidden onChange={selectFiles} />
      </div>

      {(images || []).length === 0 ? (
        <div className="rounded-2xl border border-dashed border-white/15 p-8 text-center text-sm text-slate-500">لم تتم إضافة صور بعد.</div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {images.map((image, index) => (
            <div key={image.id || image.url || index} className="overflow-hidden rounded-2xl border border-white/10 bg-slate-950/60">
              <div className="relative h-40">
                <img src={image.url} alt={`صورة ${index + 1}`} className="h-full w-full object-cover" />
                {index === 0 && <span className="absolute right-2 top-2 rounded-full bg-amber-500 px-3 py-1 text-[10px] font-black text-slate-950">صورة الغلاف</span>}
              </div>
              <div className="grid grid-cols-4 gap-1 p-2 text-xs">
                <button type="button" disabled={disabled || index === 0} onClick={() => makeCover(index)} className="rounded-lg bg-amber-500/10 p-2 text-amber-200 disabled:opacity-30" title="تعيين غلاف">★</button>
                <button type="button" disabled={disabled || index === 0} onClick={() => move(index, -1)} className="rounded-lg bg-white/5 p-2 disabled:opacity-30" title="تحريك للأمام">→</button>
                <button type="button" disabled={disabled || index === images.length - 1} onClick={() => move(index, 1)} className="rounded-lg bg-white/5 p-2 disabled:opacity-30" title="تحريك للخلف">←</button>
                <button type="button" disabled={disabled} onClick={() => remove(index)} className="rounded-lg bg-red-500/10 p-2 text-red-200 disabled:opacity-30" title="حذف">✕</button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
