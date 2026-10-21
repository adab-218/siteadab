# Checklist de aceite

## Funcionalidade
- [ ] Critérios de aceite testados.
- [ ] Fluxo principal funciona de ponta a ponta.
- [ ] Erros de entrada, ausência de dados e permissões negadas têm comportamento definido.
- [ ] Paginação, filtros, ordenação e limites são validados quando aplicáveis.
- [ ] Mensagens ao usuário são claras e não revelam detalhes internos.

## PHP e arquitetura
- [ ] Sintaxe PHP válida.
- [ ] Responsabilidades e padrões do projeto respeitados.
- [ ] Erros tratados e registrados sem vazar segredos.
- [ ] Dependências novas justificadas.
- [ ] Sem duplicação significativa ou lógica de negócio inconsistente.

## MySQL
- [ ] Migração versionada e revisada.
- [ ] Chaves, índices, restrições e transações adequados.
- [ ] Consultas parametrizadas.
- [ ] Dados existentes preservados.
- [ ] Rollback ou estratégia de correção documentada.
- [ ] Testes usam banco isolado, nunca produção.

## Segurança
- [ ] Autenticação verificada no servidor.
- [ ] Autorização validada em cada operação protegida.
- [ ] Prepared statements para SQL.
- [ ] Escape de saída conforme contexto para prevenir XSS.
- [ ] CSRF coberto em operações com sessão.
- [ ] Senhas com `password_hash()`/`password_verify()`.
- [ ] Sessão protegida e ID regenerado após login.
- [ ] Uploads, arquivos e caminhos validados quando aplicável.
- [ ] Segredos ausentes do código, logs, fixtures e commits.
- [ ] Não há dados pessoais reais nos testes.

## Frontend e acessibilidade
- [ ] Interface responsiva.
- [ ] Formulários têm rótulos e mensagens de validação acessíveis.
- [ ] Estados de carregamento, vazio, sucesso e falha definidos.
- [ ] Validação do servidor não depende da validação JavaScript.

## Qualidade
- [ ] Testes de unidade e integração relevantes passaram.
- [ ] Testes de regressão executados.
- [ ] Revisão de código independente concluída.
- [ ] Auditoria de segurança concluída para mudanças de risco.
- [ ] `git diff --check` passou.
- [ ] Documentação atualizada.
- [ ] Resultado final relata testes e limitações reais.
