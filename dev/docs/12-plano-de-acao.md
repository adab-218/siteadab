# 12 — Plano de Ação

Checklist de implementação (stack: React SPA + PHP/Slim + MySQL na
Hostgator — ver ADR 0002). Ordem sugerida; cada item = uma PR candidata.

## Fase 0 — Fundação e débitos técnicos

- [x] Confirmar versão do PHP no cPanel — **PHP 8.3 (ea-php83)**;
      SSL (AutoSSL) ativo para adab.teo.br ✅
- [x] GitHub Secrets criados: `FTP_SERVER`, `FTP_USERNAME`,
      `FTP_PASSWORD`, `DB_PASSWORD`, `SESSION_SECRET`, `MIGRATE_TOKEN`
- [x] Deploy em produção funcionando (FTPS → adab.teo.br + migrate)
- [x] **Revisão completa da equipe de agentes (10 pareceres)** —
      correções consolidadas em `dev/docs/CHECKLIST.md`
- [x] Corrigir lint: `typescript-eslint` atualizado para ^8.46 (compatível com eslint 9.34)
- [x] Corrigir `z-60` inválido (`z-[60]`) e bug de data UTC em `WeeklySchedule`
- [x] `lang="pt-BR"` + meta description no `index.html` (og tags pendente)
- [x] Remover imports não usados (`useEffect`, `Phone`, `Play`, `Clock`, `Users`)
- [x] `tsc -b` no script `build`; ano dinâmico no footer
- [x] Renomear `package.json` → `adab-gestao-templo`
- [x] `.env.example` e `api/config.example.php`; `.env*`, `api/config.php`,
      `api/vendor/`, `uploads/` no `.gitignore`
- [x] GitHub Actions: `ci.yml` (lint → tsc → vitest → PHPUnit → build)
      e `deploy.yml` (build → composer --no-dev → config.php via secrets →
      FTPS → migrate) — doc 11
- [ ] Instalar deps front: `react-router-dom`, `@tanstack/react-query`,
      `react-hook-form`, `zod`, `@hookform/resolvers`, shadcn/ui, `sonner`,
      `date-fns`
