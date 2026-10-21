import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../lib/auth-context';

interface RequireAuthProps {
  children: React.ReactNode;
  /** Se informado, só esses perfis passam (admin sempre passa). */
  perfis?: string[];
}

const RequireAuth: React.FC<RequireAuthProps> = ({ children, perfis }) => {
  const { usuario, carregando } = useAuth();
  const location = useLocation();

  if (carregando) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <p className="text-gray-500">Carregando…</p>
      </div>
    );
  }

  if (!usuario) {
    return <Navigate to="/login" state={{ from: location.pathname }} replace />;
  }

  if (perfis && usuario.perfil !== 'admin' && !perfis.includes(usuario.perfil)) {
    return <Navigate to="/admin" replace />;
  }

  return <>{children}</>;
};

export default RequireAuth;
