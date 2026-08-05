# AppContext API Reference

## Complete API Documentation for Event Hall Management System

### Context Setup

```javascript
import { useApp } from '../context/AppContext'

// Inside any component:
const {
  // State
  bookings,
  venues,
  owners,
  ratings,
  dynamicPricing,
  commissionSettings,
  metrics,

  // Booking Actions
  acceptBooking,
  declineBooking,
  deleteBooking,

  // Deposit Actions
  processDeposit,
  calculateDepositAmount,

  // Dynamic Pricing
  getPriceForDate,
  setPriceRule,

  // Ratings
  addRating,
  getOwnerRatings,

  // Venue Verification
  approveVenue,
  rejectVenue,
  getPendingVenues,

  // Venue Management
  addVenue,
  editVenue,
  deleteVenue,

  // Commission
  setCommissionRate,
  setDepositRate,

  // Owner Management
  verifyOwner,
  getOwnerInfo,
  getOwnerMetrics,
} = useApp()
```

---

## State Objects

### Booking Object
```javascript
{
  id: 'B001',                    // Unique booking ID
  customer: 'John Smith',        // Customer name
  email: 'john@example.com',     // Customer email
  hall: 'Grand Ballroom',        // Venue name (reference)
  date: '2026-05-25',            // Event date (YYYY-MM-DD)
  guests: 150,                   // Number of guests
  status: 'Confirmed',           // 'Pending' | 'Confirmed' | 'Cancelled'
  payment: 'Paid',               // 'Paid' | 'Unpaid' | 'Refunded'
  amount: 5000,                  // Total booking amount
  subtotal: 5000,                // Subtotal (before tax/fees)
  deposit: 1000,                 // Deposit amount (20% of subtotal)
  depositPaid: true,             // Has deposit been collected?
  depositDueDate: '2026-04-25',  // When deposit is due
  ownerId: 'OWN001',            // Owner of the venue
}
```

### Venue Object
```javascript
{
  id: 'V001',
  name: 'Grand Ballroom',
  location: 'Premium City',
  capacity: 500,                          // Max guests
  pricePerGuest: 50,                      // Base price
  amenities: ['WiFi', 'Parking', 'AC'],   // List of amenities
  ownerId: 'OWN001',                      // Owner ID
  verificationStatus: 'approved',         // 'pending' | 'approved' | 'rejected'
  verified: true,                         // Is venue verified?
  createdAt: '2025-01-20',               // Creation date
  avgRating: 4.8,                        // Average rating (1-5)
  rejectionReason: null,                 // Why was it rejected?
}
```

### Owner Object
```javascript
{
  id: 'OWN001',
  name: 'Alex Thompson',
  email: 'alex@example.com',
  verified: true,              // Is owner verified by platform?
  createdAt: '2025-01-15',    // Account creation date
  venueCount: 2,              // Number of venues owned
  ratings: [4.8, 4.5],        // Array of venue ratings
  earnings: 12500,            // Total earnings
}
```

### Rating Object
```javascript
{
  id: 'R001',
  bookingId: 'B001',          // Which booking is this for?
  ownerId: 'OWN001',          // Venue owner
  customerId: 'john@example.com', // Who gave the rating?
  score: 5,                   // 1-5 stars
  comment: 'Excellent venue...',
  createdAt: '2026-05-26',   // When was it created?
}
```

### Dynamic Pricing Rule Object
```javascript
{
  id: 'DP001',
  venueId: 'V001',            // Which venue?
  dateRangeStart: '2026-12-15',
  dateRangeEnd: '2026-12-31',
  multiplier: 1.3,            // 1.0 = base price, 1.3 = 30% increase
  type: 'holiday',            // 'seasonal' | 'holiday'
  reason: 'Christmas Season',  // Description
}
```

### Commission Settings Object
```javascript
{
  platformFeePercent: 10,      // Platform takes 10% commission
  depositPercent: 20,          // Deposit is 20% of booking
  lastUpdated: '2025-01-15',  // When were these settings last changed?
}
```

### Metrics Object (Calculated)
```javascript
{
  totalRevenue: 10500,            // Sum of confirmed bookings
  platformProfit: 1050,           // Revenue × commission%
  totalDepositsCollected: 3600,   // Sum of paid deposits
  pendingVenueApprovals: 2,       // Venues awaiting approval
  totalVerifiedVenues: 2,         // Number of verified venues
  activeBookings: 2,              // Number of confirmed bookings
  totalOwners: 2,                 // Number of owners
  totalVenues: 4,                 // Number of venues
}
```

---

## Action Functions

### Booking Management

#### acceptBooking(bookingId)
Change booking status from "Pending" to "Confirmed"
```javascript
acceptBooking('B002')
// Result: B002.status = 'Confirmed', B002.payment = 'Paid'
// Metrics update automatically
```

#### declineBooking(bookingId)
Change booking status to "Cancelled"
```javascript
declineBooking('B002')
// Result: B002.status = 'Cancelled', B002.payment = 'Refunded'
```

