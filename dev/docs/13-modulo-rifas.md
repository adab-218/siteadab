# Módulo M7 — Rifas Beneficentes por Departamento

> **Status:** planejamento — aguardando decisões Dx antes de implementar.
> Modelo de referência: módulo de sorteios do projeto Conectados
> (`docs/03-modulos/MODULO_03_SORTEIOS.md`), adaptado à stack ADAB
> (PHP 8.3 + Slim 4 + MySQL + sessão/CSRF, HostGator compartilhado).

## 1. Objetivo

Cada **departamento** da igreja (Missões, Infantil, Mocidade etc.) pode
criar e gerir **rifas beneficentes** para arrecadar fundos. O público
compra/reserva números numa página pública `adab.teo.br/rifa/{slug}` e o
resultado é apurado com prova pública (seed + SHA-256) ou pela Loteria
Federal — transparente e auditável.

## 2. Escopo

### Dentro (MVP)

- CRUD de rifas vinculadas a um departamento (título, descrição,
  regulamento, valor do bilhete, quantidade de números, data do sorteio,
  imagem de capa via `POST /rifas/{id}/capa` reaproveitando `Api\Uploads`)
- Prêmios por rifa (título, descrição, valor estimado, imagem, ordem)
- Ciclo de vida: `rascunho → ativo → encerrado → sorteado` (+ `cancelado`)
- **Ativação só com ≥1 prêmio e regulamento preenchido** — distribuição
  de prêmios exige regulamento público (Lei 5.768/1971, Decreto
  70.951/1972); rifa beneficente de igreja precisa do mesmo cuidado
- Página pública `/rifa/{slug}`: capa, prêmios, form de participação
  (nome + telefone), consulta de bilhetes por telefone, resultado e prova
- Bilhetes numerados sequenciais por rifa; reserva `pendente` até a
  secretaria/líder marcar como `pago` (PIX manual — HostGator não roda
  gateway de pagamento; cobrança via link `wa.me` com copia-e-cola PIX)
- Apuração **sistema** (seed aleatória + SHA-256 por prêmio, prova
  pública) e **federal** (organizador informa os números sorteados na
  Loteria Federal; últimos N dígitos casam com bilhetes elegíveis, com
  fallback determinístico) — mesmo algoritmo do Conectados
- Painel admin: lista de rifas por departamento, gestão de bilhetes
  (busca por nome/telefone/número, filtro por status de pagamento),
  botão de copiar link público e link de cobrança `wa.me`

### Fora (fases futuras)

- Pagamento online real (Mercado Pago/webhook) — HostGator compartilhado
  não suporta de forma confiável; manter PIX manual
- Cartões presenciais com QR Code + impressão A4 (fase 2)
- Chat ao vivo na página da rifa, player de live do YouTube (fase 2)
- Notificação automática de vencedores (SMS/WhatsApp API) — no MVP o
  responsável copia o resultado e envia manualmente
- Sorteio **gratuito** (leads) e **patrocinado por terceiros** — aqui só
  existe o tipo beneficente da igreja

## 3. Regras de negócio propostas

- **R1.** Toda rifa pertence a um `departamento_id` e é criada por
  admin/secretaria ou pelo **líder do departamento** (D5-scope: líder só
  gerencia rifas do seu depto).
- **R2.** Bilhete = `{rifa_id, numero}` sequencial; reserva pública exige
  `nome` + `telefone`; limite `max_por_pessoa` (padrão 10) e
  `total_numeros` fecham o universo do sorteio (ex.: 200 números).
- **R3.** Só bilhetes `pago` (ou `gratuito` — concessões internas) entram
  na apuração; `pendente` é reserva que pode expirar/liberar o número.
- **R4.** Após `data_sorteio` as reservas públicas são bloqueadas
  ("Vendas encerradas"), mesmo que o status ainda seja `ativo`.
