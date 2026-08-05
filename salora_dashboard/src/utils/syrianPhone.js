const arabicDigits = "٠١٢٣٤٥٦٧٨٩";
const persianDigits = "۰۱۲۳۴۵۶۷۸۹";

export function normaliseSyrianPhone(value = "") {
  return String(value)
    .replace(/[٠-٩]/g, (digit) => String(arabicDigits.indexOf(digit)))
    .replace(/[۰-۹]/g, (digit) => String(persianDigits.indexOf(digit)))
    .replace(/\D/g, "")
    .slice(0, 10);
}

export function isValidSyrianPhone(value = "") {
  return /^09\d{8}$/.test(normaliseSyrianPhone(value));
}

export const syrianPhoneMessage = "أدخل رقم هاتف سوري صحيحاً من 10 أرقام يبدأ بـ 09.";
