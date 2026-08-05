# Salora Dashboard UI / Logic Fixes

## Fixed in this version

- Dark input/select styling across the whole dashboard to remove white boxes and white dropdowns.
- Login now has separate Admin and Owner accounts:
  - admin@salora.com
  - owner@salora.com
- Password is empty by default and typed manually by the user, avoiding the default weak saved password warning.
- Owner My Halls cards now show hall details directly like the mobile app:
  - image
  - address
  - map link
  - capacity
  - USD + SYP price
  - supported event types
  - included services
  - paid upgrades
- Removed the useless View button from Owner halls.
- Admin Venues Details modal now actually renders inside Layout and shows real venue details.
- Add Hall form now matches the app logic:
  - supported event types
  - included hall services
  - paid hall upgrades
  - amenities
  - policies
  - suggested vendor categories
  - image preview
  - map URL
  - USD/SYP price summary
- Scheduling Calendar no longer adds manual bookings; it is now for monitoring and conflict resolution only.
- Event Types & To-Do Templates page redesigned and clarified: it controls app templates, not standalone events.
- Settings page redesigned around useful dashboard settings:
  - API base URL
  - currency exchange rate
  - global dynamic pricing
  - commission/policies
  - owner broadcast notifications
- Services now show emojis and USD/SYP prices.
- Offers page now supports adding global offers that apply to all venues.
- Build verified with `npm run build`.

## Backend readiness

The project remains mock-data based, but core entities use backend-friendly IDs:

- ownerId
- venueId
- customerId
- bookingId

API client remains in:

```text
src/services/apiClient.js
```
