import { useMemo, useState } from 'react';
import { useForm, type Resolver } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import {
  useCloseShift,
  useCreateTransaction,
  useCurrentShift,
  useOpenShift,
  usePayTransaction,
  useProducts,
  useShiftSummary,
  useTransactions,
  type Product,
} from '@/features/pos/api';
import { ApiError } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { SyncBadge, TrxBadge } from '@/components/common/StatusBadge';
import { formatRupiah } from '@/lib/format';
import { estimateCart } from '@/lib/cart';
import { useDebounce } from '@/hooks/use-debounce';

interface CartLine {
  product: Product;
  qty: number;
}

function OpenShiftForm() {
  const open = useOpenShift();
  const form = useForm<{ opening_cash: number }>({ defaultValues: { opening_cash: 0 } });
  return (
    <Card className="max-w-sm">
      <CardHeader>
        <CardTitle>Buka Shift</CardTitle>
      </CardHeader>
      <CardContent>
        <form
          className="space-y-4"
          onSubmit={form.handleSubmit((v) =>
            open.mutate(v.opening_cash, {
              onSuccess: () => toast.success('Shift dibuka.'),
              onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Gagal membuka shift.'),
            }),
          )}
        >
          <div className="space-y-2">
            <label className="text-sm font-medium">Modal kas awal (Rp)</label>
            <Input type="number" min={0} {...form.register('opening_cash', { valueAsNumber: true })} />
          </div>
          <Button type="submit" disabled={open.isPending}>
            Buka Shift
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

const paySchema = z.object({
  payment_method: z.enum(['cash', 'debit', 'qris']),
  paid_amount: z.coerce.number().min(0),
});

function PayDialog({
  trx,
  onDone,
}: {
  trx: { id: number; grand_total: string } | null;
  onDone: () => void;
}) {
  const pay = usePayTransaction();
  const form = useForm<z.infer<typeof paySchema>>({
    resolver: zodResolver(paySchema) as Resolver<z.infer<typeof paySchema>>,
    defaultValues: { payment_method: 'cash', paid_amount: 0 },
  });
  const method = form.watch('payment_method');
  const paid = form.watch('paid_amount') ?? 0;
  const grand = Number(trx?.grand_total ?? 0);

  return (
    <Dialog open={!!trx} onOpenChange={(o) => !o && onDone()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Bayar {formatRupiah(grand)}</DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <form
            className="space-y-4"
            onSubmit={form.handleSubmit((v) =>
              pay.mutate(
                { id: trx!.id, payment_method: v.payment_method, paid_amount: v.payment_method === 'cash' ? v.paid_amount : grand },
                {
                  onSuccess: (d) => {
                    if (d.finance_sync_status !== 'synced') {
                      toast.warning('Pembayaran sukses, tetapi sync ke Finance gagal. Bisa resync nanti.');
                    } else {
                      toast.success(`Dibayar. Kembalian ${formatRupiah(d.change_amount ?? 0)}`);
                    }
                    onDone();
                  },
                  onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Gagal membayar.'),
                },
              ),
            )}
          >
            <FormField
              control={form.control}
              name="payment_method"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Metode</FormLabel>
                  <Select
                    value={field.value}
                    onValueChange={(m) => {
                      field.onChange(m);
                      if (m !== 'cash') form.setValue('paid_amount', grand);
                    }}
                  >
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="cash">Tunai</SelectItem>
                      <SelectItem value="debit">Debit</SelectItem>
                      <SelectItem value="qris">QRIS</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            {method === 'cash' ? (
              <FormField
                control={form.control}
                name="paid_amount"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Uang diterima (Rp)</FormLabel>
                    <FormControl>
                      <Input type="number" min={0} {...field} />
                    </FormControl>
                    <FormMessage />
                    <p className="text-sm text-muted-foreground">Kembalian: {formatRupiah(Math.max(0, paid - grand))}</p>
                  </FormItem>
                )}
              />
            ) : (
              <p className="text-sm text-muted-foreground">Nominal otomatis = {formatRupiah(grand)}</p>
            )}
            <DialogFooter>
              <Button type="submit" disabled={pay.isPending}>
                Bayar
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}

function CloseShiftDialog({ shiftId, expected, onDone }: { shiftId: number; expected: string; onDone: (unsynced: number) => void }) {
  const [open, setOpen] = useState(false);
  const [actual, setActual] = useState(0);
  const close = useCloseShift();
  const variance = actual - Number(expected);

  return (
    <>
      <Button variant="outline" onClick={() => setOpen(true)}>
        Tutup Shift
      </Button>
      <Dialog
        open={open}
        onOpenChange={setOpen}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Tutup Shift</DialogTitle>
          </DialogHeader>
          <div className="space-y-2 text-sm">
            <p>
              Expected cash: <b>{formatRupiah(expected)}</b>
            </p>
            <label className="font-medium">Kas fisik (Rp)</label>
            <Input type="number" min={0} value={actual} onChange={(e) => setActual(Number(e.target.value))} />
            <p>
              Selisih: <b className={variance !== 0 ? 'text-red-600' : ''}>{formatRupiah(variance)}</b>
            </p>
          </div>
          <DialogFooter>
            <Button
              disabled={close.isPending}
              onClick={() =>
                close.mutate(
                  { id: shiftId, actual_cash: actual },
                  {
                    onSuccess: (d) => {
                      toast.success(`Shift ditutup. Selisih ${formatRupiah(d.shift.cash_variance ?? 0)}`);
                      if (d.unsynced_count > 0) toast.warning(`${d.unsynced_count} transaksi belum synced.`);
                      setOpen(false);
                      onDone(d.unsynced_count);
                    },
                    onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Gagal menutup shift.'),
                  },
                )
              }
            >
              Konfirmasi Tutup
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

export function CashierPage() {
  const { data: shift, isLoading } = useCurrentShift();
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search);
  const { data: products } = useProducts({ search: debounced || undefined, is_active: '1', per_page: 50 });
  const [cart, setCart] = useState<CartLine[]>([]);
  const [discount, setDiscount] = useState(0);
  const [payTrx, setPayTrx] = useState<{ id: number; grand_total: string } | null>(null);
  const create = useCreateTransaction();
  const summary = useShiftSummary(shift?.id ?? null);
  const shiftTrx = useTransactions(shift ? { shift_id: shift.id, per_page: 50 } : { per_page: 1, status: '__none__' });

  const est = useMemo(
    () => estimateCart(cart.map((l) => ({ price: l.product.price, qty: l.qty })), discount),
    [cart, discount],
  );

  if (isLoading) return <Skeleton className="h-40" />;
  if (!shift) return <OpenShiftForm />;

  const addToCart = (p: Product) =>
    setCart((c) => {
      const found = c.find((l) => l.product.id === p.id);
      if (found) return c.map((l) => (l.product.id === p.id ? { ...l, qty: l.qty + 1 } : l));
      return [...c, { product: p, qty: 1 }];
    });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-xl font-bold">
          Shift #{shift.id} — {shift.outlet_code} ({formatRupiah(shift.opening_cash)})
        </h1>
        <CloseShiftDialog shiftId={shift.id} expected={summary.data?.summary.expected_cash ?? shift.opening_cash} onDone={() => {}} />
      </div>

      {summary.data && (
        <Card>
          <CardHeader>
            <CardTitle>Ringkasan Shift</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-2 text-sm sm:grid-cols-3 lg:grid-cols-6">
              <div>Transaksi paid: <b>{summary.data.summary.paid_count}</b></div>
              <div>Pending: <b>{summary.data.summary.pending_count}</b></div>
              <div>Void: <b>{summary.data.summary.void_count}</b></div>
              <div>Tunai: <b>{formatRupiah(summary.data.summary.totals.cash)}</b></div>
              <div>Debit: <b>{formatRupiah(summary.data.summary.totals.debit)}</b></div>
              <div>QRIS: <b>{formatRupiah(summary.data.summary.totals.qris)}</b></div>
            </div>
            <p className="mt-2 text-sm">Expected cash: <b>{formatRupiah(summary.data.summary.expected_cash)}</b></p>
          </CardContent>
        </Card>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Produk</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder="Cari produk..." value={search} onChange={(e) => setSearch(e.target.value)} />
            <div className="max-h-96 space-y-1 overflow-auto">
              {(products?.data ?? []).map((p) => (
                <div key={p.id} className="flex items-center justify-between gap-2 border-b py-2 text-sm">
                  <div>
                    <div className="font-medium">{p.name}</div>
                    <div className="text-muted-foreground">
                      {p.sku} — {formatRupiah(p.price)}
                    </div>
                  </div>
                  <Button size="sm" onClick={() => addToCart(p)}>
                    Tambah
                  </Button>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Keranjang</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {cart.map((l) => (
              <div key={l.product.id} className="flex items-center gap-2 text-sm">
                <span className="flex-1">{l.product.name}</span>
                <Input
                  type="number"
                  min={1}
                  className="w-16"
                  value={l.qty}
                  onChange={(e) =>
                    setCart((c) => c.map((x) => (x.product.id === l.product.id ? { ...x, qty: Math.max(1, Number(e.target.value)) } : x)))
                  }
                />
                <Button size="sm" variant="ghost" onClick={() => setCart((c) => c.filter((x) => x.product.id !== l.product.id))}>
                  Hapus
                </Button>
              </div>
            ))}
            <div className="flex items-center gap-2 text-sm">
              <span className="flex-1">Diskon (Rp)</span>
              <Input type="number" min={0} className="w-32" value={discount} onChange={(e) => setDiscount(Number(e.target.value))} />
            </div>
            <div className="space-y-1 border-t pt-2 text-sm">
              <div className="flex justify-between">
                <span>Subtotal</span>
                <span>{formatRupiah(est.subtotal)}</span>
              </div>
              <div className="flex justify-between">
                <span>PPN 11%</span>
                <span>{formatRupiah(est.tax)}</span>
              </div>
              <div className="flex justify-between font-bold">
                <span>Total</span>
                <span>{formatRupiah(est.total)}</span>
              </div>
            </div>
            <Button
              className="w-full"
              disabled={cart.length === 0 || create.isPending}
              onClick={() =>
                create.mutate(
                  { items: cart.map((l) => ({ product_id: l.product.id, quantity: l.qty })), discount_amount: discount },
                  {
                    onSuccess: (t) => {
                      toast.success(`Transaksi ${t.trx_number} dibuat: ${formatRupiah(t.grand_total)}`);
                      setCart([]);
                      setDiscount(0);
                      setPayTrx({ id: t.id, grand_total: t.grand_total });
                    },
                    onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Gagal membuat transaksi.'),
                  },
                )
              }
            >
              Buat Transaksi
            </Button>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Transaksi Shift Berjalan</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nomor</TableHead>
                <TableHead>Total</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Sync</TableHead>
                <TableHead>Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(shiftTrx.data?.data ?? []).map((t) => (
                <TableRow key={t.id}>
                  <TableCell>{t.trx_number}</TableCell>
                  <TableCell>{formatRupiah(t.grand_total)}</TableCell>
                  <TableCell>
                    <TrxBadge status={t.status} />
                  </TableCell>
                  <TableCell>
                    <SyncBadge status={t.finance_sync_status} />
                  </TableCell>
                  <TableCell>{t.status === 'pending' && <Button size="sm" onClick={() => setPayTrx({ id: t.id, grand_total: t.grand_total })}>Bayar</Button>}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <PayDialog trx={payTrx} onDone={() => setPayTrx(null)} />
    </div>
  );
}
