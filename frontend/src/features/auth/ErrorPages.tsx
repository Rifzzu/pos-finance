import { Link } from 'react-router';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

export function ForbiddenPage() {
  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm text-center">
        <CardHeader>
          <CardTitle>403 — Akses Ditolak</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-sm text-muted-foreground">Role Anda tidak boleh membuka halaman ini.</p>
          <Button asChild>
            <Link to="/">Kembali ke Dashboard</Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}

export function NotFoundPage() {
  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm text-center">
        <CardHeader>
          <CardTitle>404 — Tidak Ditemukan</CardTitle>
        </CardHeader>
        <CardContent>
          <Button asChild>
            <Link to="/">Kembali ke Dashboard</Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
