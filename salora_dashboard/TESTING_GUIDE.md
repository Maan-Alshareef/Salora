# Testing Guide - Event Hall Management System

## Quick Start
```bash
npm run dev        # Start dev server on http://localhost:5175
npm run build      # Build for production
```

## Test Scenarios

### 1. Test Admin Dashboard - Commission Rate Controller
**Location:** Admin Dashboard → Commission Rate Controller

**Steps:**
1. Navigate to Admin Dashboard
2. Find "Commission Rate Controller" section
3. Move the slider from 10% to 15%
4. **Expected:** 
   - Rate displays "15%"
   - Example calculation updates: "$45,000 × 15% = $6,750"
   - Platform profit recalculates across all dashboards

**Mock Data:** Default 10% commission on $10,500 total revenue = $1,050 profit

---

### 2. Test Deposit Rate Controller
**Location:** Admin Dashboard → Deposit Rate Controller

**Steps:**
1. Find "Deposit Rate Controller" slider
2. Change from 20% to 25%
3. **Expected:**
   - Rate updates to "25%"
   - Timestamp shows "lastUpdated" date
   - All deposit calculations in system update (e.g., $5,000 booking → $1,250 deposit)

**Mock Data:** 20% default, 5 bookings with deposits totaling $3,600 collected

---

### 3. Test Venue Approval Workflow
**Location:** Admin Dashboard → Venue Verification Queue

**Mock Data:** 2 pending venues (Diamond Hall V003, unassigned), 2 approved venues

**Test Approve:**
1. Scroll to "Venue Verification Queue"
2. Ensure "Pending" tab is selected
3. Find "Diamond Hall" with status "Pending"
4. Click "Approve" button
5. **Expected:**
   - Row changes to "✓ Approved" status
   - Row moves to "Approved" tab
   - Pending Approvals badge decreases from 2 to 1
   - Admin metrics update: Active Venues increases

**Test Reject:**
1. Click "Reject" button on another pending venue
2. Modal appears asking for rejection reason
3. Type: "Capacity documentation incomplete"
4. Click "Reject Venue"
5. **Expected:**
   - Modal closes
   - Venue shows "✗ Rejected" status
   - Row moves to "Rejected" tab
   - Pending count decreases

---

### 4. Test Owner Dashboard - Booking Acceptance
**Location:** Owner Dashboard → Booking Approval Workflow

**Mock Data:** 
- Pending: B002 (Sarah Johnson), B004 (Emma Wilson)
- Confirmed: B001 (John Smith), B005 (Robert Brown)

**Test Accept Booking:**
1. Navigate to Owner Dashboard
2. Click "Pending" tab (should show 2 bookings)
3. Find B002 "Sarah Johnson" booking
4. Click "Accept" button
5. **Expected:**
   - Button row shows "✓ Approved"
   - Booking moves to "Confirmed" tab
   - Pending count decreases to 1
   - Metrics update: Occupancy Rate increases, Earnings increase

**Test Decline Booking:**
1. On "Pending" tab, click "Decline" on remaining pending
2. **Expected:**
   - Booking moves to "Cancelled" tab
   - Pending count shows 0
   - All pending bookings are handled

---

### 5. Test Owner Metrics Calculation
**Location:** Owner Dashboard → Top metrics cards

**Expected Values:**
```
Owner: OWN001 (Alex Thompson)

Total Bookings (Confirmed): 2 (B001 $5000 + B004 $4000)
Total Confirmed Revenue: $9,000
Commission (10%): $900
Net Earnings: $8,100 ✅

Deposit Collection:
- B001: $1,000 (paid) ✓
- B004: $800 (paid) ✓
- Collected: $1,800
- Total Due: $1,800
```

**Verify:**
1. Check "Total Venue Earnings" card shows ~$8,100
2. Check "Deposit Collection" shows "$1,800 of $1,800"
3. Check "Occupancy Rate" shows 50% (2 confirmed / 4 total)
4. Change commission to 15% in Admin Dashboard
5. **Expected:** Owner earnings recalculate to ~$7,650

---

### 6. Test Dynamic Pricing Rules
**Location:** Owner Dashboard → Dynamic Pricing Manager

**Mock Data:** 
- Grand Ballroom has 2 rules:
  - Rule 1: 1.3x multiplier (Dec 15-31) "Christmas Season"
  - Rule 2: 1.2x multiplier (Jun 21 - Aug 31) "Summer Peak"
- Base price: $50/guest

**Test Add New Rule:**
1. Find "Grand Ballroom" in Dynamic Pricing Manager
2. Click "Edit Rules" button
3. Fill form:
   - Start Date: 2026-11-20
   - End Date: 2026-11-30
   - Multiplier: 1.5
   - Type: holiday
   - Reason: "Thanksgiving"
4. Click "Add Rule"
5. **Expected:**
   - Modal closes
   - New rule appears in Grand Ballroom section
   - Shows "$75/guest (Thanksgiving 1.5x multiplier Nov 20-30)"

**Test Price Calculation:**
1. Call getPriceForDate('V001', '2026-12-20')
2. **Expected:** Returns $65 (50 × 1.3)
3. Call getPriceForDate('V001', '2026-03-15')
4. **Expected:** Returns $50 (no rule, base price)

---

### 7. Test Ratings and Reviews System
**Location:** Owner Dashboard → Customer Ratings & Reviews

