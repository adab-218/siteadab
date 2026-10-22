import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Loader2, Pencil, Plus, Search, Trash2, UserMinus, UserPlus, Users } from 'lucide-react';
import Modal from '../../components/ui/Modal';
import Field from '../../components/ui/Field';
import {
  ApiError,
  Departamento,
  departamentosApi,
  membrosApi,
  MembroListItem,
} from '../../lib/api';
import { useAuth } from '../../lib/auth-context';

/**
 * M2 — Departamentos: lista, CRUD (admin/secretaria) e gestão de
 * vínculos de membros (admin/secretaria + líder do próprio depto — D5).
 */
const DepartamentosPage: React.FC = () => {
  const { usuario } = useAuth();
  const podeEditarDepto = usuario?.perfil === 'admin' || usuario?.perfil === 'secretaria';

  const [itens, setItens] = useState<Departamento[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');
  const [mostrarInativos, setMostrarInativos] = useState(false);

  const [detalhe, setDetalhe] = useState<Departamento | null>(null);
  const [modalForm, setModalForm] = useState<null | { id?: number }>(null);
  const [formNome, setFormNome] = useState('');
  const [formDescricao, setFormDescricao] = useState('');
  const [confirmDesativar, setConfirmDesativar] = useState<Departamento | null>(null);
  const [salvando, setSalvando] = useState(false);
  const [erroForm, setErroForm] = useState('');

  // Busca de membro p/ vínculo ou líder
  const [buscaMembro, setBuscaMembro] = useState('');
  const [resultadosMembro, setResultadosMembro] = useState<MembroListItem[]>([]);
  const [membroSel, setMembroSel] = useState<MembroListItem | null>(null);
  const [liderSel, setLiderSel] = useState<MembroListItem | null>(null);
  const [funcao, setFuncao] = useState('');
  const debounce = useRef<ReturnType<typeof setTimeout>>();

  const carregar = useCallback(async () => {
    setCarregando(true);
    setErro('');
    try {
      setItens(await departamentosApi.listar(mostrarInativos ? undefined : true));
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar departamentos.');
    } finally {
      setCarregando(false);
    }
  }, [mostrarInativos]);

  useEffect(() => {
    carregar();
  }, [carregar]);

  // Busca de membros ativos (debounced)
  useEffect(() => {
    clearTimeout(debounce.current);
    const termo = buscaMembro.trim();
    if (termo.length < 2) {
      setResultadosMembro([]);
      return;
    }
    debounce.current = setTimeout(async () => {
      try {
        const r = await membrosApi.listar({ q: termo, status: 'ativo', por_pagina: 10 });
        setResultadosMembro(r.itens);
      } catch {
        setResultadosMembro([]);
      }
    }, 300);
    return () => clearTimeout(debounce.current);
  }, [buscaMembro]);

  const abrirDetalhe = async (id: number) => {
    try {
      setDetalhe(await departamentosApi.ver(id));
      setBuscaMembro('');
      setMembroSel(null);
      setFuncao('');
      setErroForm('');
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao abrir departamento.');
    }
  };

  const podeGerenciarDetalhe =
    podeEditarDepto ||
    (usuario?.perfil === 'lider_departamento' && detalhe?.lider_id === usuario.pessoa_id);

  const salvarDepto = async (e: React.FormEvent) => {
    e.preventDefault();
    setErroForm('');
    setSalvando(true);
    try {
      const dados = { nome: formNome.trim(), descricao: formDescricao || undefined, lider_id: liderSel?.pessoa_id };
      if (modalForm?.id) {
        await departamentosApi.atualizar(modalForm.id, dados);
      } else {
        await departamentosApi.criar(dados);
      }
      setModalForm(null);
      carregar();
    } catch (e2) {
      setErroForm(e2 instanceof ApiError ? e2.message : 'Erro ao salvar.');
    } finally {
      setSalvando(false);
    }
  };

  const desativar = async () => {
    if (!confirmDesativar) return;
    setSalvando(true);
    setErroForm('');
    try {
      await departamentosApi.remover(confirmDesativar.id);
      setConfirmDesativar(null);
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao desativar.');
      setConfirmDesativar(null);
    } finally {
      setSalvando(false);
    }
  };

  const adicionarMembro = async () => {
    if (!detalhe || !membroSel) return;
    setSalvando(true);
    setErroForm('');
    try {
      const d = await departamentosApi.adicionarMembro(detalhe.id, membroSel.pessoa_id, funcao || undefined);
      setDetalhe(d);
      setMembroSel(null);
      setBuscaMembro('');
      setFuncao('');
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao vincular membro.');
    } finally {
      setSalvando(false);
    }
  };

  const removerMembro = async (pessoaId: number) => {
    if (!detalhe) return;
    setSalvando(true);
    setErroForm('');
    try {
      await departamentosApi.removerMembro(detalhe.id, pessoaId);
      setDetalhe(await departamentosApi.ver(detalhe.id));
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao remover vínculo.');
    } finally {
      setSalvando(false);
    }
  };

  const abrirForm = async (d?: Departamento) => {
    setErroForm('');
    setLiderSel(null);
    setBuscaMembro('');
    if (d) {
      setFormNome(d.nome);
      setFormDescricao(d.descricao ?? '');
      if (d.lider_id && d.lider_nome) {
        setLiderSel({ pessoa_id: d.lider_id, nome_completo: d.lider_nome } as MembroListItem);
      }
      setModalForm({ id: d.id });
    } else {
      setFormNome('');
      setFormDescricao('');
      setModalForm({});
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Departamentos</h1>
          <p className="text-gray-500 text-sm">{itens.length} departamento(s)</p>
        </div>
        {podeEditarDepto && (
          <button
            onClick={() => abrirForm()}
            className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2"
          >
            <Plus size={16} />
            Novo departamento
          </button>
        )}
      </div>

      <label className="flex items-center gap-2 text-sm text-gray-600">
        <input
          type="checkbox"
          checked={mostrarInativos}
          onChange={(e) => setMostrarInativos(e.target.checked)}
          className="rounded border-gray-300 text-red-600 focus:ring-red-600"
        />
        Mostrar inativos
      </label>

      <div className="bg-white rounded-lg border overflow-hidden">
        {carregando ? (
          <div className="flex items-center justify-center py-16 text-gray-500" role="status">
            <Loader2 size={22} className="animate-spin mr-2" /> Carregando…
          </div>
        ) : erro ? (
          <p className="p-6 text-red-600" role="alert">{erro}</p>
        ) : erroForm && !confirmDesativar && !modalForm && !detalhe ? (
          <p className="p-6 text-red-600" role="alert">{erroForm}</p>
        ) : itens.length === 0 ? (
          <p className="p-6 text-gray-500">Nenhum departamento encontrado.</p>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-left text-gray-600">
              <tr>
                <th className="px-4 py-3 font-medium">Nome</th>
                <th className="px-4 py-3 font-medium">Líder</th>
                <th className="px-4 py-3 font-medium">Membros</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium text-right">Ações</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {itens.map((d) => (
                <tr key={d.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-800">{d.nome}</td>
                  <td className="px-4 py-3 text-gray-600">{d.lider_nome ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-600">{d.total_membros}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${d.ativo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'}`}>
                      {d.ativo ? 'Ativo' : 'Inativo'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end gap-1">
                      <button
                        onClick={() => abrirDetalhe(d.id)}
                        className="p-1.5 text-gray-400 hover:text-red-600 transition-colors"
                        aria-label={`Ver membros de ${d.nome}`}
                      >
                        <Users size={16} />
                      </button>
                      {podeEditarDepto && (
                        <>
                          <button
                            onClick={() => abrirForm(d)}
                            className="p-1.5 text-gray-400 hover:text-blue-600 transition-colors"
                            aria-label={`Editar ${d.nome}`}
                          >
                            <Pencil size={16} />
                          </button>
                          {!!d.ativo && (
                            <button
                              onClick={() => { setConfirmDesativar(d); setErroForm(''); }}
                              className="p-1.5 text-gray-400 hover:text-red-600 transition-colors"
                              aria-label={`Desativar ${d.nome}`}
                            >
                              <Trash2 size={16} />
                            </button>
                          )}
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Detalhe + membros */}
      {detalhe && (
        <Modal titulo={detalhe.nome} onClose={() => setDetalhe(null)} largura="max-w-2xl">
          {erroForm && (
            <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3 mb-4" role="alert">{erroForm}</div>
          )}
          <div className="space-y-4">
            <div className="text-sm text-gray-600">
              <p>{detalhe.descricao || 'Sem descrição.'}</p>
              <p className="mt-1">Líder: <strong>{detalhe.lider_nome ?? '—'}</strong></p>
            </div>

            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-2">
                Membros ({detalhe.membros?.length ?? 0})
              </h3>
              {detalhe.membros && detalhe.membros.length > 0 ? (
                <ul className="divide-y border rounded-lg">
                  {detalhe.membros.map((m) => (
                    <li key={m.pessoa_id} className="flex items-center justify-between px-3 py-2 text-sm">
                      <div>
                        <span className="font-medium text-gray-800">{m.nome_completo}</span>
                        {m.funcao && <span className="text-gray-500"> · {m.funcao}</span>}
                      </div>
                      {podeGerenciarDetalhe && (
                        <button
                          onClick={() => removerMembro(m.pessoa_id)}
                          disabled={salvando}
                          className="p-1 text-gray-400 hover:text-red-600 transition-colors"
                          aria-label={`Remover ${m.nome_completo} do departamento`}
                        >
                          <UserMinus size={16} />
                        </button>
                      )}
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-sm text-gray-500">Nenhum membro vinculado.</p>
              )}
            </div>

            {podeGerenciarDetalhe && (
              <div className="border-t pt-4 space-y-3">
                <h3 className="text-sm font-semibold text-gray-700">Vincular membro</h3>
                <div className="relative">
                  <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input
                    type="search"
                    placeholder="Buscar membro ativo por nome…"
                    value={buscaMembro}
                    onChange={(e) => { setBuscaMembro(e.target.value); setMembroSel(null); }}
                    aria-label="Buscar membro para vincular"
                    className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2"
                  />
                  {resultadosMembro.length > 0 && !membroSel && (
                    <ul className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-auto">
                      {resultadosMembro.map((m) => (
                        <li key={m.pessoa_id}>
                          <button
                            type="button"
                            onClick={() => { setMembroSel(m); setBuscaMembro(m.nome_completo); setResultadosMembro([]); }}
                            className="w-full text-left px-3 py-2 text-sm hover:bg-red-50"
                          >
                            {m.nome_completo}
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
                <div className="flex gap-2">
                  <input
                    type="text"
                    placeholder="Função (opcional)"
                    value={funcao}
                    onChange={(e) => setFuncao(e.target.value)}
                    aria-label="Função no departamento"
                    className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  />
                  <button
                    onClick={adicionarMembro}
                    disabled={!membroSel || salvando}
                    className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50 flex items-center gap-2 text-sm"
                  >
                    <UserPlus size={16} />
                    Vincular
                  </button>
                </div>
              </div>
            )}
          </div>
        </Modal>
      )}

      {/* Form criar/editar */}
      {modalForm && (
        <Modal titulo={modalForm.id ? 'Editar departamento' : 'Novo departamento'} onClose={() => setModalForm(null)}>
          <form onSubmit={salvarDepto} className="space-y-4">
            {erroForm && (
              <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>
            )}
            <Field id="d-nome" label="Nome" required value={formNome} onChange={(e) => setFormNome(e.target.value)} />
            <Field id="d-desc" label="Descrição" value={formDescricao} onChange={(e) => setFormDescricao(e.target.value)} />
            <div className="relative">
              <label htmlFor="d-lider" className="block text-sm font-medium text-gray-700 mb-1">Líder</label>
              <input
                id="d-lider"
                type="search"
                placeholder="Buscar membro ativo por nome…"
                value={buscaMembro}
                onChange={(e) => { setBuscaMembro(e.target.value); setLiderSel(null); }}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
              />
              {resultadosMembro.length > 0 && !liderSel && (
                <ul className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-auto">
                  {resultadosMembro.map((m) => (
                    <li key={m.pessoa_id}>
                      <button
                        type="button"
                        onClick={() => { setLiderSel(m); setBuscaMembro(m.nome_completo); setResultadosMembro([]); }}
                        className="w-full text-left px-3 py-2 text-sm hover:bg-red-50"
                      >
                        {m.nome_completo}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
            <div className="flex gap-3 pt-2">
              <button type="submit" disabled={salvando || !formNome.trim()} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
                {salvando ? 'Salvando…' : 'Salvar'}
              </button>
              <button type="button" onClick={() => setModalForm(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                Cancelar
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* Confirmar desativação */}
      {confirmDesativar && (
        <Modal titulo="Desativar departamento" onClose={() => setConfirmDesativar(null)} largura="max-w-md">
          {erroForm && (
            <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3 mb-4" role="alert">{erroForm}</div>
          )}
          <p className="text-gray-700 mb-4">
            Desativar <strong>{confirmDesativar.nome}</strong>? O departamento só
            pode ser desativado sem membros vinculados.
          </p>
          <div className="flex gap-3">
            <button onClick={desativar} disabled={salvando} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
              {salvando ? 'Desativando…' : 'Desativar'}
            </button>
            <button onClick={() => setConfirmDesativar(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
              Cancelar
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
};

export default DepartamentosPage;
