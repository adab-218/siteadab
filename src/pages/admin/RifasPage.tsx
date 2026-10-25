import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  Banknote, Check, Copy, ExternalLink, Gift, Loader2, Pencil, Play,
  Plus, Square, Ticket, Trash2, Trophy, Users, X, Ban,
} from 'lucide-react';
import Modal from '../../components/ui/Modal';
import Field from '../../components/ui/Field';
import ImagemUpload from '../../components/ui/ImagemUpload';
import {
  ApiError,
  departamentosApi,
  Departamento,
  membrosApi,
  MembroListItem,
  RespostaPaginada,
  Rifa,
  RifaBilhete,
  RifaDetalhe,
  RifaExtrato,
  RifaMetodo,
  RifaPayload,
  RifaRankingItem,
  RifaStatus,
  rifasApi,
} from '../../lib/api';
import { mascaraTelefone } from '../../lib/mascaras';
import { useAuth } from '../../lib/auth-context';

const moeda = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const formatarDataHora = (iso: string | null): string => {
  if (!iso) return '—';
  const d = new Date(iso.replace(' ', 'T'));
  return `${d.toLocaleDateString('pt-BR')} ${d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;
};

const STATUS_META: Record<RifaStatus, { label: string; classes: string }> = {
  rascunho: { label: 'Rascunho', classes: 'bg-gray-200 text-gray-700' },
  ativo: { label: 'Ativo', classes: 'bg-green-100 text-green-800' },
  encerrado: { label: 'Encerrado', classes: 'bg-blue-100 text-blue-800' },
  sorteado: { label: 'Sorteado', classes: 'bg-amber-100 text-amber-800' },
  cancelado: { label: 'Cancelado', classes: 'bg-red-100 text-red-800' },
};

const PAG_LABEL: Record<string, string> = {
  pendente: 'Pendente',
  pago: 'Pago',
  gratuito: 'Gratuito',
  expirado: 'Expirado',
  falhou: 'Falhou',
};

const RIFA_VAZIA: RifaPayload = {
  titulo: '', valor_numero: 10, total_numeros: 100, metodo: 'sistema',
};

type Aba = 'premios' | 'reparticao' | 'divulgadores' | 'bilhetes' | 'ranking' | 'resultado';

/**
 * M7 — Sorteios beneficentes (doc 13): rifa multiprêmios com prêmios por
 * departamento, repartição %, links de divulgação por membro e bilhetes.
 */
const RifasPage: React.FC = () => {
  const { usuario } = useAuth();
  const podeEditar = ['admin', 'secretaria'].includes(usuario?.perfil ?? '');
  const podeDivulgar = podeEditar || usuario?.perfil === 'lider_departamento';

  const [itens, setItens] = useState<Rifa[]>([]);
  const [total, setTotal] = useState(0);
  const [pagina, setPagina] = useState(1);
  const [filtroStatus, setFiltroStatus] = useState('');
  const [busca, setBusca] = useState('');
  const [deptos, setDeptos] = useState<Departamento[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');
  const [aviso, setAviso] = useState('');

  const [modalForm, setModalForm] = useState<null | { id?: number }>(null);
  const [form, setForm] = useState<RifaPayload>(RIFA_VAZIA);
  const [capaUrl, setCapaUrl] = useState<string | null>(null);
  const [erroForm, setErroForm] = useState('');
  const [salvando, setSalvando] = useState(false);

  const [detalhe, setDetalhe] = useState<RifaDetalhe | null>(null);
  const [aba, setAba] = useState<Aba>('premios');
  const [confirm, setConfirm] = useState<null | { acao: 'ativar' | 'encerrar' | 'excluir'; rifa: Rifa }>(null);
  const [modalCancelar, setModalCancelar] = useState<Rifa | null>(null);
  const [motivoCancelar, setMotivoCancelar] = useState('');
  const [modalSortear, setModalSortear] = useState<RifaDetalhe | null>(null);
  const [seed, setSeed] = useState('');
  const [federal, setFederal] = useState('');

  const carregar = useCallback(async () => {
    setCarregando(true);
    setErro('');
    try {
      const r = await rifasApi.listar({
        status: filtroStatus || undefined,
        q: busca || undefined,
        pagina,
      });
      setItens(r.itens);
      setTotal(r.total);
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao carregar sorteios.');
    } finally {
      setCarregando(false);
    }
  }, [filtroStatus, busca, pagina]);

  useEffect(() => { carregar(); }, [carregar]);
  useEffect(() => { departamentosApi.listar().then(setDeptos).catch(() => setDeptos([])); }, []);

  const abrirDetalhe = async (rifa: Rifa) => {
    setErro('');
    try {
      setDetalhe(await rifasApi.ver(rifa.id));
      setAba('premios');
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao abrir sorteio.');
    }
  };

  const recarregarDetalhe = async () => {
    if (detalhe) {
      setDetalhe(await rifasApi.ver(detalhe.id));
    }
    carregar();
  };

  const salvar = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setSalvando(true);
    setErroForm('');
    try {
      const payload = {
        ...form,
        data_sorteio: form.data_sorteio ? form.data_sorteio.replace('T', ' ') : null,
      };
      if (modalForm?.id) {
        await rifasApi.atualizar(modalForm.id, payload);
      } else {
        await rifasApi.criar(payload);
      }
      setModalForm(null);
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Erro ao salvar.');
    } finally {
      setSalvando(false);
    }
  };

  const executarCiclo = async () => {
    if (!confirm) return;
    setSalvando(true);
    setErro('');
    try {
      if (confirm.acao === 'ativar') await rifasApi.ativar(confirm.rifa.id);
      if (confirm.acao === 'encerrar') await rifasApi.encerrar(confirm.rifa.id);
      if (confirm.acao === 'excluir') await rifasApi.remover(confirm.rifa.id);
      setConfirm(null);
      setDetalhe(null);
      carregar();
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Falha na operação.');
      setConfirm(null);
    } finally {
      setSalvando(false);
    }
  };

  const cancelar = async () => {
    if (!modalCancelar) return;
    setSalvando(true);
    try {
      await rifasApi.cancelar(modalCancelar.id, motivoCancelar);
      setModalCancelar(null);
      setMotivoCancelar('');
      setDetalhe(null);
      carregar();
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao cancelar.');
    } finally {
      setSalvando(false);
    }
  };

  const sortear = async () => {
    if (!modalSortear) return;
    setSalvando(true);
    setErro('');
    try {
      await rifasApi.sortear(modalSortear.id, {
        seed: seed || undefined,
        federal_numeros: modalSortear.metodo === 'federal' ? federal : undefined,
      });
      setModalSortear(null);
      setSeed('');
      setFederal('');
      recarregarDetalhe();
      setAviso('Sorteio realizado! Confira a aba Resultado.');
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro ao sortear.');
    } finally {
      setSalvando(false);
    }
  };

  const copiarLink = (rifa: Rifa) => {
    navigator.clipboard.writeText(`${window.location.origin}/sorteio/${rifa.slug}`);
    setAviso('Link público copiado!');
    setTimeout(() => setAviso(''), 3000);
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <Ticket className="text-red-700" aria-hidden="true" /> Sorteios Beneficentes
        </h1>
        {podeEditar && (
          <button
            onClick={() => { setForm(RIFA_VAZIA); setCapaUrl(null); setErroForm(''); setModalForm({}); }}
            className="bg-red-700 text-white rounded-lg px-4 py-2 text-sm font-semibold hover:bg-red-800 flex items-center gap-2"
          >
            <Plus size={16} aria-hidden="true" /> Novo sorteio
          </button>
        )}
      </div>

      <div className="flex flex-wrap gap-2">
        <input
          type="search" placeholder="Buscar por título…" aria-label="Buscar sorteio"
          value={busca} onChange={(e) => { setBusca(e.target.value); setPagina(1); }}
          className="border rounded-lg px-3 py-2 text-sm w-64"
        />
        <select
          aria-label="Filtrar por status" value={filtroStatus}
          onChange={(e) => { setFiltroStatus(e.target.value); setPagina(1); }}
          className="border rounded-lg px-3 py-2 text-sm"
        >
          <option value="">Todos os status</option>
          {Object.entries(STATUS_META).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
        </select>
      </div>

      {aviso && <p role="status" className="bg-green-50 border border-green-200 text-green-800 rounded-lg px-3 py-2 text-sm">{aviso}</p>}
      {erro && <p role="alert" className="bg-red-50 border border-red-200 text-red-700 rounded-lg px-3 py-2 text-sm">{erro}</p>}

      {carregando ? (
        <p className="flex items-center gap-2 text-gray-500"><Loader2 size={16} className="animate-spin" /> Carregando…</p>
      ) : itens.length === 0 ? (
        <p className="text-gray-500">Nenhum sorteio encontrado.</p>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {itens.map((r) => {
            const meta = STATUS_META[r.status];
            return (
              <div key={r.id} className="bg-white rounded-lg border p-4 space-y-2">
                <div className="flex items-start justify-between gap-2">
                  <h2 className="font-semibold text-gray-800 leading-tight">{r.titulo}</h2>
                  <span className={`shrink-0 text-xs rounded-full px-2 py-0.5 ${meta.classes}`}>{meta.label}</span>
                </div>
                <p className="text-sm text-gray-600">
                  {moeda(Number(r.valor_numero))}/número · {r.total_numeros} números · {r.total_premios ?? 0} prêmios
                </p>
                <p className="text-xs text-gray-500">
                  Sorteio: {formatarDataHora(r.data_sorteio)} · Pagos: {r.bilhetes_pagos ?? 0} · Pendentes: {r.bilhetes_pendentes ?? 0}
                </p>
                <div className="flex flex-wrap gap-1 pt-1">
                  <button onClick={() => abrirDetalhe(r)} className="text-xs bg-gray-100 hover:bg-gray-200 rounded px-2 py-1">
                    Gerenciar
                  </button>
                  <button onClick={() => copiarLink(r)} className="text-xs bg-gray-100 hover:bg-gray-200 rounded px-2 py-1 inline-flex items-center gap-1">
                    <Copy size={12} aria-hidden="true" /> Link
                  </button>
                  <a href={`/sorteio/${r.slug}`} target="_blank" rel="noreferrer" className="text-xs bg-gray-100 hover:bg-gray-200 rounded px-2 py-1 inline-flex items-center gap-1">
                    <ExternalLink size={12} aria-hidden="true" /> Página
                  </a>
                  {podeEditar && r.status === 'rascunho' && (
                    <>
                      <button onClick={() => { setForm({ ...r, descricao: r.descricao ?? '', regulamento: r.regulamento ?? '', pix_instrucoes: r.pix_instrucoes ?? '', data_sorteio: r.data_sorteio?.replace(' ', 'T').slice(0, 16) ?? '' }); setCapaUrl(r.capa_url); setErroForm(''); setModalForm({ id: r.id }); }} className="text-xs bg-gray-100 hover:bg-gray-200 rounded px-2 py-1 inline-flex items-center gap-1">
                        <Pencil size={12} aria-hidden="true" /> Editar
                      </button>
                      <button onClick={() => setConfirm({ acao: 'ativar', rifa: r })} className="text-xs bg-green-100 hover:bg-green-200 text-green-800 rounded px-2 py-1 inline-flex items-center gap-1">
                        <Play size={12} aria-hidden="true" /> Ativar
                      </button>
                      <button onClick={() => setConfirm({ acao: 'excluir', rifa: r })} className="text-xs bg-red-100 hover:bg-red-200 text-red-700 rounded px-2 py-1 inline-flex items-center gap-1">
                        <Trash2 size={12} aria-hidden="true" /> Excluir
                      </button>
                    </>
                  )}
                  {podeEditar && r.status === 'ativo' && (
                    <>
                      <button onClick={() => setConfirm({ acao: 'encerrar', rifa: r })} className="text-xs bg-blue-100 hover:bg-blue-200 text-blue-800 rounded px-2 py-1 inline-flex items-center gap-1">
                        <Square size={12} aria-hidden="true" /> Encerrar
                      </button>
                      <button onClick={async () => { setDetalhe(await rifasApi.ver(r.id)); setModalSortear(await rifasApi.ver(r.id)); }} className="text-xs bg-amber-100 hover:bg-amber-200 text-amber-800 rounded px-2 py-1 inline-flex items-center gap-1">
                        <Trophy size={12} aria-hidden="true" /> Sortear
                      </button>
                    </>
                  )}
                  {podeEditar && (r.status === 'rascunho' || r.status === 'ativo' || r.status === 'encerrado') && (
                    <button onClick={() => { setMotivoCancelar(''); setModalCancelar(r); }} className="text-xs bg-red-50 hover:bg-red-100 text-red-700 rounded px-2 py-1 inline-flex items-center gap-1">
                      <Ban size={12} aria-hidden="true" /> Cancelar
                    </button>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
      {total > itens.length && (
        <button onClick={() => setPagina((p) => p + 1)} className="text-sm text-red-700 hover:underline">
          Carregar mais ({itens.length}/{total})
        </button>
      )}

      {/* modal criar/editar */}
      {modalForm && (
        <Modal titulo={modalForm.id ? 'Editar sorteio' : 'Novo sorteio'} onClose={() => setModalForm(null)} largura="max-w-2xl">
          <form onSubmit={salvar} className="space-y-3">
            <Field id="r-titulo" label="Título" required value={form.titulo}
                   onChange={(e) => setForm({ ...form, titulo: e.target.value })} />
            <div className="grid sm:grid-cols-3 gap-3">
              <Field id="r-valor" label="Valor do número (R$)" required type="number" step="0.01" min="0.01"
                     value={form.valor_numero}
                     onChange={(e) => setForm({ ...form, valor_numero: Number(e.target.value) })} />
              <Field id="r-total" label="Total de números" required type="number" min="1" max="100000"
                     value={form.total_numeros}
                     onChange={(e) => setForm({ ...form, total_numeros: Number(e.target.value) })} />
              <Field id="r-max" label="Máx. por pessoa" type="number" min="1" max="1000"
                     value={form.max_por_pessoa ?? 50}
                     onChange={(e) => setForm({ ...form, max_por_pessoa: Number(e.target.value) })} />
            </div>
            <div className="grid sm:grid-cols-2 gap-3">
              <Field id="r-data" label="Data/hora do sorteio" type="datetime-local"
                     value={form.data_sorteio ?? ''}
                     onChange={(e) => setForm({ ...form, data_sorteio: e.target.value })} />
              <div>
                <label htmlFor="r-metodo" className="block text-sm font-medium text-gray-700 mb-1">Forma de apuração</label>
                <select id="r-metodo" value={form.metodo}
                        onChange={(e) => setForm({ ...form, metodo: e.target.value as RifaMetodo })}
                        className="w-full border rounded-lg px-3 py-2">
                  <option value="sistema">Pelo sistema (seed + prova)</option>
                  <option value="federal">Loteria Federal</option>
                </select>
              </div>
            </div>
            <div>
              <label htmlFor="r-desc" className="block text-sm font-medium text-gray-700 mb-1">Descrição (propósito)</label>
              <textarea id="r-desc" rows={3} value={form.descricao ?? ''}
                        onChange={(e) => setForm({ ...form, descricao: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
            </div>
            <div>
              <label htmlFor="r-reg" className="block text-sm font-medium text-gray-700 mb-1">
                Regulamento público <span className="text-red-600" aria-hidden="true">*</span>
                <span className="text-xs text-gray-500 font-normal"> (obrigatório para ativar — Lei 5.768/1971)</span>
              </label>
              <textarea id="r-reg" rows={4} value={form.regulamento ?? ''}
                        onChange={(e) => setForm({ ...form, regulamento: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
            </div>
            <div>
              <label htmlFor="r-pix" className="block text-sm font-medium text-gray-700 mb-1">PIX copia-e-cola / chave</label>
              <textarea id="r-pix" rows={2} value={form.pix_instrucoes ?? ''}
                        onChange={(e) => setForm({ ...form, pix_instrucoes: e.target.value })}
                        placeholder="Ex.: Chave PIX: pix@adab.teo.br — envie o comprovante pelo WhatsApp (91) 9xxxx-xxxx"
                        className="w-full border rounded-lg px-3 py-2" />
            </div>
            {modalForm.id ? (
              <ImagemUpload
                url={capaUrl}
                rotulo="Capa do sorteio (página pública)"
                formato="banner"
                enviar={async (f) => { const r = await rifasApi.enviarCapa(modalForm.id!, f); setCapaUrl(r.capa_url); carregar(); }}
                remover={async () => { await rifasApi.removerCapa(modalForm.id!); setCapaUrl(null); carregar(); }}
              />
            ) : (
              <p className="text-xs text-gray-500">A capa pode ser enviada após salvar o sorteio.</p>
            )}
            {erroForm && <p role="alert" className="text-sm text-red-700">{erroForm}</p>}
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => setModalForm(null)} className="px-4 py-2 text-sm border rounded-lg">Cancelar</button>
              <button type="submit" disabled={salvando} className="px-4 py-2 text-sm bg-red-700 text-white rounded-lg font-semibold disabled:opacity-60 flex items-center gap-2">
                {salvando && <Loader2 size={14} className="animate-spin" aria-hidden="true" />} Salvar
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* confirmações de ciclo */}
      {confirm && (
        <Modal titulo="Confirmar" onClose={() => setConfirm(null)}>
          <p className="text-sm text-gray-700 mb-4">
            {confirm.acao === 'ativar' && 'Ativar este sorteio? Ele ficará visível para reservas públicas.'}
            {confirm.acao === 'encerrar' && 'Encerrar as vendas deste sorteio? Novas reservas serão bloqueadas.'}
            {confirm.acao === 'excluir' && 'Excluir este sorteio em rascunho? A ação não pode ser desfeita.'}
          </p>
          <div className="flex justify-end gap-2">
            <button onClick={() => setConfirm(null)} className="px-4 py-2 text-sm border rounded-lg">Voltar</button>
            <button onClick={executarCiclo} disabled={salvando} className="px-4 py-2 text-sm bg-red-700 text-white rounded-lg font-semibold disabled:opacity-60">
              Confirmar
            </button>
          </div>
        </Modal>
      )}

      {/* cancelar */}
      {modalCancelar && (
        <Modal titulo="Cancelar sorteio" onClose={() => setModalCancelar(null)}>
          <form onSubmit={(e) => { e.preventDefault(); cancelar(); }} className="space-y-3">
            <p className="text-sm text-gray-600">Bilhetes pagos ficam registrados para devolução manual.</p>
            <Field id="r-motivo" label="Motivo do cancelamento" required maxLength={300}
                   value={motivoCancelar} onChange={(e) => setMotivoCancelar(e.target.value)} />
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => setModalCancelar(null)} className="px-4 py-2 text-sm border rounded-lg">Voltar</button>
              <button type="submit" disabled={salvando} className="px-4 py-2 text-sm bg-red-700 text-white rounded-lg font-semibold disabled:opacity-60">Cancelar sorteio</button>
            </div>
          </form>
        </Modal>
      )}

      {/* sortear */}
      {modalSortear && (
        <Modal titulo={`Sortear — ${modalSortear.titulo}`} onClose={() => setModalSortear(null)}>
          <form onSubmit={(e) => { e.preventDefault(); sortear(); }} className="space-y-3">
            {modalSortear.metodo === 'federal' ? (
              <Field id="r-federal" label="Números da Loteria Federal (na ordem dos prêmios, separados por vírgula)" required
                     placeholder="04589, 12345, 67890"
                     value={federal} onChange={(e) => setFederal(e.target.value)} />
            ) : (
              <Field id="r-seed" label="Seed (opcional — deixe vazio para aleatória)"
                     placeholder="Ex.: sorteio-reforma-2026"
                     value={seed} onChange={(e) => setSeed(e.target.value)} />
            )}
            <p className="text-xs text-gray-500">
              A prova do sorteio (SHA-256) será exibida publicamente. Somente bilhetes pagos participam.
            </p>
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => setModalSortear(null)} className="px-4 py-2 text-sm border rounded-lg">Voltar</button>
              <button type="submit" disabled={salvando} className="px-4 py-2 text-sm bg-amber-600 text-white rounded-lg font-semibold disabled:opacity-60 flex items-center gap-2">
                {salvando && <Loader2 size={14} className="animate-spin" aria-hidden="true" />} Sortear agora
              </button>
            </div>
          </form>
        </Modal>
      )}

      {/* detalhe com abas */}
      {detalhe && (
        <DetalheRifa
          detalhe={detalhe}
          aba={aba}
          setAba={setAba}
          deptos={deptos}
          podeEditar={podeEditar}
          podeDivulgar={podeDivulgar}
          onClose={() => setDetalhe(null)}
          onChange={recarregarDetalhe}
          onErro={setErro}
        />
      )}
    </div>
  );
};

// =====================================================================
// Detalhe com abas

interface DetalheProps {
  detalhe: RifaDetalhe;
  aba: Aba;
  setAba: (a: Aba) => void;
  deptos: Departamento[];
  podeEditar: boolean;
  podeDivulgar: boolean;
  onClose: () => void;
  onChange: () => void;
  onErro: (m: string) => void;
}

const ABAS: { id: Aba; label: string; icon: React.ElementType }[] = [
  { id: 'premios', label: 'Prêmios', icon: Gift },
  { id: 'reparticao', label: 'Repartição', icon: Banknote },
  { id: 'divulgadores', label: 'Divulgadores', icon: Users },
  { id: 'bilhetes', label: 'Bilhetes', icon: Ticket },
  { id: 'ranking', label: 'Ranking', icon: Trophy },
  { id: 'resultado', label: 'Resultado/Extrato', icon: Check },
];

const DetalheRifa: React.FC<DetalheProps> = (props) => {
  const { detalhe, aba, setAba, onClose } = props;
  const meta = STATUS_META[detalhe.status];
  return (
    <Modal titulo={detalhe.titulo} onClose={onClose} largura="max-w-5xl">
      <div className="mb-3 flex flex-wrap items-center gap-3 text-sm">
        <span className={`text-xs rounded-full px-2 py-0.5 ${meta.classes}`}>{meta.label}</span>
        <span className="text-gray-600">{moeda(Number(detalhe.valor_numero))}/número · {detalhe.total_numeros} números</span>
        <span className="text-gray-500">Sorteio: {formatarDataHora(detalhe.data_sorteio)}</span>
        {detalhe.status === 'cancelado' && detalhe.cancelado_motivo && (
          <span className="text-red-700">Motivo: {detalhe.cancelado_motivo}</span>
        )}
      </div>
      <div className="flex flex-wrap gap-1 border-b mb-4">
        {ABAS.map(({ id, label, icon: Icon }) => (
          <button
            key={id} onClick={() => setAba(id)}
            className={`flex items-center gap-1 px-3 py-2 text-sm font-medium border-b-2 -mb-px ${
              aba === id ? 'border-red-700 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-800'
            }`}
          >
            <Icon size={14} aria-hidden="true" /> {label}
          </button>
        ))}
      </div>
      {aba === 'premios' && <AbaPremios {...props} />}
      {aba === 'reparticao' && <AbaReparticao {...props} />}
      {aba === 'divulgadores' && <AbaDivulgadores {...props} />}
      {aba === 'bilhetes' && <AbaBilhetes {...props} />}
      {aba === 'ranking' && <AbaRanking {...props} />}
      {aba === 'resultado' && <AbaResultado {...props} />}
    </Modal>
  );
};

// ---- Prêmios ----

const AbaPremios: React.FC<DetalheProps> = ({ detalhe, deptos, podeEditar, onChange, onErro }) => {
  const [form, setForm] = useState({ titulo: '', departamento_id: 0, valor_estimado: '', ordem: detalhe.premios.length + 1 });
  const [salvando, setSalvando] = useState(false);
  const imutavel = ['sorteado', 'cancelado'].includes(detalhe.status);

  const adicionar = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setSalvando(true);
    try {
      await rifasApi.criarPremio(detalhe.id, {
        titulo: form.titulo,
        departamento_id: form.departamento_id,
        ordem: form.ordem,
        valor_estimado: form.valor_estimado ? Number(form.valor_estimado) : null,
      });
      setForm({ titulo: '', departamento_id: 0, valor_estimado: '', ordem: detalhe.premios.length + 2 });
      onChange();
    } catch (e) {
      onErro(e instanceof ApiError ? e.message : 'Erro ao adicionar prêmio.');
    } finally {
      setSalvando(false);
    }
  };

  return (
    <div className="space-y-3">
      <ul className="space-y-2">
        {detalhe.premios.map((p) => (
          <li key={p.id} className="flex items-center justify-between gap-3 border rounded-lg px-3 py-2 text-sm">
            <div className="flex items-center gap-3 min-w-0">
              <ImagemPremioInput
                premio={p}
                rifaId={detalhe.id}
                podeEditar={podeEditar && !imutavel}
                onChange={onChange}
                onErro={onErro}
              />
              <div className="min-w-0">
                <span className="font-medium">{p.ordem}º — {p.titulo}</span>
                <span className="text-gray-500"> · doado por {p.departamento_nome}</span>
                {p.valor_estimado !== null && <span className="text-green-700"> · {moeda(Number(p.valor_estimado))}</span>}
                {p.bilhete_vencedor_numero && <span className="ml-2 text-amber-700 font-semibold">🏆 #{p.bilhete_vencedor_numero}</span>}
              </div>
            </div>
            {podeEditar && !imutavel && (
              <button
                onClick={async () => { await rifasApi.removerPremio(detalhe.id, p.id); onChange(); }}
                className="shrink-0 text-red-600 hover:text-red-800" aria-label={`Remover prêmio ${p.titulo}`}
              >
                <Trash2 size={15} aria-hidden="true" />
              </button>
            )}
          </li>
        ))}
        {detalhe.premios.length === 0 && <li className="text-sm text-gray-500">Nenhum prêmio cadastrado.</li>}
      </ul>
      {podeEditar && !imutavel && (
        <form onSubmit={adicionar} className="grid sm:grid-cols-[1fr_200px_120px_80px_auto] gap-2 items-end border-t pt-3">
          <Field id="p-titulo" label="Prêmio" required value={form.titulo}
                 onChange={(e) => setForm({ ...form, titulo: e.target.value })} />
          <div>
            <label htmlFor="p-dep" className="block text-sm font-medium text-gray-700 mb-1">Departamento doador *</label>
            <select id="p-dep" required value={form.departamento_id}
                    onChange={(e) => setForm({ ...form, departamento_id: Number(e.target.value) })}
                    className="w-full border rounded-lg px-3 py-2">
              <option value={0}>Selecione…</option>
              {deptos.filter((d) => d.ativo).map((d) => <option key={d.id} value={d.id}>{d.nome}</option>)}
            </select>
          </div>
          <Field id="p-valor" label="Valor estimado" type="number" step="0.01" min="0" placeholder="R$"
                 value={form.valor_estimado}
                 onChange={(e) => setForm({ ...form, valor_estimado: e.target.value })} />
          <Field id="p-ordem" label="Ordem" type="number" min="1" max="99"
                 value={form.ordem} onChange={(e) => setForm({ ...form, ordem: Number(e.target.value) })} />
          <button type="submit" disabled={salvando} className="bg-red-700 text-white rounded-lg px-3 py-2 text-sm font-semibold disabled:opacity-60">
            Adicionar
          </button>
        </form>
      )}
    </div>
  );
};

// Mini-upload de imagem do prêmio (valida tipo/tamanho como ImagemUpload).
const ImagemPremioInput: React.FC<{
  premio: { id: number; titulo: string; imagem_url: string | null };
  rifaId: number;
  podeEditar: boolean;
  onChange: () => void;
  onErro: (m: string) => void;
}> = ({ premio, rifaId, podeEditar, onChange, onErro }) => {
  const inputRef = useRef<HTMLInputElement>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const arquivo = e.target.files?.[0];
    e.target.value = '';
    if (!arquivo) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(arquivo.type)) {
      onErro('Imagem do prêmio: use JPG, PNG ou WebP.');
      return;
    }
    if (arquivo.size > 2 * 1024 * 1024) {
      onErro('Imagem do prêmio excede 2 MB.');
      return;
    }
    setEnviando(true);
    try {
      await rifasApi.enviarImagemPremio(rifaId, premio.id, arquivo);
      onChange();
    } catch (err) {
      onErro(err instanceof ApiError ? err.message : 'Falha no upload.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div className="shrink-0 flex flex-col items-center gap-1">
      <button
        type="button"
        onClick={() => podeEditar && inputRef.current?.click()}
        disabled={!podeEditar || enviando}
        aria-label={podeEditar ? `Imagem do prêmio ${premio.titulo}` : `Imagem do prêmio ${premio.titulo}`}
        className="w-14 h-14 rounded-lg border bg-gray-100 overflow-hidden flex items-center justify-center text-gray-400 hover:border-red-300 disabled:opacity-70"
      >
        {enviando ? (
          <Loader2 size={16} className="animate-spin" />
        ) : premio.imagem_url ? (
          <img src={premio.imagem_url} alt={premio.titulo} className="w-full h-full object-cover" />
        ) : (
          <Gift size={18} aria-hidden="true" />
        )}
      </button>
      {premio.imagem_url && podeEditar && (
        <button
          type="button"
          onClick={async () => { await rifasApi.removerImagemPremio(rifaId, premio.id); onChange(); }}
          className="text-[10px] text-red-600 hover:underline"
        >
          remover
        </button>
      )}
      <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={enviar} className="sr-only" />
    </div>
  );
};

// ---- Repartição ----

interface LinhaRep { destino: string; tipo: 'livre' | 'departamentos'; percentual: string }

const AbaReparticao: React.FC<DetalheProps> = ({ detalhe, podeEditar, onChange, onErro }) => {
  const [linhas, setLinhas] = useState<LinhaRep[]>(
    detalhe.reparticao.map((r) => ({ destino: r.destino, tipo: r.tipo, percentual: String(r.percentual) }))
  );
  const [salvando, setSalvando] = useState(false);
  const imutavel = ['sorteado', 'cancelado'].includes(detalhe.status);
  const soma = linhas.reduce((s, l) => s + (Number(l.percentual) || 0), 0);

  const set = (i: number, patch: Partial<LinhaRep>) =>
    setLinhas((ls) => ls.map((l, j) => (j === i ? { ...l, ...patch } : l)));

  const salvar = async () => {
    setSalvando(true);
    try {
      await rifasApi.salvarReparticao(detalhe.id, linhas.map((l) => ({
        destino: l.destino, tipo: l.tipo, percentual: Number(l.percentual),
      })));
      onChange();
    } catch (e) {
      onErro(e instanceof ApiError ? e.message : 'Erro ao salvar repartição.');
    } finally {
      setSalvando(false);
    }
  };

  return (
    <div className="space-y-3">
      <p className="text-sm text-gray-600">
        Percentuais sobre o total arrecadado (bilhetes pagos). "Departamentos" rateia
        proporcionalmente às vendas de cada depto.
      </p>
      <ul className="space-y-2">
        {linhas.map((l, i) => (
          <li key={i} className="grid grid-cols-[1fr_110px_130px_auto] gap-2 items-center">
            <input
              aria-label={`Destino ${i + 1}`} value={l.destino} required disabled={imutavel}
              onChange={(e) => set(i, { destino: e.target.value })}
              placeholder="Ex.: Construção do templo"
              className="border rounded-lg px-3 py-2 text-sm disabled:bg-gray-100"
            />
            <input
              aria-label={`Percentual ${i + 1}`} type="number" min="0.01" max="100" step="0.01" required disabled={imutavel}
              value={l.percentual} onChange={(e) => set(i, { percentual: e.target.value })}
              className="border rounded-lg px-3 py-2 text-sm disabled:bg-gray-100"
            />
            <label className="text-xs text-gray-600 flex items-center gap-1">
              <input type="checkbox" checked={l.tipo === 'departamentos'} disabled={imutavel}
                     onChange={(e) => set(i, { tipo: e.target.checked ? 'departamentos' : 'livre' })} />
              Rateio p/ deptos
            </label>
            {podeEditar && !imutavel && (
              <button onClick={() => setLinhas((ls) => ls.filter((_, j) => j !== i))} aria-label="Remover linha" className="text-red-600">
                <X size={15} aria-hidden="true" />
              </button>
            )}
          </li>
        ))}
      </ul>
      {podeEditar && !imutavel && (
        <div className="flex items-center gap-3">
          <button onClick={() => setLinhas((ls) => [...ls, { destino: '', tipo: 'livre', percentual: '' }])}
                  className="text-sm text-red-700 hover:underline inline-flex items-center gap-1">
            <Plus size={14} aria-hidden="true" /> Adicionar destino
          </button>
          <span className={`text-sm font-semibold ${Math.abs(soma - 100) < 0.01 ? 'text-green-700' : 'text-red-700'}`}>
            Total: {soma}% {Math.abs(soma - 100) < 0.01 ? '✓' : '(precisa ser 100%)'}
          </span>
          <button onClick={salvar} disabled={salvando || Math.abs(soma - 100) > 0.01}
                  className="ml-auto bg-red-700 text-white rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-60">
            Salvar repartição
          </button>
        </div>
      )}
    </div>
  );
};

// ---- Divulgadores ----

const AbaDivulgadores: React.FC<DetalheProps> = ({ detalhe, podeDivulgar, onChange, onErro }) => {
  const [busca, setBusca] = useState('');
  const [resultados, setResultados] = useState<MembroListItem[]>([]);
  const debounce = useRef<ReturnType<typeof setTimeout>>();
  const imutavel = !['rascunho', 'ativo'].includes(detalhe.status);

  useEffect(() => {
    clearTimeout(debounce.current);
    const t = busca.trim();
    if (t.length < 2) { setResultados([]); return; }
    debounce.current = setTimeout(async () => {
      try {
        const r: RespostaPaginada<MembroListItem> = await membrosApi.listar({ q: t, status: 'ativo', por_pagina: 8 });
        setResultados(r.itens);
      } catch { setResultados([]); }
    }, 300);
  }, [busca]);

  const link = (token: string) => `${window.location.origin}/sorteio/${detalhe.slug}?v=${token}`;

  return (
    <div className="space-y-3">
      <p className="text-sm text-gray-600">
        Cada membro recebe um link exclusivo — as vendas por ele contam para o membro e para o depto.
      </p>
      {podeDivulgar && !imutavel && (
        <div className="relative">
          <Field id="dv-busca" label="Adicionar membro divulgador" placeholder="Digite o nome…"
                 value={busca} onChange={(e) => setBusca(e.target.value)} />
          {resultados.length > 0 && (
            <ul className="absolute z-10 bg-white border rounded-lg shadow w-full mt-1 max-h-48 overflow-auto">
              {resultados.map((m) => (
                <li key={m.pessoa_id}>
                  <button
                    className="w-full text-left px-3 py-2 text-sm hover:bg-gray-50"
                    onClick={async () => {
                      setBusca('');
                      setResultados([]);
                      try {
                        await rifasApi.criarDivulgador(detalhe.id, m.pessoa_id);
                        onChange();
                      } catch (e) {
                        onErro(e instanceof ApiError ? e.message : 'Erro ao gerar link.');
                      }
                    }}
                  >
                    {m.nome_completo}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
      <ul className="space-y-1">
        {detalhe.divulgadores.map((d) => (
          <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 border rounded-lg px-3 py-2 text-sm">
            <div className="min-w-0">
              <span className="font-medium">{d.nome_completo}</span>
              {!d.ativo && <span className="ml-2 text-xs text-red-600">(link desativado)</span>}
              <p className="text-xs text-gray-500 truncate">{link(d.token)}</p>
            </div>
            <div className="flex items-center gap-3">
              <span className="text-xs text-gray-600">{d.vendas_pagas ?? 0} pagos / {d.vendas ?? 0} reservas</span>
              <button
                onClick={() => { navigator.clipboard.writeText(link(d.token)); }}
                className="text-xs bg-gray-100 hover:bg-gray-200 rounded px-2 py-1 inline-flex items-center gap-1"
              >
                <Copy size={12} aria-hidden="true" /> Copiar
              </button>
              {podeDivulgar && !imutavel && d.ativo === 1 && (
                <button
                  onClick={async () => { await rifasApi.desativarDivulgador(detalhe.id, d.id); onChange(); }}
                  className="text-xs text-red-600 hover:underline"
                >
                  Desativar
                </button>
              )}
            </div>
          </li>
        ))}
        {detalhe.divulgadores.length === 0 && <li className="text-sm text-gray-500">Nenhum divulgador gerado ainda.</li>}
      </ul>
    </div>
  );
};

// ---- Bilhetes ----

const AbaBilhetes: React.FC<DetalheProps> = ({ detalhe, podeEditar, onErro }) => {
  const [itens, setItens] = useState<RifaBilhete[]>([]);
  const [total, setTotal] = useState(0);
  const [pagina, setPagina] = useState(1);
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const [carregando, setCarregando] = useState(true);

  const carregar = useCallback(async () => {
    setCarregando(true);
    try {
      const r = await rifasApi.bilhetes(detalhe.id, {
        q: q || undefined, status_pag: status || undefined, pagina,
      });
      setItens(r.itens);
      setTotal(r.total);
    } catch (e) {
      onErro(e instanceof ApiError ? e.message : 'Erro ao carregar bilhetes.');
    } finally {
      setCarregando(false);
    }
  }, [detalhe.id, q, status, pagina, onErro]);

  useEffect(() => { carregar(); }, [carregar]);

  const acao = async (id: number, tipo: 'pagar' | 'estornar') => {
    try {
      if (tipo === 'pagar') await rifasApi.pagarBilhete(detalhe.id, id);
      else await rifasApi.estornarBilhete(detalhe.id, id);
      carregar();
    } catch (e) {
      onErro(e instanceof ApiError ? e.message : 'Falha na operação.');
    }
  };

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-2">
        <input type="search" placeholder="Nome, telefone ou número…" aria-label="Buscar bilhete"
               value={q} onChange={(e) => { setQ(e.target.value); setPagina(1); }}
               className="border rounded-lg px-3 py-2 text-sm w-64" />
        <select aria-label="Status do pagamento" value={status}
                onChange={(e) => { setStatus(e.target.value); setPagina(1); }}
                className="border rounded-lg px-3 py-2 text-sm">
          <option value="">Todos</option>
          {Object.entries(PAG_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
      </div>
      {carregando ? (
        <p className="text-sm text-gray-500 flex items-center gap-2"><Loader2 size={14} className="animate-spin" /> Carregando…</p>
      ) : (
        <div className="overflow-auto max-h-96">
          <table className="w-full text-sm">
            <thead className="text-left text-xs text-gray-500 border-b sticky top-0 bg-white">
              <tr><th className="py-2">Nº</th><th>Comprador</th><th>Telefone</th><th>Divulgador</th><th>Status</th><th /></tr>
            </thead>
            <tbody>
              {itens.map((b) => (
                <tr key={b.id} className="border-b last:border-0">
                  <td className="py-2 font-mono font-semibold">{b.numero !== null ? `#${b.numero}` : '—'}</td>
                  <td>{b.nome}</td>
                  <td>{mascaraTelefone(b.telefone)}</td>
                  <td className="text-gray-500">{b.divulgador_nome ?? '—'}{b.departamento_nome ? ` (${b.departamento_nome})` : ''}</td>
                  <td><span className="text-xs">{PAG_LABEL[b.status_pag] ?? b.status_pag}</span></td>
                  <td className="text-right">
                    {podeEditar && b.status_pag === 'pendente' && (
                      <button onClick={() => acao(b.id, 'pagar')} className="text-xs bg-green-100 text-green-800 rounded px-2 py-1 hover:bg-green-200">
                        Confirmar pago
                      </button>
                    )}
                    {podeEditar && ['pago', 'pendente', 'falhou'].includes(b.status_pag) && (
                      <button onClick={() => acao(b.id, 'estornar')} className="ml-1 text-xs bg-red-50 text-red-700 rounded px-2 py-1 hover:bg-red-100">
                        {b.status_pag === 'pago' ? 'Estornar' : 'Liberar'}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {itens.length === 0 && <tr><td colSpan={6} className="py-4 text-center text-gray-500">Nenhum bilhete.</td></tr>}
            </tbody>
          </table>
        </div>
      )}
      {total > itens.length && (
        <button onClick={() => setPagina((p) => p + 1)} className="text-sm text-red-700 hover:underline">
          Carregar mais ({itens.length}/{total})
        </button>
      )}
    </div>
  );
};

