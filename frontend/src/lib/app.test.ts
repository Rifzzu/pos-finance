import { describe, expect, it } from 'vitest';
import { formatRupiah, formatDateOnly } from '@/lib/format';
import { estimateCart } from '@/lib/cart';
import { canAccess, menuForRole } from '@/lib/menu';

describe('formatRupiah', () => {
  it('memformat string desimal', () => {
    expect(formatRupiah('111000.00')).toBe('Rp 111.000');
  });
  it('menangani null/undefined/kosong', () => {
    expect(formatRupiah(null)).toBe('Rp0');
    expect(formatRupiah(undefined)).toBe('Rp0');
    expect(formatRupiah('')).toBe('Rp0');
  });
  it('menangani negatif (variance)', () => {
    expect(formatRupiah('-5000.00')).toContain('5.000');
  });
});

describe('formatDateOnly', () => {
  it('membalik Y-m-d', () => {
    expect(formatDateOnly('2026-06-15')).toBe('15/06/2026');
  });
});

describe('estimateCart', () => {
  it('S1: 100rb -> total 111rb', () => {
    expect(estimateCart([{ price: '100000.00', qty: 1 }], 0)).toEqual({ subtotal: 100000, tax: 11000, total: 111000 });
  });
  it('diskon penuh -> total 0', () => {
    expect(estimateCart([{ price: '100000.00', qty: 1 }], 100000)).toEqual({ subtotal: 100000, tax: 0, total: 0 });
  });
  it('qty ganda', () => {
    expect(estimateCart([{ price: '50000.00', qty: 2 }], 0).total).toBe(111000);
  });
});

describe('menu & guard', () => {
  it('kasir: 3 menu, tanpa posting/users', () => {
    const paths = menuForRole('kasir').map((m) => m.to);
    expect(paths).toEqual(['/', '/kasir', '/transaksi']);
  });
  it('supervisor: dashboard, riwayat, produk', () => {
    expect(menuForRole('supervisor_pos').map((m) => m.to)).toEqual(['/', '/transaksi', '/produk']);
  });
  it('finance: dashboard, posting, rekonsiliasi', () => {
    expect(menuForRole('staff_finance').map((m) => m.to)).toEqual(['/', '/posting', '/rekonsiliasi']);
  });
  it('superadmin: semua 7 menu', () => {
    expect(menuForRole('superadmin')).toHaveLength(7);
  });
  it('canAccess menolak lintas role', () => {
    expect(canAccess('/posting', 'kasir')).toBe(false);
    expect(canAccess('/posting', 'manager_finance')).toBe(true);
    expect(canAccess('/users', 'manager_finance')).toBe(false);
    expect(canAccess('/kasir', 'supervisor_pos')).toBe(false);
  });
});
