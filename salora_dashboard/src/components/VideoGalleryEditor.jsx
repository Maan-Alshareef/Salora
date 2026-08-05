import React, { useEffect, useRef } from "react";

const SUPPORTED_TYPES = new Set(["video/mp4", "video/quicktime", "video/webm", "video/x-m4v"]);
const MAX_BYTES = 50 * 1024 * 1024;

export function createLocalVideos(files) {
  return Array.from(files || []).map((file) => ({
    id: `local-video-${file.name}-${file.size}-${file.lastModified}-${Math.random()}`,
    file,
    url: URL.createObjectURL(file),
    local: true,
  }));
}

export default function VideoGalleryEditor({ videos, onChange, maxVideos = 5, label = "فيديوهات الصالة", disabled = false }) {
  const inputRef = useRef(null);
  const latestRef = useRef(videos || []);
  latestRef.current = videos || [];

  useEffect(() => () => {
    latestRef.current.filter((item) => item.local && item.url).forEach((item) => URL.revokeObjectURL(item.url));
  }, []);

  const selectFiles = (event) => {
    const files = Array.from(event.target.files || []);
    event.target.value = "";
    if (!files.length) return;
    const invalid = files.find((file) => !SUPPORTED_TYPES.has(file.type));
    if (invalid) return window.alert(`الفيديو ${invalid.name} يجب أن يكون MP4 أو MOV أو WebM أو M4V.`);
    const oversized = files.find((file) => file.size > MAX_BYTES);
    if (oversized) return window.alert(`الفيديو ${oversized.name} أكبر من 50 MB.`);
    if ((videos?.length || 0) + files.length > maxVideos) return window.alert(`يمكن إضافة ${maxVideos} فيديوهات كحد أقصى.`);
    onChange?.([...(videos || []), ...createLocalVideos(files)]);
  };

  const remove = (index) => {
    const next = [...(videos || [])];
    const [removed] = next.splice(index, 1);
    if (removed?.local && removed.url) URL.revokeObjectURL(removed.url);
    onChange?.(next);
  };

  const move = (index, direction) => {
    const target = index + direction;
    if (target < 0 || target >= (videos?.length || 0)) return;
    const next = [...videos];
    [next[index], next[target]] = [next[target], next[index]];
    onChange?.(next);
  };

  return (
    <div className="space-y-4 rounded-3xl border border-violet-400/20 bg-violet-500/[.06] p-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="font-black text-violet-100">🎬 {label}</h3>
          <p className="mt-1 text-xs text-slate-400">حتى {maxVideos} فيديوهات، بحد أقصى 50 MB للفيديو الواحد. يمكن ترتيبها قبل الحفظ.</p>
        </div>
        <button type="button" disabled={disabled || (videos?.length || 0) >= maxVideos} onClick={() => inputRef.current?.click()} className="rounded-xl border border-violet-400/30 bg-violet-500/10 px-4 py-2 text-xs font-black text-violet-200 disabled:opacity-40">
          إضافة فيديو ({videos?.length || 0}/{maxVideos})
        </button>
        <input ref={inputRef} type="file" accept="video/mp4,video/quicktime,video/webm,video/x-m4v" multiple hidden onChange={selectFiles} />
      </div>

      {(videos || []).length === 0 ? (
        <div className="rounded-2xl border border-dashed border-white/15 p-8 text-center text-sm text-slate-500">لم تتم إضافة فيديوهات بعد.</div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {videos.map((video, index) => (
            <div key={video.id || video.url || index} className="overflow-hidden rounded-2xl border border-white/10 bg-slate-950/60">
              <video src={video.url} controls preload="metadata" className="h-56 w-full bg-black object-contain" />
              <div className="grid grid-cols-3 gap-1 p-2 text-xs">
                <button type="button" disabled={disabled || index === 0} onClick={() => move(index, -1)} className="rounded-lg bg-white/5 p-2 disabled:opacity-30" title="تحريك للأمام">→</button>
                <button type="button" disabled={disabled || index === videos.length - 1} onClick={() => move(index, 1)} className="rounded-lg bg-white/5 p-2 disabled:opacity-30" title="تحريك للخلف">←</button>
                <button type="button" disabled={disabled} onClick={() => remove(index)} className="rounded-lg bg-red-500/10 p-2 text-red-200 disabled:opacity-30" title="حذف">✕</button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
