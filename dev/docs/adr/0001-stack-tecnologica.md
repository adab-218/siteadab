# ADR 0001 — Stack tecnológica

**Status**: Substituída por [ADR 0002](0002-stack-php-mysql-hostgator.md)
(hospedagem contratada é Hostgator compartilhada — PHP + MySQL only)
**Data**: 2026-10-19

## Contexto

O sistema atual é um site estático (Vite + React + TS + Tailwind) sem backend.
A refatoração exige persistência, autenticação, permissões por papel e
relatórios, mantendo baixo custo operacional para uma equipe pequena.

## Decisão

| Camada | Escolha | Versão alvo |
|--------|---------|-------------|
| Frontend | React + Vite + TypeScript strict | React 18, Vite 7, TS 5.5 |
| UI | Tailwind CSS 3 + shadcn/ui + lucide-react | — |
| Rotas | React Router | 6.x |
| Estado servidor | TanStack Query | 5.x |
| Formulários | react-hook-form + zod | — |
| Backend | **Supabase** (PostgreSQL + Auth + RLS + Storage + Edge Functions) | — |
| Testes | Vitest + Testing Library + Playwright (e2e) | — |
| CI/CD | GitHub Actions + Netlify (front) / Supabase Cloud | — |
| Node | 20+ LTS | — |

## Justificativa

- **Supabase** entrega banco relacional real (essencial para membros,
  matrículas e frequências), autenticação, autorização por RLS e storage de
  fotos/banners — sem escrever nem operar um servidor próprio.
- **Manter Vite + React** aproveita o código e o conhecimento atuais; o
  painel administrativo não precisa de SSR.
- **RLS no Postgres** centraliza a autorização: a API gerada não confia no
  cliente — decisivo para dados sensíveis (LGPD).

## Alternativas consideradas

| Alternativa | Prós | Contras | Veredito |
|-------------|------|---------|----------|
| NestJS + Prisma + Postgres | Controle total, testável | Escrever e hospedar API própria; mais código p/ mesma entrega | Rejeitada p/ equipe pequena |
| Firebase | Rápido de começar | NoSQL ruim p/ dados relacionais; RLS via rules menos maduras | Rejeitada |
| Next.js + Supabase | SSR/SEO no site público; um framework só | Migração maior; painel não precisa de SSR | Alternativa viável — reavaliar se SEO do site se tornar prioridade |
| PocketBase | Simples, self-hosted | Operação própria; menos maduro que Supabase | Rejeitada |

## Consequências

- (+) Menos código de backend; segurança por padrão com RLS.
- (+) Free tier do Supabase provavelmente suficiente para começar.
- (−) Lock-in parcial no SDK Supabase (mitigado: queries centralizadas em
  `src/lib/`, schema em SQL padrão portável).
- (−) SEO do site público segue limitado ao CSR (mitigável com
  pré-render/SSG futuro ou migração para Next.js).
