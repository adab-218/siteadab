import React, { useCallback, useEffect, useRef, useState } from 'react';
import { HandHeart, HeartHandshake, Loader2, Pencil, Plus, Trash2, UserPlus } from 'lucide-react';
import Modal from '../../components/ui/Modal';
import Field from '../../components/ui/Field';
import {
  Acao,
  AcaoBeneficiario,
  AcaoPapel,
  AcaoPayload,
  AcaoStatus,
  AcaoTipo,
  AcaoVoluntario,
  acoesApi,
  ApiError,
  departamentosApi,
  Departamento,
  membrosApi,
  MembroListItem,
} from '../../lib/api';
import { useAuth } from '../../lib/auth-context';

const formatarData = (iso: string | null): string => {
  if (!iso) return '—';
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('pt-BR');
};

const TIPO_LABEL: Record<AcaoTipo, string> = {
  social: 'Ação Social',
  evangelistica: 'Evangelística',
  outra: 'Outra',
};

const STATUS_META: Record<AcaoStatus, { label: string; classes: string }> = {
  planejada: { label: 'Planejada', classes: 'bg-gray-200 text-gray-700' },
  em_andamento: { label: 'Em andamento', classes: 'bg-blue-100 text-blue-800' },
  realizada: { label: 'Realizada', classes: 'bg-green-100 text-green-800' },
  cancelada: { label: 'Cancelada', classes: 'bg-red-100 text-red-800' },
};

const ACAO_VAZIA: AcaoPayload = { nome: '', tipo: 'social', status: 'planejada' };
const BENEF_VAZIO = { nome: '', contato: '', observacao: '' };

/**
 * M5 — Ações sociais/evangelísticas + voluntários + beneficiários.
 */
