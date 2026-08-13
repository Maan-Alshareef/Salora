import React, { useEffect, useId, useRef, useState } from "react";

const DEFAULT_CENTER = { lat: 33.5138, lng: 36.2765 };
let googleMapsPromise = null;

function loadGoogleMaps(apiKey) {
  if (window.google?.maps) return Promise.resolve(window.google.maps);
  if (googleMapsPromise) return googleMapsPromise;

  googleMapsPromise = new Promise((resolve, reject) => {
    const callbackName = `saloraGoogleMapsReady_${Date.now()}`;
    window[callbackName] = () => {
      delete window[callbackName];
      if (window.google?.maps) resolve(window.google.maps);
      else reject(new Error("لم يتم تحميل مكتبة الخرائط."));
    };

    const script = document.createElement("script");
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&v=weekly&language=ar&region=SY&callback=${callbackName}`;
    script.async = true;
    script.defer = true;
    script.onerror = () => {
      delete window[callbackName];
      googleMapsPromise = null;
      reject(new Error("فشل تحميل Google Maps JavaScript API."));
    };
    document.head.appendChild(script);
  });

  return googleMapsPromise;
}

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
    if (Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
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
  const response = await fetch(url.toString(), { headers: { Accept: "application/json" } });
  if (!response.ok) throw new Error("تعذر البحث عن العنوان حالياً.");
  const items = await response.json();
  const first = Array.isArray(items) ? items[0] : null;
  if (!first) throw new Error("لم يتم العثور على العنوان. جرّب كتابة المدينة والمنطقة معاً.");
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
  const response = await fetch(url.toString(), { headers: { Accept: "application/json" } });
  if (!response.ok) return "";
  const item = await response.json();
  return item?.display_name || "";
}

function osmEmbedUrl(lat, lng) {
  if (lat === null || lng === null) return "";
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
  height = 360,
}) {
  const mapElementRef = useRef(null);
  const mapRef = useRef(null);
  const markerRef = useRef(null);
  const geocoderRef = useRef(null);
  const latestChangeRef = useRef(onChange);
  const [query, setQuery] = useState(address || "");
  const [loading, setLoading] = useState(false);
  const [ready, setReady] = useState(false);
  const [mapsUnavailable, setMapsUnavailable] = useState(false);
  const [error, setError] = useState("");
  const fieldId = useId();
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

  useEffect(() => {
    latestChangeRef.current = onChange;
  }, [onChange]);

  const emitLocation = async (lat, lng, fallbackAddress = "", placeId = null) => {
    const location = { lat: Number(lat), lng: Number(lng) };
    let resolvedAddress = fallbackAddress;
    let resolvedPlaceId = placeId;

    try {
      if (geocoderRef.current) {
        const response = await geocoderRef.current.geocode({ location });
        const first = response?.results?.[0];
        if (first) {
          resolvedAddress = first.formatted_address || resolvedAddress;
          resolvedPlaceId = first.place_id || resolvedPlaceId;
        }
      } else if (!resolvedAddress) {
        resolvedAddress = await reverseOpenStreetMap(location.lat, location.lng);
      }
    } catch (_) {
      // Coordinates remain usable even when reverse geocoding is unavailable.
    }

    if (resolvedAddress) setQuery(resolvedAddress);
    latestChangeRef.current?.({
      latitude: location.lat,
      longitude: location.lng,
      address: resolvedAddress || fallbackAddress || address || "",
      googlePlaceId: resolvedPlaceId || "",
      mapUrl: `https://www.google.com/maps/search/?api=1&query=${location.lat},${location.lng}`,
    });
  };

  const moveMarker = (lat, lng, { emit = true, zoom = 16, fallbackAddress = "", placeId = null } = {}) => {
    const position = { lat: Number(lat), lng: Number(lng) };
    markerRef.current?.setPosition(position);
    if (mapRef.current) {
      mapRef.current.panTo(position);
      if (zoom) mapRef.current.setZoom(zoom);
    }
    if (emit) emitLocation(position.lat, position.lng, fallbackAddress, placeId);
  };

  useEffect(() => {
    let cancelled = false;
    const listeners = [];

    async function initialise() {
      if (!apiKey) {
        setMapsUnavailable(true);
        setError("خرائط Google غير مفعلة على اللوحة، لكن يمكنك تحديد الموقع بكتابة العنوان أو لصق رابط/إحداثيات بدون استخدام GPS.");
        return;
      }
      try {
        setLoading(true);
        const maps = await loadGoogleMaps(apiKey);
        if (cancelled || !mapElementRef.current) return;

        const initialLat = toNumber(latitude);
        const initialLng = toNumber(longitude);
        const initialPosition = initialLat !== null && initialLng !== null
          ? { lat: initialLat, lng: initialLng }
          : DEFAULT_CENTER;

        const map = new maps.Map(mapElementRef.current, {
          center: initialPosition,
          zoom: initialLat !== null ? 16 : 12,
          streetViewControl: false,
          mapTypeControl: false,
          fullscreenControl: true,
          gestureHandling: "greedy",
        });
        const marker = new maps.Marker({
          map,
          position: initialPosition,
          title: "موقع الصالة",
          draggable: !disabled,
        });

        mapRef.current = map;
        markerRef.current = marker;
        geocoderRef.current = new maps.Geocoder();

        listeners.push(map.addListener("click", (event) => {
          if (disabled || !event.latLng) return;
          moveMarker(event.latLng.lat(), event.latLng.lng());
        }));
        listeners.push(marker.addListener("dragend", (event) => {
          if (disabled || !event.latLng) return;
          moveMarker(event.latLng.lat(), event.latLng.lng(), { zoom: null });
        }));

        setReady(true);
        setMapsUnavailable(false);
        setError("");
      } catch (exception) {
        if (!cancelled) {
          setMapsUnavailable(true);
          setError("تعذر تشغيل خريطة Google. يمكنك الاستمرار بكتابة العنوان أو لصق رابط Google Maps وسيتم تحديد الإحداثيات بطريقة بديلة.");
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    initialise();
    return () => {
      cancelled = true;
      listeners.forEach((listener) => listener?.remove?.());
      markerRef.current?.setMap(null);
      markerRef.current = null;
      mapRef.current = null;
      geocoderRef.current = null;
    };
    // Map initialization is intentionally tied only to the key and disabled state.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [apiKey, disabled]);

  useEffect(() => {
    const lat = toNumber(latitude);
    const lng = toNumber(longitude);
    if (!ready || lat === null || lng === null || !markerRef.current) return;
    markerRef.current.setPosition({ lat, lng });
    mapRef.current?.panTo({ lat, lng });
  }, [latitude, longitude, ready]);

  useEffect(() => {
    if (address && address !== query) setQuery(address);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [address]);

  const searchAddress = async () => {
    const value = query.trim();
    if (!value) return;
    setLoading(true);
    setError("");
    try {
      const direct = parseCoordinates(value);
      if (direct) {
        moveMarker(direct.lat, direct.lng, { fallbackAddress: address || value });
        return;
      }

      if (geocoderRef.current) {
        try {
          const response = await geocoderRef.current.geocode({ address: value, region: "SY" });
          const result = response?.results?.[0];
          const location = result?.geometry?.location;
          if (location) {
            moveMarker(location.lat(), location.lng(), {
              fallbackAddress: result.formatted_address || value,
              placeId: result.place_id || "",
            });
            return;
          }
        } catch (_) {
          // Fall back to no-key address search below.
        }
      }

      const fallback = await searchOpenStreetMap(value);
      moveMarker(fallback.lat, fallback.lng, { fallbackAddress: fallback.address });
      if (!ready) setMapsUnavailable(true);
    } catch (exception) {
      setError(exception?.message || "تعذر البحث عن العنوان.");
    } finally {
      setLoading(false);
    }
  };

  const useCurrentLocation = () => {
    if (!navigator.geolocation) {
      setError("المتصفح لا يدعم قراءة الموقع الحالي. استخدم البحث بالعنوان بدلاً منه.");
      return;
    }
    setLoading(true);
    setError("");
    navigator.geolocation.getCurrentPosition(
      (position) => {
        moveMarker(position.coords.latitude, position.coords.longitude);
        setLoading(false);
      },
      (positionError) => {
        const denied = positionError?.code === 1;
        setError(denied
          ? "تم رفض إذن الموقع. لا مشكلة: اكتب عنوان الصالة واضغط «تحديد العنوان»؛ لا تحتاج إلى تفعيل GPS."
          : "تعذر قراءة موقعك الحالي. استخدم البحث بالعنوان أو الصق رابط Google Maps.");
        setLoading(false);
      },
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 },
    );
  };

  const lat = toNumber(latitude);
  const lng = toNumber(longitude);
  const fallbackMapUrl = osmEmbedUrl(lat, lng);

  return (
    <div className="space-y-3 rounded-3xl border border-blue-400/20 bg-blue-500/[.06] p-4">
      <div className="rounded-2xl border border-emerald-400/20 bg-emerald-500/[.06] px-4 py-3 text-xs leading-6 text-emerald-100">
        الأسهل: اكتب اسم المنطقة والمدينة مثل «صحنايا، دمشق» ثم اضغط <b>تحديد العنوان</b>. ويمكنك أيضاً لصق رابط Google Maps أو إحداثيات بالشكل 33.4355, 36.2376. زر «موقعي الحالي» اختياري فقط.
      </div>

      <div className="flex flex-col gap-2 sm:flex-row">
        <label htmlFor={fieldId} className="sr-only">ابحث عن موقع الصالة</label>
        <input
          id={fieldId}
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === "Enter") {
              event.preventDefault();
              searchAddress();
            }
          }}
          disabled={disabled}
          placeholder="العنوان أو رابط Google Maps أو الإحداثيات"
          className="field-surface flex-1"
        />
        <button type="button" onClick={searchAddress} disabled={disabled || loading} className="rounded-xl bg-blue-600 px-4 py-3 text-xs font-black text-white disabled:opacity-50">
          🔎 تحديد العنوان
        </button>
        <button type="button" onClick={useCurrentLocation} disabled={disabled || loading} className="rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-xs font-black text-emerald-200 disabled:opacity-50">
          📍 موقعي الحالي
        </button>
      </div>

      <div className="overflow-hidden rounded-2xl border border-white/10 bg-slate-950/60" style={{ height }}>
        {!mapsUnavailable && <div ref={mapElementRef} className="h-full w-full" />}
        {mapsUnavailable && fallbackMapUrl && (
          <iframe title="موقع الصالة" src={fallbackMapUrl} className="h-full w-full border-0" loading="lazy" />
        )}
        {mapsUnavailable && !fallbackMapUrl && (
          <div className="grid h-full place-items-center p-6 text-center text-sm leading-7 text-slate-400">
            <div><div className="mb-3 text-5xl">📍</div>اكتب العنوان بالأعلى واضغط «تحديد العنوان» لعرض الموقع هنا.</div>
          </div>
        )}
      </div>

      <div className="flex flex-col gap-2 text-xs text-slate-300 sm:flex-row sm:items-center sm:justify-between">
        <span>{ready ? "اضغط على الخريطة أو حرّك الدبوس حتى باب الصالة بدقة." : "الموقع المحفوظ يعتمد على الإحداثيات الناتجة من البحث، ويمكن فتحه لاحقاً في Google Maps."}</span>
        <span className="font-mono text-blue-200">{lat !== null && lng !== null ? `${lat.toFixed(6)}, ${lng.toFixed(6)}` : "لم يتم تحديد الإحداثيات"}</span>
      </div>
      {loading && <div className="text-xs font-bold text-blue-200">جاري تحديد الموقع...</div>}
      {error && <div className="rounded-xl border border-amber-400/30 bg-amber-500/10 px-3 py-2 text-xs leading-6 text-amber-100">{error}</div>}
    </div>
  );
}
