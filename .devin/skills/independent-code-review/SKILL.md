---
name: independent-code-review
description: Revisar alterações de código de forma independente antes da integração.
triggers:
  - user
  - model
---
Inspecione `git diff`, contexto e critérios de aceite. Priorize bugs, segurança, autorização, migrações, regressões, concorrência e manutenção. Não edite no passe de revisão. Informe achados com severidade, caminho/linha, impacto e recomendação. Se não houver achados, informe o escopo e as limitações da revisão.
