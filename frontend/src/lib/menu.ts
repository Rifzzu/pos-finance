import type { Role } from '@/types';

export interface MenuItem {
  to: string;
  label: string;
  roles: Role[];
}

export const MENU: MenuItem[] = [
  { to: '/', label: 'Dashboard', roles: ['kasir', 'supervisor_pos', 'staff_finance', 'manager_finance', 'superadmin'] },
  { to: '/kasir', label: 'Layar Kasir', roles: ['kasir', 'superadmin'] },
  { to: '/transaksi', label: 'Riwayat Transaksi', roles: ['kasir', 'supervisor_pos', 'superadmin'] },
  { to: '/produk', label: 'Produk', roles: ['supervisor_pos', 'superadmin'] },
  { to: '/posting', label: 'Posting & Jurnal', roles: ['staff_finance', 'manager_finance', 'superadmin'] },
  { to: '/rekonsiliasi', label: 'Rekonsiliasi EOD', roles: ['staff_finance', 'manager_finance', 'superadmin'] },
  { to: '/users', label: 'Manajemen User', roles: ['superadmin'] },
];

export function menuForRole(role: Role): MenuItem[] {
  return MENU.filter((m) => m.roles.includes(role));
}

export function canAccess(path: string, role: Role): boolean {
  const item = MENU.find((m) => m.to === path);
  if (!item) return true;
  return item.roles.includes(role);
}
