# Módulo M7 — Sorteios Beneficentes (multiprêmios, por departamentos)

> **Status:** planejamento — aguardando decisões Dx antes de implementar.
> Modelo de referência: módulo de sorteios do projeto Conectados
> (`conectados/docs/03-modulos/MODULO_03_SORTEIOS.md`), adaptado à stack
> ADAB (PHP 8.3 + Slim 4 + MySQL + sessão/CSRF, HostGator compartilhado)
> e ao cenário da igreja abaixo.

## 1. Cenário-alvo (requisito do usuário)

Um **sorteio especial multiprêmios** com dois propósitos: reforma do
templo e caixa dos departamentos.

- Cada **departamento doa um prêmio** (valor estimado R$ 100–200).
- Cada **membro de departamento** recebe um **link exclusivo de
  divulgação** — as vendas feitas por aquele link contam para o membro
  e para o departamento dele (ranking e repasse).
- O sorteio define **repartição percentual** da arrecadação por
  propósito. Ex.: Construção do templo 60% / Departamentos 30% /
  Escola Bíblica 10%.
- Página pública de compra, apuração auditável (seed + SHA-256 ou
  Loteria Federal).

O modelo fica genérico o suficiente para rifas futuras de um depto só,
mas o desenho nasce do cenário multiprêmios acima.

## 2. Escopo

### Dentro (MVP)

- CRUD de sorteios: título, descrição, regulamento, valor do número,
  total de números, data do sorteio, capa (reuso `Api\Uploads`),
  método de apuração (`sistema`|`federal`)
- **Prêmios por departamento**: cada prêmio registra o depto doador +
  valor estimado (R$ 100–200) + imagem
- **Links de divulgação por membro**: token único por
  `(sorteio, membro)`; reserva feita via `?v={token}` atribui a venda ao
  membro e ao departamento dele; venda sem token fica "sem divulgador"
- **Repartição percentual**: lista `{destino, percentual}` por sorteio;
  destino pode ser livre ("Construção do templo", "EBD") ou
  "departamentos" com rateio proporcional às vendas de cada depto;
  ativação exige Σ = 100%
- Ciclo de vida `rascunho → ativo → encerrado → sorteado` (+cancelado);
  ativação exige ≥1 prêmio, regulamento e repartição fechada em 100%
- Página pública `/sorteio/{slug}`: capa, propósito, prêmios (com depto
  doador), form de reserva (nome+telefone+qtde, link ?v= preservado),
  "meus números" por telefone, resultado + prova pública
- Painel admin: bilhetes (busca, pagar/estornar manual — PIX), ranking
  de vendas por membro e por departamento, **extrato de repartição**
  (arrecadado × % → valores por destino e por depto)
- PIX copia-e-cola/chave exibida na confirmação da reserva (texto
  configurável por sorteio)

### Fora (fases futuras)

- Pagamento online real (gateway) — PIX manual
- Cartões presenciais com QR + impressão A4; chat/live na página
- Notificações automáticas (WhatsApp API); área do divulgador com
  autoatendimento das vendas dele
- Múltiplos sorteios simultâneos no ar — modelo suporta, UX valida 1 ativo

## 3. Regras de negócio

- **R1.** Sorteio criado por admin/secretaria; **líder de departamento**
  pode: gerar/regenerar links dos membros do SEU depto, ver ranking do
  depto e marcar pagos nos bilhetes vendidos pelo seu depto (escopo D5).
- **R2.** Número = sequencial por sorteio (`UNIQUE(rifa_id,numero)`);
  reserva exige nome+telefone; `max_por_pessoa` (padrão 50) e
  `total_numeros` fecham o universo.
- **R3.** Só `pago`/`gratuito` participam da apuração; `pendente` é
  reserva aguardando PIX manual.
- **R4.** Após `data_sorteio`, reservas públicas bloqueiam
  ("Vendas encerradas").
- **R5.** `sorteado` é imutável; `cancelado` exige motivo e registra
  bilhetes pagos para devolução manual.
- **R6.** Um mesmo bilhete não ganha 2 prêmios; comprador pode ganhar
  prêmios diferentes com bilhetes diferentes? **Decisão D-R7.**
- **R7.** Resultado público exibe seed + SHA-256 (sistema) ou os
  números da Federal informados — auditável por qualquer pessoa.
- **R8.** Link de divulgação atribui a venda no momento da RESERVA; se o
  mesmo telefone comprar por 2 links, cada bilhete fica com o
  divulgador do link usado.
- **R9.** Repartição: soma dos percentuais deve ser 100% para ativar;
  destino `departamentos` rateia proporcionalmente às vendas confirmadas
  de cada depto participante **(D-R8: ou igualmente entre eles)**.
