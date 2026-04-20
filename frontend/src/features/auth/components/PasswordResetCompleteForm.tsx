'use client';

import { useState } from 'react';
import { usePasswordReset } from '../hooks/usePasswordReset';
import { ErrorMessage } from '../../../shared/components/ErrorMessage';

interface PasswordResetCompleteFormProps {
  token: string;
  onSuccess?: () => void;
}

export function PasswordResetCompleteForm({ token, onSuccess }: PasswordResetCompleteFormProps) {
  const [newPassword, setNewPassword] = useState('');
  const { completeReset, loading, error, success } = usePasswordReset();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const ok = await completeReset(token, newPassword);
    if (ok) onSuccess?.();
  };

  const errorMessages: Record<string, string> = {
    TOKEN_EXPIRED: 'This reset link has expired. Please request a new one.',
    TOKEN_INVALID: 'This reset link is invalid or has already been used.',
    VALIDATION_ERROR: 'Your new password does not meet the requirements.',
  };

  return (
    <form onSubmit={(e) => void handleSubmit(e)} noValidate>
      {error && <ErrorMessage message={errorMessages[error] ?? 'An error occurred.'} />}

      <div>
        <label htmlFor="new_password">New password</label>
        <input
          id="new_password"
          type="password"
          value={newPassword}
          onChange={(e) => setNewPassword(e.target.value)}
          required
          autoComplete="new-password"
        />
      </div>

      <button type="submit" disabled={loading}>
        {loading ? 'Saving…' : 'Set new password'}
      </button>
    </form>
  );
}
