# Fluxo de trabalho da equipe

## Papéis

- **Agente raiz / Tech Lead:** coordena, decide a sequência técnica, delega tarefas, integra resultados e comunica bloqueios. Não delega a aprovação final de uma alteração a quem a implementou.
- **requirements-analyst:** clarifica fluxos, regras, exceções e critérios de aceite; não cria requisitos de negócio por suposição.
- **software-architect:** define limites entre camadas, contratos, estrutura e decisões técnicas.
- **database-engineer:** projeta esquema, migrações, integridade, índices e estratégia de rollback.
- **php-backend-developer:** implementa domínio, validações, autorização, endpoints e persistência.
- **frontend-developer:** implementa UI responsiva e integração com contratos do backend.
- **qa-test-engineer:** cria/executa testes e relata evidências; não altera implementação para ocultar falhas.
- **security-reviewer:** faz auditoria independente, preferencialmente somente leitura, com achados classificados por severidade.
- **code-reviewer:** revisa correção, manutenção, regressões e aderência às convenções.
- **devops-release-engineer:** prepara configuração, observabilidade, backup e plano de release; não implanta sem autorização explícita.

## Fluxo por funcionalidade

1. **Descoberta:** requirements-analyst define ator, fluxo principal, regras, exceções, permissões e critérios de aceite.
2. **Plano:** Tech Lead decompõe em tarefas pequenas e identifica dependências.
3. **Contrato:** arquiteto define interfaces/rotas/formatos de dados; banco define esquema e migrações. Registrar decisões em `docs/`.
4. **Implementação:** backend e frontend podem trabalhar em paralelo apenas após os contratos serem acordados. Evitar editar arquivos compartilhados ao mesmo tempo.
5. **Testes:** QA prepara testes de unidade, integração, autorização e fluxos críticos. Testes podem ser desenvolvidos em paralelo com implementação.
6. **Revisão:** code-reviewer inspeciona diff; security-reviewer audita autenticação, autorização, validação, saída, SQL, sessão, CSRF e segredos.
7. **Integração:** Tech Lead resolve conflitos e executa a suíte completa relevante.
8. **Aceite:** aplicar `docs/ACCEPTANCE_CHECKLIST.md`, atualizar documentação e reportar pendências.
9. **Release:** devops-release-engineer prepara passos e rollback; humano autoriza qualquer operação em produção.

## Política de paralelismo

- Preferir 2–3 subagentes simultâneos por tarefa.
- Uma tarefa por agente deve declarar arquivos ou área sob responsabilidade.
- Não permitir que dois agentes modifiquem o mesmo arquivo ao mesmo tempo.
- Agentes de auditoria/pesquisa podem rodar em background se só precisarem de ferramentas pré-aprovadas.
- Subagentes não devem criar subagentes, salvo decisão explícita e justificada do Tech Lead.
- Ao concluir, cada agente devolve: resumo, arquivos afetados, decisões, comandos executados, resultados e riscos.

## Definition of Ready

Uma tarefa está pronta para começar quando possui:
- objetivo claro e motivação;
- contexto dos módulos existentes;
- entradas/saídas e permissões;
- critérios de aceite testáveis;
- dependências identificadas;
- escopo e arquivos prováveis;
- estratégia de teste;
- decisões de negócio não resolvidas explicitadas.

## Definition of Done

- Critérios de aceite verificados.
- Testes relevantes executados e resultado registrado.
- Sem regressões conhecidas.
- Revisão independente concluída.
- Segurança revisada para mudanças de risco.
- Migrações reversíveis ou plano documentado; mudanças destrutivas explicitamente aprovadas.
- Documentação atualizada.
- `git diff --check` aprovado.
- Nenhuma credencial ou dado real sensível incluído.
- Resumo final com evidências e limitações.

## Regras de dependência para PRs

1. Requisitos/contratos antes da implementação.
2. Migração compatível antes do código que depende dela, ou plano de implantação coordenado.
3. Backend deve documentar contrato antes da integração frontend.
4. Testes automatizados acompanham a funcionalidade, não são adiados para “depois”.
5. Segurança e revisão de código antes de merge.
6. Deploy separado do merge e sempre com aprovação humana.

## Comandos de validação sugeridos

Adaptar aos comandos reais do repositório. Não afirmar que existem antes de verificar:
- `php -l caminho/do/arquivo.php`
- `composer validate` (se Composer for usado)
- `vendor/bin/phpunit` (se PHPUnit estiver instalado/configurado)
- `vendor/bin/phpstan analyse` (se PHPStan estiver configurado)
- `vendor/bin/phpcs` (se PHP_CodeSniffer estiver configurado)
- `git diff --check`
- testes de integração em banco de teste isolado
