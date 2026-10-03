import { useParams } from 'react-router';
import { useReconDetail } from '@/features/finance/api';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ReconBadge } from '@/components/common/StatusBadge';
import { formatRupiah } from '@/lib/format';

export function ReconciliationDetailPage() {
  const { id } = useParams();
  const { data: r, isLoading, isError } = useReconDetail(id);

  if (isLoading) return <Skeleton className="h-64" />;
  if (isError || !r) return <p className="text-sm text-destructive">Rekonsiliasi tidak ditemukan.</p>;

  const rows = [
    { label: 'Tunai', pos: r.pos_cash, fin: r.fin_cash },
    { label: 'Debit', pos: r.pos_debit, fin: r.fin_debit },
    { label: 'QRIS', pos: r.pos_qris, fin: r.fin_qris },
  ];

  return (
    <div className="space-y-4">
      <h1 className="flex items-center gap-2 text-xl font-bold">
        Rekonsiliasi #{r.id} <ReconBadge status={r.status} />
      </h1>
      <Card>
        <CardHeader>
          <CardTitle>
            Outlet {r.outlet_code ?? r.outlet_id} — {r.business_date}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead></TableHead>
                <TableHead>POS</TableHead>
                <TableHead>Finance</TableHead>
                <TableHead>Selisih</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((x) => {
                const diff = Number(x.pos) - Number(x.fin);
                return (
                  <TableRow key={x.label}>
                    <TableCell>{x.label}</TableCell>
                    <TableCell>{formatRupiah(x.pos)}</TableCell>
                    <TableCell>{formatRupiah(x.fin)}</TableCell>
                    <TableCell className={diff !== 0 ? 'font-bold text-red-600' : ''}>{formatRupiah(diff)}</TableCell>
                  </TableRow>
                );
              })}
              <TableRow>
                <TableCell>Jumlah Transaksi</TableCell>
                <TableCell>{r.pos_trx_count}</TableCell>
                <TableCell>{r.fin_trx_count}</TableCell>
                <TableCell className={r.pos_trx_count !== r.fin_trx_count ? 'font-bold text-red-600' : ''}>
                  {r.pos_trx_count - r.fin_trx_count}
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
          <p className="mt-2 text-sm">Cash variance: <b>{formatRupiah(r.cash_variance)}</b></p>
          {r.resolution_note && <p className="mt-1 text-sm">Catatan resolve: {r.resolution_note}</p>}
        </CardContent>
      </Card>
    </div>
  );
}
