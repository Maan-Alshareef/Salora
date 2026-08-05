import React from "react";
import HallCreateForm from "./OwnerBookingSettingsV2";

class AddHallErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    console.error("Add hall page render error", error, info);
  }

  render() {
    if (this.state.error) {
      return (
        <div dir="rtl" className="m-8 rounded-3xl border border-red-500/40 bg-red-950/30 p-8 text-right">
          <h1 className="text-2xl font-black text-red-200">تعذر عرض نموذج إضافة الصالة</h1>
          <p className="mt-3 text-red-100">
            {this.state.error?.message || "حدث خطأ داخل نموذج الصالة."}
          </p>
        </div>
      );
    }

    return this.props.children;
  }
}

export default function AddEditHall() {
  return (
    <AddHallErrorBoundary>
      <HallCreateForm />
    </AddHallErrorBoundary>
  );
}