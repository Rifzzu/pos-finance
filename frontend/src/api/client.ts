import axios, { AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios';
import { toast } from 'sonner';
import { ApiError } from '@/types';
import { useAuthStore } from '@/store/auth';

function toApiError(status: number, data: unknown, fallback: string): ApiError {
  const body =
    data && typeof data === 'object' && 'message' in data
      ? {
          message: String((data as { message: unknown }).message ?? fallback),
          code: String((data as { code?: unknown }).code ?? 'REQUEST_FAILED'),
          errors: ((data as { errors?: unknown }).errors as Record<string, string[]> | null) ?? null,
        }
      : { message: fallback, code: 'REQUEST_FAILED', errors: null };
  return new ApiError(status, body);
}

let refreshPromise: Promise<string> | null = null;

async function refreshOnce(): Promise<string> {
  if (!refreshPromise) {
    refreshPromise = (async () => {
      const { refreshToken, setTokens, logout } = useAuthStore.getState();
      if (!refreshToken) throw new ApiError(401, { message: 'Sesi habis.', code: 'NO_REFRESH', errors: null });
      try {
        const res = await axios.post('/api/auth/refresh', { refresh_token: refreshToken });
        const d = res.data.data as { access_token: string; refresh_token: string };
        setTokens(d.access_token, d.refresh_token);
        return d.access_token;
      } catch {
        logout();
        throw new ApiError(401, { message: 'Sesi habis, silakan login.', code: 'REFRESH_FAILED', errors: null });
      } finally {
        refreshPromise = null;
      }
    })();
  }
  return refreshPromise;
}

function createClient(baseURL: string): AxiosInstance {
  const client = axios.create({ baseURL });

  client.interceptors.request.use((config: InternalAxiosRequestConfig) => {
    const token = useAuthStore.getState().accessToken;
    if (token) config.headers.set('Authorization', `Bearer ${token}`);
    return config;
  });

  client.interceptors.response.use(
    (res) => res,
    async (error: AxiosError) => {
      const original = error.config as (InternalAxiosRequestConfig & { _retried?: boolean }) | undefined;

      if (!error.response) {
        toast.error('Terjadi kesalahan pada server.');
        throw new ApiError(0, { message: 'Jaringan/server tidak dapat dihubungi.', code: 'NETWORK_ERROR', errors: null });
      }

      const { status, data } = error.response;

      const url = original?.url ?? '';
      const isAuthEndpoint = url.includes('/login') || url.includes('/refresh');

      if (status === 401 && original && !original._retried && !isAuthEndpoint) {
        if (!useAuthStore.getState().refreshToken) {
          throw toApiError(status, data, 'Email atau password salah.');
        }
        original._retried = true;
        try {
          const token = await refreshOnce();
          original.headers.set('Authorization', `Bearer ${token}`);
          return client(original);
        } catch (e) {
          if (window.location.pathname !== '/login') window.location.href = '/login';
          throw e;
        }
      }

      if (status === 403) {
        if (!isAuthEndpoint && window.location.pathname !== '/forbidden') window.location.href = '/forbidden';
        throw toApiError(status, data, 'Akses ditolak.');
      }

      if (status === 409 || status === 422) {
        throw toApiError(status, data, 'Permintaan tidak dapat diproses.');
      }

      if (status >= 500) {
        toast.error('Terjadi kesalahan pada server.');
      }

      throw toApiError(status, data, 'Permintaan gagal.');
    },
  );

  return client;
}

export const authApi = createClient('/api/auth');
export const posApi = createClient('/api/pos');
export const financeApi = createClient('/api/finance');
