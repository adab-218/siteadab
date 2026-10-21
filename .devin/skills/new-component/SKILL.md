---
name: new-component
description: Criar componente React seguindo as convenções do projeto
argument-hint: "<nome-do-componente>"
allowed-tools:
  - read
  - write
  - edit
  - glob
  - grep
---

Crie um novo componente React em `src/components/` seguindo as
convenções do projeto:

1. Antes de escrever, leia `src/components/WeeklySchedule.tsx` e/ou os
   componentes internos de `src/App.tsx` para seguir o mesmo estilo
2. Requisitos:
   - Componente funcional com TypeScript (`React.FC<Props>`)
   - Interface de props tipada declarada acima do componente
   - Estilização com classes utilitárias Tailwind (cor primária: `red-600`)
   - Ícones via `lucide-react` quando necessário
   - Textos visíveis ao usuário em pt-BR
   - Export default
3. Informe o caminho do arquivo criado e como importá-lo
