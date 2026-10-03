import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import type { Resolver } from 'react-hook-form';
import { useSaveUser, useUserStatus, useUsers } from '@/features/users/api';
import { useOutlets } from '@/features/pos/api';
import { ApiError, type Role } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

const ROLES: Role[] = ['superadmin', 'kasir', 'supervisor_pos', 'staff_finance', 'manager_finance'];
const NEED_OUTLET: Role[] = ['kasir', 'supervisor_pos'];

const schema = z.object({
  name: z.string().min(1, 'Nama wajib.').max(150),
  email: z.string().email('Email tidak valid.'),
  password: z.string().min(8, 'Minimal 8 karakter.').max(100).optional().or(z.literal('')),
  role: z.enum(['superadmin', 'kasir', 'supervisor_pos', 'staff_finance', 'manager_finance']),
  outlet_id: z.coerce.number().nullable().optional(),
});

type Values = z.infer<typeof schema>;

export function UsersPage() {
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('__all__');
  const { data, isLoading } = useUsers({ search: search || undefined, role: role === '__all__' ? undefined : role, per_page: 20 });
  const { data: outlets } = useOutlets();
  const [editing, setEditing] = useState<{ id?: number } & Partial<Values> | null>(null);
  const save = useSaveUser();
  const setStatus = useUserStatus();

  const form = useForm<Values>({ resolver: zodResolver(schema) as Resolver<Values>, defaultValues: { name: '', email: '', password: '', role: 'kasir', outlet_id: null } });
  const watchRole = form.watch('role') as Role;

  const openAdd = () => {
    form.reset({ name: '', email: '', password: '', role: 'kasir', outlet_id: null });
    setEditing({});
  };
  const openEdit = (u: { id: number; name: string; email: string; role: Role; outlet_id: number | null }) => {
    form.reset({ name: u.name, email: u.email, password: '', role: u.role, outlet_id: u.outlet_id });
    setEditing({ id: u.id });
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold">Manajemen User</h1>
        <Button onClick={openAdd}>Tambah</Button>
      </div>
      <div className="flex gap-2">
        <Input placeholder="Cari nama/email..." value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs" />
        <Select value={role} onValueChange={setRole}>
          <SelectTrigger className="w-44"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="__all__">Semua role</SelectItem>
            {ROLES.map((r) => (
              <SelectItem key={r} value={r}>{r}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      {isLoading ? (
        <Skeleton className="h-64" />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Nama</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Role</TableHead>
              <TableHead>Outlet</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {(data?.data ?? []).map((u) => (
              <TableRow key={u.id}>
                <TableCell>{u.name}</TableCell>
                <TableCell>{u.email}</TableCell>
                <TableCell>{u.role}</TableCell>
                <TableCell>{u.outlet_id ?? '-'}</TableCell>
                <TableCell>
                  <Badge variant={u.is_active ? 'success' : 'secondary'}>{u.is_active ? 'aktif' : 'nonaktif'}</Badge>
                </TableCell>
                <TableCell className="space-x-1">
                  <Button size="sm" variant="outline" onClick={() => openEdit(u)}>Ubah</Button>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                      setStatus.mutate(
                        { id: u.id, is_active: !u.is_active },
                        { onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Gagal.') },
                      )
                    }
                  >
                    {u.is_active ? 'Nonaktifkan' : 'Aktifkan'}
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
            <DialogTitle>{editing?.id ? 'Ubah User' : 'Tambah User'}</DialogTitle>
          </DialogHeader>
          <Form {...form}>
            <form
              className="space-y-4"
              onSubmit={form.handleSubmit((v) =>
                save.mutate(
                  {
                    id: editing?.id,
                    name: v.name,
                    email: v.email,
                    password: v.password || undefined,
                    role: v.role,
                    outlet_id: NEED_OUTLET.includes(v.role) ? v.outlet_id : null,
                  },
                  {
                    onSuccess: () => { toast.success('User disimpan.'); setEditing(null); },
                    onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Gagal menyimpan.'),
                  },
                ),
              )}
            >
              <FormField control={form.control} name="name" render={({ field }) => (
                <FormItem><FormLabel>Nama</FormLabel><FormControl><Input {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="email" render={({ field }) => (
                <FormItem><FormLabel>Email</FormLabel><FormControl><Input type="email" {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="password" render={({ field }) => (
                <FormItem><FormLabel>Password {editing?.id ? '(kosongkan bila tidak diubah)' : ''}</FormLabel><FormControl><Input type="password" {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="role" render={({ field }) => (
                <FormItem><FormLabel>Role</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl><SelectTrigger><SelectValue /></SelectTrigger></FormControl>
                    <SelectContent>{ROLES.map((r) => <SelectItem key={r} value={r}>{r}</SelectItem>)}</SelectContent>
                  </Select><FormMessage />
                </FormItem>
              )} />
              {NEED_OUTLET.includes(watchRole) && (
                <FormField control={form.control} name="outlet_id" render={({ field }) => (
                  <FormItem><FormLabel>Outlet</FormLabel>
                    <Select value={field.value ? String(field.value) : ''} onValueChange={(v) => field.onChange(Number(v))}>
                      <FormControl><SelectTrigger><SelectValue placeholder="Pilih outlet" /></SelectTrigger></FormControl>
                      <SelectContent>{(outlets ?? []).map((o) => <SelectItem key={o.id} value={String(o.id)}>{o.code} — {o.name}</SelectItem>)}</SelectContent>
                    </Select><FormMessage />
                  </FormItem>
                )} />
              )}
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
