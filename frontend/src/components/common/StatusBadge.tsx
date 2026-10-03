import { Badge } from '@/components/ui/badge';

const trxVariant: Record<string, 'secondary' | 'success' | 'dark'> = {
  pending: 'secondary',
  paid: 'success',
  void: 'dark',
};

const syncVariant: Record<string, 'success' | 'warning' | 'danger'> = {
  synced: 'success',
  pending: 'warning',
  failed: 'danger',
};

const reconVariant: Record<string, 'secondary' | 'success' | 'danger' | 'info'> = {
  pending: 'secondary',
  matched: 'success',
  mismatch: 'danger',
  resolved: 'info',
};

export function TrxBadge({ status }: { status: string }) {
  return <Badge variant={trxVariant[status] ?? 'secondary'}>{status}</Badge>;
}

export function SyncBadge({ status }: { status: string }) {
  return <Badge variant={syncVariant[status] ?? 'secondary'}>{status}</Badge>;
}

export function ReconBadge({ status }: { status: string }) {
  return <Badge variant={reconVariant[status] ?? 'secondary'}>{status}</Badge>;
}
