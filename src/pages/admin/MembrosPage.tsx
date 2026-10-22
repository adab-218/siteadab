import React, { useCallback, useEffect, useState } from 'react';
import { Loader2, Pencil, Plus, Search, Trash2, UserCog } from 'lucide-react';
import Modal from '../../components/ui/Modal';
import Field from '../../components/ui/Field';
import ImagemUpload from '../../components/ui/ImagemUpload';
import {
  ApiError,
  Cargo,
  cargosApi,
  MembroListItem,
  MembroPayload,
  membrosApi,
  PessoaPayload,
  StatusMembro,
} from '../../lib/api';
import { useAuth } from '../../lib/auth-context';

const STATUS_LABEL: Record<StatusMembro, string> = {
  ativo: 'Ativo',
  afastado: 'Afastado',
  transferido: 'Transferido',
  falecido: 'Falecido',
  disciplinado: 'Disciplinado',
};

const STATUS_COR: Record<StatusMembro, string> = {
  ativo: 'bg-green-100 text-green-800',
  afastado: 'bg-yellow-100 text-yellow-800',
  transferido: 'bg-blue-100 text-blue-800',
  falecido: 'bg-gray-200 text-gray-700',
  disciplinado: 'bg-red-100 text-red-800',
};

const PESSOA_VAZIA: PessoaPayload = { nome_completo: '' };
const MEMBRO_VAZIO: MembroPayload = {};

