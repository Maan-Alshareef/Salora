import React from "react";
import { Navigate, Route, Routes, useLocation } from "react-router-dom";
import { AppProvider, useApp } from "./context/AppContext";
import { ROLES } from "./config/permissions";
import Layout from "./components/Layout";
import Login from "./pages/Auth/Login";
import RequiredPasswordChange from "./pages/Auth/RequiredPasswordChange";
import AdminDashboard from "./pages/AdminDashboard";
import ApprovalsCenter from "./pages/ApprovalsCenter";
import VenuesManagement from "./pages/VenuesManagement";
import ServicesManagement from "./pages/ServicesManagement";
import BookingsManagement from "./pages/BookingsManagement";
import PaymentsReview from "./pages/PaymentsReview";
import AdminRefunds from "./pages/AdminRefunds";
import AdminCommissions from "./pages/AdminCommissions";
import AdminBookingFinancialsV2 from "./pages/AdminBookingFinancialsV2";
import AdminFinanceHub from "./pages/AdminFinanceHub";
import OwnerBookingSettingsV2 from "./pages/Owner/OwnerBookingSettingsV2";
import OwnerWorkingHours from "./pages/Owner/OwnerWorkingHours";
import PaymentMethodsManagement from "./pages/PaymentMethodsManagement";
import OffersDiscounts from "./pages/OffersDiscounts";
import ComplaintsSupport from "./pages/ComplaintsSupport";
import SchedulingCalendar from "./pages/SchedulingCalendar";
import EventTypesTemplates from "./pages/EventTypesTemplates";
import UsersManagement from "./pages/UsersManagement";
import ReportsPage from "./pages/ReportsPage";
import ActivityLog from "./pages/ActivityLog";
import NotificationsPage from "./pages/NotificationsPage";
import SettingsPage from "./pages/SettingsPage";
import ReviewsManagement from "./pages/ReviewsManagement";
import ServiceCategoriesManagement from "./pages/ServiceCategoriesManagement";
import VenueRevisionsPage from "./pages/VenueRevisionsPage";
import OwnerDashboard from "./pages/Owner/OwnerDashboard";
import MyHalls from "./pages/Owner/MyHalls";
import OwnerBookings from "./pages/Owner/OwnerBookings";
import BookingCalendar from "./pages/Owner/BookingCalendar";
import RevenueEngine from "./pages/Owner/RevenueEngine";
import ReviewsLog from "./pages/Owner/ReviewsLog";
import OwnerProfile from "./pages/Owner/OwnerProfile";
import OwnerNotifications from "./pages/Owner/OwnerNotifications";
import OwnerPaymentStatus from "./pages/Owner/OwnerPaymentStatus";
import PayoutAccounts from "./pages/Owner/PayoutAccounts";
import OwnerServices from "./pages/Owner/OwnerServices";
import OwnerOffers from "./pages/Owner/OwnerOffers";
import OwnerComplaints from "./pages/Owner/OwnerComplaints";
import NotFound from "./pages/NotFound";
import VenueForm from "./pages/VenueForm";

const homeByRole = {
  [ROLES.ADMIN]: "/admin",
  [ROLES.OWNER]: "/owner",
};

function ProtectedDashboard({ role, children }) {
  const { currentRole, currentUser, authLoading } = useApp();
  const location = useLocation();

  if (authLoading) {
    return <div className="min-h-screen grid place-items-center bg-slate-950 text-slate-100">جاري التحقق من الجلسة...</div>;
  }

  const token = localStorage.getItem("salora_token");
  if (!token || !currentUser?.id || !currentRole) {
    return <Navigate to="/auth/login" replace state={{ from: location }} />;
  }

  if (currentUser.mustChangePassword) {
    return <Navigate to="/auth/change-required-password" replace state={{ from: location }} />;
  }

  if (role && currentRole !== role) {
    return <Navigate to={homeByRole[currentRole] || "/auth/login"} replace state={{ from: location }} />;
  }

  return <Layout>{children}</Layout>;
}

