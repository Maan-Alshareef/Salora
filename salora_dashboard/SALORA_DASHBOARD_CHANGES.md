# Salora Dashboard Backend-Ready Update

## What changed

- Fixed all dashboard routes in `src/App.jsx`.
- Removed dashboard routes that do not belong in admin/owner workspaces: `Checkout` and standalone `Invoice`.
- Removed Redux from runtime entry because current dashboard uses one source of truth: `AppContext`.
- Added `src/services/apiClient.js` for future Laravel API integration.
- Added `src/config/permissions.js` for roles, booking statuses, payment statuses, service types, and event types.
- Renamed UI branding from VenueHub to Salora.
- Added Admin Reviews Moderation page.
- Added Owner pages:
  - Payment Status
  - Hall Services
  - Offers
  - Complaints
- Reworked Admin Bookings page so bookings contain event data internally rather than a separate Events dashboard.
- Reworked Admin Payments Review so only Admin can approve/reject proof.
- Reworked Owner Bookings so owner sees only bookings for his halls and can only approve/reject availability.
- Reworked Owner dashboard and calendar to depend on `ownerId` filtering.
- Reworked Services Management into three types:
  - Included Hall Service
  - Paid Hall Upgrade
  - External Vendor Service
- Reworked Venue Form to submit backend-ready hall payload with:
  - supportedEventTypes
  - includedServices
  - paidUpgrades
  - ownerId-ready structure

## Backend integration target

When Laravel backend is ready, replace mock mutations in `src/context/AppContext.jsx` with calls from:

```js
import { dashboardApi } from "./services/apiClient";
```

The API base URL is read from:

```env
VITE_API_BASE_URL=http://localhost:8000/api
```

## Main workflow

Customer books hall in mobile app → Owner approves availability → Customer uploads payment proof → Admin verifies proof → Booking becomes confirmed.
