import { API_BASE_URL } from "../services/apiClient";

const API_ORIGIN = API_BASE_URL.replace(/\/api\/?$/, "");
const STORAGE_PREFIXES = [
  "avatars/", "venues/", "services/", "service-categories/", "payment-methods/",
  "offers/", "providers/", "provider-portfolios/", "service-media/"
];

const cleanStoragePath = (value = "") => String(value || "")
  .replaceAll("\\", "/")
  .replace(/^\/?storage\//i, "")
  .replace(/^(?:storage\/)+/i, "")
  .replace(/^\/+/, "");

const publicMediaUrl = (path) => `${API_BASE_URL}/media/public-file?path=${encodeURIComponent(cleanStoragePath(path))}`;

export function resolveMediaUrl(value = "") {
  const raw = String(value || "").trim();
  if (!raw) return "";
  if (/^(data:|blob:)/i.test(raw)) return raw;

  if (/^https?:\/\//i.test(raw)) {
    try {
      const parsed = new URL(raw);
      const storage = parsed.pathname.match(/\/storage\/(.+)$/i);
      if (storage && ["localhost", "127.0.0.1", "10.0.2.2", new URL(API_ORIGIN).hostname].includes(parsed.hostname)) {
        return publicMediaUrl(storage[1]);
      }
      return raw;
    } catch (_) { return raw; }
  }

  const normal = raw.replaceAll("\\", "/");
  const storage = normal.match(/^\/?storage\/(.+)$/i);
  if (storage) return publicMediaUrl(storage[1]);
  const plain = normal.replace(/^\/+/, "");
  if (STORAGE_PREFIXES.some((prefix) => plain.startsWith(prefix))) return publicMediaUrl(plain);
  if (normal.startsWith("/")) return `${API_ORIGIN}${normal}`;
  return `${API_ORIGIN}/${plain}`;
}
