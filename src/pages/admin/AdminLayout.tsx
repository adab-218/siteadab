import React from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { Calendar, Church, HandHeart, LayoutDashboard, LogOut, Network, ShieldCheck, Users, GraduationCap } from 'lucide-react';
import { useAuth } from '../../lib/auth-context';

const navItems = [
  { to: '/admin', label: 'Dashboard', icon: LayoutDashboard, end: true },
  { to: '/admin/membros', label: 'Membros', icon: Users },
  { to: '/admin/departamentos', label: 'Departamentos', icon: Network },
  { to: '/admin/cultos', label: 'Cultos', icon: Church },
  { to: '/admin/eventos', label: 'Eventos', icon: Calendar },
  { to: '/admin/acoes', label: 'Ações', icon: HandHeart },
  { to: '/admin/ebd', label: 'EBD', icon: GraduationCap },
  { to: '/admin/usuarios', label: 'Usuários', icon: ShieldCheck, perfis: ['admin'] },
];

const perfilLabel: Record<string, string> = {
  admin: 'Administrador',
  secretaria: 'Secretaria',
  dirigente: 'Dirigente',
  ebd_admin: 'EBD · Admin',
  ebd_professor: 'EBD · Professor',
  lider_departamento: 'Líder de Departamento',
};

const AdminLayout: React.FC = () => {
  const { usuario, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <div className="min-h-screen bg-gray-100 flex">
      {/* Sidebar */}
      <aside className="w-56 bg-white border-r flex flex-col">
        <div className="p-4 border-b">
          <img src="/img/logo.jpeg" alt="Logo ADAB" className="h-10 w-auto" />
          <p className="text-xs text-gray-500 mt-2">Gestão do Templo</p>
        </div>
        <nav className="flex-1 p-3 space-y-1">
          {navItems
            .filter((i) => !i.perfis || i.perfis.includes(usuario?.perfil ?? ''))
            .map(({ to, label, icon: Icon, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                  isActive ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50'
                }`
              }
            >
              <Icon size={16} />
              {label}
            </NavLink>
          ))}
        </nav>
      </aside>

      {/* Conteúdo */}
      <div className="flex-1 flex flex-col min-w-0">
        <header className="bg-white border-b px-6 py-3 flex items-center justify-between">
          <div className="min-w-0">
            <p className="font-semibold text-gray-800 truncate">{usuario?.nome}</p>
            <span className="inline-block text-xs bg-red-100 text-red-700 rounded-full px-2 py-0.5">
              {perfilLabel[usuario?.perfil ?? ''] ?? usuario?.perfil}
            </span>
          </div>
          <button
            onClick={handleLogout}
            className="flex items-center gap-2 text-sm text-gray-500 hover:text-red-600 transition-colors"
          >
            <LogOut size={16} />
            Sair
          </button>
        </header>

        <main className="flex-1 p-6 overflow-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default AdminLayout;
