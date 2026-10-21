import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { auth, Usuario } from './api';

interface AuthContextValue {
  usuario: Usuario | null;
  carregando: boolean;
  login: (email: string, senha: string) => Promise<Usuario>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [carregando, setCarregando] = useState(true);

  // Bootstrap de sessão: restaura usuário + token CSRF após F5.
  useEffect(() => {
    auth.me()
      .then(setUsuario)
      .catch(() => setUsuario(null))
      .finally(() => setCarregando(false));
  }, []);

  const login = useCallback(async (email: string, senha: string) => {
    const u = await auth.login(email, senha);
    setUsuario(u);
    return u;
  }, []);

  const logout = useCallback(async () => {
    try {
      await auth.logout();
    } finally {
      setUsuario(null);
    }
  }, []);

  const value = useMemo(
    () => ({ usuario, carregando, login, logout }),
    [usuario, carregando, login, logout]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth deve ser usado dentro de <AuthProvider>');
  }
  return ctx;
}
