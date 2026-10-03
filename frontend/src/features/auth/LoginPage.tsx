import { useState } from 'react';
import { useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { useAuthStore } from '@/store/auth';
import { ApiError } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

const schema = z.object({
  email: z.string().email('Email tidak valid.'),
  password: z.string().min(1, 'Password wajib diisi.'),
});

type FormValues = z.infer<typeof schema>;

export function LoginPage() {
  const navigate = useNavigate();
  const login = useAuthStore((s) => s.login);
  const [busy, setBusy] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { email: '', password: '' } });

  const onSubmit = async (values: FormValues) => {
    setBusy(true);
    setFormError(null);
    try {
      await login(values.email, values.password);
      navigate('/');
    } catch (e) {
      const msg = e instanceof ApiError ? e.message : 'Login gagal.';
      setFormError(msg);
      toast.error(msg);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>POS + Finance</CardTitle>
          <CardDescription>Masuk untuk melanjutkan.</CardDescription>
        </CardHeader>
        <CardContent>
          {formError && (
            <Alert variant="destructive" className="mb-4">
              <AlertDescription>{formError}</AlertDescription>
            </Alert>
          )}
          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Email</FormLabel>
                    <FormControl>
                      <Input type="email" placeholder="kasir.bdg@demo.test" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Password</FormLabel>
                    <FormControl>
                      <Input type="password" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <Button type="submit" className="w-full" disabled={busy}>
                {busy ? 'Memproses...' : 'Masuk'}
              </Button>
            </form>
          </Form>
        </CardContent>
      </Card>
    </div>
  );
}
