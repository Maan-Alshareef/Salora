const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000/api").replace(/\/$/, "");
const REQUEST_TIMEOUT_MS = Number(import.meta.env.VITE_API_TIMEOUT_MS || 15000);

export class ApiError extends Error {
  constructor(message, status = 0, errors = null) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
    this.code = errors?.code || null;
  }
}

const clearStoredSession = () => {
  localStorage.removeItem("salora_token");
  localStorage.removeItem("salora_role");
  localStorage.removeItem("salora_user");
};

const firstValidationMessage = (errors) => {
  if (!errors || typeof errors !== "object") return null;
  for (const value of Object.values(errors)) {
    if (Array.isArray(value) && value[0]) return value[0];
    if (typeof value === "string") return value;
  }
  return null;
};

async function request(path, options = {}) {
  const token = localStorage.getItem("salora_token");
  const isFormData = options.body instanceof FormData;
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

  try {
    const response = await fetch(`${API_BASE_URL}${path}`, {
      ...options,
      signal: controller.signal,
      headers: {
        ...(isFormData ? {} : { "Content-Type": "application/json" }),
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(options.headers || {})
      }
    });

    let payload = null;
    try { payload = await response.json(); } catch (_) { payload = null; }

    if (!response.ok) {
      const code = payload?.errors?.code || null;
      const inactive = ["account_inactive", "account_suspended", "account_deleted"].includes(code)
        || payload?.message === "Account is not active.";
      const mustChangePassword = code === "must_change_password";
      if (mustChangePassword) {
        window.dispatchEvent(new CustomEvent("salora:password-change-required"));
      }
      if (inactive || response.status === 401) {
        clearStoredSession();
        window.dispatchEvent(new CustomEvent("salora:auth-invalid", { detail: { inactive, code } }));
      }
      const message = payload?.message
        || firstValidationMessage(payload?.errors)
        || (mustChangePassword ? "يجب تغيير كلمة المرور قبل استخدام لوحة الأعمال." : "فشل تنفيذ الطلب.");
      throw new ApiError(message, response.status, payload?.errors || null);
    }

    if (response.status === 204) return null;
    return payload?.data ?? payload;
  } catch (error) {
    if (error?.name === "AbortError") {
      throw new ApiError("انتهت مهلة الاتصال بالخادم. تحقق من تشغيل الـ Backend ثم أعد المحاولة.", 408);
    }
    if (error instanceof ApiError) throw error;
    throw new ApiError("تعذر الاتصال بالخادم. تحقق من رابط API وحالة الشبكة.", 0);
  } finally {
    window.clearTimeout(timeout);
  }
}

const encodeQuery = (params = {}) => {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") search.set(key, String(value));
  });
  const query = search.toString();
  return query ? `?${query}` : "";
};

export const apiClient = {
  get: (path) => request(path),
  post: (path, body = {}) => request(path, {
    method: "POST",
    body: body instanceof FormData ? body : JSON.stringify(body)
  }),
  put: (path, body = {}) => request(path, {
    method: "PUT",
    body: body instanceof FormData ? body : JSON.stringify(body)
  }),
  delete: (path, body = null) => request(path, {
    method: "DELETE",
    ...(body ? { body: JSON.stringify(body) } : {})
  })
};

