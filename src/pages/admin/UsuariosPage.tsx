import React, { useCallback, useEffect, useRef, useState } from 'react';
import { KeyRound, Loader2, Pencil, Plus, Search, UserX } from 'lucide-react';
import Modal from '../../components/ui/Modal';
import Field from '../../components/ui/Field';
import {
  ApiError,
  membrosApi,
  MembroListItem,
  Usuario,
  usuariosApi,
  UsuarioListItem,
} from '../../lib/api';
import { useAuth } from '../../lib/auth-context';

const PERFIL_LABEL: Record<Usuario['perfil'], string> = {
  admin: 'Administrador',
  secretaria: 'Secretaria',
  dirigente: 'Dirigente',
  ebd_admin: 'EBD · Admin',
  ebd_professor: 'EBD · Professor',
  lider_departamento: 'Líder de Departamento',
};

/**
 * T2.2 — Gestão de usuários (somente admin; a rota /usuarios é
 * protegida no servidor — este guarda é só cosmético).
 */
const UsuariosPage: React.FC = () => {
  const { usuario } = useAuth();
  const [itens, setItens] = useState<UsuarioListItem[]>([]);
  const [busca, setBusca] = useState('');
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');

  const [modalForm, setModalForm] = useState<null | { id?: number }>(null);
  const [modalSenha, setModalSenha] = useState<null | UsuarioListItem>(null);
  const [confirmDesativar, setConfirmDesativar] = useState<null | UsuarioListItem>(null);
  const [form, setForm] = useState({ email: '', senha: '', perfil: 'secretaria' as Usuario['perfil'], ativo: true });
  const [pessoaSel, setPessoaSel] = useState<MembroListItem | null>(null);
  const [buscaPessoa, setBuscaPessoa] = useState('');
  const [resultadosPessoa, setResultadosPessoa] = useState<MembroListItem[]>([]);
  const [salvando, setSalvando] = useState(false);
  const [erroForm, setErroForm] = useState('');
  const debounce = useRef<ReturnType<typeof setTimeout>>();

  const carregar = useCallback(async () => {
    setCarregando(true);
    setErro('');
    try {
      setItens(await usuariosApi.listar(busca || undefined));
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar usuários.');
    } finally {
      setCarregando(false);
    }
  }, [busca]);

  useEffect(() => {
    carregar();
  }, [carregar]);

  // Busca de pessoa (membros) p/ vincular usuário — debounced
  useEffect(() => {
    clearTimeout(debounce.current);
    const termo = buscaPessoa.trim();
    if (termo.length < 2) {
      setResultadosPessoa([]);
      return;
    }
    debounce.current = setTimeout(async () => {
      try {
        const r = await membrosApi.listar({ q: termo, por_pagina: 10 });
        setResultadosPessoa(r.itens);
      } catch {
        setResultadosPessoa([]);
      }
    }, 300);
    return () => clearTimeout(debounce.current);
  }, [buscaPessoa]);

  const abrirNovo = () => {
    setForm({ email: '', senha: '', perfil: 'secretaria', ativo: true });
    setPessoaSel(null);
    setBuscaPessoa('');
    setErroForm('');
    setModalForm({});
  };

  const abrirEditar = (u: UsuarioListItem) => {
    setForm({ email: u.email, senha: '', perfil: u.perfil, ativo: !!u.ativo });
    setErroForm('');
    setModalForm({ id: u.id });
  };

  const salvar = async (e: React.FormEvent) => {
    e.preventDefault();
    setErroForm('');
    setSalvando(true);
    try {
      if (modalForm?.id) {
        await usuariosApi.atualizar(modalForm.id, {
          email: form.email,
          perfil: form.perfil,
          ativo: form.ativo,
        });
      } else {
        if (!pessoaSel) {
          setErroForm('Selecione a pessoa vinculada ao usuário.');
          setSalvando(false);
          return;
        }
        await usuariosApi.criar({
          pessoa_id: pessoaSel.pessoa_id,
          email: form.email,
          senha: form.senha,
          perfil: form.perfil,
        });
      }
      setModalForm(null);
      carregar();
    } catch (e2) {
      setErroForm(e2 instanceof ApiError ? e2.message : 'Erro ao salvar.');
    } finally {
      setSalvando(false);
    }
  };

  const salvarSenha = async () => {
    if (!modalSenha) return;
    setSalvando(true);
    setErroForm('');
    try {
      await usuariosApi.senha(modalSenha.id, form.senha);
      setModalSenha(null);
      setForm((f) => ({ ...f, senha: '' }));
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao trocar senha.');
    } finally {
      setSalvando(false);
    }
  };

  const desativar = async () => {
    if (!confirmDesativar) return;
    setSalvando(true);
    try {
      await usuariosApi.remover(confirmDesativar.id);
      setConfirmDesativar(null);
      carregar();
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao desativar.');
      setConfirmDesativar(null);
    } finally {
      setSalvando(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Usuários</h1>
          <p className="text-gray-500 text-sm">{itens.length} usuário(s) com acesso ao painel</p>
        </div>
        <button
          onClick={abrirNovo}
          className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2"
        >
          <Plus size={16} />
          Novo usuário
        </button>
      </div>

      <div className="bg-white rounded-lg border p-4">
        <div className="relative max-w-md">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            type="search"
            placeholder="Buscar por nome ou e-mail…"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            aria-label="Buscar usuários"
            className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2"
          />
        </div>
      </div>

      <div className="bg-white rounded-lg border overflow-hidden">
        {carregando ? (
          <div className="flex items-center justify-center py-16 text-gray-500" role="status">
            <Loader2 size={22} className="animate-spin mr-2" /> Carregando…
          </div>
        ) : erro ? (
          <p className="p-6 text-red-600" role="alert">{erro}</p>
        ) : itens.length === 0 ? (
          <p className="p-6 text-gray-500">Nenhum usuário encontrado.</p>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-left text-gray-600">
              <tr>
                <th className="px-4 py-3 font-medium">Nome</th>
                <th className="px-4 py-3 font-medium">E-mail</th>
                <th className="px-4 py-3 font-medium">Perfil</th>
                <th className="px-4 py-3 font-medium">Último acesso</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium text-right">Ações</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {itens.map((u) => (
                <tr key={u.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-800">{u.nome_completo}</td>
                  <td className="px-4 py-3 text-gray-600">{u.email}</td>
                  <td className="px-4 py-3">
                    <span className="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">
                      {PERFIL_LABEL[u.perfil]}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-600">
                    {u.ultimo_login_em ? new Date(u.ultimo_login_em).toLocaleString('pt-BR') : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${u.ativo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'}`}>
                      {u.ativo ? 'Ativo' : 'Inativo'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end gap-1">
                      <button
                        onClick={() => abrirEditar(u)}
                        className="p-1.5 text-gray-400 hover:text-blue-600 transition-colors"
                        aria-label={`Editar ${u.nome_completo}`}
                      >
                        <Pencil size={16} />
                      </button>
                      <button
                        onClick={() => { setModalSenha(u); setForm((f) => ({ ...f, senha: '' })); setErroForm(''); }}
                        className="p-1.5 text-gray-400 hover:text-yellow-600 transition-colors"
                        aria-label={`Redefinir senha de ${u.nome_completo}`}
                      >
                        <KeyRound size={16} />
                      </button>
                      {!!u.ativo && u.id !== usuario?.id && (
                        <button
                          onClick={() => setConfirmDesativar(u)}
                          className="p-1.5 text-gray-400 hover:text-red-600 transition-colors"
                          aria-label={`Desativar ${u.nome_completo}`}
                        >
                          <UserX size={16} />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Modal criar/editar */}
      {modalForm && (
        <Modal titulo={modalForm.id ? 'Editar usuário' : 'Novo usuário'} onClose={() => setModalForm(null)}>
          <form onSubmit={salvar} className="space-y-4">
            {erroForm && (
              <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>
            )}
            {!modalForm.id && (
              <div className="relative">
                <label htmlFor="u-pessoa" className="block text-sm font-medium text-gray-700 mb-1">
                  Pessoa vinculada <span className="text-red-600" aria-hidden="true">*</span>
                </label>
                <input
                  id="u-pessoa"
                  type="search"
                  placeholder="Buscar membro por nome…"
                  value={buscaPessoa}
                  onChange={(e) => { setBuscaPessoa(e.target.value); setPessoaSel(null); }}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2"
                />
                {resultadosPessoa.length > 0 && !pessoaSel && (
                  <ul className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-auto">
                    {resultadosPessoa.map((m) => (
                      <li key={m.pessoa_id}>
                        <button
                          type="button"
                          onClick={() => { setPessoaSel(m); setBuscaPessoa(m.nome_completo); setResultadosPessoa([]); }}
                          className="w-full text-left px-3 py-2 text-sm hover:bg-red-50"
                        >
                          {m.nome_completo}
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            )}
            <Field id="u-email" label="E-mail" type="email" required value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} />
            {!modalForm.id && (
              <Field id="u-senha" label="Senha inicial (mín. 10)" type="password" required minLength={10} value={form.senha} onChange={(e) => setForm((f) => ({ ...f, senha: e.target.value }))} />
            )}
            <div>
              <label htmlFor="u-perfil" className="block text-sm font-medium text-gray-700 mb-1">Perfil</label>
              <select
                id="u-perfil"
                value={form.perfil}
                onChange={(e) => setForm((f) => ({ ...f, perfil: e.target.value as Usuario['perfil'] }))}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
                {Object.entries(PERFIL_LABEL).map(([v, l]) => (
                  <option key={v} value={v}>{l}</option>
                ))}
              </select>
            </div>
            {modalForm.id && (
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  checked={form.ativo}
                  onChange={(e) => setForm((f) => ({ ...f, ativo: e.target.checked }))}
                  className="rounded border-gray-300 text-red-600 focus:ring-red-600"
                />
                Usuário ativo
              </label>
            )}
            <div className="flex gap-3 pt-2">
              <button type="submit" disabled={salvando} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
                {salvando ? 'Salvando…' : 'Salvar'}
              </button>
              <button type="button" onClick={() => setModalForm(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                Cancelar
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* Modal redefinir senha */}
      {modalSenha && (
        <Modal titulo={`Nova senha — ${modalSenha.nome_completo}`} onClose={() => setModalSenha(null)} largura="max-w-md">
          {erroForm && (
            <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3 mb-4" role="alert">{erroForm}</div>
          )}
          <form
            className="space-y-4"
            onSubmit={(e) => { e.preventDefault(); void salvarSenha(); }}
          >
            <Field
              id="u-novasenha"
              label="Nova senha (mín. 10 caracteres)"
              type="password"
              minLength={10}
              required
              value={form.senha}
              onChange={(e) => setForm((f) => ({ ...f, senha: e.target.value }))}
            />
            <div className="flex gap-3">
              <button type="submit" disabled={salvando || form.senha.length < 10} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
                {salvando ? 'Salvando…' : 'Redefinir'}
              </button>
              <button type="button" onClick={() => setModalSenha(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                Cancelar
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* Confirmar desativação */}
      {confirmDesativar && (
        <Modal titulo="Desativar usuário" onClose={() => setConfirmDesativar(null)} largura="max-w-md">
          <p className="text-gray-700 mb-4">
            Desativar <strong>{confirmDesativar.nome_completo}</strong> ({confirmDesativar.email})?
            O usuário perde o acesso ao painel imediatamente.
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

export default UsuariosPage;
