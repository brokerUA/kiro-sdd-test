import type { AuthError } from '../types/AuthErrors';
import type {
  AccountLockedResponse,
  SessionTokenResponse,
  ValidationErrorResponse,
} from '../types/AuthResponses';

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? '';

export class AuthApiError extends Error {
  constructor(
    public readonly code: AuthError,
    public readonly retryAfterSeconds?: number,
    public readonly fields?: string[],
    public readonly message: string = code,
  ) {
    super(message);
    this.name = 'AuthApiError';
  }
}

async function handleResponse<T>(res: Response): Promise<T> {
  if (res.ok) {
    return res.json() as Promise<T>;
  }

  const body = await res.json().catch(() => ({}));
  const code: AuthError = body.error ?? 'AUTHENTICATION_FAILED';

  if (code === 'ACCOUNT_LOCKED') {
    const locked = body as AccountLockedResponse;
    throw new AuthApiError(code, locked.retry_after_seconds);
  }

  if (code === 'VALIDATION_ERROR') {
    const validation = body as ValidationErrorResponse;
    throw new AuthApiError(code, undefined, validation.fields, validation.message);
  }

  throw new AuthApiError(code);
}

export const authApiClient = {
  async login(email: string, password: string): Promise<SessionTokenResponse> {
    const res = await fetch(`${API_BASE}/api/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    });
    return handleResponse<SessionTokenResponse>(res);
  },

  async logout(sessionToken: string): Promise<void> {
    const res = await fetch(`${API_BASE}/api/auth/logout`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${sessionToken}` },
    });
    await handleResponse<Record<string, never>>(res);
  },

  async requestPasswordReset(email: string): Promise<void> {
    const res = await fetch(`${API_BASE}/api/auth/password-reset/request`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email }),
    });
    await handleResponse<Record<string, never>>(res);
  },

  async completePasswordReset(token: string, newPassword: string): Promise<void> {
    const res = await fetch(`${API_BASE}/api/auth/password-reset/complete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token, new_password: newPassword }),
    });
    await handleResponse<Record<string, never>>(res);
  },
};
