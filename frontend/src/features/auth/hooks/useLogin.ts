'use client';

import { useState } from 'react';
import { authApiClient, AuthApiError } from '../services/authApiClient';
import type { AuthError } from '../types/AuthErrors';

export function useLogin() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<AuthError | null>(null);
  const [retryAfterSeconds, setRetryAfterSeconds] = useState<number | undefined>();

  const login = async (email: string, password: string): Promise<string | null> => {
    setLoading(true);
    setError(null);
    setRetryAfterSeconds(undefined);

    try {
      const { session_token } = await authApiClient.login(email, password);
      return session_token;
    } catch (e) {
      if (e instanceof AuthApiError) {
        setError(e.code);
        setRetryAfterSeconds(e.retryAfterSeconds);
      } else {
        setError('AUTHENTICATION_FAILED');
      }
      return null;
    } finally {
      setLoading(false);
    }
  };

  return { login, loading, error, retryAfterSeconds };
}
