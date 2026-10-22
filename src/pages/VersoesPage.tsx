import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, Church, Code2, Database, GitCommitHorizontal, Layers, Rocket, Wrench } from 'lucide-react';
import { VERSAO_ATUAL, VERSOES, Versao } from '../lib/versoes';

const TIPO_META: Record<Versao['tipo'], { label: string; classes: string }> = {
  feature: { label: 'Feature', classes: 'bg-red-100 text-red-700' },
  correcao: { label: 'Correção', classes: 'bg-yellow-100 text-yellow-800' },
  docs: { label: 'Docs', classes: 'bg-blue-100 text-blue-700' },
  refactor: { label: 'Refactor', classes: 'bg-purple-100 text-purple-700' },
  teste: { label: 'Teste', classes: 'bg-teal-100 text-teal-700' },
  chore: { label: 'Chore', classes: 'bg-gray-200 text-gray-700' },
};

const FILTROS = [
  { chave: 'todos', label: 'Todos' },
  { chave: 'feature', label: 'Features' },
  { chave: 'correcao', label: 'Correções' },
  { chave: 'docs', label: 'Docs' },
  { chave: 'refactor', label: 'Refactor' },
  { chave: 'teste', label: 'Testes' },
  { chave: 'chore', label: 'Chores' },
] as const;

const STACK = [
  { nome: 'React', detalhe: 'v18' },
  { nome: 'TypeScript', detalhe: 'v5 strict' },
  { nome: 'Vite', detalhe: 'v7' },
  { nome: 'Tailwind', detalhe: 'v3' },
  { nome: 'PHP', detalhe: '8.3' },
  { nome: 'Slim', detalhe: 'v4' },
  { nome: 'MySQL', detalhe: 'cPanel' },
  { nome: 'PDO', detalhe: 'prepared' },
  { nome: 'Sessões', detalhe: 'PHP+CSRF' },
  { nome: 'FTPS', detalhe: 'deploy' },
];

const formatarData = (iso: string): string => {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
};

/**
 * Página pública /versoes — histórico de versões derivado do git log
 * (cada commit = v1.0.N; versoes.ts é gerado em prebuild/predev).
 */
