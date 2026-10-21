import React, { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Lock } from 'lucide-react';
import { auth, ApiError } from '../../lib/api';

const RedefinirSenhaPage: React.FC = () => {
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';

  const [senha, setSenha] = useState('');
  const [confirmacao, setConfirmacao] = useState('');
  const [erro, setErro] = useState('');
  const [ok, setOk] = useState(false);
  const [enviando, setEnviando] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErro('');

    if (senha.length < 10) {
      setErro('A senha deve ter pelo menos 10 caracteres.');
      return;
    }
    if (senha !== confirmacao) {
      setErro('As senhas não coincidem.');
      return;
    }

    setEnviando(true);
    try {
      await auth.redefinir(token, senha);
      setOk(true);
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Não foi possível redefinir a senha.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-100 flex items-center justify-center px-4">
      <div className="bg-white rounded-lg shadow-lg w-full max-w-md p-8">
        <h1 className="text-xl font-bold text-gray-800 text-center mb-6">Redefinir senha</h1>

        {ok ? (
          <div className="text-center space-y-4">
            <p className="text-green-700 bg-green-50 rounded-lg px-4 py-3" role="status">
              Senha redefinida com sucesso.
            </p>
            <Link to="/login" className="text-red-600 font-semibold hover:underline">
              Ir para o login
            </Link>
          </div>
        ) : !token ? (
          <div className="text-center space-y-4">
            <p className="text-red-700 bg-red-50 rounded-lg px-4 py-3" role="alert">
              Link inválido — solicite uma nova redefinição.
            </p>
            <Link to="/login" className="text-red-600 font-semibold hover:underline">
              Voltar ao login
            </Link>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            {erro && (
              <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">
                {erro}
              </div>
            )}

            <div>
              <label htmlFor="nova-senha" className="block text-sm font-medium text-gray-700 mb-1">
                Nova senha (mín. 10 caracteres)
              </label>
              <div className="relative">
                <Lock size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  id="nova-senha"
                  type="password"
                  required
                  autoComplete="new-password"
                  value={senha}
                  onChange={(e) => setSenha(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg pl-10 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent"
                />
              </div>
            </div>

            <div>
              <label htmlFor="confirmar-senha" className="block text-sm font-medium text-gray-700 mb-1">
                Confirmar senha
              </label>
              <input
                id="confirmar-senha"
                type="password"
                required
                autoComplete="new-password"
                value={confirmacao}
                onChange={(e) => setConfirmacao(e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent"
              />
            </div>

            <button
              type="submit"
              disabled={enviando}
              className="w-full bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors disabled:opacity-60"
            >
              {enviando ? 'Redefinindo…' : 'Redefinir senha'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
};

export default RedefinirSenhaPage;
