'use client';

import { useState } from 'react';
import { authApiClient } from '../services/authApiClient';

export function useLogout() {
  const [loading, setLoading] = useState(false);

  const logout = async (sessionToken: string): Promise<void> => {
    setLoading(true);
    try {
      await authApiClient.logout(sessionToken);
    } finally {
      setLoading(false);
    }
  };

  return { logout, loading };
}
