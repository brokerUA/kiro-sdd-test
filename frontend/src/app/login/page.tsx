'use client';

import { useRouter } from 'next/navigation';
import { LoginForm } from '../../features/auth/components/LoginForm';

export default function LoginPage() {
  const router = useRouter();

  return (
    <main>
      <h1>Sign in</h1>
      <LoginForm onSuccess={() => void router.push('/')} />
    </main>
  );
}