- **R5.** Uma vez `sorteado`, a rifa é imutável (resultado público).
  `cancelado` exige motivo e libera números; bilhetes pagos ficam
  registrados para devolução manual.
- **R6.** Mesmo comprador não pode ganhar 2 prêmios da mesma rifa
  (bilhete vencedor sai do pool).
- **R7.** Resultado público exibe seed + SHA-256(seed) (sistema) ou os
  números da Federal informados — qualquer um pode auditar.

## 4. Modelo de dados (migration `0009_rifas.sql`)

```sql
rifas            id, departamento_id(FK), titulo, slug UNIQUE,
                 descricao TEXT, regulamento TEXT, capa_url,
                 valor_bilhete DECIMAL(10,2),
                 total_numeros INT, max_por_pessoa INT DEFAULT 10,
                 data_sorteio DATETIME,
                 metodo ENUM('sistema','federal') DEFAULT 'sistema',
                 status ENUM('rascunho','ativo','encerrado','sorteado',
                             'cancelado') DEFAULT 'rascunho',
                 seed, prova_hash, federal_numeros, cancelado_motivo,
                 created_by(FK usuarios), created_at, updated_at,
                 deleted_at
rifa_premios     id, rifa_id(FK), ordem, titulo, descricao,
                 valor_estimado DECIMAL(10,2), imagem_url,
                 bilhete_vencedor_id NULL, created_at
rifa_bilhetes    id, rifa_id(FK), numero INT, nome, telefone,
                 email NULL, status_pag ENUM('pendente','pago','gratuito',
                                            'falhou') DEFAULT 'pendente',
                 pago_em, pago_por(FK usuarios), created_at,
                 UNIQUE (rifa_id, numero),
                 INDEX (rifa_id, telefone)
```

View pública `v_rifas_publicas`: só `status IN ('ativo','encerrado',
'sorteado')`, sem dados de contato dos compradores.

## 5. Rotas (contrato — detalhar em `06-api`)

**Autenticadas** (`/api/rifas…`, escrita: admin/secretaria; líder só nas
do seu depto):
- `GET/POST /rifas`, `GET/PUT /rifas/{id}`, `DELETE` (soft, só rascunho)
- `POST /rifas/{id}/ativar` (valida prêmio+regulamento → 422)
- `POST /rifas/{id}/encerrar`, `/cancelar` `{motivo}`
- `POST /rifas/{id}/sortear` `{seed? | federal_numeros}` → prova
- `POST/PUT/DELETE /rifas/{id}/premios[/{pid}]`
- `GET /rifas/{id}/bilhetes?q=&status_pag=` , `POST` (venda manual/balcão),
  `PATCH /rifas/{id}/bilhetes/{bid}/pagar`, `/estornar`
- `POST/DELETE /rifas/{id}/capa`, `POST/DELETE
  /rifas/{id}/premios/{pid}/imagem` (multipart `arquivo`, ≤2MB)

**Públicas** (`/api/public/rifas…`, sem auth):
- `GET /public/rifas` — rifas ativas (lista no site)
- `GET /public/rifas/{slug}` — rifa + prêmios + contadores + resultado
- `POST /public/rifas/{slug}/reservar` `{nome, telefone, quantidade}` →
  aloca números (transação + lock na rifa) — respeita R2/R4, anti-flood
  por IP/telefone (janela na tabela)
- `GET /public/rifas/{slug}/bilhetes?telefone=` — consulta próprios
  bilhetes + se é vencedor

## 6. Frontend

- `RifasPage` admin: lista por depto, modal criar/editar (nome, valor,
  total de números, data, método, regulamento, capa `ImagemUpload`),
  detalhe com abas **Prêmios / Bilhetes / Resultado**; ações de ciclo de
  vida com confirmação; botão "Sortear" com input de seed opcional ou
  números da Federal
