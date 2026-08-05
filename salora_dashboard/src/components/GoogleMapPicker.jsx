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
      }
    } catch (_) {
      // Coordinates remain usable even when reverse geocoding is unavailable.
    }

    if (resolvedAddress) setQuery(resolvedAddress);
    latestChangeRef.current?.({
      latitude: location.lat,
      longitude: location.lng,
      address: resolvedAddress || fallbackAddress || "",
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
        setError("أضف VITE_GOOGLE_MAPS_API_KEY إلى ملف .env لتفعيل تحديد الموقع.");
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
        setError("");
      } catch (exception) {
        if (!cancelled) setError(`تعذر تحميل خرائط Google: ${exception?.message || exception}`);
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
    if (!value || !geocoderRef.current) return;
    setLoading(true);
    setError("");
    try {
      const response = await geocoderRef.current.geocode({ address: value, region: "SY" });
      const result = response?.results?.[0];
      const location = result?.geometry?.location;
      if (!location) throw new Error("لم يتم العثور على الموقع.");
      moveMarker(location.lat(), location.lng(), {
        fallbackAddress: result.formatted_address || value,
        placeId: result.place_id || "",
      });
    } catch (exception) {
      setError(exception?.message || "تعذر البحث عن العنوان.");
    } finally {
      setLoading(false);
    }
  };

  const useCurrentLocation = () => {
    if (!navigator.geolocation) {
      setError("المتصفح لا يدعم قراءة الموقع الحالي.");
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
        setError(positionError.message || "تعذر قراءة موقعك الحالي. تأكد من منح إذن الموقع.");
        setLoading(false);
      },
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 },
    );
  };

  const lat = toNumber(latitude);
  const lng = toNumber(longitude);

  return (
    <div className="space-y-3 rounded-3xl border border-blue-400/20 bg-blue-500/[.06] p-4">
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
          disabled={disabled || !ready}
          placeholder="ابحث عن العنوان، مثال: دمشق المزة"
          className="field-surface flex-1"
        />
        <button type="button" onClick={searchAddress} disabled={disabled || !ready || loading} className="rounded-xl bg-blue-600 px-4 py-3 text-xs font-black text-white disabled:opacity-50">
          🔎 بحث
        </button>
        <button type="button" onClick={useCurrentLocation} disabled={disabled || !ready || loading} className="rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-xs font-black text-emerald-200 disabled:opacity-50">
          📍 موقعي الحالي
        </button>
      </div>

      <div className="overflow-hidden rounded-2xl border border-white/10 bg-slate-950/60" style={{ height }}>
        <div ref={mapElementRef} className="h-full w-full" />
      </div>

      <div className="flex flex-col gap-2 text-xs text-slate-300 sm:flex-row sm:items-center sm:justify-between">
        <span>اضغط على الخريطة أو حرّك الدبوس حتى باب الصالة بدقة.</span>
        <span className="font-mono text-blue-200">{lat !== null && lng !== null ? `${lat.toFixed(6)}, ${lng.toFixed(6)}` : "لم يتم تحديد الإحداثيات"}</span>
      </div>
      {loading && <div className="text-xs font-bold text-blue-200">جاري تحميل/تحديد الموقع...</div>}
      {error && <div className="rounded-xl border border-red-400/30 bg-red-500/10 px-3 py-2 text-xs text-red-200">{error}</div>}
    </div>
  );
}
