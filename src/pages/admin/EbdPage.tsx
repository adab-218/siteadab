import React, { useCallback, useEffect, useRef, useState } from 'react';
import { BookOpen, CalendarDays, CheckSquare, GraduationCap, Plus, Trash2, UserPlus, Users } from 'lucide-react';
import Modal from '../../components/ui/Modal';
import Field from '../../components/ui/Field';
import {
  ApiError,
  ebdApi,
  EbdAula,
  EbdChamadaItem,
  EbdClasse,
  EbdPeriodo,
  EbdProfessor,
  EbdAluno,
  membrosApi,
  MembroListItem,
} from '../../lib/api';
import { useAuth } from '../../lib/auth-context';

const formatarData = (iso: string | null | undefined): string => {
  if (!iso) return '—';
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('pt-BR');
};

const PAPEL_LABEL: Record<EbdProfessor['papel'], string> = {
  titular: 'Titular',
  auxiliar: 'Auxiliar',
  substituto: 'Substituto',
};

type Aba = 'classes' | 'alunos' | 'periodos' | 'chamada';

/** Busca de pessoa reutilizada nas três abas. */
function useBuscaPessoa() {
  const [busca, setBusca] = useState('');
  const [resultados, setResultados] = useState<MembroListItem[]>([]);
  const [selecionada, setSelecionada] = useState<MembroListItem | null>(null);
  const debounce = useRef<ReturnType<typeof setTimeout>>();

  useEffect(() => {
    clearTimeout(debounce.current);
    const termo = busca.trim();
    if (termo.length < 2 || selecionada) {
      setResultados([]);
      return;
    }
    debounce.current = setTimeout(async () => {
      try {
        const r = await membrosApi.listar({ q: termo, status: 'ativo', por_pagina: 10 });
        setResultados(r.itens);
      } catch {
        setResultados([]);
      }
    }, 300);
    return () => clearTimeout(debounce.current);
  }, [busca, selecionada]);

  const limpar = () => { setBusca(''); setSelecionada(null); setResultados([]); };
  return { busca, setBusca, resultados, selecionada, setSelecionada, limpar };
}

/** Picker de membro — nível de módulo (dentro do componente remonta a cada tecla e perde o foco). */
const PessoaPicker: React.FC<{ busca: ReturnType<typeof useBuscaPessoa>; rotulo: string }> = ({ busca: b, rotulo }) => (
  <div className="relative">
    <label className="block text-sm font-medium text-gray-700 mb-1">{rotulo}</label>
    <input
      type="search"
      placeholder="Buscar membro ativo…"
      value={b.busca}
      onChange={(e) => { b.setBusca(e.target.value); b.setSelecionada(null); }}
      className="w-full border border-gray-300 rounded-lg px-3 py-2"
    />
    {b.resultados.length > 0 && (
      <ul className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-40 overflow-auto">
        {b.resultados.map((m) => (
          <li key={m.pessoa_id}>
            <button
              type="button"
              onClick={() => { b.setSelecionada(m); b.setBusca(m.nome_completo); }}
              className="w-full text-left px-3 py-2 text-sm hover:bg-red-50"
            >
              {m.nome_completo}
            </button>
          </li>
        ))}
      </ul>
    )}
  </div>
);

