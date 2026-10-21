# Equipe de agentes — Devin CLI / PHP + MySQL

Este pacote é um scaffold de configuração para um projeto web de porte médio. Ele contém instruções globais, 9 perfis de subagentes, 10 skills reutilizáveis e um fluxo de trabalho. Não inclui o sistema de negócio em si.

## Conteúdo

- `AGENTS.md`: regras permanentes do repositório.
- `.devin/agents/*.md`: perfis de subagentes personalizados do Devin CLI.
- `.devin/skills/*/SKILL.md`: skills acionáveis com `/nome-da-skill`.
- `docs/TEAM_WORKFLOW.md`: fluxo de trabalho e contratos de equipe.
- `docs/TASK_TEMPLATE.md`: modelo para delegar tarefas.
- `docs/ACCEPTANCE_CHECKLIST.md`: checklist de qualidade e segurança.

A configuração usa os diretórios de projeto documentados pelo Devin CLI: `.devin/agents/` e `.devin/skills/`. Perfis personalizados são experimentais e podem mudar entre versões. Consulte a documentação atual antes de depender de campos específicos.

## Como instalar no seu repositório

1. Faça backup/commit das alterações atuais.
2. Extraia este pacote em uma pasta temporária.
3. Compare o `AGENTS.md` com o arquivo existente. Mescle as regras; não sobrescreva instruções úteis do seu projeto.
4. Copie `.devin/agents/`, `.devin/skills/` e os arquivos de `docs/` para o repositório. Se já houver arquivos com os mesmos nomes, revise as diferenças antes de substituir.
5. Revise `AGENTS.md` para definir a versão PHP, hosting, convenções e comandos reais do projeto.
6. Abra o repositório no terminal e execute `devin`.
7. Teste se o Devin reconhece os perfis e skills. As skills podem ser invocadas com `/nome-da-skill`; peça ao agente raiz para usar um perfil pelo nome, por exemplo: “Use o subagente `security-reviewer` para revisar o diff”.
8. Comece em modo de planejamento (`/plan`) para validar o plano sem editar arquivos. Use modo normal para implementação e aprovação humana para operações de alto impacto.

## Recomendações de execução

- Use inicialmente 2–3 subagentes por tarefa; o paralelismo aumenta custo e pode criar conflitos.
- Pesquisa e auditoria somente leitura podem rodar em background. Alterações em código e banco devem ser coordenadas e revisadas.
- Não suponha que o nome de um perfil garante sua execução: confira o painel/log de subagentes.
- O projeto deve definir comandos de teste reais. Não invente resultados nem marque critérios como atendidos sem evidência.
- Devin CLI executa localmente com as permissões do ambiente; mantenha credenciais e acesso de produção fora do alcance da sessão sempre que possível.

## Referências oficiais

- Devin CLI: https://devin.ai/cli
- Documentação CLI: https://docs.devin.ai/cli
- Subagents: https://docs.devin.ai/cli/subagents
- Skills: https://docs.devin.ai/cli/extensibility/skills/overview
- Creating Skills: https://docs.devin.ai/cli/extensibility/skills/creating-skills
