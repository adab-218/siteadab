import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './lib/auth-context';
import RequireAuth from './routes/RequireAuth';
import App from './App.tsx';
import LoginPage from './pages/auth/LoginPage';
import RedefinirSenhaPage from './pages/auth/RedefinirSenhaPage';
import AdminLayout from './pages/admin/AdminLayout';
import DashboardPage from './pages/admin/DashboardPage';
import MembrosPage from './pages/admin/MembrosPage';
import DepartamentosPage from './pages/admin/DepartamentosPage';
import UsuariosPage from './pages/admin/UsuariosPage';
import EmBrevePage from './pages/admin/EmBrevePage';
import './index.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/" element={<App />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/redefinir-senha" element={<RedefinirSenhaPage />} />
          <Route
            path="/admin"
            element={
              <RequireAuth>
                <AdminLayout />
              </RequireAuth>
            }
          >
            <Route index element={<DashboardPage />} />
            <Route path="membros" element={<MembrosPage />} />
            <Route path="departamentos" element={<DepartamentosPage />} />
            <Route path="usuarios" element={<UsuariosPage />} />
            <Route path=":modulo" element={<EmBrevePage />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>
);