const EbdPage: React.FC = () => {
  const { usuario } = useAuth();
  const ebdAdmin = ['admin', 'ebd_admin'].includes(usuario?.perfil ?? '');

  const [aba, setAba] = useState<Aba>('classes');
  const [erro, setErro] = useState('');
  const [erroForm, setErroForm] = useState('');
  const [salvando, setSalvando] = useState(false);
  const [avisoChamada, setAvisoChamada] = useState('');

  // ----- Classes -----
  const [classes, setClasses] = useState<EbdClasse[]>([]);
  const [modalClasse, setModalClasse] = useState<null | { id?: number }>(null);
  const [classeForm, setClasseForm] = useState({ nome: '', faixa_etaria: '', sala: '', ativo: true });
  const [modalProfs, setModalProfs] = useState<null | EbdClasse>(null);
  const [profs, setProfs] = useState<EbdProfessor[]>([]);
  const [papelProf, setPapelProf] = useState<EbdProfessor['papel']>('auxiliar');
  const buscaProf = useBuscaPessoa();

  // ----- Alunos -----
  const [alunos, setAlunos] = useState<EbdAluno[]>([]);
  const [filtroClasseAluno, setFiltroClasseAluno] = useState('');
  const [filtroStatusAluno, setFiltroStatusAluno] = useState('matriculado');
  const [modalMatricula, setModalMatricula] = useState(false);
  const [classeMatricula, setClasseMatricula] = useState('');
  const [obsMatricula, setObsMatricula] = useState('');
  const buscaAluno = useBuscaPessoa();
  const [modalTransferir, setModalTransferir] = useState<null | EbdAluno>(null);
  const [classeDestino, setClasseDestino] = useState('');

  // ----- Períodos -----
  const [periodos, setPeriodos] = useState<EbdPeriodo[]>([]);
  const [periodoSel, setPeriodoSel] = useState<EbdPeriodo | null>(null);
  const [aulas, setAulas] = useState<EbdAula[]>([]);
  const [periodoForm, setPeriodoForm] = useState({ ano: new Date().getFullYear(), trimestre: 1, tema_revista: '', data_inicio: '', data_fim: '' });
  const [aulaForm, setAulaForm] = useState({ data: '', titulo_licao: '', texto_aureo: '', leitura_biblica: '' });

  // ----- Chamada -----
  const [chamPeriodo, setChamPeriodo] = useState('');
  const [chamAulas, setChamAulas] = useState<EbdAula[]>([]);
  const [chamAula, setChamAula] = useState('');
  const [chamClasse, setChamClasse] = useState('');
  const [chamada, setChamada] = useState<EbdChamadaItem[]>([]);
  const [aulaInfo, setAulaInfo] = useState<EbdAula | null>(null);

  // ==========================================================
  const carregarClasses = useCallback(async () => {
    try {
      setClasses(await ebdApi.classes());
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar classes.');
    }
  }, []);

  const carregarPeriodos = useCallback(async () => {
    try {
      setPeriodos(await ebdApi.periodos());
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar períodos.');
    }
  }, []);

  const carregarAlunos = useCallback(async () => {
    try {
      setAlunos(await ebdApi.alunos({
        classe_id: filtroClasseAluno ? Number(filtroClasseAluno) : undefined,
        status: (filtroStatusAluno as EbdAluno['status']) || undefined,
      }));
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar alunos.');
    }
  }, [filtroClasseAluno, filtroStatusAluno]);

  useEffect(() => {
    carregarClasses();
    carregarPeriodos();
  }, [carregarClasses, carregarPeriodos]);

  useEffect(() => {
    if (aba === 'alunos') carregarAlunos();
  }, [aba, carregarAlunos]);

  // ===== Classes =====
  const salvarClasse = async (e: React.FormEvent) => {
    e.preventDefault();
    setSalvando(true);
    setErroForm('');
    try {
      if (modalClasse?.id) {
        await ebdApi.atualizarClasse(modalClasse.id, classeForm);
      } else {
        await ebdApi.criarClasse(classeForm);
      }
      setModalClasse(null);
      carregarClasses();
    } catch (e2) {
      setErroForm(e2 instanceof ApiError ? e2.message : 'Erro ao salvar classe.');
    } finally {
      setSalvando(false);
    }
  };

  const abrirProfs = async (c: EbdClasse) => {
    setModalProfs(c);
    setErroForm('');
    buscaProf.limpar();
    try {
      setProfs(await ebdApi.professores(c.id));
    } catch {
      setProfs([]);
    }
  };

  const adicionarProf = async () => {
    if (!modalProfs || !buscaProf.selecionada) return;
    setSalvando(true);
    setErroForm('');
    try {
      setProfs(await ebdApi.vincularProfessor(modalProfs.id, buscaProf.selecionada.pessoa_id, papelProf));
      buscaProf.limpar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao vincular professor.');
    } finally {
      setSalvando(false);
    }
  };

  const removerProf = async (pid: number) => {
    if (!modalProfs) return;
    try {
      setProfs(await ebdApi.desvincularProfessor(modalProfs.id, pid));
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao remover professor.');
    }
  };

  // ===== Alunos =====
  const matricular = async () => {
    if (!buscaAluno.selecionada || !classeMatricula) return;
    setSalvando(true);
    setErroForm('');
    try {
      await ebdApi.matricular(buscaAluno.selecionada.pessoa_id, Number(classeMatricula), obsMatricula || undefined);
      setModalMatricula(false);
      buscaAluno.limpar();
      setObsMatricula('');
      carregarAlunos();
      carregarClasses();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao matricular.');
    } finally {
      setSalvando(false);
    }
  };

  const transferir = async () => {
    if (!modalTransferir || !classeDestino) return;
    setSalvando(true);
    setErroForm('');
    try {
      await ebdApi.transferir(modalTransferir.id, Number(classeDestino));
      setModalTransferir(null);
      carregarAlunos();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao transferir.');
    } finally {
      setSalvando(false);
    }
  };

  const desistir = async (a: EbdAluno) => {
    try {
      await ebdApi.desistir(a.id);
      carregarAlunos();
      carregarClasses();
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao desistir.');
    }
  };

  // ===== Períodos =====
  const criarPeriodo = async () => {
    setSalvando(true);
    setErroForm('');
    try {
      await ebdApi.criarPeriodo({
        ...periodoForm,
        tema_revista: periodoForm.tema_revista || undefined,
      });
      setPeriodoForm({ ano: new Date().getFullYear(), trimestre: 1, tema_revista: '', data_inicio: '', data_fim: '' });
      carregarPeriodos();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao criar período.');
    } finally {
      setSalvando(false);
    }
  };

  const abrirPeriodo = async (p: EbdPeriodo) => {
    setPeriodoSel(p);
    setErroForm('');
    try {
      setAulas(await ebdApi.aulas(p.id));
    } catch {
      setAulas([]);
    }
  };

  const gerarDomingos = async () => {
    if (!periodoSel) return;
    setSalvando(true);
    setErroForm('');
    try {
      const r = await ebdApi.gerarAulas(periodoSel.id);
      setAulas(r.aulas);
      carregarPeriodos();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao gerar aulas.');
    } finally {
      setSalvando(false);
    }
  };

  const criarAulaPontual = async () => {
    if (!periodoSel || !aulaForm.data) return;
    setSalvando(true);
    setErroForm('');
    try {
      await ebdApi.criarAula(periodoSel.id, aulaForm);
      setAulaForm({ data: '', titulo_licao: '', texto_aureo: '', leitura_biblica: '' });
      setAulas(await ebdApi.aulas(periodoSel.id));
      carregarPeriodos();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao criar aula.');
    } finally {
      setSalvando(false);
    }
  };

  // ===== Chamada =====
  const aoEscolherPeriodoChamada = async (pid: string) => {
    setChamPeriodo(pid);
    setChamAula('');
    setChamada([]);
    setAulaInfo(null);
    if (pid) {
      try {
        setChamAulas(await ebdApi.aulas(Number(pid)));
      } catch {
        setChamAulas([]);
      }
    } else {
      setChamAulas([]);
    }
  };

  const carregarChamada = async () => {
    if (!chamAula || !chamClasse) return;
    setErro('');
    try {
      const r = await ebdApi.chamada(Number(chamAula), Number(chamClasse));
      setAulaInfo(r.aula);
      setChamada(r.chamada.map((c) => ({ ...c, presente: c.presente ?? 0 })));
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar chamada.');
      setChamada([]);
      setAulaInfo(null);
    }
  };

  const toggleFreq = (alunoId: number, campo: 'presente' | 'trouxe_biblia' | 'trouxe_revista') => {
    setChamada((list) =>
      list.map((c) => (c.aluno_id === alunoId ? { ...c, [campo]: c[campo] ? 0 : 1 } : c))
    );
  };

  const salvarChamada = async () => {
    if (!aulaInfo) return;
    setSalvando(true);
    setErro('');
    setAvisoChamada('');
    try {
      const r = await ebdApi.salvarChamada(
        aulaInfo.id,
        Number(chamClasse),
        chamada.map((c) => ({
          aluno_id: c.aluno_id,
          presente: c.presente,
          trouxe_biblia: c.trouxe_biblia,
          trouxe_revista: c.trouxe_revista,
          oferta: c.oferta || undefined,
          visitante: c.visitante,
        }))
      );
      setChamada(r.chamada);
      setAvisoChamada(`${r.registrados} registro(s) de presença salvos.`);
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao salvar chamada.');
    } finally {
      setSalvando(false);
    }
  };

  const abas: { chave: Aba; label: string; icone: React.ElementType }[] = [
    { chave: 'classes', label: 'Classes', icone: GraduationCap },
    { chave: 'alunos', label: 'Alunos', icone: Users },
    { chave: 'periodos', label: 'Períodos & Aulas', icone: CalendarDays },
    { chave: 'chamada', label: 'Chamada', icone: CheckSquare },
  ];

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-gray-800">Escola Bíblica Dominical</h1>
        <p className="text-gray-500 text-sm">Classes, matrículas, períodos letivos e chamada de domingo</p>
      </div>

      {/* Abas */}
      <div className="flex gap-1 border-b">
        {abas.map(({ chave, label, icone: Icone }) => (
          <button
            key={chave}
            onClick={() => { setAba(chave); setErro(''); setErroForm(''); }}
            className={`flex items-center gap-2 px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${
              aba === chave ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-800'
            }`}
          >
            <Icone size={15} />
            {label}
          </button>
        ))}
      </div>

      {erro && <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erro}</div>}

      {/* ============ ABA CLASSES ============ */}
      {aba === 'classes' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            {ebdAdmin && (
              <button
                onClick={() => { setClasseForm({ nome: '', faixa_etaria: '', sala: '', ativo: true }); setErroForm(''); setModalClasse({}); }}
                className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 text-sm"
              >
                <Plus size={15} /> Nova classe
              </button>
            )}
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {classes.map((c) => (
              <div key={c.id} className={`bg-white rounded-lg border p-4 ${!c.ativo ? 'opacity-60' : ''}`}>
                <div className="flex items-start justify-between">
                  <div>
                    <h3 className="font-bold text-gray-800">{c.nome}</h3>
                    <p className="text-xs text-gray-500">
                      {c.faixa_etaria ?? 'Todas as idades'}
                      {c.sala && ` · ${c.sala}`}
                    </p>
                  </div>
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${c.ativo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'}`}>
                    {c.ativo ? 'Ativa' : 'Inativa'}
                  </span>
                </div>
                <p className="text-xs text-gray-500 mt-2">
                  {c.total_professores ?? 0} professor(es) · {c.total_alunos ?? 0} aluno(s)
                </p>
                <div className="flex gap-2 mt-3">
                  <button onClick={() => abrirProfs(c)} className="text-xs text-red-600 hover:underline">Professores</button>
                  {ebdAdmin && (
                    <button
                      onClick={() => {
                        setClasseForm({ nome: c.nome, faixa_etaria: c.faixa_etaria ?? '', sala: c.sala ?? '', ativo: !!c.ativo });
                        setErroForm('');
                        setModalClasse({ id: c.id });
                      }}
                      className="text-xs text-blue-600 hover:underline"
                    >
                      Editar
                    </button>
                  )}
                </div>
              </div>
            ))}
            {classes.length === 0 && (
              <p className="col-span-full text-gray-500 text-sm p-4">Nenhuma classe cadastrada.</p>
            )}
          </div>
        </div>
      )}

      {/* ============ ABA ALUNOS ============ */}
      {aba === 'alunos' && (
        <div className="space-y-4">
          <div className="bg-white rounded-lg border p-4 flex flex-wrap gap-3 items-center">
            <div>
              <label htmlFor="al-classe" className="block text-sm font-medium text-gray-700 mb-1">Classe</label>
              <select id="al-classe" value={filtroClasseAluno} onChange={(e) => setFiltroClasseAluno(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-2">
                <option value="">Todas</option>
                {classes.filter((c) => c.ativo).map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
              </select>
            </div>
            <div>
              <label htmlFor="al-status" className="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select id="al-status" value={filtroStatusAluno} onChange={(e) => setFiltroStatusAluno(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-2">
                <option value="">Todos</option>
                <option value="matriculado">Matriculados</option>
                <option value="transferido">Transferidos</option>
                <option value="desistente">Desistentes</option>
              </select>
            </div>
            {ebdAdmin && (
              <button
                onClick={() => { buscaAluno.limpar(); setObsMatricula(''); setErroForm(''); setModalMatricula(true); }}
                className="ml-auto bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 text-sm"
              >
                <Plus size={15} /> Matricular
              </button>
            )}
          </div>

          <div className="bg-white rounded-lg border overflow-hidden">
            {alunos.length === 0 ? (
              <p className="p-6 text-gray-500">Nenhum aluno encontrado.</p>
            ) : (
              <table className="w-full text-sm">
                <thead className="bg-gray-50 text-left text-gray-600">
                  <tr>
                    <th className="px-4 py-3 font-medium">Aluno</th>
                    <th className="px-4 py-3 font-medium">Classe</th>
                    <th className="px-4 py-3 font-medium">Matrícula</th>
                    <th className="px-4 py-3 font-medium">Status</th>
                    {ebdAdmin && <th className="px-4 py-3 font-medium text-right">Ações</th>}
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {alunos.map((a) => (
                    <tr key={a.id} className="hover:bg-gray-50">
                      <td className="px-4 py-3 font-medium text-gray-800">{a.nome_completo}</td>
                      <td className="px-4 py-3 text-gray-600">{a.classe_nome}</td>
                      <td className="px-4 py-3 text-gray-600">{formatarData(a.data_matricula)}</td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                          a.status === 'matriculado' ? 'bg-green-100 text-green-800'
                          : a.status === 'transferido' ? 'bg-blue-100 text-blue-800'
                          : 'bg-gray-200 text-gray-600'
                        }`}>
                          {a.status === 'matriculado' ? 'Matriculado' : a.status === 'transferido' ? 'Transferido' : 'Desistente'}
                        </span>
                      </td>
                      {ebdAdmin && a.status === 'matriculado' && (
                        <td className="px-4 py-3 text-right">
                          <button onClick={() => { setModalTransferir(a); setClasseDestino(''); setErroForm(''); }} className="text-xs text-blue-600 hover:underline mr-3">
                            Transferir
                          </button>
                          <button onClick={() => desistir(a)} className="text-xs text-red-600 hover:underline">
                            Desistir
                          </button>
                        </td>
                      )}
                      {ebdAdmin && a.status !== 'matriculado' && <td />}
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}

      {/* ============ ABA PERÍODOS ============ */}
      {aba === 'periodos' && (
        <div className="space-y-4">
          {ebdAdmin && (
            <div className="bg-white rounded-lg border p-4">
              <h3 className="font-semibold text-gray-800 mb-3">Novo período (trimestral)</h3>
              {erroForm && <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3 mb-3" role="alert">{erroForm}</div>}
              <div className="grid sm:grid-cols-5 gap-3 items-end">
                <Field id="p-ano" label="Ano" type="number" value={String(periodoForm.ano)} onChange={(e) => setPeriodoForm((f) => ({ ...f, ano: Number(e.target.value) }))} />
                <div>
                  <label htmlFor="p-tri" className="block text-sm font-medium text-gray-700 mb-1">Trimestre</label>
                  <select id="p-tri" value={periodoForm.trimestre} onChange={(e) => setPeriodoForm((f) => ({ ...f, trimestre: Number(e.target.value) }))} className="w-full border border-gray-300 rounded-lg px-3 py-2">
                    {[1, 2, 3, 4].map((t) => <option key={t} value={t}>T{t}</option>)}
                  </select>
                </div>
                <Field id="p-ini" label="Início" type="date" value={periodoForm.data_inicio} onChange={(e) => setPeriodoForm((f) => ({ ...f, data_inicio: e.target.value }))} />
                <Field id="p-fim" label="Fim" type="date" value={periodoForm.data_fim} onChange={(e) => setPeriodoForm((f) => ({ ...f, data_fim: e.target.value }))} />
                <button onClick={criarPeriodo} disabled={salvando || !periodoForm.data_inicio || !periodoForm.data_fim} className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50 text-sm">
                  Criar
                </button>
              </div>
              <div className="mt-2">
                <Field id="p-tema" label="Tema da revista (opcional)" value={periodoForm.tema_revista} onChange={(e) => setPeriodoForm((f) => ({ ...f, tema_revista: e.target.value }))} />
              </div>
            </div>
          )}

          <div className="grid lg:grid-cols-2 gap-4">
            <div className="bg-white rounded-lg border overflow-hidden">
              <div className="p-3 bg-gray-50 font-semibold text-sm text-gray-700">Períodos</div>
              <ul className="divide-y max-h-96 overflow-auto">
                {periodos.map((p) => (
                  <li key={p.id}>
                    <button
                      onClick={() => abrirPeriodo(p)}
                      className={`w-full text-left px-4 py-3 text-sm hover:bg-red-50 ${periodoSel?.id === p.id ? 'bg-red-50' : ''}`}
                    >
                      <p className="font-medium text-gray-800">{p.ano}-T{p.trimestre}</p>
                      <p className="text-xs text-gray-500">
                        {formatarData(p.data_inicio)} – {formatarData(p.data_fim)} · {p.total_aulas ?? 0} aula(s)
                        {p.tema_revista && ` · ${p.tema_revista}`}
                      </p>
                    </button>
                  </li>
                ))}
                {periodos.length === 0 && <li className="p-4 text-sm text-gray-500">Nenhum período.</li>}
              </ul>
            </div>

            <div className="bg-white rounded-lg border overflow-hidden">
              <div className="p-3 bg-gray-50 flex items-center justify-between">
                <span className="font-semibold text-sm text-gray-700">
                  {periodoSel ? `Aulas de ${periodoSel.ano}-T${periodoSel.trimestre}` : 'Selecione um período'}
                </span>
                {periodoSel && ebdAdmin && (
                  <button onClick={gerarDomingos} disabled={salvando} className="text-xs bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 disabled:opacity-50">
                    Gerar domingos
                  </button>
                )}
              </div>
              {periodoSel && (
                <>
                  <ul className="divide-y max-h-64 overflow-auto">
                    {aulas.map((a) => (
                      <li key={a.id} className="px-4 py-2 text-sm">
                        <span className="font-medium text-gray-800">Lição {a.numero_licao}</span>
                        <span className="text-gray-500"> · {formatarData(a.data)}</span>
                        {a.titulo_licao && <span className="text-gray-600"> · {a.titulo_licao}</span>}
                        {new Date(a.data + 'T00:00').getDay() !== 0 && (
                          <span className="ml-2 px-1.5 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800">pontual</span>
                        )}
                      </li>
                    ))}
                    {aulas.length === 0 && <li className="p-4 text-sm text-gray-500">Sem aulas — gere os domingos.</li>}
                  </ul>
                  {ebdAdmin && (
                    <div className="border-t p-3 space-y-2 bg-gray-50">
                      <p className="text-xs font-semibold text-gray-600">Aula pontual / transferida (outro dia)</p>
                      <div className="grid grid-cols-2 gap-2">
                        <input type="date" value={aulaForm.data} onChange={(e) => setAulaForm((f) => ({ ...f, data: e.target.value }))} className="border border-gray-300 rounded-lg px-3 py-2 text-sm" aria-label="Data da aula pontual" />
                        <input placeholder="Título da lição" value={aulaForm.titulo_licao} onChange={(e) => setAulaForm((f) => ({ ...f, titulo_licao: e.target.value }))} className="border border-gray-300 rounded-lg px-3 py-2 text-sm" aria-label="Título da lição" />
                      </div>
                      <button onClick={criarAulaPontual} disabled={salvando || !aulaForm.data} className="w-full bg-gray-700 text-white py-1.5 rounded-lg text-sm hover:bg-gray-800 disabled:opacity-50">
                        Adicionar aula pontual
                      </button>
                    </div>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ============ ABA CHAMADA ============ */}
      {aba === 'chamada' && (
        <div className="space-y-4">
          {avisoChamada && (
            <div className="rounded-lg bg-green-50 text-green-800 text-sm px-4 py-3" role="status">
              {avisoChamada}
            </div>
          )}
          <div className="bg-white rounded-lg border p-4 grid sm:grid-cols-3 gap-3 items-end">
            <div>
              <label htmlFor="ch-periodo" className="block text-sm font-medium text-gray-700 mb-1">Período</label>
              <select id="ch-periodo" value={chamPeriodo} onChange={(e) => aoEscolherPeriodoChamada(e.target.value)} className="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">Selecione…</option>
                {periodos.map((p) => <option key={p.id} value={p.id}>{p.ano}-T{p.trimestre}</option>)}
              </select>
            </div>
            <div>
              <label htmlFor="ch-aula" className="block text-sm font-medium text-gray-700 mb-1">Aula</label>
              <select id="ch-aula" value={chamAula} onChange={(e) => { setChamAula(e.target.value); setChamada([]); setAulaInfo(null); }} className="w-full border border-gray-300 rounded-lg px-3 py-2" disabled={!chamAulas.length}>
                <option value="">Selecione…</option>
                {chamAulas.map((a) => <option key={a.id} value={a.id}>Lição {a.numero_licao} — {formatarData(a.data)}</option>)}
              </select>
            </div>
            <div>
              <label htmlFor="ch-classe" className="block text-sm font-medium text-gray-700 mb-1">Classe</label>
              <select id="ch-classe" value={chamClasse} onChange={(e) => setChamClasse(e.target.value)} className="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">Selecione…</option>
                {classes.filter((c) => c.ativo).map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
              </select>
            </div>
            <button onClick={carregarChamada} disabled={!chamAula || !chamClasse} className="sm:col-span-3 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-50 text-sm">
              Carregar chamada
            </button>
          </div>

          {aulaInfo && (
            <div className="bg-white rounded-lg border overflow-hidden">
              <div className="p-3 bg-gray-50 text-sm text-gray-700 flex items-center justify-between">
                <span className="font-medium">Lição {aulaInfo.numero_licao} — {formatarData(aulaInfo.data)}</span>
                <span className="text-xs">{chamada.filter((c) => c.presente).length} presente(s) de {chamada.length}</span>
              </div>
              {chamada.length === 0 ? (
                <p className="p-6 text-sm text-gray-500">Sem alunos matriculados nesta classe.</p>
              ) : (
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 text-left text-gray-600">
                    <tr>
                      <th className="px-4 py-2 font-medium">Aluno</th>
                      <th className="px-4 py-2 font-medium text-center">Presente</th>
                      <th className="px-4 py-2 font-medium text-center">Bíblia</th>
                      <th className="px-4 py-2 font-medium text-center">Revista</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {chamada.map((c) => (
                      <tr key={c.aluno_id} className="hover:bg-gray-50">
                        <td className="px-4 py-2 text-gray-800">{c.nome_completo}</td>
                        {(['presente', 'trouxe_biblia', 'trouxe_revista'] as const).map((campo) => (
                          <td key={campo} className="px-4 py-2 text-center">
                            <input
                              type="checkbox"
                              checked={!!c[campo]}
                              onChange={() => toggleFreq(c.aluno_id, campo)}
                              className="rounded border-gray-300 text-red-600 focus:ring-red-600 w-4 h-4"
                              aria-label={`${campo === 'presente' ? 'Presente' : campo === 'trouxe_biblia' ? 'Trouxe Bíblia' : 'Trouxe revista'} — ${c.nome_completo}`}
                            />
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
              {chamada.length > 0 && (
                <div className="p-3 border-t flex justify-end">
                  <button onClick={salvarChamada} disabled={salvando} className="bg-red-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60 text-sm">
                    {salvando ? 'Salvando…' : 'Salvar chamada'}
                  </button>
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* Modal classe */}
      {modalClasse && (
        <Modal titulo={modalClasse.id ? 'Editar classe' : 'Nova classe'} onClose={() => setModalClasse(null)} largura="max-w-md">
          <form onSubmit={salvarClasse} className="space-y-4">
            {erroForm && <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>}
            <Field id="cl-nome" label="Nome" required value={classeForm.nome} onChange={(e) => setClasseForm((f) => ({ ...f, nome: e.target.value }))} />
            <Field id="cl-faixa" label="Faixa etária" placeholder="Ex.: 9 a 12 anos" value={classeForm.faixa_etaria} onChange={(e) => setClasseForm((f) => ({ ...f, faixa_etaria: e.target.value }))} />
            <Field id="cl-sala" label="Sala" placeholder="Ex.: Sala 3" value={classeForm.sala} onChange={(e) => setClasseForm((f) => ({ ...f, sala: e.target.value }))} />
            {modalClasse.id && (
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" checked={classeForm.ativo} onChange={(e) => setClasseForm((f) => ({ ...f, ativo: e.target.checked }))} className="rounded border-gray-300 text-red-600 focus:ring-red-600" />
                Classe ativa
              </label>
            )}
            <div className="flex gap-3">
              <button type="submit" disabled={salvando || !classeForm.nome.trim()} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
                Salvar
              </button>
              <button type="button" onClick={() => setModalClasse(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                Cancelar
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* Modal professores */}
      {modalProfs && (
        <Modal titulo={`Professores — ${modalProfs.nome}`} onClose={() => setModalProfs(null)} largura="max-w-lg">
          <ul className="divide-y border rounded-lg mb-4">
            {profs.filter((p) => p.ativo).map((p) => (
              <li key={p.id} className="px-3 py-2 text-sm flex items-center justify-between">
                <span>
                  {p.nome_completo}
                  <span className={`ml-2 px-1.5 py-0.5 rounded text-xs font-medium ${p.papel === 'titular' ? 'bg-red-100 text-red-700' : 'bg-gray-200 text-gray-700'}`}>
                    {PAPEL_LABEL[p.papel]}
                  </span>
                </span>
                {ebdAdmin && (
                  <button onClick={() => removerProf(p.id)} className="p-1 text-gray-400 hover:text-red-600" aria-label={`Remover ${p.nome_completo}`}>
                    <Trash2 size={14} />
                  </button>
                )}
              </li>
            ))}
            {profs.filter((p) => p.ativo).length === 0 && <li className="p-4 text-sm text-gray-500">Nenhum professor.</li>}
          </ul>
          {ebdAdmin && (
            <div className="space-y-3">
              {erroForm && <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>}
              <PessoaPicker busca={buscaProf} rotulo="Adicionar professor" />
              <div className="flex gap-2 items-center">
                <select value={papelProf} onChange={(e) => setPapelProf(e.target.value as EbdProfessor['papel'])} className="border border-gray-300 rounded-lg px-3 py-2 text-sm" aria-label="Papel do professor">
                  {Object.entries(PAPEL_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
                <button onClick={adicionarProf} disabled={salvando || !buscaProf.selecionada} className="flex-1 bg-red-600 text-white py-2 rounded-lg text-sm hover:bg-red-700 disabled:opacity-50 flex items-center justify-center gap-1">
                  <UserPlus size={14} /> Vincular
                </button>
              </div>
            </div>
          )}
        </Modal>
      )}

      {/* Modal matrícula */}
      {modalMatricula && (
        <Modal titulo="Matricular aluno" onClose={() => setModalMatricula(false)} largura="max-w-md">
          <div className="space-y-4">
            {erroForm && <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>}
            <PessoaPicker busca={buscaAluno} rotulo="Aluno (membro ativo)" />
            <div>
              <label htmlFor="m-classe" className="block text-sm font-medium text-gray-700 mb-1">Classe</label>
              <select id="m-classe" value={classeMatricula} onChange={(e) => setClasseMatricula(e.target.value)} className="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">Selecione…</option>
                {classes.filter((c) => c.ativo).map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
              </select>
            </div>
            <Field id="m-obs" label="Observação (opcional)" value={obsMatricula} onChange={(e) => setObsMatricula(e.target.value)} />
            <p className="text-xs text-gray-500 flex items-start gap-1">
              <BookOpen size={13} className="shrink-0 mt-0.5" />
              Um aluno só pode ter uma matrícula ativa por vez — para mudar de classe, use Transferir.
            </p>
            <div className="flex gap-3">
              <button onClick={matricular} disabled={salvando || !buscaAluno.selecionada || !classeMatricula} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
                {salvando ? 'Matriculando…' : 'Matricular'}
              </button>
              <button onClick={() => setModalMatricula(false)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                Cancelar
              </button>
            </div>
          </div>
        </Modal>
      )}

      {/* Modal transferir */}
      {modalTransferir && (
        <Modal titulo={`Transferir ${modalTransferir.nome_completo}`} onClose={() => setModalTransferir(null)} largura="max-w-md">
          <div className="space-y-4">
            {erroForm && <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>}
            <p className="text-sm text-gray-600">De <strong>{modalTransferir.classe_nome}</strong> para:</p>
            <select value={classeDestino} onChange={(e) => setClasseDestino(e.target.value)} className="w-full border border-gray-300 rounded-lg px-3 py-2" aria-label="Classe de destino">
              <option value="">Selecione a classe de destino…</option>
              {classes.filter((c) => c.ativo && c.id !== modalTransferir.classe_id).map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
            </select>
            <div className="flex gap-3">
              <button onClick={transferir} disabled={salvando || !classeDestino} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
                {salvando ? 'Transferindo…' : 'Transferir'}
              </button>
              <button onClick={() => setModalTransferir(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                Cancelar
              </button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
};

export default EbdPage;
