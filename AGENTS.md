# Regras do Projeto — Gestão do Templo ADAB

Sistema de gestão da Assembleia de Deus Templo Arthur Bernardes (ADAB):
site público + painel administrativo (membros, cultos, eventos, ações e
Escola Bíblica Dominical).

## Stack

- **Frontend**: Vite 7 + React 18 + TypeScript (strict), Tailwind 3,
  `lucide-react` — SPA servida pelo Apache
- **Backend**: `api/` PHP 8.3 + Slim 4 + PDO sobre MySQL (Hostgator
  compartilhada, mesmo domínio `adab.teo.br`)
- **Auth**: sessão PHP (cookie httpOnly SameSite) + CSRF token; perfis em
  `usuarios.perfil`
- **Deploy**: GitHub Actions → build → FTPS → `public_html` (ver
  `dev/docs/11-deploy-e-operacao.md`)
- Todo o conteúdo visível ao usuário é em **pt-BR**

## Documentação

`dev/docs/` é a fonte da verdade: requisitos, arquitetura, modelo de
dados, segurança (matriz de perfis), API, convenções e plano de ação em
`12-plano-de-acao.md`. Consulte antes de implementar módulos.

## Convenções

- Componentes funcionais `React.FC` com interfaces de props tipadas;
  componentes reutilizáveis em `src/components/`; acesso à API só via
  `src/lib/api.ts` (e futuros `api.ts` por módulo)
- PHP: PSR-12 + `declare(strict_types=1)`; SQL somente em Repositories
  com prepared statements; autorização via `AuthMiddleware`/`PerfilMiddleware`
  — nunca confiar em filtro do front
- Identidade visual: vermelho (`red-600`/`red-700`) como cor primária
- Schema novo/alterado exige migration em `api/migrations/` (NNNN_*.sql)
- Nunca commitar `api/config.php`, `.env*`, conteúdo de `uploads/`

## Verificação antes de concluir

- `npm run lint` e `npm run build` devem passar sem erros
- `composer test` (em `api/`) quando houver PHP/PHPUnit disponível

## Skills disponíveis

- `/review` — revisar alterações antes de commitar
- `/verify` — rodar lint + build e reportar problemas
- `/new-component` — criar componente seguindo as convenções do projeto
