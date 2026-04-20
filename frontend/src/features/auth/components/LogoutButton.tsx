'use client';

import { useAuth } from '../context/AuthContext';

export function LogoutButton() {
  const { logout } = useAuth();

  return (
    <button
      type="button"
      onClick={() => void logout()}
      className="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
    >
      Log out
    </button>
  );
}
