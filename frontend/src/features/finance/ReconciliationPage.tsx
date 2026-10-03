import { useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { useAuthStore } from '@/store/auth';
import {
  useReconciliations,
  useResolveRecon,
  useRunRecon,
  type MismatchReason,
  type Reconciliation,
} from '@/features/finance/api';
import { useOutlets } from '@/features/pos/api';
import { ApiError } from '@/types';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { ReconBadge } from '@/components/common/StatusBadge';
import { formatRupiah } from '@/lib/format';

function todayWib(): string {
  return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' });
}

function reasonText(r: MismatchReason): string {
  if (r.type === 'AMOUNT_DIFF') return `Selisih ${r.method}: POS ${formatRupiah(r.pos ?? 0)} vs Finance ${formatRupiah(r.finance ?? 0)}`;
  if (r.type === 'COUNT_DIFF') return `Selisih jumlah transaksi: POS ${r.pos} vs Finance ${r.finance}`;
  if (r.type === 'CASH_VARIANCE') return `Selisih kas fisik: ${formatRupiah(r.amount ?? 0)}`;
  return JSON.stringify(r);
}

function CompareTable({ r }: { r: Reconciliation }) {
  const rows = [
    { label: 'Tunai', pos: r.pos_cash, fin: r.fin_cash },
    { label: 'Debit', pos: r.pos_debit, fin: r.fin_debit },
    { label: 'QRIS', pos: r.pos_qris, fin: r.fin_qris },
  ];
  return (
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
  );
}

export function ReconciliationPage() {
  const role = useAuthStore((s) => s.user?.role);
  const [sp] = useSearchParams();
  const { data: outlets } = useOutlets();
  const [outletId, setOutletId] = useState(sp.get('outlet_id') ?? '');
  const [date, setDate] = useState(sp.get('business_date') ?? todayWib());
  const [result, setResult] = useState<Reconciliation | null>(null);
  const [alert, setAlert] = useState('');
  const [resolveOpen, setResolveOpen] = useState(false);
  const [note, setNote] = useState('');
  const run = useRunRecon();
  const resolve = useResolveRecon();
  const history = useReconciliations({ outlet_id: outletId || undefined, per_page: 10 });

  const canResolve = (role === 'manager_finance' || role === 'superadmin') && result?.status === 'mismatch';

  const doRun = () => {
    setAlert('');
    run.mutate(
      { outlet_id: Number(outletId), business_date: date },
      {
        onSuccess: (d) => setResult(d.reconciliation),
        onError: (e) => setAlert(e instanceof ApiError ? e.message : 'Gagal menjalankan rekonsiliasi.'),
      },
    );
  };

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">Rekonsiliasi EOD</h1>
      <Card>
        <CardContent className="flex flex-wrap items-end gap-2 pt-4">
          <div className="space-y-1">
            <Label>Outlet</Label>
            <Select value={outletId} onValueChange={setOutletId}>
              <SelectTrigger className="w-48">
                <SelectValue placeholder="Pilih outlet" />
              </SelectTrigger>
              <SelectContent>
                {(outlets ?? []).map((o) => (
                  <SelectItem key={o.id} value={String(o.id)}>
                    {o.code} — {o.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1">
            <Label>Tanggal</Label>
            <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
          </div>
          <Button onClick={doRun} disabled={!outletId || !date || run.isPending}>
            Jalankan Rekonsiliasi
          </Button>
          {result?.status === 'mismatch' && (
            <Button variant="outline" onClick={doRun} disabled={run.isPending}>
              Jalankan Ulang
            </Button>
          )}
          {canResolve && <Button variant="destructive" onClick={() => setResolveOpen(true)}>Resolve</Button>}
        </CardContent>
      </Card>

      {alert && (
        <Alert variant="destructive">
          <AlertDescription>{alert}</AlertDescription>
        </Alert>
      )}

      {result && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              Hasil <ReconBadge status={result.status} />
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <CompareTable r={result} />
            <p className="text-sm">
              Cash variance: <b className={Number(result.cash_variance) !== 0 ? 'text-red-600' : ''}>{formatRupiah(result.cash_variance)}</b>
            </p>
            {(result.mismatch_reasons ?? []).length > 0 && (
              <ul className="list-disc space-y-1 pl-5 text-sm">
                {(result.mismatch_reasons ?? []).map((r, i) => (
                  <li key={i}>{reasonText(r)}</li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Riwayat</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Tanggal</TableHead>
                <TableHead>Outlet</TableHead>
                <TableHead>Status</TableHead>
                <TableHead></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(history.data?.data ?? []).map((r) => (
                <TableRow key={r.id}>
                  <TableCell>{r.business_date}</TableCell>
                  <TableCell>{r.outlet_code ?? r.outlet_id}</TableCell>
                  <TableCell>
                    <ReconBadge status={r.status} />
                  </TableCell>
                  <TableCell>
                    <Button size="sm" variant="outline" onClick={() => setResult(r)}>
                      Lihat
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          <div className="mt-2 text-sm text-muted-foreground">
            Detail penuh: <Link className="underline" to={result ? `/rekonsiliasi/${result.id}` : '/rekonsiliasi'}>buka</Link>
          </div>
        </CardContent>
      </Card>

      <Dialog open={resolveOpen} onOpenChange={setResolveOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Resolve mismatch</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label>Catatan (wajib)</Label>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} />
          </div>
          <DialogFooter>
            <Button
              disabled={!note.trim() || resolve.isPending}
              onClick={() =>
                result &&
                resolve.mutate(
                  { id: result.id, resolution_note: note },
                  {
                    onSuccess: (d) => { setResult(d); setResolveOpen(false); setNote(''); },
                    onError: (e) => setAlert(e instanceof ApiError ? e.message : 'Resolve gagal.'),
                  },
                )
              }
            >
              Simpan Resolve
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
