---
name: mysql-schema-migrations
description: Projetar e revisar esquema MySQL e migrations seguras.
triggers:
  - user
  - model
---
Inspecione schema e consultas atuais. Defina PK/FK, constraints, índices justificados, tipos adequados, InnoDB e utf8mb4 quando compatíveis. Versione alterações. Explique impacto em dados existentes, ordem de deploy, compatibilidade e rollback. Teste apenas em banco isolado. Não execute em produção nem faça alterações destrutivas sem autorização humana.
