const DEFAULT_API_BASE_URL = "http://127.0.0.1:8000/api";
const REQUEST_TIMEOUT_MS = Number(import.meta.env.VITE_API_TIMEOUT_MS || 15000);

function resolveV2BaseUrl() {
  const configured = String(
    import.meta.env.VITE_API_BASE_URL ||
    import.meta.env.VITE_API_URL ||
    DEFAULT_API_BASE_URL
  ).trim();

  const base = configured.replace(/\/+$/, "");

  if (/\/api\/salora-v2$/i.test(base)) {
    return base;
  }

  if (/\/api$/i.test(base)) {
    return `${base}/salora-v2`;
  }

  return `${base}/api/salora-v2`;
}

const V2_BASE_URL = resolveV2BaseUrl();

function token() {
  return (
    localStorage.getItem("salora_token") ||
    localStorage.getItem("token") ||
    localStorage.getItem("auth_token") ||
    localStorage.getItem("access_token") ||
    ""
  );
}

function firstValidationMessage(errors) {
  if (!errors || typeof errors !== "object") return "";

  for (const value of Object.values(errors)) {
    if (Array.isArray(value) && value[0]) return String(value[0]);
    if (typeof value === "string" && value) return value;
  }

  return "";
}

export async function saloraV2(path, options = {}) {
  const cleanPath = String(path || "").replace(/^\/+/, "");
  const url = `${V2_BASE_URL}/${cleanPath}`;
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
  const isFormData = options.body instanceof FormData;

  try {
    const response = await fetch(url, {
      ...options,
      signal: controller.signal,
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...(isFormData ? {} : { "Content-Type": "application/json" }),
        ...(token() ? { Authorization: `Bearer ${token()}` } : {}),
        ...(options.headers || {}),
      },
    });

    const rawText = await response.text();
    let payload = {};

    if (rawText) {
      try {
        payload = JSON.parse(rawText);
      } catch (_) {
        payload = {};
      }
    }

    if (!response.ok) {
      const validation = firstValidationMessage(payload?.errors);
      const serverMessage = payload?.message || validation;

      let fallback = `فشل طلب النظام برمز ${response.status}.`;

      if (response.status === 401) {
        fallback = "انتهت جلسة الدخول. سجل خروجاً ثم ادخل من جديد.";
      } else if (response.status === 403) {
        fallback = "هذا الحساب لا يملك صلاحية إدارة الصالة المحددة.";
      } else if (response.status === 404) {
        fallback = "مسار نظام الساعات غير موجود على الخادم. تأكد من تشغيل Backend ومسارات salora-v2.";
      } else if (response.status === 422) {
        fallback = "البيانات المرسلة غير مكتملة أو غير صحيحة.";
      }

      console.error("Salora V2 request failed", {
        url,
        status: response.status,
        payload,
        rawText,
      });

      throw new Error(serverMessage || fallback);
    }

    return payload?.data ?? payload;
  } catch (error) {
    if (error?.name === "AbortError") {
      throw new Error("انتهت مهلة الاتصال. تأكد أن Laravel يعمل على المنفذ 8000.");
    }

    if (error instanceof TypeError) {
      console.error("Salora V2 network failure", { url, error });
      throw new Error(`تعذر الاتصال بخادم Laravel عبر ${V2_BASE_URL}. شغّل Backend ثم أعد المحاولة.`);
    }

    throw error;
  } finally {
    window.clearTimeout(timeout);
  }
}

export function saloraV2DebugInfo() {
  return {
    baseUrl: V2_BASE_URL,
    hasToken: Boolean(token()),
  };
}