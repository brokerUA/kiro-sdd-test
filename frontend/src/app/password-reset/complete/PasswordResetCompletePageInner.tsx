'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { PasswordResetCompleteForm } from '../../../features/auth/components/PasswordResetCompleteForm';

export default function PasswordResetCompletePageInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const token = searchParams.get('token') ?? '';

  if (!token) {
    return <p>Invalid or missing reset token.</p>;
  }

  return (
    <main>
      <h1>Set a new password</h1>
      <PasswordResetCompleteForm
        token={token}
        onSuccess={() => void router.push('/login')}
      />
    </main>
  );
}
