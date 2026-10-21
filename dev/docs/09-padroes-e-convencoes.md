# 09 — Padrões e Convenções

## Código — Frontend (TS/React)

- TypeScript strict; componentes funcionais `React.FC<Props>` com
  interface de props acima do componente.
- Textos de UI em pt-BR; nomes de código em pt-BR — domínio
  eclesiástico fica mais legível (`matricularAluno`, `frequencia`).
- Estilo: Tailwind utilitário; componentes complexos via shadcn/ui.
  Cor primária `red-600`.
- Todo acesso à API em `api.ts` do módulo — componentes nunca chamam
  `fetch` diretamente.
- Formulários: react-hook-form + zod; schema zod por módulo.

## Código — Backend (PHP)

- PHP 8.x, PSR-12, tipos estritos (`declare(strict_types=1)`).
- Camadas fixas: Routes → Controllers → Services → Repositories.
  SQL somente em Repositories, sempre prepared statements (PDO).
- Sem lógica em `index.php` além de bootstrap/rotas/middlewares.
- Respostas de erro padronizadas `{ "error": { "code", "message" } }`.
- `declare(strict_types=1)` + `password_hash`/`random_bytes` para
  segurança; nunca interpolar input em SQL.
- Autorização: toda rota declara o middleware de perfil; escopo de
  dados no repositório.

## Nomenclatura

| Item | Convenção | Exemplo |
|------|-----------|---------|
| Componentes | PascalCase.tsx | `FichaMembro.tsx` |
| Hooks | useCamelCase | `useMembros.ts` |
| Classes PHP | PascalCase | `FrequenciaRepository.php` |
| Tabelas | snake_case plural | `ebd_frequencias` |
| Colunas | snake_case | `data_matricula` |
| Rotas API | kebab-case, plural | `/api/ebd/alunos` |
| Migrations | `NNNN_<descricao>.sql` | `0004_create_ebd.sql` |

## Git

- Branches: `main` (protegida) + `feat/<tema>` / `fix/<tema>`.
- Commits: Conventional Commits — `feat(ebd): chamada por aula`.
- PR com checklist: lint, typecheck, testes verdes; revisão (skill `/review`).
- **Nunca** commitar `api/config.php`, `.env*`, dumps de banco ou
  conteúdo de `uploads/`.

## Definition of Done

- [ ] Lint + typecheck + testes (front e api) verdes no CI
- [ ] Autorização da rota revisada (perfil + escopo de dados)
- [ ] Migration commitada em `api/migrations/` quando schema mudar
- [ ] Tela responsiva verificada (mobile + desktop)
- [ ] Doc relevante atualizada (modelo de dados, API, etc.)