#### deleteBooking(bookingId)
Remove booking from system
```javascript
deleteBooking('B002')
// Result: Booking removed from bookings array
```

---

### Deposit System

#### calculateDepositAmount(subtotal)
Returns 20% of subtotal (or current depositPercent setting)
```javascript
const deposit = calculateDepositAmount(5000)
// Result: 1000 (20% of $5000)

// Use current settings:
const deposit = calculateDepositAmount(3500)
// Result: 700 (20% of $3500)
```

#### processDeposit(bookingId, amountPaid)
Mark deposit as paid
```javascript
processDeposit('B001', 1000)
// Result: B001.depositPaid = true, B001.deposit = 1000
// Metrics.totalDepositsCollected increases
```

---

### Dynamic Pricing

#### getPriceForDate(venueId, date)
Get the price for a venue on a specific date
```javascript
// Check if there's a pricing rule for this date
const price = getPriceForDate('V001', '2026-12-20')
// Returns: 65 (50 × 1.3 multiplier for Christmas season)

// If no rule exists, returns base price
const price = getPriceForDate('V001', '2026-03-15')
// Returns: 50 (base price, no rule)
```

#### setPriceRule(venueId, dateStart, dateEnd, multiplier, type, reason)
Add a new dynamic pricing rule
```javascript
setPriceRule(
  'V001',           // venueId
  '2026-11-20',     // dateStart
  '2026-11-30',     // dateEnd
  1.5,              // multiplier (1.0-2.0)
  'holiday',        // type: 'seasonal' or 'holiday'
  'Thanksgiving'    // reason/description
)
// Result: New pricing rule added, getPriceForDate will use it
```

---

### Ratings & Reviews

#### addRating(bookingId, ownerId, customerId, score, comment)
Add a new rating for a venue
```javascript
addRating(
  'B001',                        // bookingId
  'OWN001',                      // ownerId
  'john@example.com',            // customerId (who rated it)
  5,                             // score (1-5)
  'Excellent venue, great staff!'
)
// Result: Rating added, venue avgRating updated
```

#### getOwnerRatings(ownerId)
Get all ratings for an owner
```javascript
const ratings = getOwnerRatings('OWN001')
// Result: [
//   { id: 'R001', score: 5, comment: '...', ... },
//   { id: 'R002', score: 4, comment: '...', ... }
// ]
```

---

### Venue Verification

#### approveVenue(venueId)
Mark venue as approved and verified
```javascript
approveVenue('V003')
// Result: V003.verificationStatus = 'approved'
//         V003.verified = true
//         Metrics.pendingVenueApprovals decreases
//         Metrics.totalVerifiedVenues increases
```

#### rejectVenue(venueId, reason)
Reject a venue application
```javascript
rejectVenue('V003', 'Missing fire safety certificate')
// Result: V003.verificationStatus = 'rejected'
//         V003.verified = false
//         V003.rejectionReason = 'Missing fire safety...'
//         Metrics.pendingVenueApprovals decreases
```

#### getPendingVenues()
Get array of all pending venues
```javascript
const pending = getPendingVenues()
// Result: [ { id: 'V003', status: 'pending', ... }, ... ]
```

---

### Venue Management

#### addVenue(venueData)
Create a new venue
```javascript
addVenue({
  name: 'New Venue',
  location: 'Downtown',
  capacity: 300,
  pricePerGuest: 60,
  amenities: ['WiFi', 'Parking'],
  ownerId: 'OWN001'
})
// Result: New venue added with:
//   - Auto-generated ID
//   - verificationStatus: 'pending'
//   - verified: false
//   - createdAt: today's date
//   - avgRating: 0
```

#### editVenue(venueId, updatedData)
Update existing venue
```javascript
editVenue('V001', {
  pricePerGuest: 65,
  amenities: ['WiFi', 'Parking', 'AC', 'Catering']
})
// Result: V001 updated with new values
```

#### deleteVenue(venueId)
Remove venue from system
```javascript
deleteVenue('V001')
// Result: Venue removed from venues array
```

---

### Commission Settings

#### setCommissionRate(newRate)
Change the platform commission percentage (5-30%)
```javascript
setCommissionRate(15)
// Result: commissionSettings.platformFeePercent = 15
//         commissionSettings.lastUpdated = today
//         All metrics recalculate with new rate
//         All owner earnings recalculate
```

#### setDepositRate(newRate)
Change the deposit percentage (10-50%)
```javascript
setDepositRate(25)
// Result: commissionSettings.depositPercent = 25
//         All new bookings use 25% for deposits
//         calculateDepositAmount() returns 25% going forward
```

---

### Owner Management

#### verifyOwner(ownerId)
Mark owner as verified by platform
```javascript
verifyOwner('OWN002')
// Result: OWN002.verified = true
```

#### getOwnerInfo(ownerId)
Get complete owner details
```javascript
const owner = getOwnerInfo('OWN001')
// Result: {
//   id: 'OWN001',
//   name: 'Alex Thompson',
//   email: 'alex@example.com',
//   verified: true,
//   createdAt: '2025-01-15',
//   venueCount: 2,
//   ratings: [4.8, 4.5],
//   earnings: 12500
// }
```