- **R10.** Arrecadação de referência = Σ bilhetes `pago`+`gratuito`? —
  **não**: somente `pago` (gratuito é concessão, não gera caixa).
  O extrato mostra bruto (todos), pago e pendente.

## 4. Modelo de dados (migration `0009_rifas.sql`)

```sql
rifas               id, titulo, slug UNIQUE, descricao, regulamento,
                    capa_url, valor_numero DECIMAL(10,2) NOT NULL,
                    total_numeros INT NOT NULL,
                    max_por_pessoa INT DEFAULT 50,
                    data_sorteio DATETIME,
                    metodo ENUM('sistema','federal') DEFAULT 'sistema',
                    status ENUM('rascunho','ativo','encerrado',
                                'sorteado','cancelado')
                                DEFAULT 'rascunho',
                    pix_instrucoes TEXT,      -- chave/instrução PIX públicas
                    seed, prova_hash, federal_numeros, cancelado_motivo,
                    created_by(FK usuarios), created_at, updated_at,
                    deleted_at

rifa_premios        id, rifa_id(FK), ordem, titulo, descricao,
                    valor_estimado DECIMAL(10,2), imagem_url,
                    departamento_id(FK departamentos) NOT NULL,
                    bilhete_vencedor_id NULL, created_at

rifa_reparticao     id, rifa_id(FK), destino VARCHAR(120),
                    tipo ENUM('livre','departamentos') DEFAULT 'livre',
                    percentual DECIMAL(5,2) NOT NULL

rifa_divulgadores   id, rifa_id(FK), pessoa_id(FK membros.pessoa_id),
                    token CHAR(32) UNIQUE, ativo TINYINT DEFAULT 1,
                    UNIQUE (rifa_id, pessoa_id), created_at

rifa_bilhetes       id, rifa_id(FK), numero INT, nome, telefone,
                    email NULL,
                    status_pag ENUM('pendente','pago','gratuito',
                                    'falhou') DEFAULT 'pendente',
                    divulgador_id NULL (FK rifa_divulgadores),
                    pago_em, pago_por(FK usuarios), created_at,
                    UNIQUE (rifa_id, numero),
                    INDEX (rifa_id, telefone),
                    INDEX (rifa_id, divulgador_id)
```

Views: `v_rifas_publicas` (status ativo/encerrado/sorteado — sem dados de
contato) e `v_rifa_vendas` (totais por sorteio/membro/depto p/ ranking).

## 5. Rotas (contrato — detalhar em `06-api`)

**Autenticadas** (`/api/rifas…`; escrita admin/secretaria; líder escopado
ao próprio depto onde marcado):
- `GET/POST /rifas`, `GET/PUT /rifas/{id}`, `DELETE` (soft, só rascunho)
- `POST /rifas/{id}/ativar|encerrar|cancelar|sortear` `{seed?|federal_numeros}`
- `POST/PUT/DELETE /rifas/{id}/premios[/{pid}]` — prêmio exige `departamento_id`
- `PUT /rifas/{id}/reparticao` `[{destino,tipo,percentual}…]` (replace-all, valida Σ=100)
- `GET/POST /rifas/{id}/divulgadores` `{pessoa_id}` — só membro ativo de
  depto que doa prêmio **(D-R9: ou qualquer membro? quem divulga?)**;
  `DELETE /rifas/{id}/divulgadores/{did}` desativa
- `GET /rifas/{id}/bilhetes?q=&status_pag=&depto=&divulgador=`,
  `POST` (venda balcão), `PATCH .../{bid}/pagar`, `/estornar`
- `GET /rifas/{id}/ranking` — vendas por membro e por depto
- `GET /rifas/{id}/reparticao-extrato` — arrecadado × %, rateio por depto
- `POST/DELETE /rifas/{id}/capa`, `premios/{pid}/imagem` (≤2MB)

**Públicas** (`/api/public/sorteios…`, sem auth):
- `GET /public/sorteios` — ativos (seção/menu do site)
- `GET /public/sorteios/{slug}` — sorteio+prêmios+contadores+resultado;
  aceita `?v={token}` no retorno para o frontend exibir "você está
  comprando pelo link de {nome do membro}" **(D-R10: mostrar ou não o
  nome do divulgador publicamente?)**
- `POST /public/sorteios/{slug}/reservar` `{nome, telefone, quantidade,
  v?}` — aloca N números em transação (`FOR UPDATE`), respeita R2/R4/R8,
  anti-flood por IP+telefone
- `GET /public/sorteios/{slug}/bilhetes?telefone=` — só números+status
  (sem nome — LGPD)

## 6. Frontend

