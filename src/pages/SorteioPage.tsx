import React, { useCallback, useEffect, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { Calendar, CheckCircle2, Copy, Gift, Loader2, Search, Ticket, Trophy } from 'lucide-react';
import { ApiError, sorteioPublicoApi, SorteioPublico } from '../lib/api';
import { mascaraTelefone } from '../lib/mascaras';
import Field from '../components/ui/Field';

const moeda = (v: number) =>
  v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const formatarDataHora = (iso: string | null): string => {
  if (!iso) return 'a definir';
  const d = new Date(iso.replace(' ', 'T'));
  return `${d.toLocaleDateString('pt-BR')} às ${d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;
};

const STATUS_PAG_LABEL: Record<string, { label: string; classes: string }> = {
  pendente: { label: 'Reservado — pague em até 24h', classes: 'bg-amber-100 text-amber-800' },
  pago: { label: 'Pago', classes: 'bg-green-100 text-green-800' },
  gratuito: { label: 'Pago', classes: 'bg-green-100 text-green-800' },
};

interface MeuBilhete {
  numero: number;
  status_pag: string;
  vencedor: number;
}

/**
 * Página pública do sorteio beneficente (M7, doc 13) — sem login.
 * /sorteio/:slug — reserva com ?v={token} (link de divulgação),
 * consulta "meus números" e resultado com prova de transparência.
 */
const SorteioPage: React.FC = () => {
  const { slug = '' } = useParams();
  const [params] = useSearchParams();
  const tokenDivulgador = params.get('v') ?? undefined;

  const [sorteio, setSorteio] = useState<SorteioPublico | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');

  // reserva
  const [form, setForm] = useState({ nome: '', telefone: '', email: '', quantidade: 1 });
  const [website, setWebsite] = useState(''); // honeypot
  const [enviando, setEnviando] = useState(false);
  const [erroForm, setErroForm] = useState('');
  const [reserva, setReserva] = useState<{ numeros: number[]; total: number } | null>(null);

  // consulta
  const [telConsulta, setTelConsulta] = useState('');
  const [consultando, setConsultando] = useState(false);
  const [meus, setMeus] = useState<MeuBilhete[] | null>(null);
  const [copiou, setCopiou] = useState(false);

  const carregar = useCallback(async () => {
    setCarregando(true);
    setErro('');
    try {
      setSorteio(await sorteioPublicoApi.ver(slug));
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Sorteio não encontrado.');
    } finally {
      setCarregando(false);
    }
  }, [slug]);

  useEffect(() => {
    carregar();
  }, [carregar]);

  const vendasAbertas =
    sorteio?.status === 'ativo' &&
    (!sorteio.data_sorteio || new Date(sorteio.data_sorteio.replace(' ', 'T')) > new Date());

  const reservar = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!sorteio) return;
    setEnviando(true);
    setErroForm('');
    try {
      const r = await sorteioPublicoApi.reservar(sorteio.slug, {
        nome: form.nome,
        telefone: form.telefone,
        email: form.email || undefined,
        quantidade: form.quantidade,
        v: tokenDivulgador,
        website,
      });
      setReserva(r);
      setForm({ nome: '', telefone: '', email: '', quantidade: 1 });
      carregar();
    } catch (e) {
      setErroForm(e instanceof ApiError ? e.message : 'Não foi possível reservar. Tente novamente.');
    } finally {
      setEnviando(false);
    }
  };

  const consultar = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!sorteio) return;
    setConsultando(true);
    setMeus(null);
    try {
      setMeus(await sorteioPublicoApi.meusBilhetes(sorteio.slug, telConsulta));
    } catch (e) {
      setErro(e instanceof ApiError ? e.message : 'Erro na consulta.');
    } finally {
      setConsultando(false);
    }
  };

  const copiarPix = async () => {
    if (!sorteio?.pix_instrucoes) return;
    await navigator.clipboard.writeText(sorteio.pix_instrucoes);
    setCopiou(true);
    setTimeout(() => setCopiou(false), 2000);
  };

  if (carregando) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <Loader2 className="animate-spin text-red-700" size={40} />
      </div>
    );
  }

  if (erro || !sorteio) {
    return (
      <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center gap-4 p-6 text-center">
        <p className="text-gray-700">{erro || 'Sorteio não encontrado.'}</p>
        <Link to="/" className="text-red-700 hover:underline">Voltar ao site</Link>
      </div>
    );
  }

  const sorteado = sorteio.status === 'sorteado';
  const vencedores = sorteado && sorteio.premios
    ? sorteio.premios.filter((p) => p.numero_vencedor !== null)
    : [];

  return (
    <div className="min-h-screen bg-gray-50">
      {/* capa */}
      <header className="relative">
        {sorteio.capa_url ? (
          <img src={sorteio.capa_url} alt="" className="w-full h-44 sm:h-56 object-cover" />
        ) : (
          <div className="w-full h-44 sm:h-56 bg-gradient-to-r from-red-700 to-red-900" />
        )}
        <div className="absolute inset-0 bg-black/40 flex items-end">
          <div className="max-w-3xl mx-auto w-full px-4 pb-6">
            <Link to="/" className="text-white/80 text-xs hover:underline">ADAB · Templo Arthur Bernardes</Link>
            <h1 className="text-2xl sm:text-3xl font-bold text-white">{sorteio.titulo}</h1>
            <p className="text-white/90 text-sm mt-1 flex items-center gap-2">
              <Calendar size={14} aria-hidden="true" />
              Sorteio: {formatarDataHora(sorteio.data_sorteio)}
            </p>
          </div>
        </div>
      </header>

      <main className="max-w-3xl mx-auto px-4 py-8 space-y-8">
        {/* estatísticas */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { label: 'Número', valor: moeda(Number(sorteio.valor_numero)) },
            { label: 'Números reservados', valor: `${sorteio.numeros_reservados}/${sorteio.total_numeros}` },
            { label: 'Prêmios', valor: String(sorteio.total_premios) },
            { label: 'Máx. por pessoa', valor: String(sorteio.max_por_pessoa) },
          ].map((s) => (
            <div key={s.label} className="bg-white rounded-lg border p-3 text-center">
              <p className="text-xs text-gray-500">{s.label}</p>
              <p className="font-bold text-gray-800">{s.valor}</p>
            </div>
          ))}
        </div>

        {sorteio.descricao && (
          <section className="bg-white rounded-lg border p-5">
            <h2 className="font-semibold text-gray-800 mb-2">Sobre este sorteio</h2>
            <p className="text-gray-600 whitespace-pre-line text-sm">{sorteio.descricao}</p>
          </section>
        )}

        {/* prêmios */}
        {sorteio.premios && sorteio.premios.length > 0 && (
          <section>
            <h2 className="font-semibold text-gray-800 mb-3 flex items-center gap-2">
              <Gift size={18} className="text-red-700" aria-hidden="true" /> Prêmios
            </h2>
            <div className="grid sm:grid-cols-2 gap-3">
              {sorteio.premios.map((p) => (
                <div key={p.ordem} className="bg-white rounded-lg border p-4 flex gap-3">
                  {p.imagem_url && (
                    <img src={p.imagem_url} alt="" className="w-16 h-16 rounded object-cover" />
                  )}
                  <div className="min-w-0">
                    <p className="font-medium text-gray-800">{p.ordem}º — {p.titulo}</p>
                    <p className="text-xs text-gray-500">Doado por: {p.departamento_nome}</p>
                    {p.valor_estimado !== null && (
                      <p className="text-sm text-green-700 font-medium">{moeda(Number(p.valor_estimado))}</p>
                    )}
                    {p.numero_vencedor !== null && (
                      <p className="mt-1 inline-flex items-center gap-1 text-sm font-bold text-amber-700 bg-amber-100 rounded px-2 py-0.5">
                        <Trophy size={14} aria-hidden="true" /> Vencedor: #{p.numero_vencedor}
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </section>
        )}

        {/* resultado / prova */}
        {sorteado && (
          <section className="bg-amber-50 border border-amber-200 rounded-lg p-5">
            <h2 className="font-semibold text-amber-900 mb-2 flex items-center gap-2">
              <Trophy size={18} aria-hidden="true" /> Resultado oficial
            </h2>
            <ul className="text-sm text-amber-900 space-y-1 mb-3">
              {vencedores.map((p) => (
                <li key={p.ordem}>{p.ordem}º prêmio ({p.titulo}): número <strong>#{p.numero_vencedor}</strong></li>
              ))}
            </ul>
            <div className="text-xs text-amber-800 space-y-1">
              {sorteio.metodo === 'federal' && sorteio.federal_numeros && (
                <p>Números da Loteria Federal: <strong>{sorteio.federal_numeros}</strong></p>
              )}
              {sorteio.prova_hash && (
                <p className="break-all">Prova do sorteio (SHA-256): <code>{sorteio.prova_hash}</code></p>
              )}
            </div>
          </section>
        )}

        {/* reserva */}
        {!sorteado && (
          <section className="bg-white rounded-lg border p-5">
            <h2 className="font-semibold text-gray-800 mb-1 flex items-center gap-2">
              <Ticket size={18} className="text-red-700" aria-hidden="true" /> Reserve seus números
            </h2>
            {!vendasAbertas ? (
              <p className="text-sm font-medium text-gray-700 bg-gray-100 rounded-lg px-3 py-2">
                Vendas encerradas para este sorteio.
              </p>
            ) : reserva ? (
              <div className="text-center space-y-3">
                <CheckCircle2 className="mx-auto text-green-600" size={40} aria-hidden="true" />
                <p className="font-semibold text-gray-800">Reserva feita!</p>
                <p className="text-sm text-gray-600">
                  Seus números: {reserva.numeros.map((n) => `#${n}`).join(', ')}
                </p>
                <p className="text-lg font-bold text-red-700">Total: {moeda(reserva.total)}</p>
                {sorteio.pix_instrucoes && (
                  <div className="bg-gray-50 border rounded-lg p-3 text-sm">
                    <p className="text-gray-600 mb-1">Pague via PIX e envie o comprovante. Sua reserva vale 24h.</p>
                    <p className="break-all font-mono text-gray-800">{sorteio.pix_instrucoes}</p>
                    <button
                      onClick={copiarPix}
                      className="mt-2 inline-flex items-center gap-1 text-red-700 font-medium text-sm"
                    >
                      <Copy size={14} aria-hidden="true" /> {copiou ? 'Copiado!' : 'Copiar'}
                    </button>
                  </div>
                )}
                <button onClick={() => setReserva(null)} className="text-sm text-gray-500 hover:underline">
                  Reservar mais números
                </button>
              </div>
            ) : (
              <form onSubmit={reservar} className="space-y-3">
                <Field
                  id="rifa-nome" label="Nome completo" required
                  value={form.nome}
                  onChange={(e) => setForm({ ...form, nome: e.target.value })}
                />
                <div className="grid sm:grid-cols-2 gap-3">
                  <Field
                    id="rifa-tel" label="Telefone (WhatsApp)" required
                    type="tel" inputMode="tel" maxLength={15} placeholder="(91) 98765-4321"
                    value={form.telefone}
                    onChange={(e) => setForm({ ...form, telefone: mascaraTelefone(e.target.value) })}
                  />
                  <Field
                    id="rifa-email" label="E-mail (opcional)"
                    type="email" value={form.email}
                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                  />
                </div>
                <Field
                  id="rifa-qtd" label="Quantidade de números" required
                  type="number" min={1} max={sorteio.max_por_pessoa}
                  value={form.quantidade}
                  onChange={(e) => setForm({ ...form, quantidade: Number(e.target.value) })}
                />
                <input
                  type="text" value={website} onChange={(e) => setWebsite(e.target.value)}
                  className="hidden" tabIndex={-1} autoComplete="off" aria-hidden="true"
                />
                {erroForm && <p role="alert" className="text-sm text-red-700">{erroForm}</p>}
                <button
                  type="submit" disabled={enviando}
                  className="w-full bg-red-700 text-white font-semibold rounded-lg py-2.5 hover:bg-red-800 disabled:opacity-60 transition-colors flex items-center justify-center gap-2"
                >
                  {enviando && <Loader2 size={16} className="animate-spin" aria-hidden="true" />}
                  Reservar — {moeda(form.quantidade * Number(sorteio.valor_numero))}
                </button>
                <p className="text-xs text-gray-500 text-center">
                  Números atribuídos automaticamente. Reserva válida por 24h aguardando o PIX.
                </p>
              </form>
            )}
          </section>
        )}

        {/* meus números */}
        <section className="bg-white rounded-lg border p-5">
          <h2 className="font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <Search size={18} className="text-red-700" aria-hidden="true" /> Meus números
          </h2>
          <form onSubmit={consultar} className="flex gap-2">
            <Field
              id="rifa-consulta" label="Seu telefone" required
              type="tel" inputMode="tel" maxLength={15} placeholder="(91) 98765-4321"
              value={telConsulta}
              onChange={(e) => setTelConsulta(mascaraTelefone(e.target.value))}
            />
            <button
              type="submit" disabled={consultando}
              className="self-end bg-red-700 text-white rounded-lg px-4 py-2 text-sm font-semibold hover:bg-red-800 disabled:opacity-60"
            >
              {consultando ? <Loader2 size={16} className="animate-spin" aria-hidden="true" /> : 'Consultar'}
            </button>
          </form>
          {meus && (
            <ul className="mt-3 space-y-1">
              {meus.length === 0 && <li className="text-sm text-gray-500">Nenhum número encontrado para este telefone.</li>}
              {meus.map((b) => {
                const meta = STATUS_PAG_LABEL[b.status_pag] ?? { label: b.status_pag, classes: 'bg-gray-100 text-gray-700' };
                return (
                  <li key={b.numero} className="flex items-center justify-between text-sm border rounded-lg px-3 py-2">
                    <span className="font-mono font-semibold">#{b.numero}</span>
                    <span className="flex items-center gap-2">
                      {b.vencedor > 0 && <Trophy size={14} className="text-amber-600" aria-label="Vencedor" />}
                      <span className={`text-xs rounded-full px-2 py-0.5 ${meta.classes}`}>{meta.label}</span>
                    </span>
                  </li>
                );
              })}
            </ul>
          )}
        </section>
      </main>
    </div>
  );
};

export default SorteioPage;
