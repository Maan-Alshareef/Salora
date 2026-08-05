import React from "react";
import { Link } from "react-router-dom";

export default function NotFound() {
  return (
    <div className="min-h-screen grid place-items-center bg-slate-950 p-6 text-white">
      <div className="max-w-md rounded-3xl border border-white/10 bg-white/[.04] p-8 text-center">
        <div className="text-5xl">🧭</div>
        <h1 className="mt-4 text-2xl font-black">Page not found</h1>
        <p className="mt-2 text-sm text-slate-400">This route is not part of the Salora dashboard.</p>
        <Link to="/auth/login" className="mt-6 inline-block rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold">Back to login</Link>
      </div>
    </div>
  );
}