- `RifasPage` admin (`/admin/rifas`): lista, modal criar/editar; detalhe
  com abas **Prêmios / Repartição / Divulgadores / Bilhetes / Ranking /
  Resultado**; ações do ciclo com confirmação; link público copiável;
  por divulgador, botão "copiar link" `/sorteio/{slug}?v={token}`
- Rota SPA pública `/sorteio/:slug` (sem AdminLayout): capa, propósito,
  prêmios (com depto), form com `quantidade`, exibe chave PIX após
  reserva, consulta "meus números", resultado + prova
- Preservar `?v=` do URL no POST de reserva; se token inválido/inativo →
  reserva segue sem divulgador (não bloqueia a venda)
- Máscara de telefone (`lib/mascaras.ts`) e `ImagemUpload` reutilizados

## 7. Algoritmo de apuração (portado do Conectados)

**sistema:** `seed = informada ? : bin2hex(random_bytes(32))`; por prêmio
i: `h = hash('sha256', "$seed:$premioId:$i")`, índice
`hexdec(substr($h,0,15)) % total_elegiveis`; vencedor sai do pool;
`prova_hash = sha256($seed)` público.

**federal:** `federal_numeros` na ordem dos prêmios; últimos N dígitos
(N = dígitos de `total_numeros`); percorre até bilhete elegível;
fallback `(ultimo+posicao) % elegiveis`; seed `federal:{numeros}`.

`RifaRepository::sortear()` em transação; testes cobrem determinismo e
pool (sem bilhete repetido vencedor, pendente nunca vence).

## 8. Decisões pendentes

- **D-R7.** Mesmo comprador pode ganhar prêmios distintos com bilhetes
  distintos? *Presunção: sim (multiprêmios incentiva comprar mais).*
- **D-R8.** Destino "Departamentos 30%": rateia proporcional às vendas
  do depto ou igualmente entre participantes? *Presunção: proporcional
  (incentiva a divulgação — era a ideia do link exclusivo).*
- **D-R9.** Quem pode ser divulgador: só membros de deptos doadores de
  prêmio, ou qualquer membro? *Presunção: qualquer membro ativo; o depto
  do divulgador recebe o crédito da venda.*
- **D-R10.** A página pública exibe o nome do divulgador no link
  ("comprando com Kátia")? *Presunção: sim, ajuda a confirmação de
  vendas — ocultável se preferir anonimato.*
- **D-R11.** Reserva pendente expira (libera número após Xh sem PIX)?
  *Presunção: não no MVP — liberação manual por estorno.*
- **D-R12.** Número escolhido pelo comprador ou só sequencial?
  *Presunção: sequencial no MVP.*

## 9. Riscos

- **LGPD:** consulta por telefone mostra só números+status; nomes de
  compradores e divulgadores só em área autenticada (divulgador aparece
  só como primeiro nome no link público, se D-R10=sim).
- **Legal:** regulamento público obrigatório p/ ativar (Lei 5.768/1971);
  incluir finalidade beneficente + repartição no texto.
- **Anti-fraude:** token de divulgador não confere nenhum benefício ao
  divulgador além de métrica — reservas falsas em massa ainda são risco;
  anti-flood IP+telefone + limite por pessoa.
- **Concorrência:** `UNIQUE(rifa_id,numero)` + `SELECT ... FOR UPDATE`
  no cursor de alocação.
- **Prestação de contas:** o extrato de repartição é o artefato de
  auditoria — gerar snapshot no `sorteado`/`encerrado`.

## 10. Plano incremental

1. **M7.1** Migration + `RifaRepository` + CRUD + ciclo de vida + repartição (Σ=100) + testes
2. **M7.2** Prêmios c/ depto doador + imagens (`Api\Uploads`)
3. **M7.3** Reserva pública (com `?v=` divulgador) + consulta + `/sorteio/:slug`
4. **M7.4** Bilhetes: busca, pagar/estornar, ranking por membro/depto, extrato de repartição
5. **M7.5** Sortear (sistema+federal) + resultado público + prova + snapshot de prestação de contas
6. **M7.6** Docs, diagrama `rifas.mmd`, checklist, QA em prod

## 11. Critérios de aceite

- Líder só vê/marca bilhetes e divulgadores do próprio depto (403 fora)
- Ativar sem prêmio, sem regulamento ou repartição ≠100% → 422 claro
- Reserva simultânea nunca duplica número; `?v=` inválido não bloqueia
- Ranking: venda via link conta 1 para o membro E para o depto dele
- Extrato: R$ X arrecadado → cada destino mostra X×% e, se
  departamentos, o valor por depto proporcional às vendas
- Seed fixa reproduz resultado; pendente nunca vence; prova pública ok
- Página pública responsiva, pt-BR, máscara de telefone, PIX exibido
