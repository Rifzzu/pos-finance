export type Role = 'superadmin' | 'kasir' | 'supervisor_pos' | 'staff_finance' | 'manager_finance';

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  outlet_id: number | null;
  is_active: boolean;
}

export interface LoginResponse {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  user: User;
}

export interface ApiErrorBody {
  message: string;
  code: string;
  errors: Record<string, string[]> | null;
}

export class ApiError extends Error {
  status: number;
  code: string;
  errors: Record<string, string[]> | null;

  constructor(status: number, body: ApiErrorBody) {
    super(body.message);
    this.status = status;
    this.code = body.code;
    this.errors = body.errors;
  }
}

export interface PageMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface Paginated<T> {
  data: T[];
  meta: PageMeta;
}
