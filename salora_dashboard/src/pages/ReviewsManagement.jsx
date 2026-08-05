import React, { useMemo, useState } from "react";
import { useApp } from "../context/AppContext";

const statusClass = {
  Visible: "border-emerald-400/20 bg-emerald-500/15 text-emerald-300",
  Hidden: "border-amber-400/20 bg-amber-500/15 text-amber-300",
  Deleted: "border-red-400/20 bg-red-500/15 text-red-300"
};

export default function ReviewsManagement() {
  const { reviews, moderateReview } = useApp();
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("All");

  const filtered = useMemo(() => reviews.filter((review) => {
    const text = [review.venue, review.customer, review.comment, review.status].join(" ").toLowerCase();
    return text.includes(query.toLowerCase()) && (status === "All" || review.status === status);
  }), [reviews, query, status]);

  return (
    <div className="space-y-6 pb-12 text-white">
      <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-white">⭐ Reviews Moderation</h1>
          <p className="mt-2 text-sm text-slate-400">Admin can show/hide/delete inappropriate reviews. Owner can only reply from his workspace.</p>
        </div>
        <div className="grid w-full max-w-2xl gap-3 md:grid-cols-[1fr_180px]">
          <input className="field-surface" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search venue, customer, comment..." />
          <select className="field-surface" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option>All</option>
            <option>Visible</option>
            <option>Hidden</option>
            <option>Deleted</option>
          </select>
        </div>
      </div>

      <div className="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04]">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[980px] text-left text-sm">
            <thead className="bg-slate-950/50 text-xs uppercase tracking-widest text-blue-300">
              <tr><th className="px-5 py-4">Venue</th><th className="px-5 py-4">Customer</th><th className="px-5 py-4">Rating</th><th className="px-5 py-4">Comment</th><th className="px-5 py-4">Status</th><th className="px-5 py-4 text-center">Actions</th></tr>
            </thead>
            <tbody>
              {filtered.map((review) => (
                <tr key={review.id} className="border-t border-white/5 hover:bg-white/[.03]">
                  <td className="px-5 py-4 font-bold text-white">{review.venue}<div className="text-xs text-slate-500">{review.createdAt}</div></td>
                  <td className="px-5 py-4 text-slate-300">{review.customer}</td>
                  <td className="px-5 py-4 text-amber-300">{"★".repeat(review.rating)}{"☆".repeat(5 - review.rating)}</td>
                  <td className="px-5 py-4 text-slate-300 max-w-md">{review.comment}{review.ownerReply && <div className="mt-2 rounded-xl bg-white/[.04] p-2 text-xs text-blue-200">Owner reply: {review.ownerReply}</div>}</td>
                  <td className="px-5 py-4"><span className={`rounded-full border px-3 py-1 text-xs font-black ${statusClass[review.status] || statusClass.Visible}`}>{review.status}</span></td>
                  <td className="px-5 py-4"><div className="flex justify-center gap-2"><button onClick={() => moderateReview(review.id, "Visible")} className="rounded-lg bg-emerald-500/15 px-3 py-2 text-xs font-bold text-emerald-300">Show</button><button onClick={() => moderateReview(review.id, "Hidden")} className="rounded-lg bg-amber-500/15 px-3 py-2 text-xs font-bold text-amber-300">Hide</button><button onClick={() => moderateReview(review.id, "Deleted")} className="rounded-lg bg-red-500/15 px-3 py-2 text-xs font-bold text-red-300">Delete</button></div></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {filtered.length === 0 && <div className="p-10 text-center text-slate-400">No reviews match your filters.</div>}
      </div>
    </div>
  );
}