const MembrosPage: React.FC = () => {
  const { usuario } = useAuth();
  const podeEditar = usuario?.perfil === 'admin' || usuario?.perfil === 'secretaria';
  const podeExcluir = usuario?.perfil === 'admin';

  const [itens, setItens] = useState<MembroListItem[]>([]);
  const [total, setTotal] = useState(0);
  const [pagina, setPagina] = useState(1);
  const [porPagina] = useState(20);
  const [busca, setBusca] = useState('');
  const [statusFiltro, setStatusFiltro] = useState('');
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');

  const [modalForm, setModalForm] = useState<null | { id?: number }>(null);
  const [modalStatus, setModalStatus] = useState<null | MembroListItem>(null);
  const [confirmExcluir, setConfirmExcluir] = useState<null | MembroListItem>(null);
  const [pessoa, setPessoa] = useState<PessoaPayload>(PESSOA_VAZIA);
  const [membro, setMembro] = useState<MembroPayload>(MEMBRO_VAZIO);
  const [novoStatus, setNovoStatus] = useState<StatusMembro>('ativo');
  const [salvando, setSalvando] = useState(false);
  const [erroForm, setErroForm] = useState('');
  const [cargos, setCargos] = useState<Cargo[]>([]);
  const [detalheDeptos, setDetalheDeptos] = useState<{ nome: string; funcao: string | null }[]>([]);
  const [fotoAtual, setFotoAtual] = useState<string | null>(null);

  useEffect(() => {
    cargosApi.listar().then(setCargos).catch(() => setCargos([]));
  }, []);

  const carregar = useCallback(async () => {
    setCarregando(true);
    setErro('');
    try {
      const r = await membrosApi.listar({
        q: busca || undefined,
        status: statusFiltro || undefined,
        pagina,
        por_pagina: porPagina,
      });
      setItens(r.itens);
      setTotal(r.total);
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar membros.');
    } finally {
      setCarregando(false);
    }
  }, [busca, statusFiltro, pagina, porPagina]);

  useEffect(() => {
    carregar();
  }, [carregar]);

  const totalPaginas = Math.max(1, Math.ceil(total / porPagina));

  const abrirNovo = () => {
    setPessoa(PESSOA_VAZIA);
    setMembro(MEMBRO_VAZIO);
    setDetalheDeptos([]);
    setFotoAtual(null);
    setErroForm('');
    setModalForm({});
  };

  const abrirEditar = async (id: number) => {
    setErroForm('');
    try {
      const m = await membrosApi.ver(id);
      setPessoa({
        nome_completo: m.nome_completo,
        nome_social: m.nome_social,
        data_nascimento: m.data_nascimento,
        sexo: m.sexo,
        estado_civil: m.estado_civil,
        cpf: m.cpf,
        telefone: m.telefone,
        email: m.email,
        cep: m.cep,
        logradouro: m.logradouro,
        numero: m.numero,
        complemento: m.complemento,
        bairro: m.bairro,
        cidade: m.cidade,
        uf: m.uf,
        observacoes: m.observacoes,
      });
      setMembro({
        numero_carteirinha: m.numero_carteirinha,
        data_conversao: m.data_conversao,
        data_batismo: m.data_batismo,
        batizado_espirito_santo: !!m.batizado_espirito_santo,
        congregacao: m.congregacao,
        procedencia: m.procedencia,
        cargo_id: m.cargo_id,
      });
      setDetalheDeptos(
        (m.departamentos ?? []).map((d) => ({ nome: d.nome, funcao: d.funcao }))
      );
      setFotoAtual(m.foto_url ?? null);
      setModalForm({ id });
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao abrir membro.');
    }
  };

  const salvar = async (e: React.FormEvent) => {
    e.preventDefault();
    setErroForm('');
    setSalvando(true);
    try {
      if (modalForm?.id) {
        await membrosApi.atualizar(modalForm.id, pessoa, membro);
      } else {
        await membrosApi.criar(pessoa, membro);
      }
      setModalForm(null);
      carregar();
    } catch (e2) {
      setErroForm(e2 instanceof ApiError ? e2.message : 'Erro ao salvar.');
    } finally {
      setSalvando(false);
    }
  };

  const salvarStatus = async () => {
    if (!modalStatus) return;
    setSalvando(true);
    setErroForm('');
    try {
      await membrosApi.mudarStatus(modalStatus.pessoa_id, novoStatus);
      setModalStatus(null);
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao mudar status.');
    } finally {
      setSalvando(false);
    }
  };

  const excluir = async () => {
    if (!confirmExcluir) return;
    setSalvando(true);
    try {
      await membrosApi.remover(confirmExcluir.pessoa_id);
      setConfirmExcluir(null);
      carregar();
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao excluir.');
    } finally {
      setSalvando(false);
    }
  };

  const setP = (campo: keyof PessoaPayload) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setPessoa((p) => ({ ...p, [campo]: e.target.value }));
  const setM = (campo: keyof MembroPayload) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setMembro((m) => ({ ...m, [campo]: e.target.value }));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Membros</h1>
          <p className="text-gray-500 text-sm">{total} registro(s)</p>
        </div>
        {podeEditar && (
          <button
            onClick={abrirNovo}
            className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2"
          >
            <Plus size={16} />
            Novo membro
          </button>
        )}
      </div>

      {/* Filtros */}
      <div className="bg-white rounded-lg border p-4 flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-[200px]">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            type="search"
            placeholder="Buscar por nome ou e-mail…"
            value={busca}
            onChange={(e) => { setBusca(e.target.value); setPagina(1); }}
            aria-label="Buscar membros"
            className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2"
          />
        </div>
        <select
          value={statusFiltro}
          onChange={(e) => { setStatusFiltro(e.target.value); setPagina(1); }}
          aria-label="Filtrar por status"
          className="border border-gray-300 rounded-lg px-3 py-2"
        >
          <option value="">Todos os status</option>
          {Object.entries(STATUS_LABEL).map(([v, l]) => (
            <option key={v} value={v}>{l}</option>
          ))}
        </select>
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
          <p className="p-6 text-gray-500">Nenhum membro encontrado.</p>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-left text-gray-600">
              <tr>
                <th className="px-4 py-3 font-medium">Nome</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Cargo</th>
                <th className="px-4 py-3 font-medium">Congregação</th>
                <th className="px-4 py-3 font-medium">Contato</th>
                {podeEditar && <th className="px-4 py-3 font-medium text-right">Ações</th>}
              </tr>
            </thead>
            <tbody className="divide-y">
              {itens.map((m) => (
                <tr key={m.pessoa_id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-800">{m.nome_completo}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COR[m.status]}`}>
                      {STATUS_LABEL[m.status]}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-600">{m.cargo_nome ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-600">{m.congregacao ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-600">{m.telefone ?? m.email ?? '—'}</td>
                  {podeEditar && (
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        <button
                          onClick={() => abrirEditar(m.pessoa_id)}
                          className="p-1.5 text-gray-400 hover:text-blue-600 transition-colors"
                          aria-label={`Editar ${m.nome_completo}`}
                        >
                          <Pencil size={16} />
                        </button>
                        <button
                          onClick={() => { setModalStatus(m); setNovoStatus(m.status); setErroForm(''); }}
                          className="p-1.5 text-gray-400 hover:text-yellow-600 transition-colors"
                          aria-label={`Mudar status de ${m.nome_completo}`}
                        >
                          <UserCog size={16} />
                        </button>
                        {podeExcluir && (
                          <button
                            onClick={() => setConfirmExcluir(m)}
                            className="p-1.5 text-gray-400 hover:text-red-600 transition-colors"
                            aria-label={`Excluir ${m.nome_completo}`}
                          >
                            <Trash2 size={16} />
                          </button>
                        )}
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Paginação */}
      {totalPaginas > 1 && (
        <div className="flex items-center justify-center gap-3 text-sm">
          <button
            disabled={pagina <= 1}
            onClick={() => setPagina((p) => p - 1)}
            className="px-3 py-1 border rounded-lg disabled:opacity-40"
          >
            Anterior
          </button>
          <span className="text-gray-600">Página {pagina} de {totalPaginas}</span>
          <button
            disabled={pagina >= totalPaginas}
            onClick={() => setPagina((p) => p + 1)}
            className="px-3 py-1 border rounded-lg disabled:opacity-40"
          >
            Próxima
          </button>
        </div>
      )}

      {/* Modal criar/editar */}
      {modalForm && (
        <Modal titulo={modalForm.id ? 'Editar membro' : 'Novo membro'} onClose={() => setModalForm(null)} largura="max-w-2xl">
          <form onSubmit={salvar} className="space-y-4">
            {erroForm && (
              <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3" role="alert">{erroForm}</div>
            )}

            <fieldset className="grid sm:grid-cols-2 gap-4">
              <legend className="text-sm font-semibold text-gray-700 mb-2">Dados pessoais</legend>
              <div className="sm:col-span-2">
                <Field id="p-nome" label="Nome completo" required value={pessoa.nome_completo} onChange={setP('nome_completo')} />
              </div>
              <Field id="p-nome-social" label="Nome social" value={pessoa.nome_social ?? ''} onChange={setP('nome_social')} />
              <Field id="p-nasc" label="Nascimento" type="date" value={pessoa.data_nascimento ?? ''} onChange={setP('data_nascimento')} />
              <div>
                <label htmlFor="p-sexo" className="block text-sm font-medium text-gray-700 mb-1">Sexo</label>
                <select id="p-sexo" value={pessoa.sexo ?? ''} onChange={(e) => setPessoa((p) => ({ ...p, sexo: (e.target.value || null) as 'M' | 'F' | null }))} className="w-full border border-gray-300 rounded-lg px-3 py-2">
                  <option value="">—</option>
                  <option value="M">Masculino</option>
                  <option value="F">Feminino</option>
                </select>
              </div>
              <Field id="p-cpf" label="CPF" value={pessoa.cpf ?? ''} onChange={setP('cpf')} placeholder="000.000.000-00" />
              <Field id="p-tel" label="Telefone" value={pessoa.telefone ?? ''} onChange={setP('telefone')} />
              <Field id="p-email" label="E-mail" type="email" value={pessoa.email ?? ''} onChange={setP('email')} />
            </fieldset>

            <fieldset className="grid sm:grid-cols-2 gap-4 border-t pt-4">
              <legend className="text-sm font-semibold text-gray-700 mb-2">Ficha de membro</legend>
              <Field id="m-cart" label="Nº carteirinha" value={membro.numero_carteirinha ?? ''} onChange={setM('numero_carteirinha')} />
              <Field id="m-conv" label="Conversão" type="date" value={membro.data_conversao ?? ''} onChange={setM('data_conversao')} />
              <Field id="m-bat" label="Batismo" type="date" value={membro.data_batismo ?? ''} onChange={setM('data_batismo')} />
              <Field id="m-cong" label="Congregação" value={membro.congregacao ?? ''} onChange={setM('congregacao')} />
              <Field id="m-proc" label="Procedência" value={membro.procedencia ?? ''} onChange={setM('procedencia')} />
              <div>
                <label htmlFor="m-cargo" className="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
                <select
                  id="m-cargo"
                  value={membro.cargo_id ?? ''}
                  onChange={(e) => setMembro((m) => ({ ...m, cargo_id: e.target.value ? Number(e.target.value) : null }))}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2"
                >
                  <option value="">—</option>
                  {cargos.map((c) => (
                    <option key={c.id} value={c.id}>{c.nome}</option>
                  ))}
                </select>
              </div>
              <label className="flex items-center gap-2 text-sm text-gray-700 mt-6">
                <input
                  type="checkbox"
                  checked={!!membro.batizado_espirito_santo}
                  onChange={(e) => setMembro((m) => ({ ...m, batizado_espirito_santo: e.target.checked }))}
                  className="rounded border-gray-300 text-red-600 focus:ring-red-600"
                />
                Batizado(a) no Espírito Santo
              </label>
            </fieldset>

            {modalForm.id && (
              <div className="border-t pt-4">
                <ImagemUpload
                  rotulo="Foto do membro"
                  url={fotoAtual}
                  enviar={async (arq) => {
                    const m = await membrosApi.enviarFoto(modalForm.id!, arq);
                    setFotoAtual(m.foto_url ?? null);
                  }}
                  remover={async () => {
                    await membrosApi.removerFoto(modalForm.id!);
                    setFotoAtual(null);
                  }}
                />
              </div>
            )}

            {detalheDeptos.length > 0 && (
              <div className="border-t pt-4">
                <h3 className="text-sm font-semibold text-gray-700 mb-2">Departamentos</h3>
                <div className="flex flex-wrap gap-2">
                  {detalheDeptos.map((d) => (
                    <span key={d.nome} className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700">
                      {d.nome}{d.funcao ? ` · ${d.funcao}` : ''}
                    </span>
                  ))}
                </div>
                <p className="text-xs text-gray-500 mt-2">
                  Gerencie os vínculos na página Departamentos.
                </p>
              </div>
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

      {/* Modal status */}
      {modalStatus && (
        <Modal titulo={`Status — ${modalStatus.nome_completo}`} onClose={() => setModalStatus(null)}>
          {erroForm && (
            <div className="rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3 mb-4" role="alert">{erroForm}</div>
          )}
          <div className="space-y-4">
            <div>
              <label htmlFor="novo-status" className="block text-sm font-medium text-gray-700 mb-1">Novo status</label>
              <select
                id="novo-status"
                value={novoStatus}
                onChange={(e) => setNovoStatus(e.target.value as StatusMembro)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
                {Object.entries(STATUS_LABEL).map(([v, l]) => (
                  <option key={v} value={v}>{l}</option>
                ))}
              </select>
            </div>
            {novoStatus !== 'ativo' && (
              <p className="text-sm text-yellow-800 bg-yellow-50 rounded-lg px-4 py-3">
                Ao sair de "Ativo", vínculos de departamento, matrícula EBD e
                acesso de usuário serão encerrados automaticamente.
              </p>
            )}
            <div className="flex gap-3">
              <button onClick={salvarStatus} disabled={salvando} className="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 disabled:opacity-60">
                {salvando ? 'Salvando…' : 'Confirmar'}
              </button>
              <button onClick={() => setModalStatus(null)} className="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                Cancelar
              </button>
            </div>
          </div>
        </Modal>
      )}

      {/* Confirmação de exclusão */}
      {confirmExcluir && (
        <Modal titulo="Excluir membro" onClose={() => setConfirmExcluir(null)} largura="max-w-md">
          <p className="text-gray-700 mb-4">
            Excluir <strong>{confirmExcluir.nome_completo}</strong>? A ficha é
            arquivada (soft delete) e some das listagens.
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

export default MembrosPage;
