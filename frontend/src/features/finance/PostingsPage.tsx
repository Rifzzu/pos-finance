import { useState } from 'react';
import { Link, useParams } from 'react-router';
import { usePostingDetail, usePostings } from '@/features/finance/api';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { formatRupiah } from '@/lib/format';
import { useDebounce } from '@/hooks/use-debounce';

export function PostingsPage() {
  const [outlet, setOutlet] = useState('');
  const [method, setMethod] = useState('__all__');
  const [entry, setEntry] = useState('__all__');
  const [date, setDate] = useState('');
  const deb = useDebounce(outlet);
  const { data, isLoading } = usePostings({
    outlet_id: deb || undefined,
    business_date: date || undefined,
    payment_method: method === '__all__' ? undefined : method,
    entry_type: entry === '__all__' ? undefined : entry,
    per_page: 20,
  });

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">Posting & Jurnal</h1>
      <div className="flex flex-wrap gap-2">
        <div className="space-y-1">
          <Label>Outlet ID</Label>
          <Input className="w-28" value={outlet} onChange={(e) => setOutlet(e.target.value)} placeholder="1" />
        </div>
        <div className="space-y-1">
          <Label>Tanggal</Label>
          <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label>Metode</Label>
          <Select value={method} onValueChange={setMethod}>
            <SelectTrigger className="w-32"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="__all__">Semua</SelectItem>
              <SelectItem value="cash">cash</SelectItem>
              <SelectItem value="debit">debit</SelectItem>
              <SelectItem value="qris">qris</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1">
          <Label>Tipe</Label>
          <Select value={entry} onValueChange={setEntry}>
            <SelectTrigger className="w-32"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="__all__">Semua</SelectItem>
              <SelectItem value="sale">sale</SelectItem>
              <SelectItem value="reversal">reversal</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
      {isLoading ? (
        <Skeleton className="h-64" />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Trx</TableHead>
              <TableHead>Tipe</TableHead>
              <TableHead>Metode</TableHead>
              <TableHead>Total</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {(data?.data ?? []).map((p) => (
              <TableRow key={p.id}>
                <TableCell>{p.trx_number}</TableCell>
                <TableCell>{p.entry_type}</TableCell>
                <TableCell>{p.payment_method}</TableCell>
                <TableCell>{formatRupiah(p.total_amount)}</TableCell>
                <TableCell>
                  <Button size="sm" variant="outline" asChild>
                    <Link to={`/posting/${p.id}`}>Detail</Link>
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
  );
}

export function PostingDetailPage() {
  const { id } = useParams();
  const { data: p, isLoading, isError } = usePostingDetail(id);

  if (isLoading) return <Skeleton className="h-64" />;
  if (isError || !p) return <p className="text-sm text-destructive">Posting tidak ditemukan.</p>;

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">{p.trx_number} ({p.entry_type})</h1>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            Jurnal
            <Badge variant={p.balanced ? 'success' : 'danger'}>{p.balanced ? 'Seimbang' : 'Tidak seimbang'}</Badge>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Akun</TableHead>
                <TableHead>Debit</TableHead>
                <TableHead>Kredit</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {p.journal_lines.map((l) => (
                <TableRow key={l.id}>
                  <TableCell>{l.account_code}</TableCell>
                  <TableCell>{formatRupiah(l.debit)}</TableCell>
                  <TableCell>{formatRupiah(l.credit)}</TableCell>
                </TableRow>
              ))}
              <TableRow>
                <TableCell className="font-bold">Total</TableCell>
                <TableCell className="font-bold">{formatRupiah(p.total_debit)}</TableCell>
                <TableCell className="font-bold">{formatRupiah(p.total_credit)}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
