---
name: debug-root-cause
description: Investigar defeitos pela causa raiz sem correções especulativas.
triggers:
  - user
  - model
---
Reproduza o problema em ambiente seguro, leia logs e testes, identifique o comportamento esperado/observado, formule hipóteses e valide-as. Corrija a causa raiz com diff pequeno e acrescente teste de regressão. Não apague logs/dados, não desabilite validações e não exponha segredos. Registre comandos, evidências e limitações.
