---
name: new-migration
description: "Criar a próxima migration MySQL em api/migrations/ no formato aceito pelo Migrator (numeração, idempotência, sem DELIMITER). Use sempre que o schema mudar."
argument-hint: "<descricao_em_snake_case>"
allowed-tools: Read, Glob, Grep, Write
---

Migration pedida: $ARGUMENTS

1. Liste `api/migrations/*.sql` e use o próximo número de 4 dígitos
   (`NNNN_<descricao>.sql`). Nunca edite migration existente — já pode
   ter sido aplicada em produção.
2. Leia `api/src/Migrator.php` e `dev/docs/04-modelo-de-dados.md`.
3. Regras do Migrator: statements separados por `;` no fim da linha; sem
   `DELIMITER`, procedures, triggers ou eventos.
4. Idempotência: `CREATE TABLE IF NOT EXISTS`, `CREATE OR REPLACE VIEW
   ... SQL SECURITY INVOKER`, `INSERT IGNORE` / `ON DUPLICATE KEY UPDATE`.
   Para `ALTER TABLE ADD COLUMN/INDEX`, verifique se a migration pode
   rodar duas vezes sem erro (o `MigrationsIntegrationTest` roda do zero).
5. Padrões: InnoDB, `utf8mb4`, PK `id BIGINT UNSIGNED AUTO_INCREMENT
   PRIMARY KEY` (FKs do mesmo tipo), `created_at DATETIME NOT NULL
   DEFAULT CURRENT_TIMESTAMP`, `deleted_at DATETIME NULL` para soft
   delete, índices `ix_<tabela>_<cols>`, UNIQUE `uq_<tabela>_<cols>`,
   dinheiro em `DECIMAL(10,2)`, percentual em `DECIMAL(5,2)`.
6. Cabeçalho em comentário: objetivo, decisões (Dn) e impacto em dados
   existentes / rollback manual.
7. Atualize `dev/docs/04-modelo-de-dados.md` e, se houver ciclo de vida,
   o `stateDiagram-v2` do módulo.

Não execute a migration em nenhum banco real. Reporte o arquivo criado,
impacto e como testar (CI job `api` ou MySQL local com `ADAB_TEST_DSN`).
