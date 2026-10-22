import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Calendar, CalendarPlus, Globe, Loader2, Pencil, Plus, Trash2 } from 'lucide-react';
import Modal from '../../components/ui/Modal';
import ImagemUpload from '../../components/ui/ImagemUpload';
import Field from '../../components/ui/Field';
import {
  ApiError,
  Evento,
  EventoAtividade,
  EventoPayload,
  EventoStatus,
  EventoTipo,
  eventosApi,
  membrosApi,
  MembroListItem,
} from '../../lib/api';
import { useAuth } from '../../lib/auth-context';

const formatarData = (iso: string): string => {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('pt-BR');
};

const TIPO_LABEL: Record<EventoTipo, string> = {
  congresso: 'Congresso',
  festividade: 'Festividade',
  culto_especial: 'Culto Especial',
  data_comemorativa: 'Data Comemorativa',
  outro: 'Outro',
};

const STATUS_META: Record<EventoStatus, { label: string; classes: string }> = {
  planejado: { label: 'Planejado', classes: 'bg-gray-200 text-gray-700' },
  confirmado: { label: 'Confirmado', classes: 'bg-blue-100 text-blue-800' },
  realizado: { label: 'Realizado', classes: 'bg-green-100 text-green-800' },
  cancelado: { label: 'Cancelado', classes: 'bg-red-100 text-red-800' },
};

const EVENTO_VAZIO: EventoPayload = { nome: '', tipo: 'festividade', data_inicio: '', publico: false, status: 'planejado' };
const ATIV_VAZIA = { titulo: '', data: '', horario_inicio: '', horario_fim: '', descricao: '', responsavel_id: null as number | null };

/**
 * M4 — Eventos (festividades) + programação interna por atividade.
 */
