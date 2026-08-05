# Salora API Reference — University Edition

Base URL:

```text
http://127.0.0.1:8000/api
```

كل Endpoint محمي يتطلب:

```http
Authorization: Bearer <token>
Accept: application/json
```

## Public and Auth

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/health` | Health check |
| POST | `/auth/register` | Create customer account and email verification OTP |
| POST | `/auth/verify-email` | Verify signup OTP and issue access token |
| POST | `/auth/resend-verification` | Resend signup OTP after cooldown |
| POST | `/auth/login` | Login (verified accounts only) |
| POST | `/auth/forgot-password` | Email password-reset OTP |
| POST | `/auth/reset-password` | Reset password |
| GET | `/venues` | Approved venues with search/filter |
| GET | `/venues/{venue}` | Venue details |
| GET | `/services` | Approved services |
| GET | `/services/{service}` | Service/provider details |
| GET | `/service-categories` | Active service categories |
| GET | `/event-types` | Event types and templates |
| GET | `/offers` | Active approved offers |
| POST | `/join-requests` | Owner/provider join request |

## Authenticated Shared

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/auth/me` | Validate session/current user |
| PUT | `/auth/profile` | Update name and phone |
| POST | `/auth/profile/avatar` | Upload/replace profile image |
| POST | `/auth/change-password` | Change password |
| POST | `/auth/logout` | Revoke current token |
| GET | `/notifications` | In-app notifications |
| POST | `/notifications/{id}/read` | Mark notification read |
| GET | `/payment-proofs/{id}/image` | Private proof file with ownership check |

## Customer

| Method | Endpoint | Purpose |
|---|---|---|
| CRUD | `/customer/events` | Manage events |
| POST/PUT/DELETE | `/customer/events/{event}/todos...` | Manage event Todo items |
| GET/POST | `/customer/bookings` | List/create bookings |
| GET | `/customer/bookings/{booking}` | Booking, invoice and history |
| POST | `/customer/bookings/{booking}/provider-services` | Request external provider services |
| POST | `/customer/bookings/{booking}/change-requests` | Request modification/cancellation |
| POST | `/customer/bookings/{booking}/payment-proof` | Upload manual transfer proof |
| POST | `/customer/reviews` | Review completed owned booking |
| GET/POST | `/customer/complaints` | List/create complaints |

## Hall Manager (`owner`)

| Method | Endpoint | Purpose |
|---|---|---|
| CRUD | `/owner/venues` | Manage owned venues; updates create revision |
| POST | `/owner/venues/{venue}/images` | Upload venue image |
| GET | `/owner/bookings` | Owned venue bookings |
| POST | `/owner/bookings/{booking}/approve` | Approve and issue invoice |
| POST | `/owner/bookings/{booking}/reject` | Reject with reason |
| POST | `/owner/bookings/{booking}/complete` | Mark confirmed booking completed |
| GET/POST | `/owner/booking-change-requests...` | Review customer changes/cancellations |
| GET/POST | `/owner/offers` | Manage offers pending Admin review |
| GET | `/owner/reports/summary` | Scoped report |

## Service Provider (`provider`)

| Method | Endpoint | Purpose |
|---|---|---|
| CRUD | `/provider/services` | Manage provider services |
| GET | `/provider/requests` | Incoming service requests |
| POST | `/provider/requests/{id}/accept` | Accept before payment; update invoice |
| POST | `/provider/requests/{id}/reject` | Reject with reason |
| GET | `/provider/reports/summary` | Scoped report |

## Admin

| Group | Main endpoints |
|---|---|
| Users | `/admin/users`, suspend/activate/deactivate/delete-impact/restore |
| Join requests | `/admin/owner-requests` and approve/reject |
| Venues | `/admin/venues`, `/admin/venue-revisions` and decisions |
| Bookings | `/admin/bookings` and administrative cancellation |
| Payments | `/admin/payments` and approve/reject |
| Categories/services | `/admin/service-categories`, `/admin/services` and decisions |
| Offers | `/admin/offers` and approve/reject |
| Reviews | `/admin/reviews` and hide/restore/delete |
| Complaints | `/admin/complaints` and reply/close |
| Reports/settings | `/admin/reports/summary`, `/admin/settings` |
| Event types/tasks | `/admin/event-types` and task routes |
| Audit | `/admin/activity` |

## Canonical statuses

### Booking

```text
pending_owner_review
pending_payment
payment_under_review
confirmed
modification_requested
cancellation_requested
owner_rejected
cancelled
completed
```

### Payment

```text
unpaid
proof_uploaded
approved
rejected
refunded
```

### Approval/Offer

```text
pending
approved
rejected
active
expired
disabled
```
