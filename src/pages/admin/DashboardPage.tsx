import React, { useEffect, useState } from 'react';
import { BookOpen, Cake, CalendarDays, Church, Gift, Loader2, Users } from 'lucide-react';
import { Aniversariante, ApiError, DashboardResumo, dashboardApi, membrosApi } from '../../lib/api';
import { useAuth } from '../../lib/auth-context';

const MESES = [
  'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
];

interface KpiProps {
  icone: React.ReactNode;
  titulo: string;
  valor: React.ReactNode;
  detalhe?: React.ReactNode;
}

const Kpi: React.FC<KpiProps> = ({ icone, titulo, valor, detalhe }) => (
  <div className="bg-white rounded-lg shadow-sm border p-4">
    <p className="text-xs font-medium text-gray-500 flex items-center gap-1.5">
      <span className="text-red-600" aria-hidden="true">{icone}</span>
      {titulo}
    </p>
    <p className="text-2xl font-bold text-gray-800 mt-1">{valor}</p>
    {detalhe && <p className="text-xs text-gray-500 mt-0.5">{detalhe}</p>}
  </div>
);

const dataCurta = (iso: string) => {
  const [a, m, d] = iso.split('-');
  return `${d}/${m}/${a}`;
};

const DashboardPage: React.FC = () => {
  const { usuario } = useAuth();
  const [aniversariantes, setAniversariantes] = useState<Aniversariante[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');

  const mesAtual = new Date().getMonth() + 1;
  const [resumo, setResumo] = useState<DashboardResumo | null>(null);
  const [erroResumo, setErroResumo] = useState('');

  useEffect(() => {
    dashboardApi
      .resumo()
      .then(setResumo)
      .catch((e) => setErroResumo(e instanceof ApiError ? e.message : 'Erro ao carregar indicadores.'));
  }, []);

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

      <section aria-label="Indicadores">
        {erroResumo ? (
          <p className="text-sm text-red-600" role="alert">{erroResumo}</p>
        ) : !resumo ? (
          <p className="text-sm text-gray-400 flex items-center gap-2" role="status">
            <Loader2 size={14} className="animate-spin" /> Carregando indicadores…
          </p>
        ) : (
          <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
            <Kpi icone={<Users size={14} />} titulo="Membros ativos" valor={resumo.membros.ativos}
                 detalhe={`de ${resumo.membros.total} cadastrados`} />
            <Kpi icone={<Church size={14} />} titulo={`Cultos em ${MESES[Number(resumo.referencia_mes.slice(5, 7)) - 1] ?? ''}`}
                 valor={resumo.cultos_mes.realizados}
                 detalhe={resumo.cultos_mes.media_presentes !== null
                   ? `média de ${resumo.cultos_mes.media_presentes} presentes · ${resumo.cultos_mes.decisoes} decisão(ões)`
                   : 'sem presença lançada'} />
            <Kpi icone={<BookOpen size={14} />} titulo="Frequência EBD"
                 valor={resumo.ebd.frequencia_pct !== null ? `${resumo.ebd.frequencia_pct}%` : '—'}
                 detalhe={resumo.ebd.ultima_aula
                   ? `${resumo.ebd.presentes} presentes em ${dataCurta(resumo.ebd.ultima_aula)} · ${resumo.ebd.matriculados} matriculados`
                   : `${resumo.ebd.matriculados} matriculados · sem chamada`} />
            <Kpi icone={<CalendarDays size={14} />} titulo="Eventos (30 dias)" valor={resumo.eventos_proximos_30d} />
            <Kpi icone={<Gift size={14} />} titulo="Sorteios ativos" valor={resumo.rifas_ativas} />
          </div>
        )}
      </section>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">

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
      </div>
    </div>
  );
};

export default DashboardPage;
