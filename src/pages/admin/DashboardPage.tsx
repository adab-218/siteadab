import React, { useEffect, useState } from 'react';
import { Cake, Loader2 } from 'lucide-react';
import { Aniversariante, ApiError, membrosApi } from '../../lib/api';
import { useAuth } from '../../lib/auth-context';

const MESES = [
  'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
];

const DashboardPage: React.FC = () => {
  const { usuario } = useAuth();
  const [aniversariantes, setAniversariantes] = useState<Aniversariante[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');

  const mesAtual = new Date().getMonth() + 1;

  useEffect(() => {
    membrosApi
      .aniversariantes()
      .then((r) => setAniversariantes(r.itens))
      .catch((e) => setErro(e instanceof ApiError ? e.message : 'Erro ao carregar aniversariantes.'))
      .finally(() => setCarregando(false));
  }, []);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-800">
          Bem-vindo{usuario?.nome ? `, ${usuario.nome.split(' ')[0]}` : ''}
        </h1>
        <p className="text-gray-500">Painel de gestão do Templo Arthur Bernardes.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-white rounded-lg shadow-sm border p-5">
          <h2 className="font-semibold text-gray-800 mb-2">Membros</h2>
          <p className="text-sm text-gray-500">
            Cadastro, status e vínculos de departamento — disponível no menu Membros.
          </p>
        </div>

        <div className="bg-white rounded-lg shadow-sm border p-5">
          <h2 className="font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <Cake size={16} className="text-red-600" />
            Aniversariantes de {MESES[mesAtual - 1]}
          </h2>
          {carregando ? (
            <p className="text-sm text-gray-400 flex items-center gap-2" role="status">
              <Loader2 size={14} className="animate-spin" /> Carregando…
            </p>
          ) : erro ? (
            <p className="text-sm text-red-600" role="alert">{erro}</p>
          ) : aniversariantes.length === 0 ? (
            <p className="text-sm text-gray-500">Nenhum aniversariante neste mês.</p>
          ) : (
            <ul className="text-sm text-gray-700 space-y-1 max-h-48 overflow-auto">
              {aniversariantes.map((a) => (
                <li key={a.pessoa_id} className="flex justify-between gap-2">
                  <span className="truncate">{a.nome_completo}</span>
                  <span className="text-gray-500 shrink-0">dia {a.dia}</span>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="bg-white rounded-lg shadow-sm border p-5">
          <h2 className="font-semibold text-gray-800 mb-2">EBD</h2>
          <p className="text-sm text-gray-500">
            Classes, chamada e relatórios — após Membros e Cultos.
          </p>
        </div>
      </div>
    </div>
  );
};

export default DashboardPage;
