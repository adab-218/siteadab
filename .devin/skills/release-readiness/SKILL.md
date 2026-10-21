---
name: release-readiness
description: Verificar preparação de release, configuração e plano de rollback.
triggers:
  - user
  - model
---
Inspecione ambiente e procedimentos existentes. Verifique configuração por variáveis de ambiente, `.env.example`, logs sem dados sensíveis, requisitos de runtime, migrations, backup/restore, health checks, smoke tests e rollback. Não faça deploy nem altere produção sem autorização explícita. Marque cada item como verificado, pendente ou não aplicável com evidência.