**Mock Data:** 2 ratings:
- R001: ⭐⭐⭐⭐⭐ (5 stars) John Smith - "Excellent venue..."
- R002: ⭐⭐⭐⭐ (4 stars) Robert Brown - "Great atmosphere..."

**Expected Display:**
```
Average Rating: 4.5 / 5
Reviews: 2

[Latest review first]
⭐⭐⭐⭐ | robert@example.com | "Great atmosphere..." | 2026-08-11
⭐⭐⭐⭐⭐ | john@example.com | "Excellent venue..." | 2026-05-26
```

**Test Add Rating:**
1. Call addRating('B001', 'OWN001', 'test@example.com', 5, 'Perfect event!')
2. **Expected:**
   - New rating appears at top of list
   - Average updates to 4.67 (5+5+4)/3
   - Venue avgRating updates

---

### 8. Test Verified Venues Badge
**Location:** Owner Dashboard → Managed Venues

**Expected:**
- Grand Ballroom: Shows "✓ Verified" (green)
- Executive Suite: Shows "✓ Verified" (green)
- (Any pending venue would show "⏳ Pending Approval")

---

### 9. Test Deposit Collection
**Location:** Owner Dashboard → Deposit Collection Dashboard

**Mock Data:** OWN001 bookings:
- B001: $1,000 deposit (paid ✓)
- B004: $800 deposit (paid ✓)
- B002: $500 deposit (pending - paid ⏳)

**Expected Display:**
```
Total Collected: $1,800
Total Due: $3,300

Pending Deposits:
- Sarah Johnson, Due: 2026-05-10, $500
- Emma Wilson, Due: 2026-06-15, $800
```

**Test processDeposit:**
1. Call processDeposit('B002', 500)
2. **Expected:**
   - B002.depositPaid becomes true
   - Collected increases to $2,300
   - Pending deposits list updates

---

### 10. Test Venue Verification Status Lifecycle
**Location:** AdminDashboard venue table

**Full Lifecycle:**
1. Start: V003 Diamond Hall status = "Pending"
2. Admin clicks "Approve" → status = "✓ Approved"
3. Admin clicks "Reject" (after reload/in pending list) → opens modal
4. Type reason: "Missing certificate"
5. Click "Reject Venue" → status = "✗ Rejected"
6. Filter shows: Pending (0), Approved (3), Rejected (1)

---

## Metrics Verification

### Admin Dashboard Metrics
```
Expected with mock data:
- Total Platform Revenue: $10,500 (only confirmed: B001 $5000 + B005 $3500)
- Platform Profit: $1,050 (10% of $10,500)
- Active Venues: 2 (V001, V002, V004 = 3 verified, but V003 pending)
- Total Owners: 2 (OWN001, OWN002)
- Pending Approvals: 2 (V003 + one more if added)
```

### Owner Dashboard Metrics (OWN001)
```
- Total Venue Earnings: $8,100 (net after commission)
  Calculation: ($5000 + $4000) - 10% = $9000 - $900
- Deposit Collection: $1,800 of $1,800 (B001 + B004)
- Booking Occupancy: 50% (2 confirmed of 4 total)
- Average Rating: 5.0 (from R001 only, as it's OWN001's)
```

---

## State Flow Verification

### Test acceptBooking Flow:
```
1. Initial: B002 status='Pending'
2. Click Accept
3. acceptBooking('B002') called
4. State updates: B002.status='Confirmed', B002.payment='Paid'
5. Metrics recalculate:
   - totalRevenue increases
   - platformProfit increases
   - occupancyRate increases
```

### Test setCommissionRate Flow:
```
1. Initial: commission=10%
2. Slider to 15%
3. setCommissionRate(15) called
4. commissionSettings.platformFeePercent = 15
5. useMemo recalculates:
   - platformProfit = totalRevenue * 0.15
   - getOwnerMetrics returns new netEarnings
6. All dashboards re-render with new values
```

---

## Console Verification
Run these in browser console:

```javascript
// Get useApp context
const { bookings, venues, getOwnerMetrics } = useApp()

// Verify owner metrics
console.log(getOwnerMetrics('OWN001'))
// Output: { totalEarnings: 9000, commission: 900, netEarnings: 8100, ... }

// Verify dynamic pricing
console.log(venues[0].pricePerGuest) // 50
// With rule: getPriceForDate('V001', '2026-12-20') // 65

// Verify deposits
console.log(bookings[0].deposit) // 1000 (20% of 5000)
```

---

## Success Criteria Checklist
- ✅ No console errors when loading either dashboard
- ✅ Accepting booking moves it to Confirmed tab
- ✅ Declining booking moves it to Cancelled tab
- ✅ Changing commission % updates owner earnings immediately
- ✅ Approving venue removes it from Pending tab
- ✅ Rejecting venue requires reason and removes from Pending
- ✅ Adding rating updates average and appears in list
- ✅ Dynamic pricing rule displays with calculated price
- ✅ Deposit calculations show 20% of booking amount
- ✅ All metrics cards display correct values

---

## Known Test Data
### Owner IDs:
- `OWN001` - Alex Thompson (2 venues, 4 bookings)
- `OWN002` - Maria Garcia (1 venue, 1 booking)

### Booking IDs:
- B001, B002, B003, B004, B005

### Venue IDs:
- V001, V002, V003, V004

### Default Rates:
- Commission: 10%
- Deposit: 20%
