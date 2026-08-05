import React, { createContext, useContext, useEffect, useMemo, useState } from "react";
import { BOOKING_STATUS, EVENT_TYPES, PAYMENT_STATUS, ROLES, SERVICE_TYPES } from "../config/permissions";
import { API_BASE_URL, dashboardApi } from "../services/apiClient";

const AppContext = createContext(null);
export const useApp = () => useContext(AppContext);

const nowTime = () => new Intl.DateTimeFormat("ar", { hour: "2-digit", minute: "2-digit" }).format(new Date());
const today = () => new Date().toISOString().slice(0, 10);

const API_ORIGIN = API_BASE_URL.replace(/\/api\/?$/, '');
const assetUrl = (value = '') => {
  const raw = String(value || '').trim();
  if (!raw) return '';
  if (/^(data:|blob:)/i.test(raw)) return raw;

  const publicMediaUrl = (path) => {
    const normalized = decodeURIComponent(String(path || ''))
      .replace(/^\/?storage\//, '')
      .replace(/^\/+/, '');
    return `${API_BASE_URL}/media/public-file?path=${encodeURIComponent(normalized)}`;
  };

  if (/^https?:/i.test(raw)) {
    try {
      const parsed = new URL(raw);
      if (parsed.pathname.startsWith('/storage/')) return publicMediaUrl(parsed.pathname);
      if (parsed.pathname.endsWith('/api/media/public-file') && parsed.searchParams.get('path')) {
        return publicMediaUrl(parsed.searchParams.get('path'));
      }
      if (!['127.0.0.1', 'localhost', '10.0.2.2', '0.0.0.0'].includes(parsed.hostname.toLowerCase())) return raw;
      return `${API_ORIGIN}${parsed.pathname}${parsed.search}`;
    } catch (_) {
      return raw;
    }
  }

  if (raw.startsWith('/storage/') || raw.startsWith('storage/')) return publicMediaUrl(raw);
  if (raw.startsWith('/api/media/public-file') || raw.startsWith('api/media/public-file')) {
    try {
      const parsed = new URL(raw.startsWith('/') ? raw : `/${raw}`, API_ORIGIN);
      if (parsed.searchParams.get('path')) return publicMediaUrl(parsed.searchParams.get('path'));
    } catch (_) { /* fall through */ }
  }
  if (raw.startsWith('/')) return `${API_ORIGIN}${raw}`;
  return `${API_ORIGIN}/${raw.replace(/^\/+/, '')}`;
};

const toDateKey = (value = "") => {
  const text = String(value || "");
  const iso = text.match(/\d{4}-\d{2}-\d{2}/);
  if (iso) return iso[0];
  const dmy = text.match(/(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/);
  if (dmy) return `${dmy[3]}-${String(dmy[2]).padStart(2, "0")}-${String(dmy[1]).padStart(2, "0")}`;
  return text.slice(0, 10);
};
const toTimeKey = (value = "") => {
  const text = String(value || "");
  const hm = text.match(/(\d{1,2}):(\d{2})/);
  if (hm) return `${String(hm[1]).padStart(2, "0")}:${hm[2]}`;
  return text;
};
const formatUsd = (amount = 0) => `$${Number(amount || 0).toLocaleString()}`;
const formatSyp = (amount = 0) => `${Number(amount || 0).toLocaleString()} ل.س`;
const formatPricePair = (usd = null, suffix = "", syp = null) => {
  const parts = [];
  if (usd !== null && usd !== "" && Number(usd) > 0) parts.push(`${formatUsd(usd)}${suffix}`);
  if (syp !== null && syp !== "" && Number(syp) > 0) parts.push(`${formatSyp(syp)}${suffix}`);
  return parts.length ? parts.join(" / ") : formatSyp(0);
};

const eventEmoji = {
  Wedding: "💍",
  Engagement: "💞",
  Graduation: "🎓",
  Birthday: "🎂",
  "Family Event": "👨‍👩‍👧",
  "Birthday / Family Event": "🎂",
  Condolence: "🕊️",
  Conference: "🧑‍💼",
  Meeting: "🤝"
};


const arabicLabel = (value = "") => ({
  Approved: "مقبول", Pending: "قيد المراجعة", Rejected: "مرفوض", Active: "فعال", Disabled: "معطل", Inactive: "غير نشط", Suspended: "مجمّد", Locked: "مقفول مؤقتاً", Deleted: "محذوف", Visible: "ظاهر", Hidden: "مخفي", Open: "مفتوحة", "In Progress": "قيد المعالجة", Answered: "تم الرد", Resolved: "تم الرد والحل", Closed: "مغلقة", Confirmed: "مؤكد", Completed: "منجز", Cancelled: "ملغي", Expired: "انتهت مهلة الدفع", "Pending Owner Review": "حجز قديم قيد المراجعة", "Pending Payment": "بانتظار الدفع", "Owner Approved": "بانتظار الدفع", "Modification Requested": "طلب تعديل قيد المراجعة", "Cancellation Requested": "طلب إلغاء قيد المراجعة", Verified: "مدفوع ومقبول", "Pending Admin Verification": "بانتظار مراجعة مالك الصالة", "Payment Under Review": "قيد مراجعة الدفع", "Proof Uploaded": "تم رفع الإثبات", "Not Uploaded": "لم يتم رفع الإثبات", Unpaid: "غير مدفوع", "Rejected Proof": "إثبات الدفع مرفوض", "Re-upload Requested": "مطلوب إعادة رفع الإثبات", Refunded: "مسترد", Customer: "عميل", Owner: "مالك صالة", Provider: "مقدم خدمة", Admin: "مدير النظام", Wedding: "زفاف", Engagement: "خطوبة", Graduation: "تخرج", Birthday: "عيد ميلاد", "Family Event": "مناسبة عائلية", "Birthday / Family Event": "عيد ميلاد / مناسبة عائلية", Condolence: "عزاء", Conference: "مؤتمر", Meeting: "اجتماع", "Included Hall Service": "خدمة مجانية ضمن الصالة", "Paid Hall Upgrade": "خدمة مدفوعة إضافية", "External Vendor Service": "خدمة من مقدم خارجي", Lighting: "إضاءة", Catering: "ضيافة", تصوير: "تصوير", Service: "خدمة"
}[value] || value);

const serviceEmoji = {
  "إضاءة أساسية": "💡",
  "إضاءة مميزة": "✨",
  "طاولات وكراسي": "🪑",
  تنظيف: "🧹",
  "موقف سيارات": "🅿️",
  "مياه": "💧",
  "تكييف": "❄️",
  "إنترنت": "📶",
  "صوت أساسي": "🔊",
  "ضيافة مميزة": "☕",
  "ساعة إضافية": "⏱️",
  "بروجكتور": "📽️",
  "ركن تصوير": "📸",
  "قارئ / شيخ": "📖",
  "قهوة وشاي": "☕",
  "ضيافة عزاء": "🕊️",
  "ديكور": "🌸",
  "مأكولات ومشروبات": "🍽️",
  Cake: "🎂",
  "فريق تنظيم": "🧑‍💼"
};


const asArray = (value) => Array.isArray(value) ? value : Array.isArray(value?.data) ? value.data : [];
const normalizeStatus = (status = "") => String(status || "")
  .split("_")
  .map((x) => x.charAt(0).toUpperCase() + x.slice(1))
  .join(" ");
const serviceTypeLabel = (type = "") => type === "included" ? SERVICE_TYPES.INCLUDED : type === "hall_upgrade" ? SERVICE_TYPES.HALL_UPGRADE : SERVICE_TYPES.EXTERNAL_VENDOR;
const categoryAr = (value = "") => ({
  included: "خدمات مجانية",
  hall_upgrade: "ترقيات مدفوعة",
  external_vendor: "خدمات خارجية",
  lighting: "إضاءة",
  catering: "ضيافة",
  decoration: "ديكور",
  photography: "تصوير",
  cake: "كيك وحلويات",
  service: "خدمة"
}[String(value || "").toLowerCase()] || arabicLabel(value));
const serviceApprovalStatus = (service = {}) => {
  const approval = String(service.approval_status || "").toLowerCase();
  if (["pending", "rejected"].includes(approval)) return normalizeStatus(approval);
  if (approval === "approved") return service.is_active === false ? "Disabled" : "Approved";
  return service.is_active === false ? "Disabled" : "Approved";
};
const activeOfferStatus = (status = "") => ["approved", "active"].includes(String(status).toLowerCase()) ? "Active" : normalizeStatus(status || "pending");
const bookingStatusFromApi = (status = "") => {
  const value = String(status || "").toLowerCase();
  if (["pending_owner_review", "pending", "owner_review"].includes(value)) return BOOKING_STATUS.PENDING_OWNER_REVIEW;
  if (["pending_payment", "owner_approved", "approved_by_owner"].includes(value)) return BOOKING_STATUS.PENDING_PAYMENT || "Pending Payment";
  if (["payment_under_review"].includes(value)) return BOOKING_STATUS.PAYMENT_UNDER_REVIEW || "Payment Under Review";
  if (["modification_requested", "pending_modification"].includes(value)) return BOOKING_STATUS.MODIFICATION_REQUESTED || "Modification Requested";
  if (["cancellation_requested", "pending_cancellation"].includes(value)) return BOOKING_STATUS.CANCELLATION_REQUESTED || "Cancellation Requested";
  if (["confirmed", "approved", "paid"].includes(value)) return BOOKING_STATUS.CONFIRMED;
  if (["completed", "done"].includes(value)) return BOOKING_STATUS.COMPLETED;
  if (["rejected", "owner_rejected"].includes(value)) return BOOKING_STATUS.REJECTED;
  if (["cancelled", "canceled"].includes(value)) return BOOKING_STATUS.CANCELLED;
  if (value === "expired") return "Expired";
  return normalizeStatus(status || "pending_payment");
};
const paymentStatusFromApi = (status = "") => {
  const value = String(status || "").toLowerCase();
  if (["approved", "verified", "paid"].includes(value)) return PAYMENT_STATUS.VERIFIED;
  if (["proof_uploaded", "payment_under_review", "under_review", "pending", "pending_admin_verification"].includes(value)) return PAYMENT_STATUS.PENDING_ADMIN_VERIFICATION;
  if (["rejected", "rejected_proof"].includes(value)) return PAYMENT_STATUS.REJECTED_PROOF;
  if (["reupload_requested", "request_reupload"].includes(value)) return PAYMENT_STATUS.REUPLOAD_REQUESTED;
  if (["refunded"].includes(value)) return PAYMENT_STATUS.REFUNDED;
  return PAYMENT_STATUS.NOT_UPLOADED;
};
const userFromApi = (u = {}) => ({
  id: String(u.id ?? ""),
  name: u.name || "User",
  email: u.email || "",
  role: u.role === "admin" ? ROLES.ADMIN : u.role === "owner" ? ROLES.OWNER : u.role === "provider" ? ROLES.PROVIDER : "Customer",
  status: u.deleted_at ? "Deleted" : normalizeStatus(u.account_state || u.status || "active"),
  phone: u.phone || "",
  avatarUrl: assetUrl(u.avatar_url || u.avatar || ""),
  mustChangePassword: Boolean(u.must_change_password),
  emailVerified: Boolean(u.email_verified_at),
  lockedUntil: u.locked_until || null,
  suspendedUntil: u.suspended_until || null,
  suspensionReason: u.suspension_reason || "",
  deletedAt: u.deleted_at || null,
  joinedAt: String(u.created_at || "").slice(0, 10)
});
const venueFromApi = (v = {}) => {
  const imageObjects = asArray(v.images).map((img, index) => ({
    id: String(img.id ?? `image-${index}`),
    url: assetUrl(img.image_url || img.url),
    rawUrl: img.image_url || img.url || "",
    isMain: Boolean(img.is_main) || index === 0,
    sortOrder: Number(img.sort_order ?? index + 1)
  })).filter((img) => img.url);
  const videoObjects = asArray(v.videos).map((video, index) => ({
    id: String(video.id ?? `video-${index}`),
    url: assetUrl(video.resolved_url || video.video_url || video.url),
    rawUrl: video.video_url || video.url || "",
    sortOrder: Number(video.sort_order ?? index + 1)
  })).filter((video) => video.url);
  const pendingRevision = v.pending_revision && v.pending_revision.status === "pending" ? v.pending_revision : null;
  const pendingPayload = pendingRevision?.payload || {};
  const editableImageObjects = pendingRevision?.replace_images
    ? asArray(pendingRevision.image_urls).map((url, index) => ({ id: `pending-image-${index}`, url: assetUrl(url), rawUrl: url, isMain: index === 0, sortOrder: index + 1 })).filter((image) => image.url)
    : imageObjects;
  const editableVideoObjects = pendingRevision?.replace_videos
    ? asArray(pendingRevision.video_urls).map((url, index) => ({ id: `pending-video-${index}`, url: assetUrl(url), rawUrl: url, sortOrder: index + 1 })).filter((video) => video.url)
    : videoObjects;
  return {
    id: String(v.id ?? ""),
    name: pendingPayload.name_ar || pendingPayload.name_en || v.name_ar || v.name_en || v.name || "صالة",
    city: pendingPayload.city ?? v.city ?? "",
    address: pendingPayload.address ?? v.address ?? "",
    mapUrl: pendingPayload.map_url ?? v.map_url ?? "",
    latitude: (pendingPayload.latitude ?? v.latitude) === null || (pendingPayload.latitude ?? v.latitude) === undefined ? null : Number(pendingPayload.latitude ?? v.latitude),
    longitude: (pendingPayload.longitude ?? v.longitude) === null || (pendingPayload.longitude ?? v.longitude) === undefined ? null : Number(pendingPayload.longitude ?? v.longitude),
    googlePlaceId: v.google_place_id || "",
    openingHours: v.opening_hours || {},
    ownerId: String(v.owner_id ?? v.owner?.id ?? ""),
    owner: v.owner?.name || "مالك الصالة",
    capacity: Number(pendingPayload.capacity ?? v.capacity ?? 0),
    basePrice: Number(v.price_usd ?? 0),
    finalPrice: Number(v.final_price_usd ?? v.price_usd ?? 0),
    priceSyp: Number(pendingPayload.price_syp ?? v.price_syp ?? 0),
    finalPriceSyp: Number(v.final_price_syp ?? v.price_syp ?? 0),
    priceCurrency: v.currency_base || "USD",
    status: normalizeStatus(v.status || "pending"),
    description: pendingPayload.description_ar || pendingPayload.description_en || v.description_ar || v.description_en || "",
    supportedEventTypes: asArray(v.event_types).map((e) => e.name_ar || e.name_en || e.name || String(e)),
    includedServices: asArray(v.services).filter((x) => x.type === "included").map((x) => `${x.emoji || serviceEmoji[x.name_ar] || serviceEmoji[x.name_en] || "✅"} ${x.name_ar || x.name_en || x.name}`),
    paidUpgrades: asArray(v.services).filter((x) => x.type === "hall_upgrade").map((x) => `${x.emoji || serviceEmoji[x.name_ar] || serviceEmoji[x.name_en] || "✨"} ${x.name_ar || x.name_en || x.name}`),
    vendorCategories: Array.from(new Set([
      ...asArray(v.vendor_categories),
      ...asArray(v.services)
        .filter((x) => x.type === "external_vendor")
        .map((x) => x.category_model?.name_ar || x.category_model?.name_en || x.category || x.name_ar || x.name_en || x.name)
    ].filter(Boolean))),
    amenities: asArray(v.amenities),
    policies: asArray(v.policies),
    services: asArray(v.services).map((x) => x.name_ar || x.name_en || x.name),
    rating: Number(v.rating_avg || 0),
    reviewsCount: Number(v.reviews_count || 0),
    images: imageObjects.length,
    imageObjects,
    imageUrls: imageObjects.map((img) => img.url),
    rawImageUrls: imageObjects.map((img) => img.rawUrl),
    videos: videoObjects.length,
    videoObjects,
    videoUrls: videoObjects.map((video) => video.url),
    rawVideoUrls: videoObjects.map((video) => video.rawUrl),
    hasOffer: Boolean(v.has_offer),
    discount: Number(v.discount_percentage || 0),
    activeOffer: v.active_offer?.title_ar || v.active_offer?.title_en || "",
    badge: v.badge || (v.is_featured ? "مميزة" : ""),
    pendingRevision,
    editImageObjects: editableImageObjects,
    editVideoObjects: editableVideoObjects,
    createdAt: String(v.created_at || "").slice(0, 10)
  };
};
const bookingFromApi = (b = {}) => ({
  id: String(b.id ?? ""),
  customerId: String(b.customer_id ?? b.customer?.id ?? ""),
  customer: b.customer?.name || "العميل",
  email: b.customer?.email || "",
  venueId: String(b.venue_id ?? b.venue?.id ?? ""),
  ownerId: String(b.owner_id ?? b.owner?.id ?? b.venue?.owner_id ?? ""),
  venue: b.venue?.name_ar || b.venue?.name_en || b.venue_name || "الصالة",
  eventType: b.event_type?.name_ar || b.event_type?.name_en || b.event_type || "المناسبة",
  eventName: b.event_name || "مناسبة",
  date: toDateKey(b.event_date || b.date || ""),
  time: toTimeKey(b.start_time || b.time || ""),
  endTime: toTimeKey(b.end_time || b.endTime || ""),
  guests: Number(b.guests_count || 0),
  amount: Number(b.total_usd || 0),
  amountSyp: Number(b.total_syp || 0),
  invoiceTotal: Number(b.total_usd || 0),
  status: bookingStatusFromApi(b.booking_status || "pending_owner_review"),
  paymentStatus: paymentStatusFromApi(b.payment_status || "unpaid"),
  paymentProofId: String(b.latest_payment_proof?.id ?? b.payment_proofs?.[0]?.id ?? ''),
  proof: b.latest_payment_proof?.image_url || b.payment_proofs?.[0]?.image_url || "",
  proofUrl: assetUrl(b.latest_payment_proof?.image_full_url || b.payment_proofs?.[0]?.image_full_url || b.latest_payment_proof?.image_url || b.payment_proofs?.[0]?.image_url || "")
});
const serviceFromApi = (s = {}) => {
  const venueOwners = asArray(s.venues).map((v) => String(v.owner_id || "")).filter(Boolean);
  return {
    id: String(s.id ?? ""),
    name: s.name_ar || s.name_en || s.name || "خدمة",
    provider: s.provider?.name || (s.provider_id ? `مقدم خدمة #${s.provider_id}` : "خدمة صالة"),
    category: categoryAr(s.category_model?.name_ar || s.category_model?.name_en || s.category || s.type || "خدمة"),
    categoryId: String(s.category_id ?? s.category_model?.id ?? ""),
    description: s.description_ar || s.description_en || "",
    imageUrl: assetUrl(s.cover_image_url || s.image_url || s.images?.[0]?.image_url || ""),
    imageUrls: asArray(s.images).map((image) => assetUrl(image.image_url || image.url)).filter(Boolean),
    imageObjects: asArray(s.images).map((image, index) => ({
      id: String(image.id ?? index),
      url: assetUrl(image.image_url || image.url),
      rawUrl: image.image_url || image.url || "",
      isMain: Boolean(image.is_main) || index === 0,
      sortOrder: Number(image.sort_order ?? index + 1)
    })),
    pricingUnit: s.pricing_unit || "per_event",
    durationMinutes: Number(s.duration_minutes || 0),
    type: s.type || "external_vendor",
    serviceType: serviceTypeLabel(s.type || "external_vendor"),
    price: Number(s.price_usd || 0),
    priceSyp: Number(s.price_syp || 0),
    ownerId: String(s.provider_id || venueOwners[0] || ""),
    providerId: String(s.provider_id || ""),
    venueOwners,
    status: serviceApprovalStatus(s),
    isActive: Boolean(s.is_active),
    rejectionReason: s.rejection_reason || "",
    emoji: s.emoji || serviceEmoji[s.name_ar] || serviceEmoji[s.name_en] || "🔧",
    availableFor: asArray(s.available_for),
    rating: Number(s.rating || 0),
    orders: Number(s.orders || 0)
  };
};
const serviceCategoryFromApi = (category = {}) => ({
  id: String(category.id ?? ""),
  parentId: category.parent_id ? String(category.parent_id) : "",
  parentName: category.parent?.name_ar || category.parent?.name_en || "",
  name: category.name_ar || category.name_en || "تصنيف خدمة",
  nameEn: category.name_en || "",
  description: category.description || "",
  imageUrl: assetUrl(category.image_url || ""),
  appliesTo: category.applies_to || "both",
  isActive: category.is_active !== false,
  sortOrder: Number(category.sort_order || 0),
  servicesCount: Number(category.services_count || 0),
  childrenCount: Number(category.children_count || category.children?.length || 0),
  children: asArray(category.children).map((child) => serviceCategoryFromApi(child))
});
const reviewFromApi = (r = {}) => ({
  id: String(r.id ?? ""),
  venueId: String(r.venue_id ?? r.venue?.id ?? ""),
  ownerId: String(r.venue?.owner_id ?? ""),
  venue: r.venue?.name_ar || r.venue?.name_en || "الصالة",
  customer: r.customer?.name || "العميل",
  rating: Number(r.rating || 0),
  comment: r.comment || "",
  status: normalizeStatus(r.status || "visible"),
  ownerReply: r.owner_reply || ""
});
const complaintFromApi = (c = {}) => ({
  id: String(c.id ?? ""),
  customer: c.customer?.name || "العميل",
  user: c.customer?.name || "العميل",
  role: "Customer",
  venue: c.venue?.name_ar || c.venue?.name_en || "الصالة",
  ownerId: String(c.owner_id || c.venue?.owner_id || ""),
  subject: c.subject || "شكوى",
  message: c.message || "",
  status: normalizeStatus(c.status || "open"),
  priority: normalizeStatus(c.priority || "medium"),
  adminReply: c.admin_reply || "",
  ownerReply: c.owner_reply || "",
  reply: [c.admin_reply ? `رد الإدارة: ${c.admin_reply}` : "", c.owner_reply ? `رد المالك: ${c.owner_reply}` : ""].filter(Boolean).join("\n"),
  createdAt: String(c.created_at || "").slice(0, 10)
});
const ownerRequestFromApi = (r = {}) => {
  const isProvider = (r.request_type || "owner") === "provider";
  const categoryLabel = typeof r.service_category === "string"
    ? r.service_category
    : (r.service_category?.name_ar || r.service_category?.name_en || "");
  return {
    id: String(r.id ?? ""),
    type: isProvider ? "Provider Request" : "Owner Request",
    requestType: isProvider ? "provider" : "owner",
    requestId: String(r.id ?? ""),
    title: isProvider ? `طلب مقدم خدمة: ${categoryLabel || r.full_name || "غير محدد"}` : (r.hall_name || `طلب مالك صالة: ${r.full_name || "غير محدد"}`),
    requester: r.full_name || (isProvider ? "مقدم خدمة جديد" : "مالك جديد"),
    email: r.email || "",
    emailVerified: Boolean(r.email_verified_at),
    phone: r.phone || "",
    city: r.city || "",
    applicant: r.applicant || null,
    serviceCategory: categoryLabel,
    serviceCategoryId: String(r.service_category_id || r.service_category?.id || ""),
    serviceDescription: r.service_description || "",
    status: normalizeStatus(r.status || "pending"),
    createdAt: String(r.created_at || "").slice(0, 10),
    details: isProvider
      ? `طلب انضمام كمقدم خدمة • النوع: ${categoryLabel || "غير محدد"} • الهاتف: ${r.phone || "غير محدد"} • المدينة: ${r.city || "غير محددة"} • البريد التجاري: ${r.email || "غير محدد"} • الوصف: ${r.service_description || "لا يوجد"}`
      : `طلب انضمام كمالك صالة • الهاتف: ${r.phone || "غير محدد"} • المدينة: ${r.city || "غير محددة"} • البريد التجاري: ${r.email || "غير محدد"}`
  };
};
const offerFromApi = (o = {}) => ({
  id: String(o.id ?? ""),
  title: o.title_ar || o.title_en || "عرض",
  target: o.scope || "كل الصالات",
  venueId: String(o.venue_id || ""),
  type: o.discount_type || "percentage",
  discount: Number(o.discount_value || 0),
  startsAt: o.start_date || "",
  endsAt: o.end_date || "",
  status: activeOfferStatus(o.status || "pending"),
  rawStatus: o.status || "pending",
  ownerId: String(o.owner_id || o.creator?.id || "")
});

const validDashboardRoles = [ROLES.ADMIN, ROLES.OWNER];
const emptyDashboardUser = { id: "", name: "", email: "", phone: "", role: "", avatarUrl: "", mustChangePassword: false };

const readStoredDashboardSession = () => {
  if (typeof window === "undefined") return { role: null, user: null };
  const storedRole = window.localStorage.getItem("salora_role");
  if (!validDashboardRoles.includes(storedRole)) return { role: null, user: null };

  try {
    const rawUser = window.localStorage.getItem("salora_user");
    return { role: storedRole, user: rawUser ? JSON.parse(rawUser) : null };
  } catch (_) {
    window.localStorage.removeItem("salora_role");
    window.localStorage.removeItem("salora_user");
    return { role: null, user: null };
  }
};

const persistDashboardSession = (role, user) => {
  if (typeof window === "undefined") return;
  window.localStorage.setItem("salora_role", role);
  window.localStorage.setItem("salora_user", JSON.stringify(user));
};

const clearDashboardSession = () => {
  if (typeof window === "undefined") return;
  window.localStorage.removeItem("salora_token");
  window.localStorage.removeItem("salora_role");
  window.localStorage.removeItem("salora_user");
};

const notificationFromApi = (notification = {}) => ({
  id: String(notification.id ?? ""),
  title: notification.title || "إشعار",
  message: notification.body || "",
  type: notification.type || "system",
  time: String(notification.created_at || "").slice(0, 16),
  read: Boolean(notification.is_read),
  data: notification.data_json || {}
});

const eventTypeFromApi = (eventType = {}) => {
  const todoItems = asArray(eventType.todo_templates).map((item) => ({
    id: String(item.id),
    title: item.task_ar || item.task_en || ""
  }));
  return {
    id: String(eventType.id ?? ""),
    name: eventType.name_ar || eventType.name_en || "مناسبة",
    nameEn: eventType.name_en || "",
    emoji: eventType.emoji || "🎯",
    status: eventType.is_active === false ? "Disabled" : "Active",
    todoItems,
    todo: todoItems.map((item) => item.title)
  };
};

export function AppProvider({ children }) {
  const storedSession = useMemo(() => readStoredDashboardSession(), []);
  const [currentRole, setCurrentRole] = useState(storedSession.role);
  const [currentUser, setCurrentUser] = useState(storedSession.user || emptyDashboardUser);
  const [userProfile, setUserProfile] = useState(storedSession.user || emptyDashboardUser);
  const [authLoading, setAuthLoading] = useState(true);
  const [dataLoading, setDataLoading] = useState(false);
  const [backendError, setBackendError] = useState("");
  const [refreshKey, setRefreshKey] = useState(0);
  const [reportData, setReportData] = useState(null);
  const [users, setUsers] = useState([]);
  const [venues, setVenues] = useState([]);
  const [bookings, setBookings] = useState([]);
  const [providers, setProviders] = useState([]);
  const [services, setServices] = useState([]);
  const [offers, setOffers] = useState([]);
  const [paymentProviders, setPaymentProviders] = useState([]);
  const [complaints, setComplaints] = useState([]);
  const [eventTypes, setEventTypes] = useState([]);
  const [serviceCategories, setServiceCategories] = useState([]);
  const [approvals, setApprovals] = useState([]);
  const [ownerRequests, setOwnerRequests] = useState([]);
  const [reviews, setReviews] = useState([]);
  const [activityLog, setActivityLog] = useState([]);
  const [adminNotifications, setAdminNotifications] = useState([]);
  const [ownerNotifications, setOwnerNotifications] = useState([]);
  const [globalViewVenue, setGlobalViewVenue] = useState(null);
  const [activeRuleId, setActiveRuleId] = useState("standard");

  const switchRole = (role, apiUser) => {
    if (!validDashboardRoles.includes(role) || !apiUser) {
      clearDashboardSession();
      setCurrentRole(null);
      setCurrentUser(emptyDashboardUser);
      setUserProfile(emptyDashboardUser);
      return false;
    }
    const nextUser = userFromApi(apiUser);
    setCurrentRole(role);
    setCurrentUser(nextUser);
    setUserProfile(nextUser);
    persistDashboardSession(role, nextUser);
    return true;
  };

  const resetClientData = () => {
    setUsers([]);
    setVenues([]);
    setBookings([]);
    setProviders([]);
    setServices([]);
    setOffers([]);
    setPaymentProviders([]);
    setComplaints([]);
    setEventTypes([]);
    setServiceCategories([]);
    setApprovals([]);
    setOwnerRequests([]);
    setReviews([]);
    setActivityLog([]);
    setAdminNotifications([]);
    setOwnerNotifications([]);
    setReportData(null);
  };

  const logout = async () => {
    try {
      if (localStorage.getItem("salora_token")) await dashboardApi.auth.logout();
    } catch (_) {
      // Local cleanup must still happen if the server is unavailable.
    } finally {
      clearDashboardSession();
      setCurrentRole(null);
      setCurrentUser(emptyDashboardUser);
      setUserProfile(emptyDashboardUser);
      setBackendError("");
      resetClientData();
    }
  };

  const refreshData = () => setRefreshKey((value) => value + 1);

  const updateCurrentProfile = async (payload) => {
    try {
      const saved = await dashboardApi.auth.profile(payload);
      const mapped = userFromApi(saved);
      setCurrentUser(mapped);
      setUserProfile(mapped);
      if (currentRole) persistDashboardSession(currentRole, mapped);
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر تحديث الملف الشخصي.");
    }
  };


  const updateCurrentAvatar = async (file) => {
    if (!file) return null;
    try {
      const saved = await dashboardApi.auth.uploadAvatar(file);
      const mapped = userFromApi(saved);
      setCurrentUser(mapped);
      setUserProfile(mapped);
      if (currentRole) persistDashboardSession(currentRole, mapped);
      setUsers((prev) => prev.map((user) => user.id === mapped.id ? { ...user, avatarUrl: mapped.avatarUrl } : user));
      setProviders((prev) => prev.map((user) => user.id === mapped.id ? { ...user, avatarUrl: mapped.avatarUrl } : user));
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر رفع الصورة الشخصية.");
    }
  };

  const refreshCurrentUser = async () => {
    try {
      const apiUser = await dashboardApi.auth.me();
      const role = apiUser?.role === "admin" ? ROLES.ADMIN : apiUser?.role === "owner" ? ROLES.OWNER : null;
      if (!role) return null;
      switchRole(role, apiUser);
      return userFromApi(apiUser);
    } catch (error) {
      return showMutationError(error, "تعذر تحديث بيانات الجلسة.");
    }
  };

  useEffect(() => {
    let cancelled = false;

    const handleInvalidAuth = () => {
      if (cancelled) return;
      clearDashboardSession();
      setCurrentRole(null);
      setCurrentUser(emptyDashboardUser);
      setUserProfile(emptyDashboardUser);
      resetClientData();
      setAuthLoading(false);
    };
    const handlePasswordChangeRequired = () => {
      if (cancelled) return;
      setCurrentUser((previous) => {
        const next = { ...previous, mustChangePassword: true };
        if (currentRole) persistDashboardSession(currentRole, next);
        return next;
      });
      setUserProfile((previous) => ({ ...previous, mustChangePassword: true }));
    };

    window.addEventListener("salora:auth-invalid", handleInvalidAuth);
    window.addEventListener("salora:password-change-required", handlePasswordChangeRequired);

    async function bootstrapSession() {
      const token = localStorage.getItem("salora_token");
      if (!token) {
        handleInvalidAuth();
        return;
      }

      try {
        const apiUser = await dashboardApi.auth.me();
        if (cancelled) return;
        const role = apiUser?.role === "admin" ? ROLES.ADMIN : apiUser?.role === "owner" ? ROLES.OWNER : null;
        if (!role) {
          handleInvalidAuth();
          return;
        }
        switchRole(role, apiUser);
      } catch (_) {
        if (!cancelled) handleInvalidAuth();
      } finally {
        if (!cancelled) setAuthLoading(false);
      }
    }

    bootstrapSession();
    return () => {
      cancelled = true;
      window.removeEventListener("salora:auth-invalid", handleInvalidAuth);
      window.removeEventListener("salora:password-change-required", handlePasswordChangeRequired);
    };
  }, [currentRole]);

  useEffect(() => {
    if (authLoading || !currentRole || !currentUser.id || currentUser.mustChangePassword || !localStorage.getItem("salora_token")) return;
    let cancelled = false;

    const errors = [];
    const load = async (label, operation, apply) => {
      try {
        const value = await operation();
        if (!cancelled) apply(value);
      } catch (error) {
        errors.push(`${label}: ${error.message}`);
      }
    };

    async function loadBackendData() {
      setDataLoading(true);
      setBackendError("");
      resetClientData();

      const notificationSetter = currentRole === ROLES.ADMIN ? setAdminNotifications : setOwnerNotifications;
      const commonLoads = [
        load("الصالات", currentRole === ROLES.ADMIN ? dashboardApi.admin.venues : dashboardApi.owner.venues, (value) => setVenues(asArray(value).map(venueFromApi))),
        load("الحجوزات", currentRole === ROLES.ADMIN ? dashboardApi.admin.bookings : dashboardApi.owner.bookings, (value) => setBookings(asArray(value).map(bookingFromApi))),
        load("الخدمات", currentRole === ROLES.ADMIN ? dashboardApi.admin.services : dashboardApi.owner.services, (value) => setServices(asArray(value).map(serviceFromApi))),
        load("العروض", currentRole === ROLES.ADMIN ? dashboardApi.admin.offers : dashboardApi.owner.offers, (value) => setOffers(asArray(value).map(offerFromApi))),
        load("التقييمات", currentRole === ROLES.ADMIN ? dashboardApi.admin.reviews : dashboardApi.owner.reviews, (value) => setReviews(asArray(value).map(reviewFromApi))),
        load("الشكاوى", currentRole === ROLES.ADMIN ? dashboardApi.admin.complaints : dashboardApi.owner.complaints, (value) => setComplaints(asArray(value).map(complaintFromApi))),
        load("الإشعارات", dashboardApi.notifications.list, (value) => notificationSetter(asArray(value).map(notificationFromApi))),
        load("التقارير", currentRole === ROLES.ADMIN ? dashboardApi.admin.reports : dashboardApi.owner.reports, setReportData),
        load(
          "أنواع المناسبات",
          currentRole === ROLES.ADMIN ? dashboardApi.admin.eventTypes : dashboardApi.public.eventTypes,
          (value) => setEventTypes(asArray(value).map(eventTypeFromApi))
        ),
        load(
          "تصنيفات مقدمي الخدمات",
          () => dashboardApi.public.serviceCategories({ for: "provider" }),
          (value) => setServiceCategories(asArray(value).map(serviceCategoryFromApi))
        )
      ];

      if (currentRole === ROLES.ADMIN) {
        commonLoads.push(
          load("المستخدمون", dashboardApi.admin.users, (value) => {
            const mappedUsers = asArray(value).map(userFromApi);
            setUsers(mappedUsers);
            setProviders(mappedUsers.filter((user) => user.role === ROLES.PROVIDER));
          }),
          load("طلبات الانضمام", dashboardApi.admin.ownerRequests, (value) => setOwnerRequests(asArray(value).map(ownerRequestFromApi))),
          load("سجل النشاط", dashboardApi.admin.activity, (value) => setActivityLog(asArray(value).map((item) => ({
            id: String(item.id),
            actor: item.user?.name || item.role || "System",
            action: item.action,
            target: `${item.target_type || ""} ${item.target_id || ""}`.trim(),
            time: String(item.created_at || "").slice(0, 16),
            type: item.target_type || "system"
          }))))
        );
      }

      await Promise.all(commonLoads);
      if (!cancelled) {
        setBackendError(errors.length ? `تعذر تحميل بعض البيانات: ${errors.join(" | ")}` : "");
        setDataLoading(false);
      }
    }

    loadBackendData();
    return () => { cancelled = true; };
  }, [authLoading, currentRole, currentUser.id, currentUser.mustChangePassword, refreshKey]);

  const refreshNotifications = async ({ silent = true } = {}) => {
    if (!currentRole || !localStorage.getItem("salora_token")) return [];
    try {
      const items = asArray(await dashboardApi.notifications.list()).map(notificationFromApi);
      if (currentRole === ROLES.ADMIN) setAdminNotifications(items);
      else setOwnerNotifications(items);
      return items;
    } catch (error) {
      if (!silent) setBackendError(`تعذر تحديث الإشعارات: ${error.message}`);
      return [];
    }
  };

  useEffect(() => {
    if (authLoading || !currentRole || currentUser.mustChangePassword || !localStorage.getItem("salora_token")) return undefined;
    const timer = window.setInterval(() => refreshNotifications({ silent: true }), 15000);
    const onFocus = () => refreshNotifications({ silent: true });
    window.addEventListener("focus", onFocus);
    return () => {
      window.clearInterval(timer);
      window.removeEventListener("focus", onFocus);
    };
  }, [authLoading, currentRole, currentUser.mustChangePassword]);

  const logAction = (action, target, type = "system", actor = currentRole) => {
    setActivityLog((prev) => [{ id: `L${Date.now()}`, actor, action, target, type, time: `${today()} ${nowTime()}` }, ...prev]);
  };

  // Pricing is authoritative on the backend. The dashboard must not invent
  // seasonal multipliers that are not persisted or audited.
  const dynamicPricingRules = [
    { id: "standard", multiplier: 1, label: "السعر الأساسي المحفوظ في الخادم" }
  ];

  const getAdjustedPrice = (price = 0) => Number(price || 0);

  const ownerVenues = useMemo(() => venues.filter((v) => v.ownerId === currentUser.id), [venues, currentUser.id]);
  const ownerBookings = useMemo(() => bookings.filter((b) => b.ownerId === currentUser.id), [bookings, currentUser.id]);
  const ownerServices = useMemo(() => services.filter((s) => s.ownerId === currentUser.id || (s.venueOwners || []).includes(currentUser.id)), [services, currentUser.id]);
  const providerServices = useMemo(() => services.filter((s) => s.providerId === currentUser.id || s.ownerId === currentUser.id), [services, currentUser.id]);
  const ownerOffers = useMemo(() => offers.filter((o) => o.ownerId === currentUser.id), [offers, currentUser.id]);
  const ownerReviews = useMemo(() => reviews.filter((r) => r.ownerId === currentUser.id), [reviews, currentUser.id]);
  const ownerComplaints = useMemo(() => complaints.filter((c) => c.ownerId === currentUser.id), [complaints, currentUser.id]);

  const derivedApprovals = useMemo(() => {
    const venueRequests = venues.filter((v) => v.status === "Pending").map((v) => ({ id: `venue-${v.id}`, type: "Venue Add", venueId: v.id, title: v.name, requester: v.owner, status: "Pending", createdAt: v.createdAt, details: `طلب إضافة صالة: ${v.name} • السعة ${v.capacity} ضيف • السعر ${formatPricePair(v.basePrice)}` }));
    const serviceRequests = services.filter((s) => s.status === "Pending").map((s) => ({
      id: `service-${s.id}`,
      type: "Service Add",
      serviceId: s.id,
      title: s.name,
      requester: s.provider || "مقدم خدمة",
      status: "Pending",
      createdAt: today(),
      details: [
        `التصنيف: ${s.category || "غير محدد"}`,
        `السعر: ${s.price === 0 && s.priceSyp === 0 ? "مجانية" : formatPricePair(s.price, "", s.priceSyp)}`,
        `سياسة التسعير: ${{ per_event: "للمناسبة", per_hour: "لكل ساعة", per_person: "لكل شخص", package: "للباقة" }[s.pricingUnit] || s.pricingUnit}`,
        s.durationMinutes ? `المدة: ${s.durationMinutes} دقيقة` : null,
        (s.availableFor || []).length ? `المناسبات: ${s.availableFor.join("، ")}` : null,
        s.description ? `الوصف: ${s.description}` : null
      ].filter(Boolean).join(" • ")
    }));
    // Offers publish immediately and payment decisions belong to the hall owner, so neither belongs in the admin approval queue.
    return [...ownerRequests.filter((r) => r.status === "Pending"), ...venueRequests, ...serviceRequests, ...approvals.filter((a) => a.status === "Pending")];
  }, [venues, services, offers, bookings, approvals, ownerRequests, currentRole, currentUser.id]);

  const metrics = useMemo(() => {
    const totalRevenue = bookings.filter((b) => ![BOOKING_STATUS.REJECTED, BOOKING_STATUS.CANCELLED].includes(b.status)).reduce((sum, b) => sum + Number(b.amount || b.invoiceTotal || 0), 0);
    return {
      totalVenues: venues.length,
      activeVenues: venues.filter((v) => v.status === "Approved").length,
      pendingVenueApprovals: venues.filter((v) => v.status === "Pending").length,
      totalBookings: bookings.length,
      pendingBookings: bookings.filter((b) => [BOOKING_STATUS.PENDING_OWNER_REVIEW, "Pending Payment", BOOKING_STATUS.OWNER_APPROVED].includes(b.status)).length,
      totalUsers: users.length,
      totalProviders: providers.length,
      verifiedPayments: bookings.filter((b) => b.paymentStatus === PAYMENT_STATUS.VERIFIED).length,
      pendingPayments: bookings.filter((b) => b.paymentStatus === PAYMENT_STATUS.PENDING_ADMIN_VERIFICATION).length,
      totalRevenue,
      platformFee: Number(reportData?.platform_fee_syp || 0),
      pendingApprovals: derivedApprovals.length,
      openComplaints: complaints.filter((c) => ["Open", "In Progress"].includes(c.status)).length,
      activeOffers: offers.filter((o) => o.status === "Active").length,
      activeServices: services.filter((s) => s.status === "Approved").length,
      ownerVenues: ownerVenues.length,
      ownerBookings: ownerBookings.length,
      ownerRevenue: ownerBookings.reduce((sum, b) => sum + Number(b.amount || b.invoiceTotal || 0), 0)
    };
  }, [venues, bookings, users, providers, derivedApprovals.length, complaints, offers, services, ownerVenues.length, ownerBookings, reportData]);

  const pushAdminNotification = (notification) => setAdminNotifications((prev) => [{ id: `N${Date.now()}`, time: nowTime(), read: false, ...notification }, ...prev]);
  const pushOwnerNotification = (notification) => setOwnerNotifications((prev) => [{ id: `ON${Date.now()}`, time: nowTime(), read: false, ...notification }, ...prev]);

  const showMutationError = (error, fallbackMessage) => {
    const message = error?.message || fallbackMessage;
    setBackendError(message);
    window.alert(message);
    return null;
  };

  const eventTypeIdsFor = (names = []) => {
    const normalized = new Set(names.map((name) => String(name || "").trim().toLocaleLowerCase()).filter(Boolean));
    return eventTypes
      .filter((item) => normalized.has(String(item.name || "").trim().toLocaleLowerCase())
        || normalized.has(String(item.nameEn || "").trim().toLocaleLowerCase()))
      .map((item) => Number(item.id))
      .filter(Number.isFinite);
  };

  const addVenue = async (payload) => {
    try {
      const priceUsd = Number(payload.basePrice || payload.price || payload.hallPrice || 0);
      const priceSyp = Number(payload.priceSyp || payload.price_syp || 0);
      const supported = payload.supportedEventTypes || [];
      const eventTypeIds = eventTypeIdsFor(supported);
      const remoteImageUrls = (payload.imageUrls || [])
        .filter((url) => url && !String(url).startsWith("blob:") && !String(url).startsWith("data:"));
      const remoteVideoUrls = (payload.videoUrls || [])
        .filter((url) => url && !String(url).startsWith("blob:") && !String(url).startsWith("data:"));
      const apiPayload = {
        name_en: payload.name || payload.venueName,
        name_ar: payload.name || payload.venueName,
        description_en: payload.description || "",
        description_ar: payload.description || "",
        city: payload.city || payload.location,
        address: payload.address,
        map_url: payload.mapUrl || null,
        google_place_id: payload.googlePlaceId || null,
        latitude: Number(payload.latitude),
        longitude: Number(payload.longitude),
        opening_hours: payload.openingHours || {},
        capacity: Number(payload.capacity),
        ...(priceUsd > 0 ? { price_usd: priceUsd } : {}),
        ...(priceSyp > 0 ? { price_syp: priceSyp } : {}),
        ...(eventTypeIds.length ? { event_type_ids: eventTypeIds } : {}),
        event_types: supported,
        included_services: payload.includedServices || payload.services || [],
        paid_upgrades: payload.paidUpgrades || [],
        amenities: payload.amenities || [],
        policies: payload.policies || [],
        vendor_categories: payload.vendorCategories || [],
        ...(remoteImageUrls.length ? { image_urls: remoteImageUrls } : {}),
        ...(remoteVideoUrls.length ? { video_urls: remoteVideoUrls } : {})
      };
      const created = await dashboardApi.owner.createVenue(apiPayload);
      let mapped = venueFromApi(created);
      setVenues((prev) => [mapped, ...prev.filter((venue) => venue.id !== mapped.id)]);

      const imageFiles = Array.isArray(payload.imageFiles)
        ? payload.imageFiles
        : payload.imageFile ? [payload.imageFile] : [];
      let imageUploadFailed = false;
      if (imageFiles.length) {
        try {
          const formData = new FormData();
          imageFiles.forEach((file) => formData.append("images[]", file));
          formData.append("is_main", "1");
          const uploaded = await dashboardApi.owner.uploadVenueImage(mapped.id, formData);
          const uploadedUrls = asArray(uploaded?.images)
            .map((image) => assetUrl(image?.image_url || image?.url || ""))
            .filter(Boolean);
          const fallbackUrl = assetUrl(uploaded?.image_url || "");
          if (!uploadedUrls.length && fallbackUrl) uploadedUrls.push(fallbackUrl);

          if (uploadedUrls.length) {
            mapped = {
              ...mapped,
              imageUrls: Array.from(new Set([...uploadedUrls, ...(mapped.imageUrls || [])])),
              images: uploadedUrls.length + Number(mapped.images || 0)
            };
            setVenues((prev) => prev.map((venue) => venue.id === mapped.id ? mapped : venue));
          }
        } catch (uploadError) {
          imageUploadFailed = true;
          setBackendError(`تم إنشاء الصالة، لكن تعذر رفع الصور: ${uploadError.message}`);
        }
      }

      const videoFiles = Array.isArray(payload.videoFiles)
        ? payload.videoFiles
        : payload.videoFile ? [payload.videoFile] : [];
      let videoUploadFailed = false;
      if (videoFiles.length) {
        try {
          const formData = new FormData();
          videoFiles.forEach((file) => formData.append("videos[]", file));
          const uploaded = await dashboardApi.owner.uploadVenueVideo(mapped.id, formData);
          const rawVideoUrls = asArray(uploaded?.final_video_urls).length
            ? asArray(uploaded.final_video_urls)
            : asArray(uploaded?.videos).map((video) => video.video_url || video.url).filter(Boolean);
          if (rawVideoUrls.length) {
            mapped = {
              ...mapped,
              videoUrls: rawVideoUrls.map(assetUrl),
              rawVideoUrls,
              videos: rawVideoUrls.length
            };
            setVenues((prev) => prev.map((venue) => venue.id === mapped.id ? mapped : venue));
          }
        } catch (uploadError) {
          videoUploadFailed = true;
          setBackendError(`تم إنشاء الصالة، لكن تعذر رفع الفيديوهات: ${uploadError.message}`);
        }
      }

      logAction("Submitted venue", mapped.name, "venue", "Owner");
      return { ...mapped, imageUploadFailed, videoUploadFailed };
    } catch (error) {
      return showMutationError(error, "تعذر إرسال الصالة إلى الخادم.");
    }
  };

  const updateVenue = async (id, updatedData) => {
    try {
      const priceUsd = Number(updatedData.basePrice || updatedData.price_usd || 0);
      const priceSyp = Number(updatedData.priceSyp || updatedData.price_syp || 0);
      const imageFiles = Array.isArray(updatedData.imageFiles) ? updatedData.imageFiles : [];
      const videoFiles = Array.isArray(updatedData.videoFiles) ? updatedData.videoFiles : [];
      let uploadedImageUrls = [];
      let uploadedVideoUrls = [];

      if (imageFiles.length) {
        const formData = new FormData();
        imageFiles.forEach((file) => formData.append("images[]", file));
        const uploaded = await dashboardApi.owner.uploadVenueImage(id, formData);
        uploadedImageUrls = asArray(uploaded?.uploaded_urls).filter(Boolean);
      }

      if (videoFiles.length) {
        const formData = new FormData();
        videoFiles.forEach((file) => formData.append("videos[]", file));
        const uploaded = await dashboardApi.owner.uploadVenueVideo(id, formData);
        uploadedVideoUrls = asArray(uploaded?.uploaded_urls).filter(Boolean);
      }

      const finalImageUrls = Array.isArray(updatedData.imageOrder)
        ? updatedData.imageOrder.map((entry) => entry?.kind === "new" ? uploadedImageUrls[Number(entry.index)] : entry?.url).filter(Boolean)
        : [...(updatedData.retainedRawImageUrls || []), ...uploadedImageUrls];
      const finalVideoUrls = Array.isArray(updatedData.videoOrder)
        ? updatedData.videoOrder.map((entry) => entry?.kind === "new" ? uploadedVideoUrls[Number(entry.index)] : entry?.url).filter(Boolean)
        : [...(updatedData.retainedRawVideoUrls || []), ...uploadedVideoUrls];

      if (!finalImageUrls.length) throw new Error("يجب إبقاء صورة واحدة على الأقل للصالة.");
      if (finalImageUrls.length > 10) throw new Error("يمكن حفظ 10 صور كحد أقصى.");
      if (finalVideoUrls.length > 3) throw new Error("يمكن حفظ 3 فيديوهات كحد أقصى.");

      const apiPayload = {
        name_en: updatedData.name,
        name_ar: updatedData.name,
        description_en: updatedData.description,
        description_ar: updatedData.description,
        city: updatedData.city,
        address: updatedData.address,
        map_url: updatedData.mapUrl || null,
        google_place_id: updatedData.googlePlaceId || null,
        ...(updatedData.latitude !== null && updatedData.latitude !== undefined ? { latitude: Number(updatedData.latitude) } : {}),
        ...(updatedData.longitude !== null && updatedData.longitude !== undefined ? { longitude: Number(updatedData.longitude) } : {}),
        opening_hours: updatedData.openingHours || {},
        capacity: Number(updatedData.capacity),
        ...(priceUsd > 0 ? { price_usd: priceUsd } : {}),
        ...(priceSyp > 0 ? { price_syp: priceSyp } : {}),
        amenities: updatedData.amenities || [],
        policies: updatedData.policies || [],
        vendor_categories: updatedData.vendorCategories || [],
        ...(eventTypeIdsFor(updatedData.supportedEventTypes || []).length
          ? { event_type_ids: eventTypeIdsFor(updatedData.supportedEventTypes || []) }
          : {}),
        event_types: updatedData.supportedEventTypes || [],
        included_services: updatedData.includedServices || [],
        paid_upgrades: updatedData.paidUpgrades || [],
        replace_images: true,
        image_urls: finalImageUrls,
        replace_videos: true,
        video_urls: finalVideoUrls,
      };

      const saved = await dashboardApi.owner.updateVenue(id, apiPayload);
      const publishedVenue = saved?.venue || saved;
      const mapped = venueFromApi(publishedVenue);
      setVenues((prev) => prev.map((venue) => venue.id === String(id) ? mapped : venue));
      setRefreshKey((key) => key + 1);
      logAction("Submitted venue update", id, "venue", "Owner");
      return { ...saved, finalImageUrls, finalVideoUrls };
    } catch (error) {
      return showMutationError(error, "تعذر حفظ تعديلات الصالة.");
    }
  };

  const deleteVenue = async (id) => {
    try {
      const saved = await dashboardApi.owner.disableVenue(id);
      setVenues((prev) => prev.map((venue) => venue.id === String(id) ? { ...venue, status: normalizeStatus(saved?.status || "disabled") } : venue));
      logAction("Disabled venue", id, "venue", "Owner");
      return saved;
    } catch (error) {
      return showMutationError(error, "تعذر تعطيل الصالة.");
    }
  };

  const adminActionVenue = async (id, status) => {
    try {
      let saved;
      if (status === "Approved") {
        saved = await dashboardApi.admin.approveVenue(id);
      } else if (status === "Rejected") {
        const reason = window.prompt("اكتب سبب رفض الصالة:");
        if (!reason?.trim()) return null;
        saved = await dashboardApi.admin.rejectVenue(id, reason.trim());
      } else if (status === "delete" || status === "Disabled") {
        saved = await dashboardApi.admin.disableVenue(id);
      } else {
        return null;
      }
      const mapped = venueFromApi(saved);
      setVenues((prev) => prev.map((venue) => venue.id === String(id) ? mapped : venue));
      logAction(`${status} venue`, mapped.name || id, "venue", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر تنفيذ الإجراء على الصالة.");
    }
  };

  const adminActionBooking = async (id, status) => {
    if (!["delete", BOOKING_STATUS.CANCELLED].includes(status)) {
      window.alert("في النسخة الجامعية الأساسية، الأدمن يراقب الحجز ولا يغير حالته إلا بالإلغاء الإداري.");
      return null;
    }
    const reason = window.prompt("اكتب سبب الإلغاء الإداري:");
    if (!reason?.trim()) return null;
    try {
      const saved = await dashboardApi.admin.cancelBooking(id, reason.trim());
      const mapped = bookingFromApi(saved);
      setBookings((prev) => prev.map((booking) => booking.id === String(id) ? mapped : booking));
      logAction("Admin cancelled booking", id, "booking", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر إلغاء الحجز.");
    }
  };

  const applyBookingFromApi = (apiBooking, fallbackId) => {
    if (!apiBooking) return null;
    const mapped = bookingFromApi(apiBooking);
    setBookings((prev) => prev.map((booking) => booking.id === String(fallbackId) ? mapped : booking));
    return mapped;
  };

  const ownerApproveBooking = async () => {
    window.alert("تم إلغاء الموافقة المبدئية. القرار النهائي يظهر بعد رفع إثبات الدفع من صفحة مراجعة المدفوعات.");
    return null;
  };

  const ownerRejectBooking = async () => {
    window.alert("تم إلغاء الرفض المبدئي. يستطيع مالك الصالة قبول الحجز أو رفضه نهائياً بعد مراجعة إثبات الدفع.");
    return null;
  };

  const completeBooking = async (id) => {
    try {
      const saved = await dashboardApi.owner.completeBooking(id);
      const mapped = applyBookingFromApi(saved, id);
      logAction("Owner completed booking", id, "booking", "Owner");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر تحديد الحجز كمنجز.");
    }
  };

  const updatePaymentStatus = async (id, paymentStatus, rejectionReason = "") => {
    if (currentRole !== ROLES.OWNER) {
      window.alert("قبول أو رفض إثبات الدفع من صلاحيات مالك الصالة المرتبط بالحجز.");
      return null;
    }
    const target = bookings.find((booking) => booking.id === String(id));
    const paymentId = target?.paymentProofId;
    if (!paymentId) {
      window.alert("لا يوجد معرّف إثبات دفع مرتبط بهذا الحجز.");
      return null;
    }
    try {
      const saved = paymentStatus === PAYMENT_STATUS.VERIFIED
        ? await dashboardApi.owner.approvePayment(paymentId)
        : await dashboardApi.owner.rejectPayment(
            paymentId,
            rejectionReason.trim() || window.prompt("سبب رفض إثبات الدفع:")?.trim() || "إثبات غير صالح"
          );
      const apiBooking = saved?.booking || saved?.payment?.booking || saved;
      if (apiBooking?.id) applyBookingFromApi(apiBooking, id);
      else refreshData();
      logAction(`Owner payment ${paymentStatus}`, id, "payment", "Owner");
      return saved;
    } catch (error) {
      return showMutationError(error, "تعذر تحديث حالة الدفع.");
    }
  };

  const addCalendarBooking = () => {
    window.alert("إنشاء الحجز يخص العميل من تطبيق الموبايل، ولا يتم إنشاء حجوزات وهمية من التقويم الإداري.");
    return null;
  };
  const updateCalendarBooking = () => {
    window.alert("تعديل الحجز يتم من خلال طلب تعديل رسمي يراجعه مالك الصالة.");
    return null;
  };
  const deleteCalendarBooking = async (id) => adminActionBooking(id, BOOKING_STATUS.CANCELLED);

  const createUserAccount = async (payload) => {
    const password = payload.password || "";
    try {
      const saved = await dashboardApi.admin.createUser({
        name: payload.name,
        email: payload.email,
        phone: payload.phone || null,
        role: String(payload.role || "customer").toLowerCase(),
        password,
        password_confirmation: payload.password_confirmation || password,
        status: "active"
      });
      const mapped = userFromApi(saved);
      setUsers((prev) => [mapped, ...prev.filter((user) => user.email !== mapped.email)]);
      if (mapped.role === ROLES.PROVIDER) setProviders((prev) => [mapped, ...prev.filter((user) => user.email !== mapped.email)]);
      logAction("Created account", mapped.email, "account", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر إنشاء الحساب.");
    }
  };


  const updateUserAccount = async (id, payload) => {
    try {
      const body = {
        name: payload.name,
        email: payload.email,
        phone: payload.phone,
        role: String(payload.role || "customer").toLowerCase(),
        ...(payload.password ? {
          password: payload.password,
          password_confirmation: payload.password_confirmation || payload.password
        } : {})
      };
      const mapped = userFromApi(await dashboardApi.admin.updateUser(id, body));
      setUsers((prev) => prev.map((user) => user.id === mapped.id ? mapped : user));
      setProviders((prev) => {
        const without = prev.filter((user) => user.id !== mapped.id);
        return mapped.role === ROLES.PROVIDER ? [mapped, ...without] : without;
      });
      logAction("Updated account", mapped.email, "account", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر تعديل بيانات الحساب.");
    }
  };

  const applyMappedUser = (saved) => {
    const mapped = userFromApi(saved);
    setUsers((prev) => prev.map((user) => user.id === mapped.id ? mapped : user));
    setProviders((prev) => prev.map((user) => user.id === mapped.id ? mapped : user));
    return mapped;
  };

  const activateUser = async (id) => {
    try {
      const mapped = applyMappedUser(await dashboardApi.admin.activateUser(id));
      logAction("Activated user", id, "account", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر تنشيط الحساب.");
    }
  };

  const deactivateUser = async (id, reason = "") => {
    try {
      const mapped = applyMappedUser(await dashboardApi.admin.deactivateUser(id, reason || null));
      logAction("Deactivated user", id, "account", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر تعطيل الحساب.");
    }
  };

  const suspendUser = async (id, suspendedUntil, reason) => {
    try {
      const mapped = applyMappedUser(await dashboardApi.admin.suspendUser(id, {
        suspended_until: suspendedUntil,
        reason
      }));
      logAction("Suspended user", id, "account", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر تجميد الحساب مؤقتاً.");
    }
  };

  const getUserDeletionImpact = async (id) => {
    try {
      return await dashboardApi.admin.userDeletionImpact(id);
    } catch (error) {
      return showMutationError(error, "تعذر فحص ارتباطات الحساب قبل الحذف.");
    }
  };

  const deleteUser = async (id) => {
    try {
      await dashboardApi.admin.deleteUser(id);
      setUsers((prev) => prev.filter((user) => user.id !== String(id)));
      setProviders((prev) => prev.filter((user) => user.id !== String(id)));
      logAction("Soft deleted user", id, "account", "Admin");
      return true;
    } catch (error) {
      const impact = error?.errors?.impact;
      if (impact?.blockers?.length) {
        setBackendError(`لا يمكن حذف الحساب قبل معالجة: ${impact.blockers.join("، ")}`);
      }
      return showMutationError(error, "تعذر حذف الحساب. يجب معالجة الارتباطات النشطة أولاً.");
    }
  };

  const restoreUser = async (id) => {
    try {
      const saved = await dashboardApi.admin.restoreUser(id);
      const mapped = userFromApi(saved);
      setUsers((prev) => [mapped, ...prev.filter((user) => user.id !== mapped.id)]);
      if (mapped.role === ROLES.PROVIDER) setProviders((prev) => [mapped, ...prev.filter((user) => user.id !== mapped.id)]);
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر استعادة الحساب.");
    }
  };

  const updateUserStatus = async (id, status) => ["Active", "فعال", "active"].includes(status)
    ? activateUser(id)
    : deactivateUser(id);
  const updateProviderStatus = async (id, status) => updateUserStatus(id, status);

  const resolveApproval = async (id, status, options = {}) => {
    const target = derivedApprovals.find((approval) => approval.id === id);
    if (!target) return null;
    try {
      if (["Owner Request", "Provider Request"].includes(target.type)) {
        if (status === "Approved") {
          const password = options.temporary_password || options.password || "";
          const saved = await dashboardApi.admin.approveOwnerRequest(target.requestId, {
            temporary_password: password,
            temporary_password_confirmation: options.temporary_password_confirmation || password
          });
          const account = saved?.account || saved?.owner;
          if (account) setUsers((prev) => [userFromApi(account), ...prev.filter((user) => user.email !== account.email)]);
          refreshData();
          logAction(`${status} approval`, target.title || id, "approval", "Admin");
          return saved;
        } else {
          const reason = options.reason || window.prompt("سبب رفض طلب الانضمام:");
          if (!reason?.trim()) return null;
          await dashboardApi.admin.rejectOwnerRequest(target.requestId, reason.trim());
        }
      } else if (target.type === "Venue Add") {
        return await adminActionVenue(target.venueId, status);
      } else if (target.type === "Service Add") {
        return await updateServiceStatus(target.serviceId, status);
      } else if (target.type === "Offer") {
        return await updateOfferStatus(target.offerId, status);
      } else if (target.type === "Payment Proof") {
        return await updatePaymentStatus(target.bookingId, status === "Approved" ? PAYMENT_STATUS.VERIFIED : PAYMENT_STATUS.REJECTED_PROOF);
      }
      refreshData();
      logAction(`${status} approval`, target.title || id, "approval", "Admin");
      return true;
    } catch (error) {
      return showMutationError(error, "تعذر مراجعة الطلب.");
    }
  };

  const updateServiceStatus = async (id, status) => {
    try {
      const saved = status === "Approved"
        ? await dashboardApi.admin.approveService(id)
        : await dashboardApi.admin.rejectService(id, window.prompt("سبب رفض الخدمة:") || "الخدمة لا تحقق المتطلبات");
      const mapped = serviceFromApi(saved);
      setServices((prev) => prev.map((service) => service.id === String(id) ? mapped : service));
      logAction(`${status} service`, id, "service", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر تحديث حالة الخدمة.");
    }
  };

  const addOffer = async (payload) => {
    try {
      const apiPayload = {
        scope: currentRole === ROLES.OWNER
          ? (payload.venueId ? "specific_venue" : "owner_venues")
          : (payload.target === "specific_venue" ? "specific_venue" : "all_venues"),
        venue_id: payload.venueId || null,
        title_en: payload.title || "Offer",
        title_ar: payload.title || "عرض",
        description_ar: payload.description || null,
        description_en: payload.description || null,
        discount_type: payload.type || "percentage",
        discount_value: Number(payload.discount || 0),
        discount_currency: (payload.type || "percentage") === "fixed" ? (payload.currency || "SYP") : null,
        start_date: payload.startsAt || today(),
        end_date: payload.endsAt || today()
      };
      const saved = currentRole === ROLES.OWNER
        ? await dashboardApi.owner.createOffer(apiPayload)
        : await dashboardApi.admin.createOffer(apiPayload);
      const mapped = offerFromApi(saved);
      setOffers((prev) => [mapped, ...prev.filter((offer) => offer.id !== mapped.id)]);
      logAction("Added offer", mapped.title, "offer", currentRole);
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر حفظ العرض.");
    }
  };

  const updateOfferStatus = async (id, status) => {
    if (currentRole !== ROLES.ADMIN) {
      window.alert("عروض المالك تُنشر مباشرة. يستطيع الأدمن مراقبتها أو حذف المخالف فقط.");
      return null;
    }
    try {
      const saved = ["Active", "Approved"].includes(status)
        ? await dashboardApi.admin.approveOffer(id)
        : await dashboardApi.admin.rejectOffer(id, window.prompt("سبب رفض العرض:") || "العرض غير مناسب");
      const mapped = offerFromApi(saved);
      setOffers((prev) => prev.map((offer) => offer.id === String(id) ? mapped : offer));
      logAction(`${status} offer`, id, "offer", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر تحديث حالة العرض.");
    }
  };

  const updatePaymentProvider = () => {
    window.alert("إدارة بوابات الدفع الحقيقية مؤجلة. النسخة الجامعية تستخدم التحويل اليدوي وإثبات الدفع فقط.");
    return null;
  };

  const updateComplaint = async (id, patch) => {
    try {
      let saved;
      if (patch.reply) {
        saved = currentRole === ROLES.ADMIN
          ? await dashboardApi.admin.replyComplaint(id, patch.reply)
          : await dashboardApi.owner.replyComplaint(id, patch.reply);
      } else if (currentRole === ROLES.ADMIN && ["Closed", "closed"].includes(patch.status)) {
        saved = await dashboardApi.admin.closeComplaint(id);
      } else {
        window.alert("غيّر حالة الشكوى من خلال الرد عليها أو إغلاقها.");
        return null;
      }
      const mapped = complaintFromApi(saved);
      setComplaints((prev) => prev.map((complaint) => complaint.id === String(id) ? mapped : complaint));
      logAction("Updated complaint", id, "support", currentRole);
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر تحديث الشكوى.");
    }
  };

  const moderateReview = async (id, status) => {
    try {
      let saved;
      if (["Hidden", "hidden"].includes(status)) saved = await dashboardApi.admin.hideReview(id);
      else if (["Visible", "visible", "Restored"].includes(status)) saved = await dashboardApi.admin.restoreReview(id);
      else if (["Deleted", "deleted"].includes(status)) {
        await dashboardApi.admin.deleteReview(id);
        setReviews((prev) => prev.filter((review) => review.id !== String(id)));
        return true;
      }
      const mapped = reviewFromApi(saved);
      setReviews((prev) => prev.map((review) => review.id === String(id) ? mapped : review));
      logAction(`Review ${status}`, id, "review", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر الإشراف على التقييم.");
    }
  };

  const replyToReview = async (id, ownerReply) => {
    try {
      const saved = await dashboardApi.owner.replyReview(id, ownerReply);
      const mapped = reviewFromApi(saved);
      setReviews((prev) => prev.map((review) => review.id === String(id) ? mapped : review));
      logAction("Owner replied to review", id, "review", "Owner");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر حفظ الرد على التقييم.");
    }
  };

  const addEventType = async (name) => {
    if (!name.trim()) return null;
    try {
      const saved = await dashboardApi.admin.createEventType({ name_en: name.trim(), name_ar: name.trim(), emoji: "🎯" });
      const mapped = { id: String(saved.id), name: saved.name_ar || saved.name_en, nameEn: saved.name_en || "", status: saved.is_active ? "Active" : "Disabled", todoItems: [], todo: [] };
      setEventTypes((prev) => [mapped, ...prev]);
      logAction("Added event type", mapped.name, "event_type", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر إضافة نوع المناسبة.");
    }
  };

  const updateEventType = async (id, patch) => {
    try {
      const saved = await dashboardApi.admin.updateEventType(id, {
        name_ar: patch.name || patch.name_ar,
        name_en: patch.nameEn || patch.name_en || patch.name,
        is_active: patch.status ? patch.status === "Active" : undefined
      });
      setEventTypes((prev) => prev.map((eventType) => eventType.id === String(id)
        ? { ...eventType, name: saved.name_ar || saved.name_en, nameEn: saved.name_en || "", status: saved.is_active ? "Active" : "Disabled" }
        : eventType));
      logAction("Updated event type", id, "event_type", "Admin");
      return saved;
    } catch (error) {
      return showMutationError(error, "تعذر تعديل نوع المناسبة.");
    }
  };

  const deleteEventType = async (id) => {
    try {
      const result = await dashboardApi.admin.deleteEventType(id);
      if (result?.is_active === false) {
        setEventTypes((prev) => prev.map((item) => item.id === String(id) ? { ...item, status: "Disabled" } : item));
      } else {
        setEventTypes((prev) => prev.filter((item) => item.id !== String(id)));
      }
      logAction("Deleted event type", id, "event_type", "Admin");
      return result;
    } catch (error) {
      return showMutationError(error, "تعذر حذف نوع المناسبة بسبب وجود ارتباطات نشطة.");
    }
  };

  const addTodoTask = async (eventTypeId, task) => {
    if (!task.trim()) return null;
    try {
      const saved = await dashboardApi.admin.addEventTask(eventTypeId, { task_en: task.trim(), task_ar: task.trim() });
      setEventTypes((prev) => prev.map((eventType) => {
        if (eventType.id !== String(eventTypeId)) return eventType;
        const todoItems = [...(eventType.todoItems || []), { id: String(saved.id), title: saved.task_ar || saved.task_en }];
        return { ...eventType, todoItems, todo: todoItems.map((item) => item.title) };
      }));
      return saved;
    } catch (error) {
      return showMutationError(error, "تعذر إضافة المهمة.");
    }
  };

  const updateTodoTask = async (eventTypeId, taskIndex, task) => {
    const eventType = eventTypes.find((item) => item.id === String(eventTypeId));
    const taskItem = eventType?.todoItems?.[taskIndex];
    if (!taskItem?.id || !task.trim()) return null;
    try {
      const saved = await dashboardApi.admin.updateEventTask(eventTypeId, taskItem.id, { task_en: task.trim(), task_ar: task.trim() });
      setEventTypes((prev) => prev.map((item) => {
        if (item.id !== String(eventTypeId)) return item;
        const todoItems = (item.todoItems || []).map((todo) => todo.id === String(taskItem.id) ? { ...todo, title: saved.task_ar || saved.task_en } : todo);
        return { ...item, todoItems, todo: todoItems.map((todo) => todo.title) };
      }));
      return saved;
    } catch (error) {
      return showMutationError(error, "تعذر تعديل المهمة.");
    }
  };

  const deleteTodoTask = async (eventTypeId, taskIndex) => {
    const eventType = eventTypes.find((item) => item.id === String(eventTypeId));
    const taskItem = eventType?.todoItems?.[taskIndex];
    if (!taskItem?.id) return null;
    try {
      await dashboardApi.admin.deleteEventTask(eventTypeId, taskItem.id);
      setEventTypes((prev) => prev.map((item) => {
        if (item.id !== String(eventTypeId)) return item;
        const todoItems = (item.todoItems || []).filter((todo) => todo.id !== String(taskItem.id));
        return { ...item, todoItems, todo: todoItems.map((todo) => todo.title) };
      }));
      return true;
    } catch (error) {
      return showMutationError(error, "تعذر حذف المهمة.");
    }
  };

  const attachServiceToVenue = async (venueId, serviceId, customPriceUsd = null, customPriceSyp = null) => {
    if (!venueId || !serviceId) return null;
    try {
      const saved = await dashboardApi.owner.attachService(venueId, {
        service_id: Number(serviceId),
        ...(customPriceUsd !== null && customPriceUsd !== "" ? { custom_price_usd: Number(customPriceUsd) } : {}),
        ...(customPriceSyp !== null && customPriceSyp !== "" ? { custom_price_syp: Number(customPriceSyp) } : {})
      });
      refreshData();
      logAction("Attached service to venue", `${venueId}:${serviceId}`, "service", "Owner");
      return saved;
    } catch (error) {
      return showMutationError(error, "تعذر ربط الخدمة بالصالة.");
    }
  };

  const createAdminService = async (payload) => {
    try {
      const saved = await dashboardApi.admin.createService({
        name_ar: payload.name_ar || payload.name,
        name_en: payload.name_en || payload.name_ar || payload.name,
        description_ar: payload.description_ar || "",
        description_en: payload.description_en || payload.description_ar || "",
        emoji: payload.emoji || "🧩",
        type: payload.type || "external_vendor",
        category: payload.category || "service",
        category_id: payload.category_id || null,
        price_usd: Number(payload.price_usd || 0),
        price_syp: Number(payload.price_syp || 0),
        provider_id: payload.provider_id || null,
        available_for: payload.available_for || []
      });
      const mapped = serviceFromApi(saved);
      setServices((prev) => [mapped, ...prev.filter((service) => service.id !== mapped.id)]);
      logAction("Created service", mapped.name, "service", "Admin");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر إنشاء الخدمة.");
    }
  };

  const createProviderService = async (payload) => {
    try {
      const saved = await dashboardApi.provider.createService({
        name_ar: payload.name_ar || payload.name,
        name_en: payload.name_en || payload.name_ar || payload.name,
        description_ar: payload.description_ar || "",
        description_en: payload.description_en || payload.description_ar || "",
        emoji: payload.emoji || "🧩",
        category: payload.category || "service",
        category_id: payload.category_id || null,
        price_syp: Number(payload.price_syp || 0),
        price_usd: Number(payload.price_usd || 0),
        available_for: payload.available_for || []
      });
      const mapped = serviceFromApi(saved);
      setServices((prev) => [mapped, ...prev.filter((service) => service.id !== mapped.id)]);
      logAction("Submitted provider service", mapped.name, "service", "Provider");
      return mapped;
    } catch (error) {
      return showMutationError(error, "تعذر إرسال الخدمة للمراجعة.");
    }
  };

  const loadAvailableServices = async () => {
    try {
      return asArray(await dashboardApi.owner.availableServices()).map(serviceFromApi);
    } catch (error) {
      showMutationError(error, "تعذر تحميل الخدمات المتاحة.");
      return [];
    }
  };

  const sendBroadcastToOwner = () => {
    window.alert("الإرسال الجماعي غير مدرج في النسخة الجامعية الحالية. استخدم إشعارات تدفق الحجوزات والشكاوى.");
    return false;
  };

  const markNotificationRead = async (id, setter) => {
    try {
      await dashboardApi.notifications.markRead(id);
      setter((prev) => prev.map((notification) => notification.id === String(id) ? { ...notification, read: true } : notification));
      return true;
    } catch (error) {
      return showMutationError(error, "تعذر تحديث حالة الإشعار.");
    }
  };

  const markAllNotificationsRead = async (setter) => {
    try {
      await dashboardApi.notifications.markAllRead();
      setter((prev) => prev.map((notification) => ({ ...notification, read: true })));
      return true;
    } catch (error) {
      return showMutationError(error, "تعذر تعليم الإشعارات كمقروءة.");
    }
  };

  const value = {
    authLoading,
    dataLoading,
    backendError,
    reportData,
    refreshData,
    refreshNotifications,
    currentRole,
    currentUser,
    switchRole,
    logout,
    userProfile,
    setUserProfile,
    updateCurrentProfile,
    updateCurrentAvatar,
    refreshCurrentUser,
    users,
    venues,
    bookings,
    providers,
    services,
    offers,
    paymentProviders,
    complaints,
    eventTypes,
    serviceCategories,
    approvals: derivedApprovals,
    ownerRequests,
    reviews,
    ownerVenues,
    ownerBookings,
    ownerServices,
    providerServices,
    ownerOffers,
    ownerReviews,
    ownerComplaints,
    activityLog,
    metrics,
    adminNotifications,
    ownerNotifications,
    notifications: currentRole === ROLES.ADMIN ? adminNotifications : ownerNotifications,
    markAsRead: (id) => markNotificationRead(id, currentRole === ROLES.ADMIN ? setAdminNotifications : setOwnerNotifications),
    activeRuleId,
    setActiveRuleId,
    dynamicPricingRules,
    getAdjustedPrice,
    formatUsd,
    formatSyp,
    formatPricePair,
    exchangeRate: null,
    eventEmoji,
    serviceEmoji,
    arabicLabel,
    globalViewVenue,
    setGlobalViewVenue,
    addVenue,
    updateVenue,
    deleteVenue,
    adminActionVenue,
    adminActionBooking,
    acceptBooking: ownerApproveBooking,
    declineBooking: ownerRejectBooking,
    completeBooking,
    updatePaymentStatus,
    addCalendarBooking,
    updateCalendarBooking,
    deleteCalendarBooking,
    createUserAccount,
    updateUserAccount,
    updateUserStatus,
    activateUser,
    deactivateUser,
    suspendUser,
    getUserDeletionImpact,
    deleteUser,
    restoreUser,
    updateProviderStatus,
    resolveApproval,
    updateServiceStatus,
    attachServiceToVenue,
    loadAvailableServices,
    createAdminService,
    createProviderService,
    addOffer,
    updateOfferStatus,
    updatePaymentProvider,
    updateComplaint,
    moderateReview,
    replyToReview,
    addEventType,
    updateEventType,
    deleteEventType,
    addTodoTask,
    updateTodoTask,
    deleteTodoTask,
    sendBroadcastToOwner,
    logAction,
    EVENT_TYPES,
    SERVICE_TYPES,
    PAYMENT_STATUS,
    BOOKING_STATUS,
    markAdminRead: (id) => markNotificationRead(id, setAdminNotifications),
    markOwnerRead: (id) => markNotificationRead(id, setOwnerNotifications),
    clearAllAdminNotifications: () => markAllNotificationsRead(setAdminNotifications),
    clearAllOwnerNotifications: () => markAllNotificationsRead(setOwnerNotifications)
  };

  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}