const VersoesPage: React.FC = () => {
  const [filtro, setFiltro] = useState<(typeof FILTROS)[number]['chave']>('todos');

  const stats = useMemo(() => {
    const por: Partial<Record<Versao['tipo'], number>> = {};
    for (const v of VERSOES) {
      por[v.tipo] = (por[v.tipo] ?? 0) + 1;
    }
    return por;
  }, []);

  const filtradas = filtro === 'todos' ? VERSOES : VERSOES.filter((v) => v.tipo === filtro);

  const cards = [
    { icone: GitCommitHorizontal, valor: VERSOES.length, label: 'Commits totais' },
    { icone: Rocket, valor: stats.feature ?? 0, label: 'Features' },
    { icone: Wrench, valor: stats.correcao ?? 0, label: 'Correções' },
    { icone: Code2, valor: stats.docs ?? 0, label: 'Docs' },
    { icone: Layers, valor: (stats.refactor ?? 0) + (stats.teste ?? 0), label: 'Refactor/Tests' },
  ];

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white border-b sticky top-0 z-40">
        <div className="container mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-2 text-red-600 font-bold">
            <Church size={24} />
            <div className="leading-tight">
              <p className="text-gray-800 text-sm">ADAB — Gestão do Templo</p>
              <p className="text-xs font-normal text-gray-500">Assembleia de Deus · Templo Arthur Bernardes</p>
            </div>
          </div>
          <Link to="/" className="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-red-600 transition-colors">
            <ArrowLeft size={16} />
            Voltar
          </Link>
        </div>
      </header>

      {/* Versão atual */}
      <section className="container mx-auto px-4 py-14 text-center">
        <p className="text-xs font-semibold tracking-widest text-gray-500 uppercase mb-2">
          Versão atual em produção
        </p>
        <h1 className="text-5xl sm:text-6xl font-extrabold text-gray-900 mb-3">
          {VERSAO_ATUAL}
        </h1>
        <p className="text-gray-600">Sistema Integrado de Gestão — ADAB Templo Arthur Bernardes</p>

        {/* Stats */}
        <div className="grid grid-cols-2 sm:grid-cols-5 gap-4 max-w-3xl mx-auto mt-10">
          {cards.map(({ icone: Icone, valor, label }) => (
            <div key={label} className="bg-white rounded-xl border p-4">
              <Icone size={18} className="mx-auto text-red-600 mb-1" />
              <p className="text-2xl font-bold text-gray-900">{valor}</p>
              <p className="text-xs text-gray-500">{label}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Stack */}
      <section className="container mx-auto px-4 pb-8">
        <h2 className="text-center text-sm font-semibold tracking-widest text-gray-500 uppercase mb-4">
          Stack Tecnológica
        </h2>
        <div className="flex flex-wrap justify-center gap-3 max-w-3xl mx-auto">
          {STACK.map((s) => (
            <div key={s.nome} className="bg-white border rounded-lg px-4 py-2 text-center">
              <p className="text-sm font-semibold text-gray-800">{s.nome}</p>
              <p className="text-xs text-gray-500">{s.detalhe}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Filtros */}
      <section className="container mx-auto px-4 pb-4">
        <div className="flex flex-wrap justify-center gap-2">
          {FILTROS.map((f) => (
            <button
              key={f.chave}
              onClick={() => setFiltro(f.chave)}
              className={`px-4 py-1.5 rounded-full text-sm font-medium transition-colors ${
                filtro === f.chave
                  ? 'bg-red-600 text-white'
                  : 'bg-white border text-gray-600 hover:border-red-600 hover:text-red-600'
              }`}
              aria-pressed={filtro === f.chave}
            >
              {f.label}
            </button>
          ))}
        </div>
      </section>

      {/* Timeline */}
      <section className="container mx-auto px-4 pb-16 max-w-3xl">
        {filtradas.length === 0 ? (
          <p className="text-center text-gray-500 py-8">Nenhuma versão neste filtro.</p>
        ) : (
          <ul className="relative border-l-2 border-gray-200 ml-4 space-y-4">
            {filtradas.map((v) => (
              <li key={v.versao} className="relative pl-6">
                <span className="absolute -left-[9px] top-4 w-4 h-4 rounded-full bg-white border-2 border-red-600" aria-hidden="true" />
                <div className="bg-white rounded-lg border p-4 hover:border-red-300 transition-colors">
                  <div className="flex flex-wrap items-center gap-2 mb-1">
                    <span className="font-mono font-bold text-gray-900">{v.versao}</span>
                    <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${TIPO_META[v.tipo].classes}`}>
                      {TIPO_META[v.tipo].label}
                    </span>
                    <span className="ml-auto flex items-center gap-3 text-xs text-gray-500">
                      <span className="hidden sm:inline">{formatarData(v.data)}</span>
                      <code className="bg-gray-100 px-1.5 py-0.5 rounded font-mono">{v.hash}</code>
                    </span>
                  </div>
                  <p className="text-sm text-gray-700">{v.mensagem}</p>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      {/* Crédito */}
      <footer className="border-t bg-white py-6">
        <p className="text-center text-sm text-gray-500">
          <Database size={14} className="inline-block mr-1 -mt-0.5 text-red-600" />
          ADAB — Sistema de Gestão do Templo Arthur Bernardes ·{' '}
          Desenvolvido por{' '}
          <a
            href="https://wa.me/5591981758645"
            target="_blank"
            rel="noopener noreferrer"
            className="text-red-600 font-medium hover:underline"
          >
            Leon James dos Santos
          </a>
        </p>
      </footer>
    </div>
  );
};

export default VersoesPage;
