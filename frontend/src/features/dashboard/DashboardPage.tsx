import { Link } from 'react-router';
import { useAuthStore } from '@/store/auth';
import { useTransactions } from '@/features/pos/api';
import { useDailySales, useReconOverview } from '@/features/finance/api';
import { useOutlets } from '@/features/pos/api';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { ReconBadge } from '@/components/common/StatusBadge';
import { formatRupiah } from '@/lib/format';

function today(): string {
  return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' });
}

export function DashboardPage() {
  const user = useAuthStore((s) => s.user);
  const isFinance = user?.role === 'staff_finance' || user?.role === 'manager_finance' || user?.role === 'superadmin';
  const isPos = user?.role === 'kasir' || user?.role === 'supervisor_pos' || user?.role === 'superadmin';

  if (isFinance && !isPos) return <FinanceDashboard />;
  if (isFinance && isPos && user?.role === 'superadmin') {
    return (
      <div className="space-y-6">
        <PosDashboard />
        <FinanceDashboard />
      </div>
    );
  }
  return <PosDashboard />;
}

function PosDashboard() {
  const user = useAuthStore((s) => s.user);
  const d = today();
  const { data, isLoading, isError } = useTransactions({ date_from: d, date_to: d, per_page: 100 });

  if (isLoading) {
    return (
      <div className="grid gap-4 md:grid-cols-4">
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-28" />
        ))}
      </div>
    );
  }

  if (isError || !data) {
    return <p className="text-sm text-destructive">Gagal memuat dashboard.</p>;
  }

  const list = data.data.filter((t) => t.status === 'paid');
  const total = list.reduce((s, t) => s + Number(t.grand_total), 0);
  const byMethod = (m: string) => list.filter((t) => t.payment_method === m).reduce((s, t) => s + Number(t.grand_total), 0);
  const unsynced = data.data.filter((t) => t.finance_sync_status === 'failed' || t.finance_sync_status === 'pending').length;

  const cards = [
    { title: 'Penjualan Hari Ini', value: formatRupiah(total) },
    { title: 'Jumlah Transaksi', value: String(list.length) },
    { title: 'Tunai / Debit / QRIS', value: `${formatRupiah(byMethod('cash'))} / ${formatRupiah(byMethod('debit'))} / ${formatRupiah(byMethod('qris'))}` },
    { title: 'Belum Synced', value: String(unsynced) },
  ];

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">Dashboard — {user?.role}</h1>
      <div className="grid gap-4 md:grid-cols-4">
        {cards.map((c) => (
          <Card key={c.title}>
            <CardHeader>
              <CardTitle className="text-sm font-medium text-muted-foreground">{c.title}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-lg font-bold">{c.value}</div>
            </CardContent>
          </Card>
        ))}
      </div>
      {unsynced > 0 && (
        <Button asChild variant="outline">
          <Link to="/transaksi?finance_sync_status=failed">Lihat transaksi belum synced ({unsynced})</Link>
        </Button>
      )}
    </div>
  );
}

function FinanceDashboard() {
  const d = today();
  const { data: outlets } = useOutlets();
  const firstOutlet = outlets?.[0]?.id ?? null;
  const { data: sales, isLoading: salesLoading } = useDailySales(firstOutlet, d);
  const { data: overview, isLoading: ovLoading } = useReconOverview(7);

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">Dashboard Finance</h1>
      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Penjualan Ledger Hari Ini {firstOutlet ? `(outlet ${firstOutlet})` : ''}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {salesLoading ? (
              <Skeleton className="h-8" />
            ) : (
              <div className="space-y-1 text-sm">
                <div className="flex justify-between"><span>Tunai</span><span>{formatRupiah(sales?.totals.cash ?? 0)}</span></div>
                <div className="flex justify-between"><span>Debit</span><span>{formatRupiah(sales?.totals.debit ?? 0)}</span></div>
                <div className="flex justify-between"><span>QRIS</span><span>{formatRupiah(sales?.totals.qris ?? 0)}</span></div>
                <div className="flex justify-between font-bold"><span>Transaksi</span><span>{sales?.trx_count ?? 0}</span></div>
              </div>
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">Rekonsiliasi 7 Hari Terakhir</CardTitle>
          </CardHeader>
          <CardContent>
            {ovLoading ? (
              <Skeleton className="h-8" />
            ) : (overview ?? []).length === 0 ? (
              <p className="text-sm text-muted-foreground">Belum ada aktivitas.</p>
            ) : (
              <div className="grid grid-cols-7 gap-1">
                {(overview ?? []).slice(0, 28).map((r) => (
                  <Link
                    key={`${r.outlet_id}-${r.business_date}`}
                    to={`/rekonsiliasi?outlet_id=${r.outlet_id}&business_date=${r.business_date}`}
                    title={`Outlet ${r.outlet_id} — ${r.business_date}`}
                  >
                    <ReconBadge status={r.status === 'not_run' ? 'pending' : r.status} />
                  </Link>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
