import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { posApi } from '@/api/client';
import type { Paginated } from '@/types';

export interface Product {
  id: number;
  sku: string;
  name: string;
  price: string;
  is_active: boolean;
}

export interface Outlet {
  id: number;
  code: string;
  name: string;
  address: string | null;
  is_active: boolean;
}

export interface Shift {
  id: number;
  outlet_id: number;
  outlet_code: string;
  cashier_id: number;
  business_date: string;
  opened_at: string;
  closed_at: string | null;
  opening_cash: string;
  expected_cash: string | null;
  actual_cash: string | null;
  cash_variance: string | null;
  status: 'open' | 'closed';
}

export interface ShiftSummary {
  paid_count: number;
  void_count: number;
  pending_count: number;
  totals: { cash: string; debit: string; qris: string };
  expected_cash: string;
}

export interface TrxItem {
  id: number;
  product_id: number;
  sku: string;
  product_name: string;
  quantity: number;
  unit_price: string;
  subtotal: string;
}

export interface Transaction {
  id: number;
  trx_number: string;
  outlet_id: number;
  outlet_code: string;
  shift_id: number;
  cashier_id: number;
  business_date: string;
  status: 'pending' | 'paid' | 'void';
  subtotal: string;
  discount_amount: string;
  tax_amount: string;
  grand_total: string;
  payment_method: 'cash' | 'debit' | 'qris' | null;
  paid_amount: string | null;
  change_amount: string | null;
  paid_at: string | null;
  void_reason: string | null;
  voided_by: number | null;
  voided_at: string | null;
  finance_sync_status: 'none' | 'pending' | 'synced' | 'failed';
  finance_synced_at: string | null;
  finance_last_error: string | null;
  items: TrxItem[];
}

export interface CloseResult {
  shift: Shift;
  unsynced_count: number;
}

export function useOutlets() {
  return useQuery({
    queryKey: ['outlets'],
    queryFn: async (): Promise<Outlet[]> => (await posApi.get('/outlets')).data.data,
  });
}

export interface ProductFilter {
  search?: string;
  is_active?: string;
  page?: number;
  per_page?: number;
}

export function useProducts(filter: ProductFilter) {
  return useQuery({
    queryKey: ['products', filter],
    queryFn: async (): Promise<Paginated<Product>> =>
      (await posApi.get('/products', { params: filter })).data,
  });
}

export function useCurrentShift() {
  return useQuery({
    queryKey: ['shift-current'],
    queryFn: async (): Promise<Shift | null> => {
      try {
        return (await posApi.get('/shifts/current')).data.data as Shift;
      } catch (e: unknown) {
        if ((e as { status?: number }).status === 404) return null;
        throw e;
      }
    },
    retry: false,
  });
}

export function useShiftSummary(id: number | null) {
  return useQuery({
    queryKey: ['shift-summary', id],
    queryFn: async (): Promise<{ shift: Shift; summary: ShiftSummary }> =>
      (await posApi.get(`/shifts/${id}/summary`)).data.data,
    enabled: id !== null,
  });
}

export function useOpenShift() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (opening_cash: number): Promise<Shift> =>
      (await posApi.post('/shifts/open', { opening_cash })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['shift-current'] }),
  });
}

export function useCloseShift() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: { id: number; actual_cash: number }): Promise<CloseResult> =>
      (await posApi.post(`/shifts/${vars.id}/close`, { actual_cash: vars.actual_cash })).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['shift-current'] });
      qc.invalidateQueries({ queryKey: ['transactions'] });
    },
  });
}

export function useCreateTransaction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: {
      items: { product_id: number; quantity: number }[];
      discount_amount?: number;
    }): Promise<Transaction> => (await posApi.post('/transactions', vars)).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['transactions'] });
      qc.invalidateQueries({ queryKey: ['shift-summary'] });
    },
  });
}

export function usePayTransaction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: { id: number; payment_method: string; paid_amount: number | string }): Promise<Transaction> =>
      (await posApi.post(`/transactions/${vars.id}/pay`, vars)).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['transactions'] });
      qc.invalidateQueries({ queryKey: ['shift-summary'] });
    },
  });
}

export function useVoidTransaction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: { id: number; reason: string }): Promise<Transaction> =>
      (await posApi.post(`/transactions/${vars.id}/void`, vars)).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['transactions'] });
      qc.invalidateQueries({ queryKey: ['shift-summary'] });
    },
  });
}

export function useResyncTransaction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number): Promise<Transaction> =>
      (await posApi.post(`/transactions/${id}/resync-finance`)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['transactions'] }),
  });
}

export function useTransactions(params: Record<string, string | number | undefined>) {
  return useQuery({
    queryKey: ['transactions', params],
    queryFn: async (): Promise<Paginated<Transaction>> => (await posApi.get('/transactions', { params })).data,
  });
}

export function useTransactionDetail(id: string | undefined) {
  return useQuery({
    queryKey: ['transaction', id],
    queryFn: async (): Promise<Transaction> => (await posApi.get(`/transactions/${id}`)).data.data,
    enabled: !!id,
  });
}

export function useSaveProduct() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: { id?: number; sku: string; name: string; price: number | string }): Promise<Product> => {
      const { id, ...body } = vars;
      const res = id ? await posApi.patch(`/products/${id}`, body) : await posApi.post('/products', body);
      return res.data.data as Product;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['products'] }),
  });
}

export function useProductStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: { id: number; is_active: boolean }): Promise<Product> =>
      (await posApi.patch(`/products/${vars.id}/status`, { is_active: vars.is_active })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['products'] }),
  });
}
