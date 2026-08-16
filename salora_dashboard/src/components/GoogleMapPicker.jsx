import React, { useEffect, useMemo, useState } from "react";

const toNumber = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
};

function parseCoordinates(value = "") {
  const text = decodeURIComponent(String(value || "").trim());
  const patterns = [
    /@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/,
    /[?&](?:query|q)=(-?\d{1,2}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)/i,
    /^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,،]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/,
  ];

  for (const pattern of patterns) {
    const match = text.match(pattern);
    if (!match) continue;
    const lat = Number(match[1]);
    const lng = Number(match[2]);
    if (
      Number.isFinite(lat) &&
      Number.isFinite(lng) &&
      Math.abs(lat) <= 90 &&
      Math.abs(lng) <= 180
    ) {
      return { lat, lng };
    }
  }
  return null;
}

async function searchOpenStreetMap(value) {
  const url = new URL("https://nominatim.openstreetmap.org/search");
  url.searchParams.set("format", "jsonv2");
  url.searchParams.set("limit", "1");
  url.searchParams.set("accept-language", "ar");
  url.searchParams.set("countrycodes", "sy");
  url.searchParams.set("q", value);

  const response = await fetch(url.toString(), {
    headers: { Accept: "application/json" },
  });
  if (!response.ok) throw new Error("تعذر البحث عن العنوان حالياً.");

  const items = await response.json();
  const first = Array.isArray(items) ? items[0] : null;
  if (!first) {
    throw new Error("لم يتم العثور على الموقع. اكتب المدينة والمنطقة بشكل أوضح.");
  }

  return {
    lat: Number(first.lat),
    lng: Number(first.lon),
    address: first.display_name || value,
  };
}

async function reverseOpenStreetMap(lat, lng) {
  const url = new URL("https://nominatim.openstreetmap.org/reverse");
  url.searchParams.set("format", "jsonv2");
  url.searchParams.set("accept-language", "ar");
  url.searchParams.set("lat", String(lat));
  url.searchParams.set("lon", String(lng));

  try {
    const response = await fetch(url.toString(), {
      headers: { Accept: "application/json" },
    });
    if (!response.ok) return "";
    const item = await response.json();
    return item?.display_name || "";
  } catch (_) {
    return "";
  }
}

function osmEmbedUrl(lat, lng) {
  const delta = 0.012;
  const bbox = [lng - delta, lat - delta, lng + delta, lat + delta].join(",");
  const url = new URL("https://www.openstreetmap.org/export/embed.html");
  url.searchParams.set("bbox", bbox);
  url.searchParams.set("layer", "mapnik");
  url.searchParams.set("marker", `${lat},${lng}`);
  return url.toString();
}

export default function GoogleMapPicker({
  latitude,
  longitude,
  address = "",
  onChange,
  disabled = false,
  height = 330,
}) {
  const [query, setQuery] = useState(address || "");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    setQuery(address || "");
  }, [address]);

  const location = useMemo(() => {
    const lat = toNumber(latitude);
    const lng = toNumber(longitude);
    if (lat === null || lng === null) return null;
    return { lat, lng };
  }, [latitude, longitude]);


  const emit = async (lat, lng, nextAddress = "") => {
    const safeLat = Number(lat);
    const safeLng = Number(lng);
    let resolvedAddress = nextAddress.trim();
    if (!resolvedAddress) {
      resolvedAddress = await reverseOpenStreetMap(safeLat, safeLng);
    }
    if (resolvedAddress) setQuery(resolvedAddress);

    onChange?.({
      latitude: safeLat,
      longitude: safeLng,
      address: resolvedAddress || address || "",
      googlePlaceId: "",
      mapUrl: `https://www.google.com/maps/search/?api=1&query=${safeLat},${safeLng}`,
    });
  };

  const findLocation = async () => {
    const value = query.trim();
    if (!value || disabled) return;

    setLoading(true);
    setError("");
    try {
      const direct = parseCoordinates(value);
      if (direct) {
        await emit(direct.lat, direct.lng, address || "");
        return;
      }

      const result = await searchOpenStreetMap(value);
      await emit(result.lat, result.lng, result.address);
    } catch (requestError) {
      setError(requestError?.message || "تعذر تحديد الموقع.");
    } finally {
      setLoading(false);
    }
  };

  const useCurrentLocation = () => {
    if (disabled || !navigator.geolocation) {
      setError("المتصفح لا يدعم تحديد الموقع الحالي.");
      return;
    }

    setLoading(true);
    setError("");
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        try {
          await emit(position.coords.latitude, position.coords.longitude);
        } finally {
          setLoading(false);
        }
      },
      () => {
        setError("تعذر قراءة موقعك الحالي. يمكنك البحث باسم المنطقة بدلاً من ذلك.");
        setLoading(false);
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 },
    );
  };

  return (
    <section className="space-y-3 rounded-3xl border border-white/10 bg-white/[.025] p-4" dir="rtl">
      <div className="grid gap-3 lg:grid-cols-[1fr_auto_auto]">
        <input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === "Enter") {
              event.preventDefault();
              findLocation();
            }
          }}
          disabled={disabled}
          className="field-surface w-full"
          placeholder="اكتب المدينة والمنطقة أو الصق إحداثيات / رابط خرائط"
        />
        <button
          type="button"
          onClick={findLocation}
          disabled={disabled || loading || !query.trim()}
          className="rounded-xl bg-blue-600 px-5 py-3 text-sm font-black text-white hover:bg-blue-500 disabled:opacity-50"
        >
          {loading ? "جارٍ التحديد..." : "تحديد الموقع"}
        </button>
        <button
          type="button"
          onClick={useCurrentLocation}
          disabled={disabled || loading}
          className="rounded-xl border border-emerald-400/25 bg-emerald-500/10 px-5 py-3 text-sm font-black text-emerald-200 hover:bg-emerald-500/15 disabled:opacity-50"
        >
          موقعي الحالي
        </button>
      </div>

      {error && (
        <div className="rounded-xl border border-red-400/20 bg-red-500/10 px-4 py-3 text-sm text-red-200">
          {error}
        </div>
      )}

      {location ? (
        <div className="overflow-hidden rounded-2xl border border-white/10 bg-slate-950/50">
          <iframe
            title="معاينة موقع الصالة"
            src={osmEmbedUrl(location.lat, location.lng)}
            className="w-full border-0"
            style={{ height }}
            loading="lazy"
          />
          <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-xs text-slate-400">
            <span className="ltr">{location.lat.toFixed(6)}, {location.lng.toFixed(6)}</span>
            <a
              href={`https://www.google.com/maps/search/?api=1&query=${location.lat},${location.lng}`}
              target="_blank"
              rel="noreferrer"
              className="font-bold text-blue-300 hover:text-blue-200"
            >
              فتح الموقع في الخرائط ↗
            </a>
          </div>
        </div>
      ) : (
        <div className="grid min-h-44 place-items-center rounded-2xl border border-dashed border-white/15 bg-slate-950/30 px-6 text-center text-sm text-slate-500">
          ابحث عن الموقع أو استخدم موقعك الحالي لتظهر المعاينة هنا.
        </div>
      )}
    </section>
  );
}