export default function App() {
  return (
    <AppProvider>
      <Routes>
        <Route path="/" element={<Navigate to="/auth/login" replace />} />
        <Route path="/auth/login" element={<Login />} />
        <Route path="/auth/change-required-password" element={<RequiredPasswordChange />} />

        <Route path="/admin" element={<ProtectedDashboard role={ROLES.ADMIN}><AdminDashboard /></ProtectedDashboard>} />
        <Route path="/admin/approvals" element={<ProtectedDashboard role={ROLES.ADMIN}><ApprovalsCenter /></ProtectedDashboard>} />
        <Route path="/admin/users" element={<ProtectedDashboard role={ROLES.ADMIN}><UsersManagement /></ProtectedDashboard>} />
        <Route path="/admin/venues" element={<ProtectedDashboard role={ROLES.ADMIN}><VenuesManagement /></ProtectedDashboard>} />
        <Route path="/admin/venue-revisions" element={<ProtectedDashboard role={ROLES.ADMIN}><VenueRevisionsPage /></ProtectedDashboard>} />
        <Route path="/admin/service-categories" element={<ProtectedDashboard role={ROLES.ADMIN}><ServiceCategoriesManagement /></ProtectedDashboard>} />
        <Route path="/admin/bookings" element={<ProtectedDashboard role={ROLES.ADMIN}><BookingsManagement /></ProtectedDashboard>} />
        <Route path="/admin/payment-methods" element={<ProtectedDashboard role={ROLES.ADMIN}><PaymentMethodsManagement /></ProtectedDashboard>} />
        <Route path="/admin/payments" element={<ProtectedDashboard role={ROLES.ADMIN}><PaymentsReview /></ProtectedDashboard>} />
        <Route path="/admin/refunds" element={<ProtectedDashboard role={ROLES.ADMIN}><AdminRefunds /></ProtectedDashboard>} />
        <Route path="/admin/commissions" element={<ProtectedDashboard role={ROLES.ADMIN}><AdminFinanceHub /></ProtectedDashboard>} />
        <Route path="/admin/booking-financials-v2" element={<Navigate to="/admin/commissions" replace />} />
        <Route path="/admin/services" element={<ProtectedDashboard role={ROLES.ADMIN}><ServicesManagement /></ProtectedDashboard>} />
        <Route path="/admin/offers" element={<ProtectedDashboard role={ROLES.ADMIN}><OffersDiscounts /></ProtectedDashboard>} />
        <Route path="/admin/reviews" element={<ProtectedDashboard role={ROLES.ADMIN}><ReviewsManagement /></ProtectedDashboard>} />
        <Route path="/admin/support" element={<ProtectedDashboard role={ROLES.ADMIN}><ComplaintsSupport /></ProtectedDashboard>} />
        <Route path="/admin/calendar" element={<ProtectedDashboard role={ROLES.ADMIN}><SchedulingCalendar /></ProtectedDashboard>} />
        <Route path="/admin/event-types" element={<ProtectedDashboard role={ROLES.ADMIN}><EventTypesTemplates /></ProtectedDashboard>} />
        <Route path="/admin/reports" element={<ProtectedDashboard role={ROLES.ADMIN}><ReportsPage /></ProtectedDashboard>} />
        <Route path="/admin/activity" element={<ProtectedDashboard role={ROLES.ADMIN}><ActivityLog /></ProtectedDashboard>} />
        <Route path="/admin/notifications" element={<ProtectedDashboard role={ROLES.ADMIN}><NotificationsPage /></ProtectedDashboard>} />
        <Route path="/admin/settings" element={<ProtectedDashboard role={ROLES.ADMIN}><SettingsPage /></ProtectedDashboard>} />
        <Route path="/admin/profile" element={<ProtectedDashboard role={ROLES.ADMIN}><OwnerProfile /></ProtectedDashboard>} />

        <Route path="/owner" element={<ProtectedDashboard role={ROLES.OWNER}><OwnerDashboard /></ProtectedDashboard>} />
        <Route path="/owner/halls" element={<ProtectedDashboard role={ROLES.OWNER}><MyHalls /></ProtectedDashboard>} />
        <Route path="/owner/add-hall" element={<ProtectedDashboard role={ROLES.OWNER}><VenueForm /></ProtectedDashboard>} />

        <Route path="/owner/bookings" element={<ProtectedDashboard role={ROLES.OWNER}><OwnerBookings /></ProtectedDashboard>} />
        <Route path="/owner/calendar" element={<ProtectedDashboard role={ROLES.OWNER}><BookingCalendar /></ProtectedDashboard>} />
        <Route path="/owner/payout-accounts" element={<ProtectedDashboard role={ROLES.OWNER}><PayoutAccounts /></ProtectedDashboard>} />
        <Route path="/owner/payments" element={<ProtectedDashboard role={ROLES.OWNER}><OwnerPaymentStatus /></ProtectedDashboard>} />
        <Route path="/owner/services" element={<ProtectedDashboard role={ROLES.OWNER}><OwnerServices /></ProtectedDashboard>} />
        <Route path="/owner/offers" element={<Navigate to="/owner/booking-settings-v2" replace />} />
        <Route path="/owner/booking-settings-v2" element={<ProtectedDashboard role={ROLES.OWNER}><OwnerBookingSettingsV2 /></ProtectedDashboard>} />
        <Route path="/owner/reviews" element={<ProtectedDashboard role={ROLES.OWNER}><ReviewsLog /></ProtectedDashboard>} />
        <Route path="/owner/complaints" element={<ProtectedDashboard role={ROLES.OWNER}><OwnerComplaints /></ProtectedDashboard>} />
        <Route path="/owner/revenue" element={<ProtectedDashboard role={ROLES.OWNER}><RevenueEngine /></ProtectedDashboard>} />
        <Route path="/owner/notifications" element={<ProtectedDashboard role={ROLES.OWNER}><OwnerNotifications /></ProtectedDashboard>} />
        <Route path="/owner/profile" element={<ProtectedDashboard role={ROLES.OWNER}><OwnerProfile /></ProtectedDashboard>} />
        <Route path="/owner/working-hours" element={<ProtectedDashboard role={ROLES.OWNER}><OwnerWorkingHours /></ProtectedDashboard>} />

        

        <Route path="*" element={<NotFound />} />
      </Routes>
    </AppProvider>
  );
}
