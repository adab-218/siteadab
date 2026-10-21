# 05 — Segurança e Permissões

## Autenticação

- `POST /api/auth/login` (email + senha) → sessão PHP com cookie
  `httpOnly; Secure; SameSite=Lax`.
- Senhas: `password_hash()` (PASSWORD_DEFAULT → bcrypt/argon2),
  verificação com `password_verify()`; mínimo 10 caracteres.
- Sessão regenera ID no login (`session_regenerate_id`), expira por
  inatividade (ex.: 2h) e tem tempo máximo (ex.: 12h).
- Recuperação de senha: token aleatório com expiração em
  `usuarios.reset_token/reset_expira` + e-mail (PHPMailer via SMTP).
- Rate limit no login: máx. 5 tentativas/15min por IP + e-mail
  (tabela `login_tentativas` ou arquivo lock).
- Como a autenticação é por cookie, toda mutação exige **CSRF token**
  (header `X-CSRF-Token` emitido no login e validado por middleware).

## Papéis (perfis)

Campo `usuarios.perfil` (enum). Autorização centralizada em **middleware
PHP** — MySQL compartilhado não tem RLS, então a API é o único guardião:
o front nunca decide sozinho, e toda rota lista o perfil exigido.

| Papel | Escopo |
|-------|--------|
| `admin` | Acesso total, gestão de usuários e parâmetros |
| `secretaria` | CRUD de membros, cultos, eventos, ações; leitura EBD |
| `dirigente` | Pastor/dirigente: leitura geral + relatórios; edição de cultos e eventos |
| `ebd_admin` | Gestão completa da EBD; leitura de membros |
| `ebd_professor` | Chamada e leitura apenas das próprias classes |
| `lider_departamento` | Leitura dos membros do seu departamento; editar ações do depto |

Matriz (• = CRUD, ◦ = leitura, – = sem acesso):

| Recurso | admin | secretaria | dirigente | ebd_admin | ebd_professor | lider_depto |
|---------|:-----:|:----------:|:---------:|:---------:|:-------------:|:-----------:|
| usuarios | • | – | – | – | – | – |
| pessoas/membros | • | • | ◦ | ◦ | ◦ (só seus alunos) | ◦ (só seu depto) |
| cultos | • | • | • | – | – | – |
| eventos | • | • | • | – | – | ◦ |
| acoes | • | • | • | – | – | • (do seu depto) |
| ebd_* | • | ◦ | ◦ | • | chamada própria classe | – |
| dashboard | • | • | • | • | – | – |

### Implementação da autorização

```php
// middleware por rota + escopo de dados na query
$app->get('/api/membros', [MembrosController::class, 'listar'])
    ->add(PerfilMiddleware::para(['admin','secretaria','dirigente','ebd_admin','lider_departamento']));

// escopo: ebd_professor e lider_departamento recebem WHERE restritivo
// ex.: professor → JOIN ebd_professores p ON p.pessoa_id = :eu AND p.ativo
```

- Escopo de dados nos repositórios, nunca só no controller de entrada.
- Chamada: `ebd_frequencias` só aceita escrita se a aula pertencer a
  classe vinculada ao professor logado.
- Rotas públicas (site): apenas `GET /api/public/programacao` —
  lê `v_programacao_publica`; nenhum dado pessoal.

## LGPD

Afiliação religiosa é **dado pessoal sensível** (LGPD art. 5º, II e
art. 11). Medidas:

- **Base legal**: consentimento e/ou finalidades da organização
  religiosa (art. 11, II); campo `pessoas.consentimento_lgpd_em`
  registra o aceite no cadastro.
- **Minimização**: CPF/documentos só se houver necessidade real.
- **Direitos do titular**: processo para consulta, correção e exclusão
  (exclusão preserva histórico agregado anonimizado).
- **Acesso**: perfis acima + auditoria (`created_by`/`updated_at` e log
  de alterações sensíveis em `audit_log`).
- **Retenção**: ex-membros/falecidos com acesso restrito.
- **Vazamento**: plano de resposta — comunicar liderança e, se
  aplicável, ANPD e titulares.

## Hardening do servidor

- `config.php` (credenciais DB, session secret) fora de versionamento,
  negado via `.htaccess` (`Require all denied` / `Deny from all`).
- `uploads/` com `.htaccess` proibindo execução de PHP.
- `.htaccess` raiz: SPA fallback + headers de segurança
  (X-Frame-Options, nosniff, Referrer-Policy; CSP na fase de hardening).
- PHP: `display_errors=Off` em produção; erros em log.
- HTTPS: forçar redirect no `.htaccess` (Hostgator emite SSL gratuito
  via AutoSSL/Let's Encrypt no cPanel).
- Senha do banco **nunca** commitada — injetada no deploy via GitHub
  Secrets (ver doc 11).
