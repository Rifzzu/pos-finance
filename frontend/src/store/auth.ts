import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { authApi } from '@/api/client';
import type { LoginResponse, User } from '@/types';

interface AuthState {
  user: User | null;
  accessToken: string | null;
  refreshToken: string | null;
  hydrated: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  setTokens: (access: string, refresh: string) => void;
  setHydrated: () => void;
}

function decodeExp(token: string): number | null {
  try {
    const payload = JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
    return typeof payload.exp === 'number' ? payload.exp : null;
  } catch {
    return null;
  }
}

let proactiveTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleProactiveRefresh() {
  if (proactiveTimer) clearTimeout(proactiveTimer);
  const { accessToken, refreshToken } = useAuthStore.getState();
  if (!accessToken || !refreshToken) return;
  const exp = decodeExp(accessToken);
  if (!exp) return;
  const ms = exp * 1000 - Date.now() - 60_000;
  if (ms <= 0) return;
  proactiveTimer = setTimeout(async () => {
    try {
      const res = await authApi.post('/refresh', { refresh_token: useAuthStore.getState().refreshToken });
      const d = res.data.data as { access_token: string; refresh_token: string };
      useAuthStore.getState().setTokens(d.access_token, d.refresh_token);
    } catch {
      /* interceptor menangani logout */
    }
  }, ms);
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      accessToken: null,
      refreshToken: null,
      hydrated: false,
      setHydrated: () => set({ hydrated: true }),
      setTokens: (accessToken, refreshToken) => {
        set({ accessToken, refreshToken });
        scheduleProactiveRefresh();
      },
      login: async (email, password) => {
        const res = await authApi.post('/login', { email, password });
        const d = res.data.data as LoginResponse;
        set({ user: d.user, accessToken: d.access_token, refreshToken: d.refresh_token });
        scheduleProactiveRefresh();
      },
      logout: async () => {
        const { accessToken, refreshToken } = get();
        try {
          if (accessToken) {
            await authApi.post(
              '/logout',
              { refresh_token: refreshToken },
              { headers: { Authorization: `Bearer ${accessToken}` } },
            );
          }
        } catch {
          /* abaikan */
        } finally {
          if (proactiveTimer) clearTimeout(proactiveTimer);
          set({ user: null, accessToken: null, refreshToken: null });
        }
      },
    }),
    {
      name: 'pos-finance-auth',
      partialize: (s) => ({ user: s.user, accessToken: s.accessToken, refreshToken: s.refreshToken }),
      onRehydrateStorage: () => (state) => {
        state?.setHydrated();
        scheduleProactiveRefresh();
      },
    },
  ),
);
