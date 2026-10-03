import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { authApi } from '@/api/client';
import type { Paginated, User } from '@/types';

export function useUsers(params: Record<string, string | number | undefined>) {
  return useQuery({
    queryKey: ['users', params],
    queryFn: async (): Promise<Paginated<User>> => (await authApi.get('/users', { params })).data,
  });
}

export function useSaveUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: {
      id?: number;
      name: string;
      email: string;
      password?: string;
      role: string;
      outlet_id?: number | null;
    }): Promise<User> => {
      const { id, ...body } = vars;
      const payload = { ...body, outlet_id: body.outlet_id ?? null };
      if (!payload.password) delete (payload as Record<string, unknown>).password;
      const res = id ? await authApi.patch(`/users/${id}`, payload) : await authApi.post('/users', payload);
      return res.data.data as User;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}

export function useUserStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: { id: number; is_active: boolean }): Promise<User> =>
      (await authApi.patch(`/users/${vars.id}/status`, { is_active: vars.is_active })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}