// ---- Ranking ----

const AbaRanking: React.FC<DetalheProps> = ({ detalhe, onErro }) => {
  const [rank, setRank] = useState<RifaRankingItem[]>([]);
  const [carregando, setCarregando] = useState(true);

  useEffect(() => {
    rifasApi.ranking(detalhe.id).then(setRank).catch((e) => onErro(e instanceof ApiError ? e.message : 'Erro no ranking.')).finally(() => setCarregando(false));
  }, [detalhe.id, onErro]);

  // agrega por depto para exibir também o total por departamento
  const porDepto = new Map<string, number>();
  rank.forEach((r) => porDepto.set(r.departamento_nome, (porDepto.get(r.departamento_nome) ?? 0) + Number(r.pagos)));

  return (
    <div className="space-y-4">
      <div>
        <h3 className="text-sm font-semibold text-gray-700 mb-2">Por departamento</h3>
        <ul className="flex flex-wrap gap-2">
          {[...porDepto.entries()].sort((a, b) => b[1] - a[1]).map(([nome, pagos], i) => (
            <li key={nome} className="bg-gray-100 rounded-lg px-3 py-2 text-sm">
              <span className="font-semibold">{i + 1}º {nome}</span> — {pagos} pagos
            </li>
          ))}
          {porDepto.size === 0 && <li className="text-sm text-gray-500">Sem vendas atribuídas ainda.</li>}
        </ul>
      </div>
      <div>
        <h3 className="text-sm font-semibold text-gray-700 mb-2">Por membro (vendas pagas)</h3>
        {carregando ? <Loader2 size={16} className="animate-spin" /> : (
          <table className="w-full text-sm">
            <thead className="text-left text-xs text-gray-500 border-b">
              <tr><th className="py-2">#</th><th>Membro</th><th>Departamento</th><th>Pagos</th><th>Pendentes</th><th>Arrecadado</th></tr>
            </thead>
            <tbody>
              {rank.map((r, i) => (
                <tr key={r.divulgador_id} className="border-b last:border-0">
                  <td className="py-2 font-semibold">{i + 1}º</td>
                  <td>{r.nome_completo}</td>
                  <td className="text-gray-500">{r.departamento_nome}</td>
                  <td>{r.pagos}</td>
                  <td className="text-amber-700">{r.pendentes}</td>
                  <td className="text-green-700 font-medium">{moeda(Number(r.arrecadado))}</td>
                </tr>
              ))}
              {rank.length === 0 && <tr><td colSpan={6} className="py-4 text-center text-gray-500">Nenhuma venda por link ainda.</td></tr>}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
};

// ---- Resultado / Extrato ----

const AbaResultado: React.FC<DetalheProps> = ({ detalhe, onErro }) => {
  const [extrato, setExtrato] = useState<RifaExtrato | null>(null);
  const [carregando, setCarregando] = useState(true);

  useEffect(() => {
    rifasApi.extrato(detalhe.id).then(setExtrato).catch((e) => onErro(e instanceof ApiError ? e.message : 'Erro no extrato.')).finally(() => setCarregando(false));
  }, [detalhe.id, onErro]);

  const vencedores = detalhe.premios.filter((p) => p.bilhete_vencedor_numero);

  return (
    <div className="space-y-4">
      {detalhe.status === 'sorteado' && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
          <h3 className="font-semibold text-amber-900 mb-2">Resultado</h3>
          <ul className="text-sm text-amber-900 space-y-1">
            {vencedores.map((p) => (
              <li key={p.id}>{p.ordem}º — {p.titulo}: número <strong>#{p.bilhete_vencedor_numero}</strong>
                {p.vencedor_nome ? ` (${p.vencedor_nome})` : ''}</li>
            ))}
          </ul>
          <div className="text-xs text-amber-800 mt-2 space-y-1">
            {detalhe.metodo === 'federal' && detalhe.federal_numeros && <p>Federal: {detalhe.federal_numeros}</p>}
            {detalhe.seed && <p className="break-all">Seed: <code>{detalhe.seed}</code></p>}
            {detalhe.prova_hash && <p className="break-all">Prova SHA-256: <code>{detalhe.prova_hash}</code></p>}
          </div>
        </div>
      )}
      {carregando ? <Loader2 size={16} className="animate-spin" /> : extrato && (
        <div className="space-y-3">
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div className="bg-gray-50 rounded-lg border p-3 text-center">
              <p className="text-xs text-gray-500">Arrecadado (pagos)</p>
              <p className="font-bold text-green-700">{moeda(extrato.arrecadado)}</p>
            </div>
            <div className="bg-gray-50 rounded-lg border p-3 text-center">
              <p className="text-xs text-gray-500">Pagos</p>
              <p className="font-bold">{extrato.bilhetes['pago'] ?? 0}</p>
            </div>
            <div className="bg-gray-50 rounded-lg border p-3 text-center">
              <p className="text-xs text-gray-500">Pendentes</p>
              <p className="font-bold text-amber-700">{extrato.bilhetes['pendente'] ?? 0}</p>
            </div>
            <div className="bg-gray-50 rounded-lg border p-3 text-center">
              <p className="text-xs text-gray-500">Número</p>
              <p className="font-bold">{moeda(extrato.valor_numero)}</p>
            </div>
          </div>
          <h3 className="text-sm font-semibold text-gray-700">Repartição da arrecadação</h3>
          <ul className="space-y-2">
            {extrato.destinos.map((d, i) => (
              <li key={i} className="border rounded-lg px-3 py-2 text-sm">
                <div className="flex justify-between">
                  <span className="font-medium">{d.destino} <span className="text-gray-400">({d.percentual}%)</span></span>
                  <span className="font-semibold text-green-700">{moeda(d.valor)}</span>
                </div>
                {d.rateio && d.rateio.length > 0 && (
                  <ul className="mt-1 ml-3 text-xs text-gray-600 space-y-0.5">
                    {d.rateio.map((r) => (
                      <li key={r.departamento_id} className="flex justify-between">
                        <span>{r.departamento_nome} ({r.bilhetes_pagos} pagos)</span>
                        <span>{moeda(r.valor)}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </li>
            ))}
            {extrato.destinos.length === 0 && <li className="text-sm text-gray-500">Repartição não configurada.</li>}
          </ul>
        </div>
      )}
    </div>
  );
};

export default RifasPage;