const AcoesPage: React.FC = () => {
  const { usuario } = useAuth();
  const podeEditar = ['admin', 'secretaria', 'dirigente'].includes(usuario?.perfil ?? '');

  const [itens, setItens] = useState<Acao[]>([]);
  const [total, setTotal] = useState(0);
  const [pagina, setPagina] = useState(1);
  const [porPagina] = useState(20);
  const [filtroTipo, setFiltroTipo] = useState('');
  const [filtroStatus, setFiltroStatus] = useState('');
  const [filtroDe, setFiltroDe] = useState('');
  const [filtroAte, setFiltroAte] = useState('');
  const [deptos, setDeptos] = useState<Departamento[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');

  const [modalForm, setModalForm] = useState<null | { id?: number }>(null);
  const [modalDetalhe, setModalDetalhe] = useState<null | Acao>(null);
  const [confirmExcluir, setConfirmExcluir] = useState<null | Acao>(null);
  const [acao, setAcao] = useState<AcaoPayload>(ACAO_VAZIA);
  const [voluntarios, setVoluntarios] = useState<AcaoVoluntario[]>([]);
  const [beneficiarios, setBeneficiarios] = useState<AcaoBeneficiario[]>([]);
  const [benefForm, setBenefForm] = useState(BENEF_VAZIO);
  const [papelSel, setPapelSel] = useState<AcaoPapel>('voluntario');
  const [salvando, setSalvando] = useState(false);
  const [erroForm, setErroForm] = useState('');

  const [buscaPessoa, setBuscaPessoa] = useState('');
  const [resultadosPessoa, setResultadosPessoa] = useState<MembroListItem[]>([]);
  const [pessoaSel, setPessoaSel] = useState<MembroListItem | null>(null);
  const debounce = useRef<ReturnType<typeof setTimeout>>();

  const carregar = useCallback(async () => {
    setCarregando(true);
    setErro('');
    try {
      const r = await acoesApi.listar({
        de: filtroDe || undefined,
        ate: filtroAte || undefined,
        tipo: filtroTipo || undefined,
        status: filtroStatus || undefined,
        pagina,
        por_pagina: porPagina,
      });
      setItens(r.itens);
      setTotal(r.total);
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar ações.');
    } finally {
      setCarregando(false);
    }
  }, [filtroDe, filtroAte, filtroTipo, filtroStatus, pagina, porPagina]);

  useEffect(() => {
    carregar();
  }, [carregar]);

  useEffect(() => {
    departamentosApi.listar().then(setDeptos).catch(() => setDeptos([]));
  }, []);

  useEffect(() => {
    clearTimeout(debounce.current);
    const termo = buscaPessoa.trim();
    if (termo.length < 2) {
      setResultadosPessoa([]);
      return;
    }
    debounce.current = setTimeout(async () => {
      try {
        const r = await membrosApi.listar({ q: termo, status: 'ativo', por_pagina: 10 });
        setResultadosPessoa(r.itens);
      } catch {
        setResultadosPessoa([]);
      }
    }, 300);
    return () => clearTimeout(debounce.current);
  }, [buscaPessoa]);

  const totalPaginas = Math.max(1, Math.ceil(total / porPagina));

  const abrirNovo = () => {
    setAcao(ACAO_VAZIA);
    setErroForm('');
    setModalForm({});
  };

  const abrirEditar = (a: Acao) => {
    setAcao({
      nome: a.nome,
      tipo: a.tipo,
      data_inicio: a.data_inicio,
      data_fim: a.data_fim,
      local: a.local,
      objetivo: a.objetivo,
      resultado: a.resultado,
      departamento_id: a.departamento_id,
      status: a.status,
    });
    setErroForm('');
    setModalForm({ id: a.id });
  };

  const abrirDetalhe = async (a: Acao) => {
    setModalDetalhe(a);
    setErroForm('');
    setPessoaSel(null);
    setBuscaPessoa('');
    setBenefForm(BENEF_VAZIO);
    try {
      const completa = await acoesApi.ver(a.id);
      setModalDetalhe(completa);
      setVoluntarios(completa.voluntarios ?? []);
      setBeneficiarios(completa.beneficiarios ?? []);
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar ação.');
      setModalDetalhe(null);
    }
  };

  const salvar = async (e: React.FormEvent) => {
    e.preventDefault();
    setErroForm('');
    setSalvando(true);
    try {
      if (modalForm?.id) {
        await acoesApi.atualizar(modalForm.id, acao);
      } else {
        await acoesApi.criar(acao);
      }
      setModalForm(null);
      carregar();
    } catch (e2) {
      setErroForm(e2 instanceof ApiError ? e2.message : 'Erro ao salvar.');
    } finally {
      setSalvando(false);
    }
  };

  const excluir = async () => {
    if (!confirmExcluir) return;
    setSalvando(true);
    try {
      await acoesApi.remover(confirmExcluir.id);
      setConfirmExcluir(null);
      if (modalDetalhe?.id === confirmExcluir.id) {
        setModalDetalhe(null);
      }
      carregar();
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao excluir.');
      setConfirmExcluir(null);
    } finally {
      setSalvando(false);
    }
  };

  const adicionarVoluntario = async () => {
    if (!modalDetalhe || !pessoaSel) return;
    setSalvando(true);
    setErroForm('');
    try {
      setVoluntarios(await acoesApi.vincularVoluntario(modalDetalhe.id, pessoaSel.pessoa_id, papelSel));
      setPessoaSel(null);
      setBuscaPessoa('');
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao adicionar voluntário.');
    } finally {
      setSalvando(false);
    }
  };

  const removerVoluntario = async (pessoa_id: number) => {
    if (!modalDetalhe) return;
    try {
      setVoluntarios(await acoesApi.desvincularVoluntario(modalDetalhe.id, pessoa_id));
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao remover voluntário.');
    }
  };

  const adicionarBeneficiario = async () => {
    if (!modalDetalhe || !benefForm.nome.trim()) return;
    setSalvando(true);
    setErroForm('');
    try {
      setBeneficiarios(await acoesApi.criarBeneficiario(modalDetalhe.id, {
        nome: benefForm.nome.trim(),
        contato: benefForm.contato || undefined,
        observacao: benefForm.observacao || undefined,
      }));
      setBenefForm(BENEF_VAZIO);
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao adicionar beneficiário.');
    } finally {
      setSalvando(false);
    }
  };

  const removerBeneficiario = async (bid: number) => {
    if (!modalDetalhe) return;
    try {
      setBeneficiarios(await acoesApi.removerBeneficiario(modalDetalhe.id, bid));
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao remover beneficiário.');
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Ações</h1>
          <p className="text-gray-500 text-sm">{total} ação(ões) social(is)/evangelística(s)</p>
        </div>
        {podeEditar && (
          <button
            onClick={abrirNovo}
            className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2"
          >
            <Plus size={16} />
            Nova ação
          </button>
        )}
      </div>

      {/* Filtros */}
      <div className="bg-white rounded-lg border p-4 flex flex-wrap gap-3 items-center">
        <Field id="f-de" label="De" type="date" value={filtroDe} onChange={(e) => { setFiltroDe(e.target.value); setPagina(1); }} />
        <Field id="f-ate" label="Até" type="date" value={filtroAte} onChange={(e) => { setFiltroAte(e.target.value); setPagina(1); }} />
        <div>
          <label htmlFor="f-tipo" className="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
          <select id="f-tipo" value={filtroTipo} onChange={(e) => { setFiltroTipo(e.target.value); setPagina(1); }} className="border border-gray-300 rounded-lg px-3 py-2">
            <option value="">Todos</option>
            {Object.entries(TIPO_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </div>
        <div>
          <label htmlFor="f-status" className="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select id="f-status" value={filtroStatus} onChange={(e) => { setFiltroStatus(e.target.value); setPagina(1); }} className="border border-gray-300 rounded-lg px-3 py-2">
            <option value="">Todos</option>
            {Object.entries(STATUS_META).map(([v, m]) => <option key={v} value={v}>{m.label}</option>)}
          </select>
        </div>
      </div>

      {/* Tabela */}
      <div className="bg-white rounded-lg border overflow-hidden">
        {carregando ? (
          <div className="flex items-center justify-center py-16 text-gray-500" role="status">
            <Loader2 size={22} className="animate-spin mr-2" /> Carregando…
          </div>
        ) : erro ? (
          <p className="p-6 text-red-600" role="alert">{erro}</p>
        ) : itens.length === 0 ? (
          <div className="p-6 text-gray-500 flex items-center gap-2">
            <HandHeart size={16} /> Nenhuma ação cadastrada.
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-left text-gray-600">
              <tr>
                <th className="px-4 py-3 font-medium">Ação</th>
                <th className="px-4 py-3 font-medium">Tipo</th>
                <th className="px-4 py-3 font-medium">Período</th>
                <th className="px-4 py-3 font-medium">Departamento</th>
                <th className="px-4 py-3 font-medium">Participantes</th>
                <th className="px-4 py-3 font-medium">Status</th>
                {podeEditar && <th className="px-4 py-3 font-medium text-right">Ações</th>}
              </tr>
            </thead>
            <tbody className="divide-y">
              {itens.map((a) => (
                <tr key={a.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => abrirDetalhe(a)}>
                  <td className="px-4 py-3 font-medium text-gray-800">{a.nome}</td>
                  <td className="px-4 py-3 text-gray-600">{TIPO_LABEL[a.tipo]}</td>
                  <td className="px-4 py-3 text-gray-600">
                    {formatarData(a.data_inicio)}
                    {a.data_fim && ` – ${formatarData(a.data_fim)}`}
                  </td>
                  <td className="px-4 py-3 text-gray-600">{a.departamento_nome ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-600">
                    {a.total_voluntarios ?? 0} vol · {a.total_beneficiarios ?? 0} ben
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_META[a.status].classes}`}>
                      {STATUS_META[a.status].label}
                    </span>
                  </td>
                  {podeEditar && (
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        <button
                          onClick={(ev) => { ev.stopPropagation(); abrirEditar(a); }}
                          className="p-1.5 text-gray-400 hover:text-blue-600 transition-colors"
                          aria-label={`Editar ${a.nome}`}
                        >
                          <Pencil size={16} />
                        </button>
                        <button
                          onClick={(ev) => { ev.stopPropagation(); setConfirmExcluir(a); }}
                          className="p-1.5 text-gray-400 hover:text-red-600 transition-colors"
                          aria-label={`Excluir ${a.nome}`}
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {totalPaginas > 1 && (
        <div className="flex items-center justify-center gap-3 text-sm">
          <button disabled={pagina <= 1} onClick={() => setPagina((p) => p - 1)} className="px-3 py-1 border rounded-lg disabled:opacity-40">Anterior</button>
          <span className="text-gray-600">Página {pagina} de {totalPaginas}</span>
          <button disabled={pagina >= totalPaginas} onClick={() => setPagina((p) => p + 1)} className="px-3 py-1 border rounded-lg disabled:opacity-40">Próxima</button>
        </div>
      )}

      {/* Modal criar/editar */}
      {modalForm && (
        <Modal titulo={modalForm.id ? 'Editar ação' : 'Nova ação'} onClose={() => setModalForm(null)} largura="max-w-2xl">
          <form onSubmit={salvar} className="space-y-4">
            {erroForm && (
              <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>
            )}
            <Field id="a-nome" label="Nome da ação" required value={acao.nome} onChange={(e) => setAcao((f) => ({ ...f, nome: e.target.value }))} />
            <div className="grid sm:grid-cols-3 gap-4">
              <div>
                <label htmlFor="a-tipo" className="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                <select id="a-tipo" value={acao.tipo} onChange={(e) => setAcao((f) => ({ ...f, tipo: e.target.value as AcaoTipo }))} className="w-full border border-gray-300 rounded-lg px-3 py-2">
                  {Object.entries(TIPO_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </div>
              <Field id="a-ini" label="Início" type="date" value={acao.data_inicio ?? ''} onChange={(e) => setAcao((f) => ({ ...f, data_inicio: e.target.value || null }))} />
              <Field id="a-fim" label="Fim" type="date" value={acao.data_fim ?? ''} onChange={(e) => setAcao((f) => ({ ...f, data_fim: e.target.value || null }))} />
            </div>
            <div className="grid sm:grid-cols-2 gap-4">
              <Field id="a-local" label="Local" value={acao.local ?? ''} onChange={(e) => setAcao((f) => ({ ...f, local: e.target.value }))} />
              <div>
                <label htmlFor="a-depto" className="block text-sm font-medium text-gray-700 mb-1">Departamento responsável</label>
                <select
                  id="a-depto"
                  value={acao.departamento_id ?? ''}
                  onChange={(e) => setAcao((f) => ({ ...f, departamento_id: e.target.value ? Number(e.target.value) : null }))}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2"
                >
                  <option value="">Nenhum</option>
                  {deptos.map((d) => <option key={d.id} value={d.id}>{d.nome}</option>)}
                </select>
              </div>
            </div>
            <Field id="a-obj" label="Objetivo" value={acao.objetivo ?? ''} onChange={(e) => setAcao((f) => ({ ...f, objetivo: e.target.value }))} />
            <Field id="a-res" label="Resultado / relatório" value={acao.resultado ?? ''} onChange={(e) => setAcao((f) => ({ ...f, resultado: e.target.value }))} />
            <div>
              <label htmlFor="a-status" className="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select id="a-status" value={acao.status} onChange={(e) => setAcao((f) => ({ ...f, status: e.target.value as AcaoStatus }))} className="w-full border border-gray-300 rounded-lg px-3 py-2">
                {Object.entries(STATUS_META).map(([v, m]) => <option key={v} value={v}>{m.label}</option>)}
              </select>
            </div>
            <div className="flex gap-3 pt-2">
              <button type="submit" disabled={salvando || !acao.nome.trim()} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
                {salvando ? 'Salvando…' : 'Salvar'}
              </button>
              <button type="button" onClick={() => setModalForm(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                Cancelar
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* Modal detalhe */}
      {modalDetalhe && (
        <Modal titulo={modalDetalhe.nome} onClose={() => setModalDetalhe(null)} largura="max-w-2xl">
          <div className="space-y-4">
            <div className="flex flex-wrap gap-3 text-sm text-gray-600">
              <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_META[modalDetalhe.status].classes}`}>{STATUS_META[modalDetalhe.status].label}</span>
              <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">{TIPO_LABEL[modalDetalhe.tipo]}</span>
              <span>{formatarData(modalDetalhe.data_inicio)}{modalDetalhe.data_fim && ` – ${formatarData(modalDetalhe.data_fim)}`}</span>
              {modalDetalhe.departamento_nome && <span>{modalDetalhe.departamento_nome}</span>}
            </div>
            {modalDetalhe.objetivo && <p className="text-sm text-gray-700"><strong>Objetivo:</strong> {modalDetalhe.objetivo}</p>}
            {modalDetalhe.resultado && <p className="text-sm text-gray-700"><strong>Resultado:</strong> {modalDetalhe.resultado}</p>}
            {erroForm && (
              <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>
            )}

            {/* Voluntários */}
            <h3 className="font-semibold text-gray-800 border-t pt-4 flex items-center gap-2">
              <UserPlus size={16} className="text-red-600" /> Voluntários ({voluntarios.length})
            </h3>
            {voluntarios.length === 0 ? (
              <p className="text-sm text-gray-500">Nenhum voluntário.</p>
            ) : (
              <ul className="divide-y border rounded-lg">
                {voluntarios.map((v) => (
                  <li key={v.pessoa_id} className="px-3 py-2 text-sm flex items-center justify-between">
                    <span className="text-gray-800">
                      {v.nome_completo}
                      {v.papel === 'coordenador' && (
                        <span className="ml-2 px-1.5 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800 font-medium">coordenador</span>
                      )}
                    </span>
                    {podeEditar && (
                      <button onClick={() => removerVoluntario(v.pessoa_id)} className="p-1 text-gray-400 hover:text-red-600" aria-label={`Remover ${v.nome_completo}`}>
                        <Trash2 size={14} />
                      </button>
                    )}
                  </li>
                ))}
              </ul>
            )}
            {podeEditar && (
              <div className="flex gap-2 items-end">
                <div className="flex-1 relative">
                  <input
                    type="search"
                    placeholder="Buscar pessoa…"
                    value={buscaPessoa}
                    onChange={(e) => { setBuscaPessoa(e.target.value); setPessoaSel(null); }}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  />
                  {resultadosPessoa.length > 0 && !pessoaSel && (
                    <ul className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-40 overflow-auto">
                      {resultadosPessoa.map((m) => (
                        <li key={m.pessoa_id}>
                          <button type="button" onClick={() => { setPessoaSel(m); setBuscaPessoa(m.nome_completo); setResultadosPessoa([]); }} className="w-full text-left px-3 py-2 text-sm hover:bg-red-50">
                            {m.nome_completo}
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
                <select value={papelSel} onChange={(e) => setPapelSel(e.target.value as AcaoPapel)} className="border border-gray-300 rounded-lg px-2 py-2 text-sm" aria-label="Papel">
                  <option value="voluntario">Voluntário</option>
                  <option value="coordenador">Coordenador</option>
                </select>
                <button onClick={adicionarVoluntario} disabled={salvando || !pessoaSel} className="bg-red-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">
                  <Plus size={14} />
                </button>
              </div>
            )}

            {/* Beneficiários */}
            <h3 className="font-semibold text-gray-800 border-t pt-4 flex items-center gap-2">
              <HeartHandshake size={16} className="text-red-600" /> Beneficiários ({beneficiarios.length})
            </h3>
            {beneficiarios.length === 0 ? (
              <p className="text-sm text-gray-500">Nenhum beneficiário registrado.</p>
            ) : (
              <ul className="divide-y border rounded-lg">
                {beneficiarios.map((b) => (
                  <li key={b.id} className="px-3 py-2 text-sm flex items-start justify-between gap-2">
                    <div>
                      <p className="text-gray-800">{b.nome}</p>
                      {b.contato && <p className="text-gray-500 text-xs">{b.contato}</p>}
                      {b.observacao && <p className="text-gray-500 text-xs">{b.observacao}</p>}
                    </div>
                    {podeEditar && (
                      <button onClick={() => removerBeneficiario(b.id)} className="p-1 text-gray-400 hover:text-red-600 shrink-0" aria-label={`Remover ${b.nome}`}>
                        <Trash2 size={14} />
                      </button>
                    )}
                  </li>
                ))}
              </ul>
            )}
            {podeEditar && (
              <div className="grid grid-cols-[1fr_auto] gap-2">
                <input
                  placeholder="Nome do beneficiário"
                  value={benefForm.nome}
                  onChange={(e) => setBenefForm((f) => ({ ...f, nome: e.target.value }))}
                  className="border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  aria-label="Nome do beneficiário"
                />
                <input
                  placeholder="Contato"
                  value={benefForm.contato}
                  onChange={(e) => setBenefForm((f) => ({ ...f, contato: e.target.value }))}
                  className="border border-gray-300 rounded-lg px-3 py-2 text-sm w-40"
                  aria-label="Contato"
                />
                <input
                  placeholder="Observação"
                  value={benefForm.observacao}
                  onChange={(e) => setBenefForm((f) => ({ ...f, observacao: e.target.value }))}
                  className="border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  aria-label="Observação"
                />
                <button onClick={adicionarBeneficiario} disabled={salvando || !benefForm.nome.trim()} className="bg-red-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">
                  <Plus size={14} />
                </button>
              </div>
            )}
          </div>
        </Modal>
      )}

      {/* Confirmar exclusão */}
      {confirmExcluir && (
        <Modal titulo="Excluir ação" onClose={() => setConfirmExcluir(null)} largura="max-w-md">
          <p className="text-gray-700 mb-4">
            Excluir <strong>{confirmExcluir.nome}</strong>? Voluntários e
            beneficiários vinculados são removidos junto.
          </p>
          <div className="flex gap-3">
            <button onClick={excluir} disabled={salvando} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
              {salvando ? 'Excluindo…' : 'Excluir'}
            </button>
            <button onClick={() => setConfirmExcluir(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
              Cancelar
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
};

export default AcoesPage;
