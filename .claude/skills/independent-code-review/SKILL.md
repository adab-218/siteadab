---
name: independent-code-review
description: "Revisão independente das alterações atuais em um subagente isolado (code-reviewer), antes da integração. Use quando quem implementou não pode aprovar o próprio trabalho."
context: fork
agent: code-reviewer
---

Revise as alterações atuais do repositório (`git status`, `git diff
--staged`, `git diff`). Se não houver git, revise os arquivos indicados
abaixo e registre a limitação.

Arquivos/escopo indicados (se houver): $ARGUMENTS

Use os critérios de aceite da tarefa, `dev/docs/CHECKLIST.md` (D1–D13) e
`docs/ACCEPTANCE_CHECKLIST.md`. Priorize bugs, autorização, migrações,
regressões, contrato da API e convenções. Não edite arquivos. Informe
achados com severidade, `arquivo:linha`, impacto e recomendação; se não
houver achados, informe escopo e limitações.
