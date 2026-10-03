import { useState } from 'react';
import { useForm, type Resolver } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { useProductStatus, useProducts, useSaveProduct } from '@/features/pos/api';
import { ApiError } from '@/types';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { formatRupiah } from '@/lib/format';
import { useDebounce } from '@/hooks/use-debounce';

const schema = z.object({
  sku: z.string().min(1, 'SKU wajib.').max(50),
  name: z.string().min(1, 'Nama wajib.').max(200),
  price: z.coerce.number().min(0.01, 'Harga harus > 0.'),
});

type Values = z.infer<typeof schema>;

export function ProductsPage() {
  const [search, setSearch] = useState('');
  const [active, setActive] = useState('__all__');
  const debounced = useDebounce(search);
  const { data, isLoading } = useProducts({
    search: debounced || undefined,
    is_active: active === '__all__' ? undefined : active,
    per_page: 20,
  });
  const [editing, setEditing] = useState<{ id?: number; sku: string; name: string; price: number } | null>(null);
  const save = useSaveProduct();
  const setStatus = useProductStatus();

  const form = useForm<Values>({ resolver: zodResolver(schema) as Resolver<Values>, defaultValues: { sku: '', name: '', price: 0 } });

  const openAdd = () => {
    form.reset({ sku: '', name: '', price: 0 });
    setEditing({ sku: '', name: '', price: 0 });
  };
  const openEdit = (p: { id: number; sku: string; name: string; price: string }) => {
    form.reset({ sku: p.sku, name: p.name, price: Number(p.price) });
    setEditing({ id: p.id, sku: p.sku, name: p.name, price: Number(p.price) });
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold">Produk</h1>
        <Button onClick={openAdd}>Tambah</Button>
      </div>
      <div className="flex gap-2">
        <Input placeholder="Cari nama/SKU..." value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs" />
        <Select value={active} onValueChange={setActive}>
          <SelectTrigger className="w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__all__">Semua</SelectItem>
            <SelectItem value="1">Aktif</SelectItem>
            <SelectItem value="0">Nonaktif</SelectItem>
          </SelectContent>
        </Select>
      </div>
      {isLoading ? (
        <Skeleton className="h-64" />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>SKU</TableHead>
              <TableHead>Nama</TableHead>
              <TableHead>Harga</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {(data?.data ?? []).map((p) => (
              <TableRow key={p.id}>
                <TableCell>{p.sku}</TableCell>
                <TableCell>{p.name}</TableCell>
                <TableCell>{formatRupiah(p.price)}</TableCell>
                <TableCell>
                  <Badge variant={p.is_active ? 'success' : 'secondary'}>{p.is_active ? 'aktif' : 'nonaktif'}</Badge>
                </TableCell>
                <TableCell className="space-x-1">
                  <Button size="sm" variant="outline" onClick={() => openEdit(p)}>
                    Ubah
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                      setStatus.mutate(
                        { id: p.id, is_active: !p.is_active },
                        { onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Gagal.') },
                      )
                    }
                  >
                    {p.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
      <Dialog open={!!editing} onOpenChange={(o) => !o && setEditing(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing?.id ? 'Ubah Produk' : 'Tambah Produk'}</DialogTitle>
          </DialogHeader>
          <Form {...form}>
            <form
              className="space-y-4"
              onSubmit={form.handleSubmit((v) =>
                save.mutate(
                  { id: editing?.id, ...v },
                  {
                    onSuccess: () => { toast.success('Produk disimpan.'); setEditing(null); },
                    onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Gagal menyimpan.'),
                  },
                ),
              )}
            >
              <FormField control={form.control} name="sku" render={({ field }) => (
                <FormItem><FormLabel>SKU</FormLabel><FormControl><Input {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="name" render={({ field }) => (
                <FormItem><FormLabel>Nama</FormLabel><FormControl><Input {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="price" render={({ field }) => (
                <FormItem><FormLabel>Harga (Rp)</FormLabel><FormControl><Input type="number" min={0} step="0.01" {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <DialogFooter>
                <Button type="submit" disabled={save.isPending}>Simpan</Button>
              </DialogFooter>
            </form>
          </Form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
