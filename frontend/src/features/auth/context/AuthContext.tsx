'use client';

import React, { createContext, useCallback, useContext, useState } from 'react';
import { authApiClient } from '../services/authApiClient';

interface AuthContextValue {
  sessionToken: string | null;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [sessionToken, setSessionToken] = useState<string | null>(null);

  const login = useCallback(async (email: string, password: string) => {
    const { session_token } = await authApiClient.login(email, password);
    setSessionToken(session_token);
  }, []);

  const logout = useCallback(async () => {
    if (sessionToken) {
      await authApiClient.logout(sessionToken);
    }
    setSessionToken(null);
  }, [sessionToken]);

  return (
    <AuthContext.Provider
      value={{
        sessionToken,
        isAuthenticated: sessionToken !== null,
        login,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return ctx;
}
