import React, { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Lock, LogIn, Mail } from 'lucide-react';
import { useAuth } from '../../lib/auth-context';
import { auth, ApiError } from '../../lib/api';

const LoginPage: React.FC = () => {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = (location.state as { from?: string } | null)?.from ?? '/admin';

  const [modo, setModo] = useState<'login' | 'recuperar'>('login');
  const [email, setEmail] = useState('');
  const [senha, setSenha] = useState('');
  const [erro, setErro] = useState('');
  const [aviso, setAviso] = useState('');
  const [enviando, setEnviando] = useState(false);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setErro('');
    setEnviando(true);
    try {
      await login(email, senha);
      navigate(from, { replace: true });
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao entrar. Tente novamente.');
    } finally {
      setEnviando(false);
    }
  };

  const handleRecuperar = async (e: React.FormEvent) => {
    e.preventDefault();
    setErro('');
    setEnviando(true);
    try {
      const r = await auth.recuperar(email);
      setAviso(r.mensagem);
      setModo('login');
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao solicitar redefinição.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-100 flex items-center justify-center px-4">
      <div className="bg-white rounded-lg shadow-lg w-full max-w-md p-8">
        <div className="text-center mb-8">
          <img
            src="https://i.imgur.com/zlIM5kM.jpeg"
            alt="Logo ADAB"
            className="h-16 w-auto mx-auto mb-4"
          />
          <h1 className="text-xl font-bold text-gray-800">
            {modo === 'login' ? 'Painel de Gestão' : 'Recuperar senha'}
          </h1>
          <p className="text-sm text-gray-500">Templo Arthur Bernardes</p>
        </div>

        {aviso && (
          <div className="mb-4 rounded-lg bg-green-50 text-green-800 text-sm px-4 py-3" role="status">
            {aviso}
          </div>
        )}
        {erro && (
          <div className="mb-4 rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">
            {erro}
          </div>
        )}

        {modo === 'login' ? (
          <form onSubmit={handleLogin} className="space-y-4">
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                E-mail
              </label>
              <div className="relative">
                <Mail size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  id="email"
                  type="email"
                  required
                  autoComplete="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg pl-10 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent"
                />
              </div>
            </div>

            <div>
              <label htmlFor="senha" className="block text-sm font-medium text-gray-700 mb-1">
                Senha
              </label>
              <div className="relative">
                <Lock size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  id="senha"
                  type="password"
                  required
                  autoComplete="current-password"
                  value={senha}
                  onChange={(e) => setSenha(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg pl-10 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent"
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={enviando}
              className="w-full bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors disabled:opacity-60 flex items-center justify-center gap-2"
            >
              <LogIn size={16} />
              {enviando ? 'Entrando…' : 'Entrar'}
            </button>

            <button
              type="button"
              onClick={() => { setModo('recuperar'); setErro(''); }}
              className="w-full text-sm text-gray-500 hover:text-red-600 transition-colors"
            >
              Esqueci minha senha
            </button>
          </form>
        ) : (
          <form onSubmit={handleRecuperar} className="space-y-4">
            <p className="text-sm text-gray-600">
              Informe seu e-mail de cadastro. Enviaremos um link para redefinir a senha.
            </p>
            <div>
              <label htmlFor="email-recuperar" className="block text-sm font-medium text-gray-700 mb-1">
                E-mail
              </label>
              <input
                id="email-recuperar"
                type="email"
                required
                autoComplete="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent"
              />
            </div>

            <button
              type="submit"
              disabled={enviando}
              className="w-full bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors disabled:opacity-60"
            >
              {enviando ? 'Enviando…' : 'Enviar link de redefinição'}
            </button>

            <button
              type="button"
              onClick={() => { setModo('login'); setErro(''); }}
              className="w-full text-sm text-gray-500 hover:text-red-600 transition-colors"
            >
              Voltar ao login
            </button>
          </form>
        )}

        <p className="text-center mt-6">
          <Link to="/" className="text-sm text-gray-400 hover:text-red-600 transition-colors">
            ← Voltar ao site
          </Link>
        </p>
      </div>
    </div>
  );
};

export default LoginPage;
