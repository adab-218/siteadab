# ADR 0002 — Stack: PHP + MySQL na Hostgator

**Status**: Aprovada (restrição de hospedagem do cliente)
**Data**: 2026-10-19
**Substitui**: [ADR 0001 — Supabase](0001-stack-tecnologica.md)

## Contexto

A hospedagem contratada é **Hostgator Plano M (compartilhada)**: somente
PHP + MySQL, acesso via FTP (FTPS explícita, porta 21) e cPanel. Sem Node
em runtime, sem PostgreSQL, sem SSH garantido. Domínio `adab.teo.br` já
aponta para os DNS da hospedagem.

## Decisão

| Camada | Escolha |
|--------|---------|
| Frontend | React + Vite + TS + Tailwind (mantém o código atual) — build estático servido pelo Apache |
| Backend | **PHP 8.x + Slim Framework 4** — API REST fina sobre PDO |
| Banco | **MySQL** (utf8mb4, InnoDB) — `sorte759_adab` |
| Auth | Sessão PHP (cookie httpOnly, SameSite) + `password_hash` |
| Storage de arquivos | `public_html/uploads/` (fora do git, preservado no deploy) |
| Deploy | GitHub Actions → build → **FTP-Deploy-Action** (FTPS :21) |
| CI/testes | GitHub Actions: lint, tsc, Vitest, PHPUnit, build |
| DB admin | phpMyAdmin via cPanel; migrations versionadas em `api/migrations/` |

### Por que Slim e não PHP puro?

Roteamento, middleware (auth, CSRF, JSON) e PSR-7 custam pouco
(o `vendor/` é gerado no CI com `composer install --no-dev` e enviado
por FTP) e deixam a API organizada sem framework full-stack.

### Por que não Laravel?

Funciona em Hostgator, mas assume SSH/artisan e estrutura própria de
deploy; com FTP-only fica frágil. Slim entrega o necessário com menos
partes móveis.

## Como ficam os segredos

- `FTP_PASSWORD`, `DB_PASSWORD`, `SESSION_SECRET` → **GitHub Secrets**
  (input único, digitados uma vez em *Settings → Secrets and variables
  → Actions*).
- O workflow gera `api/config.php` a partir dos secrets **no momento do
  deploy** — a senha vive só no servidor, nunca no git.
- `api/config.php` fica em `.gitignore` e protegido por `.htaccess`.

## Consequências

- (+) Zero custo adicional — usa a hospedagem já contratada.
- (+) Deploy automático mantido (build → FTP).
- (−) Sem RLS: autorização vive na camada de API (middleware de perfil) —
  exige disciplina e testes de autorização (ver doc 05 e 10).
- (−) MySQL localhost-only: desenvolvimento usa MySQL local (XAMPP/Docker).
- (−) Sem realtime; adequado ao caso de uso (CRUD + chamada).