const EventosPage: React.FC = () => {
  const { usuario } = useAuth();
  const podeEditar = ['admin', 'secretaria', 'dirigente'].includes(usuario?.perfil ?? '');

  const [itens, setItens] = useState<Evento[]>([]);
  const [total, setTotal] = useState(0);
  const [pagina, setPagina] = useState(1);
  const [porPagina] = useState(20);
  const [filtroTipo, setFiltroTipo] = useState('');
  const [filtroStatus, setFiltroStatus] = useState('');
  const [filtroDe, setFiltroDe] = useState('');
  const [filtroAte, setFiltroAte] = useState('');
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');

  const [modalForm, setModalForm] = useState<null | { id?: number }>(null);
  const [modalDetalhe, setModalDetalhe] = useState<null | Evento>(null);
  const [confirmExcluir, setConfirmExcluir] = useState<null | Evento>(null);
  const [evento, setEvento] = useState<EventoPayload>(EVENTO_VAZIO);
  const [atividades, setAtividades] = useState<EventoAtividade[]>([]);
  const [ativForm, setAtivForm] = useState(ATIV_VAZIA);
  const [salvando, setSalvando] = useState(false);
  const [erroForm, setErroForm] = useState('');

  const [buscaResp, setBuscaResp] = useState('');
  const [resultadosResp, setResultadosResp] = useState<MembroListItem[]>([]);
  const [respNome, setRespNome] = useState('');
  const debounce = useRef<ReturnType<typeof setTimeout>>();

  const carregar = useCallback(async () => {
    setCarregando(true);
    setErro('');
    try {
      const r = await eventosApi.listar({
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
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar eventos.');
    } finally {
      setCarregando(false);
    }
  }, [filtroDe, filtroAte, filtroTipo, filtroStatus, pagina, porPagina]);

  useEffect(() => {
    carregar();
  }, [carregar]);

  useEffect(() => {
    clearTimeout(debounce.current);
    const termo = buscaResp.trim();
    if (termo.length < 2) {
      setResultadosResp([]);
      return;
    }
    debounce.current = setTimeout(async () => {
      try {
        const r = await membrosApi.listar({ q: termo, status: 'ativo', por_pagina: 10 });
        setResultadosResp(r.itens);
      } catch {
        setResultadosResp([]);
      }
    }, 300);
    return () => clearTimeout(debounce.current);
  }, [buscaResp]);

  const totalPaginas = Math.max(1, Math.ceil(total / porPagina));

  const abrirNovo = () => {
    setEvento(EVENTO_VAZIO);
    setErroForm('');
    setModalForm({});
  };

  const abrirEditar = (e: Evento) => {
    setEvento({
      nome: e.nome,
      tipo: e.tipo,
      data_inicio: e.data_inicio,
      data_fim: e.data_fim,
      local: e.local,
      descricao: e.descricao,
      banner_url: e.banner_url,
      publico: !!e.publico,
      status: e.status,
    });
    setErroForm('');
    setModalForm({ id: e.id });
  };

  const abrirDetalhe = async (e: Evento) => {
    setModalDetalhe(e);
    setAtivForm({ ...ATIV_VAZIA });
    setRespNome('');
    setBuscaResp('');
    setResultadosResp([]);
    setErroForm('');
    try {
      const completo = await eventosApi.ver(e.id);
      setModalDetalhe(completo);
      setAtividades(completo.atividades ?? []);
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao carregar evento.');
      setModalDetalhe(null);
    }
  };

  const salvar = async (e: React.FormEvent) => {
    e.preventDefault();
    setErroForm('');
    setSalvando(true);
    try {
      if (modalForm?.id) {
        await eventosApi.atualizar(modalForm.id, evento);
      } else {
        await eventosApi.criar(evento);
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
      await eventosApi.remover(confirmExcluir.id);
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

  const salvarAtividade = async () => {
    if (!modalDetalhe || !ativForm.titulo.trim() || !ativForm.data) return;
    setSalvando(true);
    setErroForm('');
    try {
      await eventosApi.criarAtividade(modalDetalhe.id, {
        titulo: ativForm.titulo.trim(),
        data: ativForm.data,
        horario_inicio: ativForm.horario_inicio || null,
        horario_fim: ativForm.horario_fim || null,
        responsavel_id: ativForm.responsavel_id,
        descricao: ativForm.descricao || null,
      });
      setAtivForm({ ...ATIV_VAZIA });
      setRespNome('');
      const completo = await eventosApi.ver(modalDetalhe.id);
      setModalDetalhe(completo);
      setAtividades(completo.atividades ?? []);
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao adicionar atividade.');
    } finally {
      setSalvando(false);
    }
  };

  const removerAtividade = async (aid: number) => {
    if (!modalDetalhe) return;
    try {
      await eventosApi.removerAtividade(modalDetalhe.id, aid);
      const completo = await eventosApi.ver(modalDetalhe.id);
      setModalDetalhe(completo);
      setAtividades(completo.atividades ?? []);
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao remover atividade.');
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Eventos</h1>
          <p className="text-gray-500 text-sm">{total} evento(s) — os marcados como públicos aparecem no site</p>
        </div>
        {podeEditar && (
          <button
            onClick={abrirNovo}
            className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2"
          >
            <Plus size={16} />
            Novo evento
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
            <Calendar size={16} /> Nenhum evento cadastrado.
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-left text-gray-600">
              <tr>
                <th className="px-4 py-3 font-medium">Evento</th>
                <th className="px-4 py-3 font-medium">Tipo</th>
                <th className="px-4 py-3 font-medium">Período</th>
                <th className="px-4 py-3 font-medium">Local</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Público</th>
                {podeEditar && <th className="px-4 py-3 font-medium text-right">Ações</th>}
              </tr>
            </thead>
            <tbody className="divide-y">
              {itens.map((e) => (
                <tr key={e.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => abrirDetalhe(e)}>
                  <td className="px-4 py-3 font-medium text-gray-800">{e.nome}</td>
                  <td className="px-4 py-3 text-gray-600">{TIPO_LABEL[e.tipo]}</td>
                  <td className="px-4 py-3 text-gray-600">
                    {formatarData(e.data_inicio)}
                    {e.data_fim && e.data_fim !== e.data_inicio && ` – ${formatarData(e.data_fim)}`}
                  </td>
                  <td className="px-4 py-3 text-gray-600">{e.local ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_META[e.status].classes}`}>
                      {STATUS_META[e.status].label}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    {e.publico ? <Globe size={15} className="text-green-600" aria-label="Visível no site" /> : '—'}
                  </td>
                  {podeEditar && (
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        <button
                          onClick={(ev) => { ev.stopPropagation(); abrirEditar(e); }}
                          className="p-1.5 text-gray-400 hover:text-blue-600 transition-colors"
                          aria-label={`Editar ${e.nome}`}
                        >
                          <Pencil size={16} />
                        </button>
                        <button
                          onClick={(ev) => { ev.stopPropagation(); setConfirmExcluir(e); }}
                          className="p-1.5 text-gray-400 hover:text-red-600 transition-colors"
                          aria-label={`Excluir ${e.nome}`}
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
        <Modal titulo={modalForm.id ? 'Editar evento' : 'Novo evento'} onClose={() => setModalForm(null)} largura="max-w-2xl">
          <form onSubmit={salvar} className="space-y-4">
            {erroForm && (
              <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>
            )}
            <Field id="e-nome" label="Nome do evento" required value={evento.nome} onChange={(e) => setEvento((f) => ({ ...f, nome: e.target.value }))} />
            <div className="grid sm:grid-cols-3 gap-4">
              <div>
                <label htmlFor="e-tipo" className="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                <select id="e-tipo" value={evento.tipo} onChange={(e) => setEvento((f) => ({ ...f, tipo: e.target.value as EventoTipo }))} className="w-full border border-gray-300 rounded-lg px-3 py-2">
                  {Object.entries(TIPO_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </div>
              <Field id="e-ini" label="Início" type="date" required value={evento.data_inicio} onChange={(e) => setEvento((f) => ({ ...f, data_inicio: e.target.value }))} />
              <Field id="e-fim" label="Fim (opcional)" type="date" value={evento.data_fim ?? ''} onChange={(e) => setEvento((f) => ({ ...f, data_fim: e.target.value || null }))} />
            </div>
            <div className="grid sm:grid-cols-2 gap-4">
              <Field id="e-local" label="Local" value={evento.local ?? ''} onChange={(e) => setEvento((f) => ({ ...f, local: e.target.value }))} />
              <div>
                <label htmlFor="e-status" className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="e-status" value={evento.status} onChange={(e) => setEvento((f) => ({ ...f, status: e.target.value as EventoStatus }))} className="w-full border border-gray-300 rounded-lg px-3 py-2">
                  {Object.entries(STATUS_META).map(([v, m]) => <option key={v} value={v}>{m.label}</option>)}
                </select>
              </div>
            </div>
            {modalForm.id ? (
              <ImagemUpload
                rotulo="Banner do evento"
                formato="banner"
                url={evento.banner_url ?? null}
                enviar={async (arq) => {
                  const ev = await eventosApi.enviarBanner(modalForm.id!, arq);
                  setEvento((f) => ({ ...f, banner_url: ev.banner_url }));
                }}
                remover={async () => {
                  await eventosApi.removerBanner(modalForm.id!);
                  setEvento((f) => ({ ...f, banner_url: null }));
                }}
              />
            ) : (
              <p className="text-sm text-gray-500">Salve o evento para depois enviar o banner.</p>
            )}
            <Field id="e-desc" label="Descrição" value={evento.descricao ?? ''} onChange={(e) => setEvento((f) => ({ ...f, descricao: e.target.value }))} />
            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input
                type="checkbox"
                checked={!!evento.publico}
                onChange={(e) => setEvento((f) => ({ ...f, publico: e.target.checked }))}
                className="rounded border-gray-300 text-red-600 focus:ring-red-600"
              />
              Exibir no site público (enquanto não cancelado e dentro do período)
            </label>
            <div className="flex gap-3 pt-2">
              <button type="submit" disabled={salvando || !evento.nome.trim() || !evento.data_inicio} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
                {salvando ? 'Salvando…' : 'Salvar'}
              </button>
              <button type="button" onClick={() => setModalForm(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                Cancelar
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* Modal detalhe + atividades */}
      {modalDetalhe && (
        <Modal titulo={modalDetalhe.nome} onClose={() => setModalDetalhe(null)} largura="max-w-2xl">
          <div className="space-y-4">
            <div className="flex flex-wrap gap-3 text-sm text-gray-600">
              <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_META[modalDetalhe.status].classes}`}>{STATUS_META[modalDetalhe.status].label}</span>
              <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">{TIPO_LABEL[modalDetalhe.tipo]}</span>
              {!!modalDetalhe.publico && <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 inline-flex items-center gap-1"><Globe size={11} /> No site</span>}
              <span>{formatarData(modalDetalhe.data_inicio)}{modalDetalhe.data_fim && ` – ${formatarData(modalDetalhe.data_fim)}`}</span>
              {modalDetalhe.local && <span>{modalDetalhe.local}</span>}
            </div>
            {modalDetalhe.descricao && <p className="text-sm text-gray-700">{modalDetalhe.descricao}</p>}

            <h3 className="font-semibold text-gray-800 border-t pt-4">Programação interna</h3>
            {erroForm && (
              <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>
            )}
            {atividades.length === 0 ? (
              <p className="text-sm text-gray-500">Nenhuma atividade cadastrada.</p>
            ) : (
              <ul className="divide-y border rounded-lg">
                {atividades.map((a) => (
                  <li key={a.id} className="px-3 py-2 text-sm flex items-start justify-between gap-2">
                    <div>
                      <p className="font-medium text-gray-800">{a.titulo}</p>
                      <p className="text-gray-500">
                        {formatarData(a.data)}
                        {a.horario_inicio && ` · ${a.horario_inicio.slice(0, 5)}${a.horario_fim ? `–${a.horario_fim.slice(0, 5)}` : ''}`}
                        {a.responsavel_nome && ` · ${a.responsavel_nome}`}
                      </p>
                      {a.descricao && <p className="text-gray-500 text-xs mt-0.5">{a.descricao}</p>}
                    </div>
                    {podeEditar && (
                      <button onClick={() => removerAtividade(a.id)} className="p-1 text-gray-400 hover:text-red-600 shrink-0" aria-label={`Remover ${a.titulo}`}>
                        <Trash2 size={14} />
                      </button>
                    )}
                  </li>
                ))}
              </ul>
            )}

            {podeEditar && (
              <div className="border rounded-lg p-3 space-y-3 bg-gray-50">
                <p className="text-sm font-medium text-gray-700 flex items-center gap-1">
                  <CalendarPlus size={14} /> Adicionar atividade
                </p>
                <div className="grid sm:grid-cols-2 gap-3">
                  <Field id="a-titulo" label="Título" required value={ativForm.titulo} onChange={(e) => setAtivForm((f) => ({ ...f, titulo: e.target.value }))} />
                  <Field id="a-data" label="Data" type="date" required value={ativForm.data} onChange={(e) => setAtivForm((f) => ({ ...f, data: e.target.value }))} />
                  <Field id="a-ini" label="Início" type="time" value={ativForm.horario_inicio} onChange={(e) => setAtivForm((f) => ({ ...f, horario_inicio: e.target.value }))} />
                  <Field id="a-fim" label="Fim" type="time" value={ativForm.horario_fim} onChange={(e) => setAtivForm((f) => ({ ...f, horario_fim: e.target.value }))} />
                </div>
                <div className="relative">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Responsável (opcional)</label>
                  <input
                    type="search"
                    placeholder="Buscar membro…"
                    value={respNome || buscaResp}
                    onChange={(e) => { setBuscaResp(e.target.value); setRespNome(''); setAtivForm((f) => ({ ...f, responsavel_id: null })); }}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  />
                  {resultadosResp.length > 0 && (
                    <ul className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-40 overflow-auto">
                      {resultadosResp.map((m) => (
                        <li key={m.pessoa_id}>
                          <button
                            type="button"
                            onClick={() => { setAtivForm((f) => ({ ...f, responsavel_id: m.pessoa_id })); setRespNome(m.nome_completo); setBuscaResp(''); setResultadosResp([]); }}
                            className="w-full text-left px-3 py-2 text-sm hover:bg-red-50"
                          >
                            {m.nome_completo}
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
                <Field id="a-desc" label="Descrição (opcional)" value={ativForm.descricao} onChange={(e) => setAtivForm((f) => ({ ...f, descricao: e.target.value }))} />
                <button
                  onClick={salvarAtividade}
                  disabled={salvando || !ativForm.titulo.trim() || !ativForm.data}
                  className="w-full bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60 text-sm"
                >
                  {salvando ? 'Salvando…' : 'Adicionar à programação'}
                </button>
              </div>
            )}
          </div>
        </Modal>
      )}

      {/* Confirmar exclusão */}
      {confirmExcluir && (
        <Modal titulo="Excluir evento" onClose={() => setConfirmExcluir(null)} largura="max-w-md">
          <p className="text-gray-700 mb-4">
            Excluir o evento <strong>{confirmExcluir.nome}</strong>? Ele deixa de
            aparecer no painel e no site, e a programação interna é removida.
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

export default EventosPage;
