# 10 — Estratégia de Testes

## Pirâmide

| Nível | Ferramenta | Alvo |
|-------|-----------|------|
| Unitário front | Vitest + Testing Library | utils, schemas zod, regras (data/semana, agregações) |
| Unitário back | PHPUnit | Services e regras de negócio |
| Componente | Testing Library | formulários, chamada EBD, ficha do membro |
| Integração | PHPUnit + MySQL local | Repositories + autorização (escopo por perfil) |
| E2E | Playwright | fluxos críticos abaixo |

## Autorização — testes obrigatórios

Sem RLS, a segurança depende da API — para cada rota protegida,
testes positivos **e negativos**:

- `ebd_professor` só faz chamada da própria classe (e não de outra)
- `lider_departamento` só lê membros do seu departamento
- sem login → 401; perfil errado → 403
- usuário não-admin não altera `usuarios.perfil`
- `/api/public/*` não expõe dados pessoais

## Fluxos e2e críticos

1. Login → dashboard
2. Cadastrar pessoa → promover a membro → verificar na lista
3. Matricular aluno → professor faz chamada → relatório reflete
4. Criar evento `publico` → aparece no site público
5. Registrar culto com totais

## Dados de teste

- `api/migrations/` + `api/seed.sql` para base dev/testes.
- Factories em `tests/factories.ts` e `api/tests/Factories.php`.

## Metas

- Cobertura ≥ 70% em `lib/`, `schemas/`, `Services/` e `Repositories/`.
- CI roda: lint → tsc → Vitest → PHPUnit → build → e2e (PRs).
