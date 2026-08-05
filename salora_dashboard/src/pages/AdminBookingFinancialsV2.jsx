import { useEffect, useMemo, useState } from "react";
import { saloraV2 } from "../lib/saloraBookingV2Api";

const englishNumber = (value, maximumFractionDigits = 0) => `\u2066${Number(value || 0).toLocaleString("en-US", { maximumFractionDigits })}\u2069`;
const money = (value) => `\u2066${Number(value || 0).toLocaleString("en-US", { maximumFractionDigits: 2 })} ل.س\u2069`;
const recordId = (value) => `\u2066#${value ?? "-"}\u2069`;
const statusLabel = {
  due: "مستحقة",
  collected: "مُحصلة",
  partially_due: "مستحقة جزئياً",
  cancelled: "ملغاة",
  settlement_required: "مطلوب تسوية",
  settled: "تمت التسوية",
};

export default function AdminBookingFinancialsV2() {
  const [data, setData] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      setData(await saloraV2("/admin/booking-financials"));
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const rows = useMemo(() => data?.commissions?.data || [], [data]);

  if (loading) return <div className="card">جاري تحميل الحسابات...</div>;

  return (
    <div dir="rtl" style={{ display: "grid", gap: 16 }}>
      <div style={{ display: "flex", justifyContent: "space-between", gap: 12 }}>
        <div>
          <h1>تفاصيل حسابات الحجوزات والتسويات</h1>
          <p>مرتبطة بالساعات والعروض والتعديلات والإلغاءات والاستردادات.</p>
        </div>
        <button type="button" onClick={load}>تحديث</button>
      </div>

      {error ? <div className="alert alert-danger">{error}</div> : null}

      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(180px,1fr))", gap: 12 }}>
        <Summary title="عدد السجلات" value={englishNumber(data?.summary?.records)} />
        <Summary title="الأسعار النهائية" value={money(data?.summary?.final_prices_syp)} />
        <Summary title="المحتفظ به للمالكين" value={money(data?.summary?.owner_retained_syp)} />
        <Summary title="عمولة Salora" value={money(data?.summary?.commission_syp)} />
        <Summary title="المحصّل" value={money(data?.summary?.collected_syp)} />
        <Summary title="التسويات" value={money(data?.summary?.settlement_syp)} />
      </div>

      <div style={{ overflowX: "auto" }}>
        <table style={{ width: "100%", borderCollapse: "collapse" }}>
          <thead>
            <tr>
              <th>الحجز</th>
              <th>الصالة</th>
              <th>السعر النهائي</th>
              <th>المحتفظ به</th>
              <th>العمولة</th>
              <th>المحصّل</th>
              <th>التسوية</th>
              <th>الحالة</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id}>
                <td>{recordId(row.booking_id)}</td>
                <td>{recordId(row.venue_id)}</td>
                <td>{money(row.final_price_syp)}</td>
                <td>{money(row.owner_retained_syp)}</td>
                <td>{money(row.commission_syp)}</td>
                <td>{money(row.collected_syp)}</td>
                <td>{money(row.settlement_syp)}</td>
                <td>{statusLabel[row.status] || row.status}</td>
              </tr>
            ))}
            {!rows.length ? <tr><td colSpan="8">لا توجد سجلات بعد.</td></tr> : null}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function Summary({ title, value }) {
  return (
    <div className="card" style={{ padding: 16 }}>
      <div style={{ opacity: 0.7 }}>{title}</div>
      <strong style={{ fontSize: 20 }}>{value}</strong>
    </div>
  );
}