#### getOwnerMetrics(ownerId)
Get owner's financial metrics with commission deduction
```javascript
const metrics = getOwnerMetrics('OWN001')
// Result: {
//   totalEarnings: 9000,      // Sum of confirmed bookings
//   commission: 900,          // Platform takes 10%
//   netEarnings: 8100,        // Owner keeps this
//   pendingBookings: 2,       // Awaiting approval
//   depositsCollected: 1800,  // Total deposits collected
//   totalBookings: 2          // Total confirmed bookings
// }
```

---

## Usage Examples

### Example 1: Complete Booking Workflow
```javascript
const { bookings, acceptBooking, processDeposit, calculateDepositAmount } = useApp()

// 1. Customer books venue
const booking = bookings.find(b => b.id === 'B002')
console.log(`Booking: ${booking.customer}, Status: ${booking.status}`)
// Output: "Booking: Sarah Johnson, Status: Pending"

// 2. Owner accepts booking
acceptBooking('B002')

// 3. Calculate and process deposit
const deposit = calculateDepositAmount(booking.subtotal)
processDeposit('B002', deposit)

// 4. Verify it's confirmed
const updated = bookings.find(b => b.id === 'B002')
console.log(`Updated: ${updated.status}, Deposit Paid: ${updated.depositPaid}`)
// Output: "Updated: Confirmed, Deposit Paid: true"
```

### Example 2: Manage Venue Approval
```javascript
const { venues, getPendingVenues, approveVenue, rejectVenue } = useApp()

// Get all venues awaiting approval
const pending = getPendingVenues()
console.log(`${pending.length} venues awaiting approval`)

// Approve one
approveVenue(pending[0].id)

// Reject another with reason
rejectVenue(pending[1].id, 'Missing safety documentation')
```

### Example 3: Set Dynamic Pricing
```javascript
const { setPriceRule, getPriceForDate } = useApp()

// Add holiday pricing
setPriceRule(
  'V001',
  '2026-12-15',
  '2026-12-31',
  1.5,
  'holiday',
  'Christmas Premium'
)

// Check price on specific dates
const christmasPrice = getPriceForDate('V001', '2026-12-20')
const normalPrice = getPriceForDate('V001', '2026-01-15')
console.log(`Christmas: $${christmasPrice}, Normal: $${normalPrice}`)
```

### Example 4: Owner Metrics Dashboard
```javascript
const { getOwnerMetrics, getOwnerRatings } = useApp()

const metrics = getOwnerMetrics('OWN001')
const ratings = getOwnerRatings('OWN001')

console.log(`Net Earnings: $${metrics.netEarnings}`)
console.log(`Deposits Collected: $${metrics.depositsCollected}`)
console.log(`Average Rating: ${(ratings.reduce((s,r) => s+r.score, 0) / ratings.length).toFixed(1)} stars`)
```

---

## Metrics Calculation Examples

### Calculate Platform Profit
```javascript
// Manual calculation:
const totalRevenue = 10500  // Sum of all confirmed bookings
const commissionPercent = 10
const profit = totalRevenue * (commissionPercent / 100)
console.log(`Platform Profit: $${profit}`)  // $1050

// From context:
const { metrics, setCommissionRate } = useApp()
console.log(`Platform Profit: $${metrics.platformProfit}`)

// Change commission to 15%:
setCommissionRate(15)
// metrics.platformProfit automatically recalculates to $1575
```

### Calculate Owner Net Earnings
```javascript
const { getOwnerMetrics, setCommissionRate } = useApp()

let ownerMetrics = getOwnerMetrics('OWN001')
console.log(`At 10% commission: $${ownerMetrics.netEarnings}`)

// Change to 15% commission
setCommissionRate(15)

// Recalculate
ownerMetrics = getOwnerMetrics('OWN001')
console.log(`At 15% commission: $${ownerMetrics.netEarnings}`)
// Net earnings decreased because commission increased
```

---

## Error Handling
All functions handle edge cases gracefully:
```javascript
// Non-existent IDs return null or empty arrays
getOwnerInfo('INVALID')           // null
getOwnerRatings('OWN_NOTFOUND')   // []
getPendingVenues()                // [] if none pending

// Division by zero protected
getOwnerRatings('OWN001')        // [] (avg = 0)
```

---

## Performance Notes
- `calculatedMetrics` uses `useMemo` and only recalculates when dependencies change
- All state updates trigger re-renders of components using those values
- Dynamic pricing lookups are O(n) where n = number of pricing rules
- Owner metrics are calculated fresh each call (can be memoized by parent component)

---

## Integration with Components
```javascript
// In AdminDashboard.jsx
const { metrics, setCommissionRate } = useApp()

// In OwnerDashboard.jsx
const { bookings, getOwnerMetrics, acceptBooking } = useApp()

// In any component that needs commission-adjusted values
const { getOwnerMetrics, commissionSettings } = useApp()
const ownerMetrics = getOwnerMetrics('OWN001')
```
