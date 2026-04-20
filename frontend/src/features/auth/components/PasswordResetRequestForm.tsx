'use client';

import { useState } from 'react';
import { usePasswordReset } from '../hooks/usePasswordReset';
import { ErrorMessage } from '../../../shared/components/ErrorMessage';

export function PasswordResetRequestForm() {
  const [email, setEmail] = useState('');
  const { requestReset, loading, success } = usePasswordReset();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await requestReset(email);
  };

  if (success) {
    return (
      <p role="status">
        If that email is registered, you will receive a reset link shortly.
      </p>
    );
  }

  return (
    <form onSubmit={(e) => void handleSubmit(e)} noValidate>
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

      <button type="submit" disabled={loading}>
        {loading ? 'Sending…' : 'Send reset link'}
      </button>
    </form>
  );
}
