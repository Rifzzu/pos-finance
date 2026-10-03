import { useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router';
import { toast } from 'sonner';
import { useAuthStore } from '@/store/auth';
import {
  useResyncTransaction,
  useTransactionDetail,
  useTransactions,
  useVoidTransaction,
} from '@/features/pos/api';
import { posApi } from '@/api/client';
import { ApiError } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination';
import { SyncBadge, TrxBadge } from '@/components/common/StatusBadge';
import { formatDateWib, formatRupiah } from '@/lib/format';

function FilterSelect({ label, value, onChange, options }: { label: string; value: string; onChange: (v: string) => void; options: { v: string; l: string }[] }) {
  return (
    <div className="space-y-1">
      <Label>{label}</Label>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger className="w-40">
          <SelectValue placeholder="Semua" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="__all__">Semua</SelectItem>
          {options.map((o) => (
            <SelectItem key={o.v} value={o.v}>
              {o.l}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

export function TransactionsPage() {
  const [sp, setSp] = useSearchParams();
  const get = (k: string) => sp.get(k) ?? '';
  const set = (k: string, v: string) => {
    const next = new URLSearchParams(sp);
    if (!v || v === '__all__') next.delete(k);
    else next.set(k, v);
    next.delete('page');
    setSp(next);
  };
  const params: Record<string, string | number | undefined> = {
    status: get('status') || undefined,
    payment_method: get('payment_method') || undefined,
    finance_sync_status: get('finance_sync_status') || undefined,
    date_from: get('date_from') || undefined,
    date_to: get('date_to') || undefined,
    page: sp.get('page') ? Number(sp.get('page')) : 1,
  };
  const { data, isLoading, isError } = useTransactions(params);

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">Riwayat Transaksi</h1>
      <div className="flex flex-wrap gap-2">
        <FilterSelect label="Status" value={get('status') || '__all__'} onChange={(v) => set('status', v)} options={[{ v: 'pending', l: 'pending' }, { v: 'paid', l: 'paid' }, { v: 'void', l: 'void' }]} />
        <FilterSelect label="Metode" value={get('payment_method') || '__all__'} onChange={(v) => set('payment_method', v)} options={[{ v: 'cash', l: 'cash' }, { v: 'debit', l: 'debit' }, { v: 'qris', l: 'qris' }]} />
        <FilterSelect label="Sync" value={get('finance_sync_status') || '__all__'} onChange={(v) => set('finance_sync_status', v)} options={[{ v: 'synced', l: 'synced' }, { v: 'pending', l: 'pending' }, { v: 'failed', l: 'failed' }, { v: 'none', l: 'none' }]} />
        <div className="space-y-1">
          <Label>Dari</Label>
          <Input type="date" value={get('date_from')} onChange={(e) => set('date_from', e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label>Sampai</Label>
          <Input type="date" value={get('date_to')} onChange={(e) => set('date_to', e.target.value)} />
        </div>
      </div>
      {isLoading ? (
        <Skeleton className="h-64" />
      ) : isError || !data ? (
        <p className="text-sm text-destructive">Gagal memuat transaksi.</p>
      ) : data.data.length === 0 ? (
        <p className="text-sm text-muted-foreground">Belum ada transaksi.</p>
      ) : (
        <>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nomor</TableHead>
                <TableHead>Total</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Sync</TableHead>
                <TableHead></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.data.map((t) => (
                <TableRow key={t.id}>
                  <TableCell>{t.trx_number}</TableCell>
                  <TableCell>{formatRupiah(t.grand_total)}</TableCell>
                  <TableCell>
                    <TrxBadge status={t.status} />
                  </TableCell>
                  <TableCell>
                    <SyncBadge status={t.finance_sync_status} />
                  </TableCell>
                  <TableCell>
                    <Button size="sm" variant="outline" asChild>
                      <Link to={`/transaksi/${t.id}`}>Detail</Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          <Pagination>
            <PaginationContent>
              <PaginationItem>
                <PaginationPrevious
                  onClick={() => set('page', String(Math.max(1, (data.meta.page ?? 1) - 1)))}
                />
              </PaginationItem>
              <PaginationItem>
                <PaginationLink isActive>
                  {data.meta.page} / {data.meta.last_page}
                </PaginationLink>
              </PaginationItem>
              <PaginationItem>
                <PaginationNext onClick={() => set('page', String(Math.min(data.meta.last_page, (data.meta.page ?? 1) + 1)))} />
              </PaginationItem>
            </PaginationContent>
          </Pagination>
        </>
      )}
    </div>
  );
}

export function TransactionDetailPage() {
  const { id } = useParams();
  const role = useAuthStore((s) => s.user?.role);
  const { data: t, isLoading, isError } = useTransactionDetail(id);
  const [voidOpen, setVoidOpen] = useState(false);
  const [reason, setReason] = useState('');
  const voidTrx = useVoidTransaction();
  const resync = useResyncTransaction();

  if (isLoading) return <Skeleton className="h-64" />;
  if (isError || !t) return <p className="text-sm text-destructive">Transaksi tidak ditemukan.</p>;

  const canVoid = (role === 'supervisor_pos' || role === 'superadmin') && t.status === 'paid';
  const canResync = (role === 'kasir' || role === 'supervisor_pos' || role === 'superadmin') &&
    (t.finance_sync_status === 'failed' || t.finance_sync_status === 'pending');

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">{t.trx_number}</h1>
      <div className="flex gap-2">
        <TrxBadge status={t.status} />
        <SyncBadge status={t.finance_sync_status} />
      </div>
      <Card>
        <CardHeader>
          <CardTitle>Item</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Produk</TableHead>
                <TableHead>Qty</TableHead>
                <TableHead>Harga</TableHead>
                <TableHead>Subtotal</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {t.items.map((i) => (
                <TableRow key={i.id}>
                  <TableCell>
                    {i.product_name} ({i.sku})
                  </TableCell>
                  <TableCell>{i.quantity}</TableCell>
                  <TableCell>{formatRupiah(i.unit_price)}</TableCell>
                  <TableCell>{formatRupiah(i.subtotal)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          <div className="mt-2 space-y-1 text-sm">
            <div className="flex justify-between"><span>Subtotal</span><span>{formatRupiah(t.subtotal)}</span></div>
            <div className="flex justify-between"><span>Diskon</span><span>{formatRupiah(t.discount_amount)}</span></div>
            <div className="flex justify-between"><span>PPN</span><span>{formatRupiah(t.tax_amount)}</span></div>
            <div className="flex justify-between font-bold"><span>Total</span><span>{formatRupiah(t.grand_total)}</span></div>
            {t.paid_amount && <div className="flex justify-between"><span>Dibayar / Kembalian</span><span>{formatRupiah(t.paid_amount)} / {formatRupiah(t.change_amount ?? 0)}</span></div>}
            {t.void_reason && <div><span className="font-medium">Alasan void:</span> {t.void_reason}</div>}
            {t.finance_last_error && <div className="text-red-600"><span className="font-medium">Sync error:</span> {t.finance_last_error}</div>}
            <div className="text-muted-foreground">Dibayar: {formatDateWib(t.paid_at)}</div>
          </div>
        </CardContent>
      </Card>
      <div className="flex gap-2">
        <Button
          variant="outline"
          onClick={async () => {
            try {
              const res = await posApi.get(`/transactions/${t.id}/receipt.pdf`, { responseType: 'blob' });
              const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
              const a = document.createElement('a');
              a.href = url;
              a.download = `struk-${t.trx_number.replace(/\//g, '-')}.pdf`;
              document.body.appendChild(a);
              a.click();
              a.remove();
              URL.revokeObjectURL(url);
            } catch {
              toast.error('Gagal mengunduh struk.');
            }
          }}
        >
          Unduh Struk (PDF)
        </Button>
        {canVoid && <Button variant="destructive" onClick={() => setVoidOpen(true)}>Void</Button>}
        {canResync && (
          <Button
            variant="outline"
            disabled={resync.isPending}
            onClick={() =>
              resync.mutate(t.id, {
                onSuccess: (d) => toast.success(`Resync: ${d.finance_sync_status}`),
                onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Resync gagal.'),
              })
            }
          >
            Resync Finance
          </Button>
        )}
      </div>
      <Dialog open={voidOpen} onOpenChange={setVoidOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Void {t.trx_number}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label>Alasan (wajib)</Label>
            <Textarea value={reason} onChange={(e) => setReason(e.target.value)} />
          </div>
          <div className="flex justify-end gap-2">
            <Button
              variant="destructive"
              disabled={!reason.trim() || voidTrx.isPending}
              onClick={() =>
                voidTrx.mutate(
                  { id: t.id, reason },
                  {
                    onSuccess: () => { toast.success('Transaksi di-void.'); setVoidOpen(false); },
                    onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Void gagal.'),
                  },
                )
              }
            >
              Konfirmasi Void
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
