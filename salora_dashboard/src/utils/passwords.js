const PASSWORD_ALPHABET = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789";

export const strongPasswordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

export function generateTemporaryPassword() {
  const cryptoApi = globalThis.crypto;
  if (!cryptoApi?.getRandomValues) {
    throw new Error("المتصفح لا يدعم توليد كلمات مرور آمنة. استخدم متصفحاً حديثاً.");
  }

  const bytes = new Uint32Array(10);
  cryptoApi.getRandomValues(bytes);
  const random = Array.from(bytes, (value) => PASSWORD_ALPHABET[value % PASSWORD_ALPHABET.length]).join("");
  return `Salora@${random}`;
}
