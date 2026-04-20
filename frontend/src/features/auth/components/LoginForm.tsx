'use client';

import { useState } from 'react';
import { useLogin } from '../hooks/useLogin';
import { ErrorMessage } from '../../../shared/components/ErrorMessage';

interface LoginFormProps {
  onSuccess?: (sessionToken: string) => void;
}

export function LoginForm({ onSuccess }: LoginFormProps) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const { login, loading, error, retryAfterSeconds } = useLogin();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const token = await login(email, password);
    if (token) onSuccess?.(token);
  };

  const errorMessages: Record<string, string> = {
    AUTHENTICATION_FAILED: 'Invalid email or password.',
    ACCOUNT_LOCKED: `Account temporarily locked. Try again in ${retryAfterSeconds ?? 0} seconds.`,
    VALIDATION_ERROR: 'Please check your input and try again.',
  };

  return (
    <form onSubmit={(e) => void handleSubmit(e)} noValidate>
      {error && <ErrorMessage message={errorMessages[error] ?? 'An error occurred.'} />}

      <div>
        <label htmlFor="email">Email</label>
        <input
          id="email"
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
          autoComplete="email"
        />
      </div>

      <div>
        <label htmlFor="password">Password</label>
        <input
          id="password"
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
          autoComplete="current-password"
        />
      </div>

      <button type="submit" disabled={loading}>
        {loading ? 'Signing in…' : 'Sign in'}
      </button>
    </form>
  );
}
