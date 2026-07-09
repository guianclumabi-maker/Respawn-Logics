import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import * as api from './api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [booting, setBooting] = useState(true);
  const [serverUrl, setServerUrlState] = useState(null);
  const [user, setUser] = useState(null);

  useEffect(() => {
    (async () => {
      try {
        const url = await api.loadBaseUrl();
        setServerUrlState(url);
        if (url) {
          // Try to resume an existing session (cookie may still be alive).
          const u = await api.fetchCurrentUser();
          if (u) setUser(u);
        }
      } catch (e) {
        // Offline or server unreachable — land on login/setup.
      } finally {
        setBooting(false);
      }
    })();
  }, []);

  const saveServer = useCallback(async (url) => {
    const clean = await api.setBaseUrl(url);
    setServerUrlState(clean);
  }, []);

  const changeServer = useCallback(async () => {
    await api.clearBaseUrl();
    setServerUrlState(null);
    setUser(null);
  }, []);

  const signIn = useCallback(async (email, password) => {
    const { status, data } = await api.login(email, password);
    if (data && data.success) {
      if (data.require_2fa || data.require_2fa_setup) {
        throw new Error(
          'This account uses two-factor authentication. Please sign in through the web app — this basic mobile app does not support 2FA.'
        );
      }
      if (data.must_change_password) {
        throw new Error(
          'You must change your password first. Please sign in on the web app to set a new password, then come back.'
        );
      }
      setUser(data.user);
      return data.user;
    }
    throw new Error((data && data.error) || `Login failed (HTTP ${status}).`);
  }, []);

  const signOut = useCallback(async () => {
    await api.logout();
    setUser(null);
  }, []);

  const hasPermission = useCallback(
    (perm) => {
      if (!user) return false;
      if (user.is_super) return true;
      const perms = user.permissions || [];
      if (Array.isArray(perms)) return perms.includes(perm);
      return !!perms[perm];
    },
    [user]
  );

  return (
    <AuthContext.Provider
      value={{ booting, serverUrl, saveServer, changeServer, user, signIn, signOut, hasPermission }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
