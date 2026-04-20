'use client';

import { useState } from 'react';
import { authApiClient, AuthApiError } from '../services/authApiClient';
import type { AuthError } from '../types/AuthErrors';

export function usePasswordReset() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<AuthError | null>(null);
  const [success, setSuccess] = useState(false);

  const requestReset = async (email: string): Promise<void> => {
    setLoading(true);
    setError(null);
    setSuccess(false);

    try {
      await authApiClient.requestPasswordReset(email);
      setSuccess(true);
    } catch (e) {
      if (e instanceof AuthApiError) {
        setError(e.code);
      }
    } finally {
      setLoading(false);
    }
  };

  const completeReset = async (token: string, newPassword: string): Promise<boolean> => {
    setLoading(true);
    setError(null);
    setSuccess(false);

    try {
      await authApiClient.completePasswordReset(token, newPassword);
      setSuccess(true);
      return true;
    } catch (e) {
      if (e instanceof AuthApiError) {
        setError(e.code);
      }
      return false;
    } finally {
      setLoading(false);
    }
  };

  return { requestReset, completeReset, loading, error, success };
}
