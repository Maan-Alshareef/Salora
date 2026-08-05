export const ROLES = {
  ADMIN: "Admin",
  OWNER: "Owner",
  PROVIDER: "Provider"
};

export const BOOKING_STATUS = {
  PENDING_OWNER_REVIEW: "Pending Owner Review",
  PENDING_PAYMENT: "Pending Payment",
  OWNER_APPROVED: "Owner Approved",
  PAYMENT_UNDER_REVIEW: "Payment Under Review",
  MODIFICATION_REQUESTED: "Modification Requested",
  CANCELLATION_REQUESTED: "Cancellation Requested",
  CONFIRMED: "Confirmed",
  COMPLETED: "Completed",
  REJECTED: "Rejected",
  CANCELLED: "Cancelled"
};

export const PAYMENT_STATUS = {
  NOT_UPLOADED: "Not Uploaded",
  PENDING_ADMIN_VERIFICATION: "Pending Admin Verification",
  VERIFIED: "Verified",
  REJECTED_PROOF: "Rejected Proof",
  REUPLOAD_REQUESTED: "Re-upload Requested",
  REFUNDED: "Refunded"
};

export const SERVICE_TYPES = {
  INCLUDED: "Included Hall Service",
  HALL_UPGRADE: "Paid Hall Upgrade",
  EXTERNAL_VENDOR: "External Vendor Service"
};

export const EVENT_TYPES = [
  "Wedding",
  "Engagement",
  "Graduation",
  "Birthday",
  "Family Event",
  "Condolence",
  "Conference",
  "Meeting"
];
