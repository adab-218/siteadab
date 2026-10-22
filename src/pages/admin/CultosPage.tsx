import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Church, Loader2, Pencil, Plus, Settings2, Trash2, Users } from 'lucide-react';
import Modal from '../../components/ui/Modal';
import Field from '../../components/ui/Field';
import {
  ApiError,
  Culto,
  CultoPayload,
  CultoPresenca,
  cultosApi,
  membrosApi,
  MembroListItem,
  TipoCulto,
  tiposCultoApi,
} from '../../lib/api';
import { useAuth } from '../../lib/auth-context';

const formatarData = (iso: string): string => {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('pt-BR');
};

const formatarHora = (h: string | null): string => (h ? h.slice(0, 5) : '—');

const DIAS_SEMANA = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

const CULTO_VAZIO: CultoPayload = { tipo_culto_id: 0, data: '' };

/**
 * M3 — Cultos: agenda + totais de presença + gestão de tipos.
 */
const CultosPage: React.FC = () => {
  const { usuario } = useAuth();
  const podeEditar = ['admin', 'secretaria', 'dirigente'].includes(usuario?.perfil ?? '');
  const podeGerenciarTipos = usuario?.perfil === 'admin' || usuario?.perfil === 'secretaria';

  const [itens, setItens] = useState<Culto[]>([]);
  const [total, setTotal] = useState(0);
  const [pagina, setPagina] = useState(1);
  const [porPagina] = useState(20);
  const [filtroDe, setFiltroDe] = useState('');
  const [filtroAte, setFiltroAte] = useState('');
  const [filtroTipo, setFiltroTipo] = useState('');
  const [tipos, setTipos] = useState<TipoCulto[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');

  const [modalForm, setModalForm] = useState<null | { id?: number }>(null);
  const [modalTipos, setModalTipos] = useState(false);
  const [tiposTodos, setTiposTodos] = useState<TipoCulto[]>([]);
  const [confirmExcluir, setConfirmExcluir] = useState<null | Culto>(null);
  const [culto, setCulto] = useState<CultoPayload>(CULTO_VAZIO);
  const [tipoForm, setTipoForm] = useState({ nome: '', dia_semana_sugerido: '', horario_padrao: '' });
  const [salvando, setSalvando] = useState(false);
  const [erroForm, setErroForm] = useState('');

  // Chamada nominal (presenças por nome)
  const [modalPresenca, setModalPresenca] = useState<null | Culto>(null);
  const [presencas, setPresencas] = useState<CultoPresenca[]>([]);
  const [buscaPresenca, setBuscaPresenca] = useState('');
  const [resultadosPresenca, setResultadosPresenca] = useState<MembroListItem[]>([]);
  const debouncePresenca = useRef<ReturnType<typeof setTimeout>>();

  // Busca de membro p/ dirigente/pregador
  const [campoPessoa, setCampoPessoa] = useState<'dirigente' | 'pregador' | null>(null);
  const [buscaPessoa, setBuscaPessoa] = useState('');
  const [resultadosPessoa, setResultadosPessoa] = useState<MembroListItem[]>([]);
  const [nomes, setNomes] = useState<{ dirigente: string; pregador: string }>({ dirigente: '', pregador: '' });
  const debounce = useRef<ReturnType<typeof setTimeout>>();

  const carregar = useCallback(async () => {
    setCarregando(true);
    setErro('');
    try {
      const r = await cultosApi.listar({
        de: filtroDe || undefined,
        ate: filtroAte || undefined,
        tipo_culto_id: filtroTipo ? Number(filtroTipo) : undefined,
        pagina,
        por_pagina: porPagina,
      });
      setItens(r.itens);
      setTotal(r.total);
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar cultos.');
    } finally {
      setCarregando(false);
    }
  }, [filtroDe, filtroAte, filtroTipo, pagina, porPagina]);

  useEffect(() => {
    carregar();
  }, [carregar]);

  useEffect(() => {
    tiposCultoApi.listar(true).then(setTipos).catch(() => setTipos([]));
  }, []);

  useEffect(() => {
    clearTimeout(debounce.current);
    const termo = buscaPessoa.trim();
    if (termo.length < 2 || !campoPessoa) {
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
  }, [buscaPessoa, campoPessoa]);

  // Busca de membro p/ presença nominal
  useEffect(() => {
    clearTimeout(debouncePresenca.current);
    const termo = buscaPresenca.trim();
    if (termo.length < 2) {
      setResultadosPresenca([]);
      return;
    }
    debouncePresenca.current = setTimeout(async () => {
      try {
        const r = await membrosApi.listar({ q: termo, status: 'ativo', por_pagina: 10 });
        setResultadosPresenca(r.itens);
      } catch {
        setResultadosPresenca([]);
      }
    }, 300);
    return () => clearTimeout(debouncePresenca.current);
  }, [buscaPresenca]);

  const totalPaginas = Math.max(1, Math.ceil(total / porPagina));

  const abrirPresenca = async (c: Culto) => {
    setModalPresenca(c);
    setBuscaPresenca('');
    setErroForm('');
    try {
      setPresencas(await cultosApi.presencas(c.id));
    } catch {
      setPresencas([]);
    }
  };

  const adicionarPresenca = async (m: MembroListItem) => {
    if (!modalPresenca) return;
    setErroForm('');
    try {
      setPresencas(await cultosApi.adicionarPresenca(modalPresenca.id, m.pessoa_id));
      setBuscaPresenca('');
      setResultadosPresenca([]);
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao marcar presença.');
    }
  };

  const removerPresenca = async (pessoaId: number) => {
    if (!modalPresenca) return;
    try {
      setPresencas(await cultosApi.removerPresenca(modalPresenca.id, pessoaId));
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao remover presença.');
    }
  };

  const abrirNovo = () => {
    setCulto(CULTO_VAZIO);
    setNomes({ dirigente: '', pregador: '' });
    setCampoPessoa(null);
    setBuscaPessoa('');
    setErroForm('');
    setModalForm({});
  };

  const abrirEditar = (c: Culto) => {
    setCulto({
      tipo_culto_id: c.tipo_culto_id,
      data: c.data,
      horario_inicio: c.horario_inicio?.slice(0, 5) ?? null,
      dirigente_id: c.dirigente_id,
      pregador_id: c.pregador_id,
      tema: c.tema,
      total_presentes: c.total_presentes,
      total_visitantes: c.total_visitantes,
      total_decisoes: c.total_decisoes,
      observacoes: c.observacoes,
    });
    setNomes({ dirigente: c.dirigente_nome ?? '', pregador: c.pregador_nome ?? '' });
    setCampoPessoa(null);
    setBuscaPessoa('');
    setErroForm('');
    setModalForm({ id: c.id });
  };

  // Selecionar tipo → sugere horário padrão se vazio
  const aoEscolherTipo = (id: number) => {
    const tipo = tipos.find((t) => t.id === id);
    setCulto((c) => ({
      ...c,
      tipo_culto_id: id,
      horario_inicio: c.horario_inicio || tipo?.horario_padrao?.slice(0, 5) || null,
    }));
  };

  const salvar = async (e: React.FormEvent) => {
    e.preventDefault();
    setErroForm('');
    setSalvando(true);
    try {
      if (modalForm?.id) {
        await cultosApi.atualizar(modalForm.id, culto);
      } else {
        await cultosApi.criar(culto);
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
      await cultosApi.remover(confirmExcluir.id);
      setConfirmExcluir(null);
      carregar();
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao excluir.');
      setConfirmExcluir(null);
    } finally {
      setSalvando(false);
    }
  };

  const salvarTipo = async () => {
    if (!tipoForm.nome.trim()) return;
    setSalvando(true);
    setErroForm('');
    try {
      await tiposCultoApi.criar({
        nome: tipoForm.nome.trim(),
        dia_semana_sugerido: tipoForm.dia_semana_sugerido !== '' ? Number(tipoForm.dia_semana_sugerido) : null,
        horario_padrao: tipoForm.horario_padrao || null,
      });
      setTipoForm({ nome: '', dia_semana_sugerido: '', horario_padrao: '' });
      setTipos(await tiposCultoApi.listar(true));
      setTiposTodos(await tiposCultoApi.listar());
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao criar tipo.');
    } finally {
      setSalvando(false);
    }
  };

  const alternarTipo = async (t: TipoCulto) => {
    try {
      if (t.ativo) {
        await tiposCultoApi.desativar(t.id);
      } else {
        await tiposCultoApi.atualizar(t.id, { ativo: true });
      }
      setTipos(await tiposCultoApi.listar(true));
      setTiposTodos(await tiposCultoApi.listar());
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao alterar tipo.');
    }
  };

  const PessoaPicker = ({ campo, label }: { campo: 'dirigente' | 'pregador'; label: string }) => (
    <div className="relative">
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      <input
        type="search"
        placeholder="Buscar membro ativo…"
        value={campoPessoa === campo ? buscaPessoa : nomes[campo]}
        onFocus={() => { setCampoPessoa(campo); setBuscaPessoa(''); }}
        onChange={(e) => setBuscaPessoa(e.target.value)}
        className="w-full border border-gray-300 rounded-lg px-3 py-2"
      />
      {campoPessoa === campo && resultadosPessoa.length > 0 && (
        <ul className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-40 overflow-auto">
          {resultadosPessoa.map((m) => (
            <li key={m.pessoa_id}>
              <button
                type="button"
                onClick={() => {
                  setCulto((c) => ({ ...c, [`${campo}_id`]: m.pessoa_id }));
                  setNomes((n) => ({ ...n, [campo]: m.nome_completo }));
                  setCampoPessoa(null);
                  setResultadosPessoa([]);
                }}
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

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Cultos</h1>
          <p className="text-gray-500 text-sm">{total} registro(s)</p>
        </div>
        <div className="flex gap-2">
          {podeGerenciarTipos && (
            <button
              onClick={async () => {
                setModalTipos(true);
                setErroForm('');
                try {
                  setTiposTodos(await tiposCultoApi.listar());
                } catch {
                  setTiposTodos([]);
                }
              }}
              className="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 text-sm"
            >
              <Settings2 size={16} />
              Tipos de culto
            </button>
          )}
          {podeEditar && (
            <button
              onClick={abrirNovo}
              className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2"
            >
              <Plus size={16} />
              Novo culto
            </button>
          )}
        </div>
      </div>

      {/* Filtros */}
      <div className="bg-white rounded-lg border p-4 flex flex-wrap gap-3 items-center">
        <Field id="f-de" label="De" type="date" value={filtroDe} onChange={(e) => { setFiltroDe(e.target.value); setPagina(1); }} />
        <Field id="f-ate" label="Até" type="date" value={filtroAte} onChange={(e) => { setFiltroAte(e.target.value); setPagina(1); }} />
        <div>
          <label htmlFor="f-tipo" className="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
          <select
            id="f-tipo"
            value={filtroTipo}
            onChange={(e) => { setFiltroTipo(e.target.value); setPagina(1); }}
            className="border border-gray-300 rounded-lg px-3 py-2"
          >
            <option value="">Todos</option>
            {tipos.map((t) => (
              <option key={t.id} value={t.id}>{t.nome}</option>
            ))}
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
            <Church size={16} /> Nenhum culto registrado no período.
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-left text-gray-600">
              <tr>
                <th className="px-4 py-3 font-medium">Data</th>
                <th className="px-4 py-3 font-medium">Tipo</th>
                <th className="px-4 py-3 font-medium">Horário</th>
                <th className="px-4 py-3 font-medium">Tema / Pregador</th>
                <th className="px-4 py-3 font-medium">Presentes</th>
                {podeEditar && <th className="px-4 py-3 font-medium text-right">Ações</th>}
              </tr>
            </thead>
            <tbody className="divide-y">
              {itens.map((c) => (
                <tr key={c.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-800">{formatarData(c.data)}</td>
                  <td className="px-4 py-3 text-gray-600">{c.tipo_nome}</td>
                  <td className="px-4 py-3 text-gray-600">{formatarHora(c.horario_inicio)}</td>
                  <td className="px-4 py-3 text-gray-600">
                    {c.tema && <span className="font-medium">{c.tema}</span>}
                    {c.tema && c.pregador_nome && ' — '}
                    {c.pregador_nome ?? '—'}
                  </td>
                  <td className="px-4 py-3 text-gray-600">
                    {c.total_presentes ?? '—'}
                    {(c.total_nomeados ?? 0) > 0 && (
                      <span className="ml-1 text-xs text-gray-400">({c.total_nomeados} nominais)</span>
                    )}
                  </td>
                  {podeEditar && (
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        <button
                          onClick={() => abrirPresenca(c)}
                          className="p-1.5 text-gray-400 hover:text-green-600 transition-colors"
                          aria-label={`Chamada nominal do culto de ${formatarData(c.data)}`}
                          title="Chamada nominal"
                        >
                          <Users size={16} />
                        </button>
                        <button
                          onClick={() => abrirEditar(c)}
                          className="p-1.5 text-gray-400 hover:text-blue-600 transition-colors"
                          aria-label={`Editar culto de ${formatarData(c.data)}`}
                        >
                          <Pencil size={16} />
                        </button>
                        <button
                          onClick={() => setConfirmExcluir(c)}
                          className="p-1.5 text-gray-400 hover:text-red-600 transition-colors"
                          aria-label={`Excluir culto de ${formatarData(c.data)}`}
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

      {/* Modal criar/editar culto */}
      {modalForm && (
        <Modal titulo={modalForm.id ? 'Editar culto' : 'Novo culto'} onClose={() => setModalForm(null)} largura="max-w-2xl">
          <form onSubmit={salvar} className="space-y-4">
            {erroForm && (
              <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>
            )}
            <div className="grid sm:grid-cols-3 gap-4">
              <div className="sm:col-span-2">
                <label htmlFor="c-tipo" className="block text-sm font-medium text-gray-700 mb-1">
                  Tipo de culto <span className="text-red-600" aria-hidden="true">*</span>
                </label>
                <select
                  id="c-tipo"
                  required
                  value={culto.tipo_culto_id || ''}
                  onChange={(e) => aoEscolherTipo(Number(e.target.value))}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2"
                >
                  <option value="">Selecione…</option>
                  {tipos.map((t) => (
                    <option key={t.id} value={t.id}>{t.nome}</option>
                  ))}
                </select>
              </div>
              <Field id="c-data" label="Data" type="date" required value={culto.data} onChange={(e) => setCulto((c) => ({ ...c, data: e.target.value }))} />
              <Field id="c-hora" label="Horário" type="time" value={culto.horario_inicio ?? ''} onChange={(e) => setCulto((c) => ({ ...c, horario_inicio: e.target.value }))} />
              <div className="sm:col-span-2">
                <Field id="c-tema" label="Tema" value={culto.tema ?? ''} onChange={(e) => setCulto((c) => ({ ...c, tema: e.target.value }))} />
              </div>
            </div>
            <div className="grid sm:grid-cols-2 gap-4">
              <PessoaPicker campo="dirigente" label="Dirigente" />
              <PessoaPicker campo="pregador" label="Pregador" />
            </div>
            <fieldset className="grid grid-cols-3 gap-4 border-t pt-4">
              <legend className="text-sm font-semibold text-gray-700 mb-2">Totais (preencher após o culto)</legend>
              <Field id="c-pres" label="Presentes" type="number" min={0} value={culto.total_presentes ?? ''} onChange={(e) => setCulto((c) => ({ ...c, total_presentes: e.target.value === '' ? null : Number(e.target.value) }))} />
              <Field id="c-vis" label="Visitantes" type="number" min={0} value={culto.total_visitantes ?? ''} onChange={(e) => setCulto((c) => ({ ...c, total_visitantes: e.target.value === '' ? null : Number(e.target.value) }))} />
              <Field id="c-dec" label="Decisões" type="number" min={0} value={culto.total_decisoes ?? ''} onChange={(e) => setCulto((c) => ({ ...c, total_decisoes: e.target.value === '' ? null : Number(e.target.value) }))} />
            </fieldset>
            <Field id="c-obs" label="Observações" value={culto.observacoes ?? ''} onChange={(e) => setCulto((c) => ({ ...c, observacoes: e.target.value }))} />
            <div className="flex gap-3 pt-2">
              <button type="submit" disabled={salvando || !culto.tipo_culto_id || !culto.data} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
                {salvando ? 'Salvando…' : 'Salvar'}
              </button>
              <button type="button" onClick={() => setModalForm(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                Cancelar
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* Modal tipos */}
      {modalTipos && (
        <Modal titulo="Tipos de culto" onClose={() => setModalTipos(false)} largura="max-w-xl">
          {erroForm && (
            <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3 mb-4" role="alert">{erroForm}</div>
          )}
          <ul className="divide-y border rounded-lg mb-4">
            {tiposTodos.map((t) => (
              <li key={t.id} className="flex items-center justify-between px-3 py-2 text-sm">
                <div>
                  <span className="font-medium text-gray-800">{t.nome}</span>
                  <span className="text-gray-500">
                    {t.dia_semana_sugerido !== null && ` · ${DIAS_SEMANA[t.dia_semana_sugerido]}`}
                    {t.horario_padrao && ` · ${t.horario_padrao.slice(0, 5)}`}
                  </span>
                </div>
                <button
                  onClick={() => alternarTipo(t)}
                  className={`text-xs px-2 py-1 rounded-full font-medium ${t.ativo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'}`}
                >
                  {t.ativo ? 'Ativo' : 'Inativo'}
                </button>
              </li>
            ))}
          </ul>
          <div className="flex gap-2 items-end">
            <div className="flex-1">
              <Field id="t-nome" label="Novo tipo" value={tipoForm.nome} onChange={(e) => setTipoForm((f) => ({ ...f, nome: e.target.value }))} />
            </div>
            <div>
              <label htmlFor="t-dia" className="block text-sm font-medium text-gray-700 mb-1">Dia</label>
              <select id="t-dia" value={tipoForm.dia_semana_sugerido} onChange={(e) => setTipoForm((f) => ({ ...f, dia_semana_sugerido: e.target.value }))} className="border border-gray-300 rounded-lg px-2 py-2 text-sm">
                <option value="">—</option>
                {DIAS_SEMANA.map((d, i) => <option key={i} value={i}>{d}</option>)}
              </select>
            </div>
            <Field id="t-hora" label="Hora" type="time" value={tipoForm.horario_padrao} onChange={(e) => setTipoForm((f) => ({ ...f, horario_padrao: e.target.value }))} />
            <button onClick={salvarTipo} disabled={salvando || !tipoForm.nome.trim()} className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50 text-sm">
              <Plus size={16} />
            </button>
          </div>
        </Modal>
      )}

      {/* Modal chamada nominal */}
      {modalPresenca && (
        <Modal
          titulo={`Chamada nominal — ${modalPresenca.tipo_nome} · ${formatarData(modalPresenca.data)}`}
          onClose={() => setModalPresenca(null)}
          largura="max-w-lg"
        >
          {erroForm && (
            <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3 mb-4" role="alert">{erroForm}</div>
          )}
          <p className="text-xs text-gray-500 mb-3">
            {presencas.length} membro(s) marcado(s)
            {modalPresenca.total_presentes !== null && ` · total de presentes no culto: ${modalPresenca.total_presentes}`}
          </p>
          {presencas.length === 0 ? (
            <p className="text-sm text-gray-500 mb-3">Nenhuma presença nominal registrada.</p>
          ) : (
            <ul className="divide-y border rounded-lg mb-4 max-h-64 overflow-auto">
              {presencas.map((p) => (
                <li key={p.pessoa_id} className="px-3 py-2 text-sm flex items-center justify-between">
                  <span className="text-gray-800">{p.nome_completo}</span>
                  {podeEditar && (
                    <button
                      onClick={() => removerPresenca(p.pessoa_id)}
                      className="p-1 text-gray-400 hover:text-red-600"
                      aria-label={`Remover ${p.nome_completo}`}
                    >
                      <Trash2 size={14} />
                    </button>
                  )}
                </li>
              ))}
            </ul>
          )}
          {podeEditar && (
            <div className="relative">
              <input
                type="search"
                placeholder="Buscar membro ativo para marcar presença…"
                value={buscaPresenca}
                onChange={(e) => setBuscaPresenca(e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
                aria-label="Buscar membro para marcar presença"
              />
              {resultadosPresenca.length > 0 && (
                <ul className="absolute z-10 bottom-full mb-1 w-full bg-white border rounded-lg shadow-lg max-h-40 overflow-auto">
                  {resultadosPresenca.map((m) => (
                    <li key={m.pessoa_id}>
                      <button
                        type="button"
                        onClick={() => adicionarPresenca(m)}
                        disabled={presencas.some((p) => p.pessoa_id === m.pessoa_id)}
                        className="w-full text-left px-3 py-2 text-sm hover:bg-red-50 disabled:opacity-40"
                      >
                        {m.nome_completo}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </Modal>
      )}

      {/* Confirmar exclusão */}
      {confirmExcluir && (
        <Modal titulo="Excluir culto" onClose={() => setConfirmExcluir(null)} largura="max-w-md">
          <p className="text-gray-700 mb-4">
            Excluir o culto <strong>{confirmExcluir.tipo_nome}</strong> de{' '}
            <strong>{formatarData(confirmExcluir.data)}</strong>? O registro e
            as presenças nominais serão removidos.
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

export default CultosPage;
