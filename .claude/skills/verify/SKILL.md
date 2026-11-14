---
name: verify
description: "Rodar as mesmas verificações da CI (lint, typecheck, Vitest, build e, se houver PHP, PHPUnit) e reportar problemas. Use antes de concluir qualquer alteração de código."
allowed-tools: Bash(npm run lint:*), Bash(npx tsc:*), Bash(npm run test:*), Bash(npm run build:*), Bash(npm run versoes:*), Bash(git diff --check:*), Bash(php -v:*), Bash(composer test:*), Read, Grep, Glob
---

Valide o projeto na mesma ordem do job `frontend` de `.github/workflows/ci.yml`:

1. Se `node_modules/` não existir, avise e rode `npm ci` (pedir confirmação).
2. `npm run versoes` (gera `src/lib/versoes.ts`, ignorado pelo git; fora
   de um repositório git pode gerar lista vazia — não é erro do código).
3. `npm run lint`
4. `npx tsc -b`
5. `npm run test`
6. `npm run build`
7. `git diff --check` (só se a pasta for repositório git).
8. API: se `php -v` funcionar, `composer test` em `api/` (testes de
   integração exigem `ADAB_TEST_DSN`; sem ele são pulados — reporte
   quantos). Sem PHP local, informe que a API será validada no job `api` da CI.

Se algo falhar, identifique a causa raiz nos arquivos indicados e proponha
(ou aplique, se pedido) a correção — sem desabilitar regras de lint nem
enfraquecer testes.

Resumo final em tabela: etapa | OK / falhou / pulado | observação.
