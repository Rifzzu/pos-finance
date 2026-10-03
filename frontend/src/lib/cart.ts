export const TAX_RATE = 0.11;

export interface CartLineInput {
  price: string | number;
  qty: number;
}

export function estimateCart(lines: CartLineInput[], discount: number): { subtotal: number; tax: number; total: number } {
  const subtotal = lines.reduce((s, l) => s + Number(l.price) * l.qty, 0);
  const taxable = Math.max(0, subtotal - discount);
  const tax = Math.round(taxable * TAX_RATE * 100) / 100;
  return { subtotal, tax, total: subtotal - discount + tax };
}
