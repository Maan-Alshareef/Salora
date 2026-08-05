# Frontend Mapping — University Edition

## Flutter Customer

- Auth/Profile → `/api/auth/*`
- Venues and services → `/api/venues`, `/api/services`, `/api/service-categories`
- Events/Todos → `/api/customer/events` and nested todo routes
- Bookings → `/api/customer/bookings`
- Change/cancellation request → `/api/customer/bookings/{id}/change-requests`
- Payment proof → `/api/customer/bookings/{id}/payment-proof`
- Reviews/complaints/notifications → corresponding customer/shared endpoints

## Flutter Provider

- Provider services → `/api/provider/services`
- Incoming service requests → `/api/provider/requests`
- Provider reports → `/api/provider/reports/summary`

## React Admin

- Accounts/join requests → `/api/admin/users`, `/api/admin/owner-requests`
- Venue/service/offer approvals → Admin decision endpoints
- Payment review → `/api/admin/payments`
- Complaints/reviews/reports/settings/event types → Admin endpoints

## React Hall Manager

- Venues and revisions → `/api/owner/venues`
- Booking decisions and change requests → `/api/owner/bookings`, `/api/owner/booking-change-requests`
- Offers/reviews/complaints/reports → Owner endpoints