- Rota pública SPA `/rifa/:slug` (sem AdminLayout): hero com capa,
  prêmios, form de reserva (máscara telefone já existe em
  `lib/mascaras.ts`), "Meus bilhetes" por telefone, seção Resultado com
  prova SHA-256
- Item no menu do site público ("Rifas") quando houver rifa ativa —
  via `GET /public/rifas`

## 7. Algoritmo de apuração (portado do Conectados)

**sistema:** `seed = seed_info ? : bin2hex(random_bytes(32))`; para cada
prêmio i: `h = hash('sha256', "$seed:$premioId:$i")`; índice =
`hexdec(substr($h,0,15)) % total_elegiveis`; vencedor sai do pool;
`prova_hash = hash('sha256', $seed)`.

**federal:** `federal_numeros` informados na ordem dos prêmios; extrai os
últimos N dígitos (N = dígitos de `total_numeros`); percorre a lista até
cair em bilhete elegível; fallback `(ultimo + posicao) % elegiveis`;
seed registrada `federal:{numeros}`.

Ambos em `RifaRepository::sortear()` dentro de transação; testes de
integração cobrem determinismo (mesma seed → mesmo vencedor) e regras de
pool.

## 8. Decisões pendentes (responder antes de codar)

- **D-R1.** Confirmação de pagamento manual basta (PIX direto na conta
  da igreja/depto)? *Sem gateway — presunção: sim.*
- **D-R2.** Quem marca "pago": só admin/secretaria, ou líder do depto
  também? *Presunção: líder também (D5).*
- **D-R3.** Reserva sem pagamento em X horas libera o número?
  *Presunção: manual, sem expiração automática no MVP.*
- **D-R4.** Rifa gratuita (arrecadação zero, só participação) entra no
  MVP? *Presunção: não.*
- **D-R5.** Bilhete pode ser escolhido pelo comprador (número da sorte)
  ou só atribuição sequencial? *Presunção: sequencial no MVP.*
- **D-R6.** Precisa de PIX copia-e-cola/chave exibida na página de
  reserva? *Presunção: sim, texto configurável por rifa
  (`pix_instrucoes`).*

## 9. Riscos

- **LGPD:** telefone/nome de público externo — consulta por telefone
  expõe bilhetes de qualquer um que saiba o número; mitigar mostrando só
  números+status (sem nome completo) e limitar tentativas.
- **Legal:** rifa beneficente por igreja é permitida, mas o regulamento
  público é obrigatório — bloqueio na ativação é obrigatório, não
  opcional.
- **Anti-fraude:** reservas públicas em massa — rate-limit simples por
  IP + telefone + limite por pessoa.
- **Concorrência:** dois compradores pegando o mesmo número —
  `UNIQUE(rifa_id,numero)` + transação com `SELECT ... FOR UPDATE` no
  contador.

## 10. Plano incremental

1. **M7.1** Migration + `RifaRepository` + CRUD admin + ciclo de vida +
   testes de integração
2. **M7.2** Prêmios + capa/imagens (reuso de `Api\Uploads`)
3. **M7.3** Reserva pública + consulta de bilhetes + página `/rifa/:slug`
4. **M7.4** Painel de bilhetes (busca, pagar/estornar, link wa.me)
5. **M7.5** Sortear (sistema + federal) + resultado público + prova
6. **M7.6** Docs, diagrama `rifas.mmd`, checklist, QA em prod

## 11. Critérios de aceite

- Líder de depto só edita rifas do seu depto (teste de autorização 403)
- Ativação sem prêmio ou sem regulamento → 422 com mensagem clara
- Reserva pública: 2 requests simultâneos nunca pegam o mesmo número
- Sorteio com seed fixa reproduz o mesmo resultado (teste)
- Bilhete `pendente` nunca vence; vencedor só entre `pago`/`gratuito`
- Resultado público mostra prova e (federal) os números informados
- Página pública responsiva, pt-BR, máscara de telefone aplicada