export const dashboardApi = {
  auth: {
    login: (payload) => apiClient.post("/auth/login", payload),
    me: () => apiClient.get("/auth/me"),
    logout: () => apiClient.post("/auth/logout", {}),
    profile: (payload) => apiClient.put("/auth/profile", payload),
    deleteAvatar: () => apiClient.delete("/auth/profile/avatar"),
    requestEmailChange: (email) => apiClient.post("/auth/email-change/request", { email, new_email: email }),
    verifyEmailChange: (email, otp) => apiClient.post("/auth/email-change/verify", { email, new_email: email, otp, code: otp }),
    uploadAvatar: (file) => {
      const form = new FormData();
      form.append("image", file);
      return apiClient.post("/auth/profile/avatar", form);
    },
    changePassword: (payload) => apiClient.post("/auth/change-password", payload),
    forgotPassword: (email) => apiClient.post("/auth/forgot-password", { email }),
    resetPassword: (payload) => apiClient.post("/auth/reset-password", payload)
  },
  notifications: {
    list: () => apiClient.get("/notifications?per_page=100"),
    unreadCount: () => apiClient.get("/notifications/unread-count"),
    markRead: (id) => apiClient.post(`/notifications/${id}/read`, {}),
    markAllRead: () => apiClient.post("/notifications/read-all", {})
  },
  public: {
    venues: (params = {}) => apiClient.get(`/venues${encodeQuery({ per_page: 100, ...params })}`),
    eventTypes: () => apiClient.get("/event-types"),
    serviceCategories: (params = {}) => apiClient.get(`/service-categories${encodeQuery(params)}`),
    services: (params = {}) => apiClient.get(`/services${encodeQuery(params)}`),
    providers: (params = {}) => apiClient.get(`/providers${encodeQuery(params)}`),
    providerDetails: (id) => apiClient.get(`/providers/${id}`),
    offers: () => apiClient.get("/offers")
  },
  admin: {
    users: (params = {}) => apiClient.get(`/admin/users${encodeQuery(params)}`),
    createUser: (payload) => apiClient.post("/admin/users", payload),
    updateUser: (id, payload) => apiClient.put(`/admin/users/${id}`, payload),
    userDetails: (id) => apiClient.get(`/admin/users/${id}`),
    userDeletionImpact: (id) => apiClient.get(`/admin/users/${id}/deletion-impact`),
    suspendUser: (id, payload) => apiClient.post(`/admin/users/${id}/suspend`, payload),
    activateUser: (id) => apiClient.post(`/admin/users/${id}/activate`, {}),
    deactivateUser: (id, reason = null) => apiClient.post(`/admin/users/${id}/deactivate`, { reason }),
    deleteUser: (id) => apiClient.delete(`/admin/users/${id}`),
    restoreUser: (id) => apiClient.post(`/admin/users/${id}/restore`, {}),
    ownerRequests: () => apiClient.get("/admin/owner-requests"),
    approveOwnerRequest: (id, payload) => apiClient.post(`/admin/owner-requests/${id}/approve`, payload),
    rejectOwnerRequest: (id, reason) => apiClient.post(`/admin/owner-requests/${id}/reject`, { reason }),
    venues: () => apiClient.get("/admin/venues"),
    venueDetails: (id) => apiClient.get(`/admin/venues/${id}`),
    approveVenue: (id) => apiClient.post(`/admin/venues/${id}/approve`, {}),
    rejectVenue: (id, reason) => apiClient.post(`/admin/venues/${id}/reject`, { reason }),
    disableVenue: (id) => apiClient.post(`/admin/venues/${id}/disable`, {}),
    venueRevisions: (status = "pending") => apiClient.get(`/admin/venue-revisions${encodeQuery({ status })}`),
    approveVenueRevision: (id) => apiClient.post(`/admin/venue-revisions/${id}/approve`, {}),
    rejectVenueRevision: (id, reason) => apiClient.post(`/admin/venue-revisions/${id}/reject`, { reason }),
    bookings: () => apiClient.get("/admin/bookings"),
    bookingDetails: (id) => apiClient.get(`/admin/bookings/${id}`),
    cancelBooking: (id, reason) => apiClient.post(`/admin/bookings/${id}/cancel`, { reason }),
    payments: () => apiClient.get("/admin/payments"),
    paymentDetails: (id) => apiClient.get(`/admin/payments/${id}`),
    approvePayment: (id) => apiClient.post(`/admin/payments/${id}/approve`, {}),
    rejectPayment: (id, reason) => apiClient.post(`/admin/payments/${id}/reject`, { reason }),
    serviceCategories: () => apiClient.get("/admin/service-categories"),
    createServiceCategory: (payload) => apiClient.post("/admin/service-categories", payload),
    updateServiceCategory: (id, payload) => payload instanceof FormData
      ? apiClient.post(`/admin/service-categories/${id}/update`, payload)
      : apiClient.put(`/admin/service-categories/${id}`, payload),
    deleteServiceCategory: (id) => apiClient.delete(`/admin/service-categories/${id}`),
    services: () => apiClient.get("/admin/services"),
    createService: (payload) => apiClient.post("/admin/services", payload),
    updateService: (id, payload) => apiClient.put(`/admin/services/${id}`, payload),
    deleteService: (id) => apiClient.delete(`/admin/services/${id}`),
    approveService: (id) => apiClient.post(`/admin/services/${id}/approve`, {}),
    rejectService: (id, reason) => apiClient.post(`/admin/services/${id}/reject`, { reason }),
    offers: () => apiClient.get("/admin/offers"),
    createOffer: (payload) => apiClient.post("/admin/offers", payload),
    approveOffer: (id) => apiClient.post(`/admin/offers/${id}/approve`, {}),
    rejectOffer: (id, reason) => apiClient.post(`/admin/offers/${id}/reject`, { reason }),
    deleteOffer: (id) => apiClient.delete(`/admin/offers/${id}`),
    reviews: () => apiClient.get("/admin/reviews"),
    hideReview: (id) => apiClient.post(`/admin/reviews/${id}/hide`, {}),
    restoreReview: (id) => apiClient.post(`/admin/reviews/${id}/restore`, {}),
    deleteReview: (id) => apiClient.delete(`/admin/reviews/${id}`),
    complaints: () => apiClient.get("/admin/complaints"),
    replyComplaint: (id, reply) => apiClient.post(`/admin/complaints/${id}/reply`, { reply }),
    closeComplaint: (id) => apiClient.post(`/admin/complaints/${id}/close`, {}),
    commissions: (params = {}) => apiClient.get(`/admin/commissions${encodeQuery(params)}`),
    collectCommission: (id, payload = {}) => apiClient.post(`/admin/commissions/${id}/collect`, payload),
    updateCommission: (id, payload) => apiClient.put(`/admin/commissions/${id}`, payload),    reports: () => apiClient.get("/admin/reports/summary"),
    settings: () => apiClient.get("/admin/settings"),
    updateSettings: (payload) => apiClient.post("/admin/settings", payload),
    eventTypes: () => apiClient.get("/admin/event-types"),
    createEventType: (payload) => apiClient.post("/admin/event-types", payload),
    updateEventType: (id, payload) => apiClient.put(`/admin/event-types/${id}`, payload),
    deleteEventType: (id) => apiClient.delete(`/admin/event-types/${id}`),
    addEventTask: (id, payload) => apiClient.post(`/admin/event-types/${id}/tasks`, payload),
    updateEventTask: (eventTypeId, taskId, payload) => apiClient.put(`/admin/event-types/${eventTypeId}/tasks/${taskId}`, payload),
    deleteEventTask: (eventTypeId, taskId) => apiClient.delete(`/admin/event-types/${eventTypeId}/tasks/${taskId}`),
    activity: () => apiClient.get("/admin/activity"),
    paymentMethods: () => apiClient.get("/admin/payment-methods"),
    paymentRefunds: () => apiClient.get("/admin/payment-refunds")
  },
  owner: {
    venues: () => apiClient.get("/owner/venues"),
    createVenue: (payload) => apiClient.post("/owner/venues", payload),
    updateVenue: (id, payload) => apiClient.put(`/owner/venues/${id}`, payload),
    disableVenue: (id) => apiClient.delete(`/owner/venues/${id}`),
    uploadVenueImage: (id, formData) => apiClient.post(`/owner/venues/${id}/images`, formData),
    deleteVenueImage: (venueId, imageId) => apiClient.delete(`/owner/venues/${venueId}/images/${imageId}`),
    setMainVenueImage: (venueId, imageId) => apiClient.post(`/owner/venues/${venueId}/images/${imageId}/main`, {}),
    reorderVenueImages: (venueId, imageUrls) => apiClient.post(`/owner/venues/${venueId}/images/reorder`, { image_urls: imageUrls }),
    uploadVenueVideo: (id, formData) => apiClient.post(`/owner/venues/${id}/videos`, formData),
    deleteVenueVideo: (venueId, videoId) => apiClient.delete(`/owner/venues/${venueId}/videos/${videoId}`),
    reorderVenueVideos: (venueId, videoUrls) => apiClient.post(`/owner/venues/${venueId}/videos/reorder`, { video_urls: videoUrls }),
    bookings: () => apiClient.get("/owner/bookings"),
    bookingDetails: (id) => apiClient.get(`/owner/bookings/${id}`),
    approveBooking: (id) => apiClient.post(`/owner/bookings/${id}/approve`, {}),
    rejectBooking: (id, reason) => apiClient.post(`/owner/bookings/${id}/reject`, { reason }),
    completeBooking: (id) => apiClient.post(`/owner/bookings/${id}/complete`, {}),
    changeRequests: () => apiClient.get("/owner/booking-change-requests"),
    decideChangeRequest: (id, decision, reason = null) => apiClient.post(`/owner/booking-change-requests/${id}/decision`, { decision, reason }),
    services: () => apiClient.get("/owner/hall-services"),
    availableServices: () => apiClient.get("/owner/available-services"),
    attachService: (venueId, payload) => apiClient.post(`/owner/venues/${venueId}/services`, payload),
    offers: () => apiClient.get("/owner/offers"),
    createOffer: (payload) => apiClient.post("/owner/offers", payload),
    updateOffer: (id, payload) => apiClient.put(`/owner/offers/${id}`, payload),
    deleteOffer: (id) => apiClient.delete(`/owner/offers/${id}`),
    reviews: () => apiClient.get("/owner/reviews"),
    replyReview: (id, reply) => apiClient.post(`/owner/reviews/${id}/reply`, { reply }),
    complaints: () => apiClient.get("/owner/complaints"),
    replyComplaint: (id, reply) => apiClient.post(`/owner/complaints/${id}/reply`, { reply }),
    reports: () => apiClient.get("/owner/reports/summary"),
    payments: () => apiClient.get("/business/payments"),
    payoutAccounts: () => apiClient.get("/business/payout-accounts"),
    paymentMethods: () => apiClient.get("/business/payment-methods")
  },
  provider: {
    services: () => apiClient.get("/provider/services"),
    createService: (payload) => apiClient.post("/provider/services", payload),
    updateService: (id, payload) => apiClient.put(`/provider/services/${id}`, payload),
    deleteService: (id) => apiClient.delete(`/provider/services/${id}`),
    requests: () => apiClient.get("/provider/requests"),
    acceptRequest: (id) => apiClient.post(`/provider/requests/${id}/accept`, {}),
    rejectRequest: (id, reply) => apiClient.post(`/provider/requests/${id}/reject`, { reply }),
    reports: () => apiClient.get("/provider/reports/summary")
  }
};

export { API_BASE_URL, clearStoredSession };
