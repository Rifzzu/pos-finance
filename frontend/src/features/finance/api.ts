import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { financeApi } from '@/api/client';
import type { Paginated } from '@/types';

export interface JournalLine {
  id: number;
  account_code: string;
  debit: string;
  credit: string;
}

export interface Posting {
  id: number;
  idempotency_key: string;
  trx_number: string;
  entry_type: 'sale' | 'reversal';
  outlet_id: number;
  outlet_code: string;
  shift_id: number;
  business_date: string;
  payment_method: 'cash' | 'debit' | 'qris';
  net_sales_amount: string;
  tax_amount: string;
  total_amount: string;
  posted_at: string;
  journal_lines: JournalLine[];
  total_debit: string;
  total_credit: string;
  balanced: boolean;
}

export interface DailySales {
  outlet_id: number;
  business_date: string;
  totals: { cash: string; debit: string; qris: string };
  trx_count: number;
  net_sales: string;
  tax_total: string;
}

export interface MismatchReason {
  type: 'AMOUNT_DIFF' | 'COUNT_DIFF' | 'CASH_VARIANCE';
  method?: string;
  pos?: string | number;
  finance?: string | number;
  amount?: string;
}

export interface Reconciliation {
  id: number;
  outlet_id: number;
  outlet_code: string | null;
  business_date: string;
  status: 'pending' | 'matched' | 'mismatch' | 'resolved';
  pos_cash: string;
  pos_debit: string;
  pos_qris: string;
  fin_cash: string;
  fin_debit: string;
  fin_qris: string;
  pos_trx_count: number;
  fin_trx_count: number;
  cash_variance: string;
  mismatch_reasons: MismatchReason[] | null;
  run_by: number | null;
  run_at: string | null;
  resolved_by: number | null;
  resolved_at: string | null;
  resolution_note: string | null;
}

export interface OverviewRow {
  outlet_id: number;
  business_date: string;
  status: string;
  reconciliation_id: number | null;
}

export function usePostings(params: Record<string, string | number | undefined>) {
  return useQuery({
    queryKey: ['postings', params],
    queryFn: async (): Promise<Paginated<Posting>> => (await financeApi.get('/postings', { params })).data,
  });
}

export function usePostingDetail(id: string | undefined) {
  return useQuery({
    queryKey: ['posting', id],
    queryFn: async (): Promise<Posting> => (await financeApi.get(`/postings/${id}`)).data.data,
    enabled: !!id,
  });
}

export function useDailySales(outletId: number | null, date: string) {
  return useQuery({
    queryKey: ['daily-sales', outletId, date],
    queryFn: async (): Promise<DailySales> =>
      (await financeApi.get('/reports/daily-sales', { params: { outlet_id: outletId, business_date: date } })).data.data,
    enabled: outletId !== null,
  });
}

export function useReconOverview(days = 7) {
  return useQuery({
    queryKey: ['recon-overview', days],
    queryFn: async (): Promise<OverviewRow[]> =>
      (await financeApi.get('/reconciliations/overview', { params: { days } })).data.data,
  });
}

export function useRunRecon() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: { outlet_id: number; business_date: string }): Promise<{ reconciliation: Reconciliation; unsynced_count: number }> =>
      (await financeApi.post('/reconciliations/run', vars)).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['reconciliations'] });
      qc.invalidateQueries({ queryKey: ['recon-overview'] });
    },
  });
}

export function useReconciliations(params: Record<string, string | number | undefined>) {
  return useQuery({
    queryKey: ['reconciliations', params],
    queryFn: async (): Promise<Paginated<Reconciliation>> =>
      (await financeApi.get('/reconciliations', { params })).data,
  });
}

export function useReconDetail(id: string | undefined) {
  return useQuery({
    queryKey: ['reconciliation', id],
    queryFn: async (): Promise<Reconciliation> => (await financeApi.get(`/reconciliations/${id}`)).data.data,
    enabled: !!id,
  });
}

export function useResolveRecon() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: { id: number; resolution_note: string }): Promise<Reconciliation> =>
      (await financeApi.patch(`/reconciliations/${vars.id}/resolve`, { resolution_note: vars.resolution_note })).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['reconciliations'] });
      qc.invalidateQueries({ queryKey: ['reconciliation'] });
      qc.invalidateQueries({ queryKey: ['recon-overview'] });
    },
  });
}
