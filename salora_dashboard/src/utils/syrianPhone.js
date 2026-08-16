const arabicDigits = "٠١٢٣٤٥٦٧٨٩";
const persianDigits = "۰۱۲۳۴۵۶۷۸۹";

function phoneDigits(value = "") {
  let digits = String(value)
    .replace(/[٠-٩]/g, (digit) => String(arabicDigits.indexOf(digit)))
    .replace(/[۰-۹]/g, (digit) => String(persianDigits.indexOf(digit)))
    .replace(/\D/g, "");

  // Backward compatibility with old records stored as +9639XXXXXXXX.
  if (digits.startsWith("9639") && digits.length === 12) {
    digits = `0${digits.slice(3)}`;
  }
  return digits;
}

export function normaliseSyrianPhone(value = "") {
  return phoneDigits(value).slice(0, 10);
}

export function isValidSyrianPhone(value = "") {
  return /^\d{10}$/.test(phoneDigits(value));
}

export const syrianPhoneMessage = "رقم الهاتف يجب أن يتكون من 10 أرقام فقط.";