- [x] `api/composer.json` com `slim/slim`, `slim/psr7`, `phpmailer/phpmailer`;
      PSR-4 `Adab\Api\` → `api/src/`
- [ ] Reestruturar `src/` em `app/` + `admin/` + `lib/` + `schemas/`
      (`src/lib/api.ts` já criado)
- [x] `.htaccess` raiz (HTTPS + /api + SPA fallback + headers),
      `api/.htaccess` (nega tudo exceto index.php) e `uploads/.htaccess`
      (bloqueia execução de scripts)

## Fase 1 — Banco de dados e autenticação

- [x] Migration `0001`: `pessoas`, `cargos`, `departamentos`,
      `membros`, `membro_departamentos`, `usuarios`
- [x] Migration `0002`: `tipos_culto`, `cultos`, `culto_presencas`
- [x] Migration `0003`: `eventos`, `evento_atividades`
- [x] Migration `0004`: `acoes`, `acao_voluntarios`, `acao_beneficiarios`
- [x] Migration `0005`: `ebd_classes`, `ebd_professores`, `ebd_alunos`,
      `ebd_periodos`, `ebd_aulas`, `ebd_frequencias`
- [x] Migration `0006`: views (`v_programacao_publica`,
      `v_ebd_frequencia_resumo`, `v_aniversariantes_mes`,
      `v_membros_ativos`) + `audit_log` + `login_tentativas`
- [x] `api/migrate.php` (CLI) + `POST /api/admin/migrate` (token/admin)
      — runner `Migrator` com tabela de controle
- [x] `api/seed.sql` — dados de dev (usuários senha `password`,
      membros, classes, período EBD)
- [x] Auth: `POST /auth/login` (sessão + cookie httpOnly SameSite),
      logout, `/auth/me`, rate limit de login (5/15min), CSRF token
- [x] Recuperação de senha (`/auth/recuperar` + `/auth/redefinir`,
      PHPMailer/SMTP — requer `smtp` no config)
- [x] Middlewares: `AuthMiddleware`, `PerfilMiddleware`, `CsrfMiddleware`
- [ ] Escopo de dados nos repositórios (implementar junto dos módulos)
- [ ] Frontend: `/login`, guard `/admin`, contexto de sessão,
      gestão de usuários (admin)
- [ ] Testes de autorização negativos para cada perfil (doc 10)

## Fase 2 — Módulo Membros

- [ ] Repositories + Services + rotas `/api/membros` (+ pessoas, cargos,
      departamentos)
- [ ] Lista de membros: busca, filtros, paginação
- [ ] Cadastro (wizard: pessoais → eclesiásticos → foto) + ficha
- [ ] Upload de foto → `uploads/membros/` (validar tipo/tamanho)
- [ ] Vínculo com departamentos; alteração de status (soft delete)
- [ ] Campo `consentimento_lgpd_em` + termo no cadastro
- [ ] Aniversariantes do mês; exportar CSV; importar CSV com preview

## Fase 3 — Módulo Cultos

- [ ] CRUD tipos de culto; registro de culto (dirigente, pregador,
      tema, totais)
- [ ] Presença nominal (opcional) + relatório por período

## Fase 4 — Festividades e Eventos

- [ ] CRUD eventos + banner (`uploads/eventos/`)
- [ ] Programação interna por evento; flag `publico`; calendário

## Fase 5 — Ações

- [ ] CRUD ações + voluntários/departamento + beneficiários/resultado

## Fase 6 — EBD

- [ ] CRUD classes + vínculo de professores
- [ ] Períodos letivos + geração das 13 aulas do trimestre
- [ ] Matrícula de alunos (busca pessoa, transferência, desistência)
- [ ] `GET/PUT /ebd/aulas/{id}/chamada` — presença, Bíblia, revista,
      oferta, visitante
- [ ] Tela de chamada mobile
- [ ] Relatórios: frequência por classe/período, faltosos consecutivos

## Fase 7 — Dashboard e site público

- [ ] Dashboard: KPIs (membros ativos, frequência EBD, eventos,
      aniversariantes)
- [ ] `GET /api/public/programacao` alimentando o site (substitui
      programação hardcoded e CRUD em memória de `WeeklySchedule`)
- [ ] Seção de festividades dinâmica; lazy loading de imagens/iframes

## Fase 8 — Qualidade e go-live

- [ ] Cobertura ≥ 70% nas camadas de negócio; e2e dos 5 fluxos críticos
- [ ] Headers de segurança + CSP; revisão final `.htaccess`
- [ ] LGPD: termo de consentimento, fluxo de direitos do titular
- [ ] `npm audit fix` + `composer audit` + dependabot
- [ ] Cron de backup do banco + teste de restore
- [ ] Deploy produção + treinamento da secretaria/EBD
- [ ] Migração de dados existentes (se houver)

## Fase 9 — M7 Rifas Beneficentes (planejado — doc 13)

- [ ] Migration `0009`: `rifas`, `rifa_premios`, `rifa_bilhetes` +
      `v_rifas_publicas`
- [ ] CRUD admin + ciclo `rascunho→ativo→encerrado→sorteado` (ativação
      exige ≥1 prêmio + regulamento — Lei 5.768/1971)
- [ ] Reserva pública `/rifa/{slug}` (nome+telefone, números sequenciais,
      anti-duplicidade transacional) + consulta por telefone
- [ ] Apuração `sistema` (seed+SHA-256 prova) e `federal` — mesma
      regra do Conectados
- [ ] Painel de bilhetes (pagar/estornar manual, link wa.me de cobrança)

## Backlog futuro

- [ ] PWA/offline para chamada EBD
- [ ] Módulo financeiro (dízimos/ofertas)
- [ ] Comunicação (e-mail/WhatsApp)
- [ ] Relatórios PDF avançados; Sentry
- [ ] Rifas fase 2: cartões presenciais com QR, chat/live na página,
      pagamento online (gateway)
