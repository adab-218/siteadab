# 11 — Deploy e Operação

## Infraestrutura (Hostgator Plano M)

| Item | Valor |
|------|-------|
| Domínio | `adab.teo.br` (DNS já na Hostgator: ns952/ns953.hostgator.com.br) |
| cPanel | https://br952.hostgator.com.br:2083/ |
| FTP | `ftp.crescersempremais.com.br` — usuário `ftp_adab@adab.teo.br`, FTPS explícita porta 21 |
| Banco | `sorte759_adab` / usuário `sorte759_adabbd` (localhost-only) |
| PHP | selecionar **8.2+** no cPanel (MultiPHP Manager) |
| SSL | AutoSSL (Let's Encrypt) — ativar no cPanel e forçar HTTPS |

## Segredos — input único

Criar em *GitHub → repo → Settings → Secrets and variables → Actions*:

| Secret | Conteúdo |
|--------|----------|
| `FTP_SERVER` | `ftp.crescersempremais.com.br` |
| `FTP_USERNAME` | `ftp_adab@adab.teo.br` |
| `FTP_PASSWORD` | senha do FTP |
| `DB_PASSWORD` | senha de `sorte759_adabbd` |
| `SESSION_SECRET` | string aleatória longa |
| `MIGRATE_TOKEN` | token para `/api/admin/migrate` |

> As senhas **nunca** entram no repositório. O workflow monta o
> `api/config.php` em tempo de deploy. É o "input único" — digitado uma
> vez e reutilizado por todos os deploys e agentes.

## Workflow de deploy (`.github/workflows/deploy.yml`)

```yaml
on:
  push: { branches: [main] }
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: 20 }
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2' }

      - run: npm ci && npm run lint && npx tsc -b && npm run test && npm run build
      - run: composer install --no-dev --optimize-autoloader
        working-directory: api

      - name: Gerar config.php (a partir de secrets)
        run: |
          cat > api/config.php <<EOF
          <?php return [
            'db' => ['host'=>'localhost','name'=>'sorte759_adab',
                     'user'=>'sorte759_adabbd','pass'=>'${{ secrets.DB_PASSWORD }}',
                     'charset'=>'utf8mb4'],
            'session_secret' => '${{ secrets.SESSION_SECRET }}',
            'uploads_dir' => __DIR__.'/../uploads',
          ];
          EOF

      - name: Deploy via FTPS
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          protocol: ftps
          port: 21
          local-dir: ./
          server-dir: /public_html/
          include: |
            dist/**
            api/**
            .htaccess
          exclude: |
            **/config.example.php
            **/node_modules/**
            **/.git*
```

> Detalhes do deploy:
> - `exclude` preserva `uploads/` e arquivos locais do servidor
>   (usar opção `dangerous-clean-slate: false` — padrão já não deleta).
> - Após deploy, o workflow chama `POST /api/admin/migrate` com
>   `MIGRATE_TOKEN` para rodar migrations pendentes.
> - CI de PR (`ci.yml`): mesmos checks, sem deploy.

## .htaccess da raiz (public_html)

```apache
# Forçar HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# /api → front controller da API
RewriteRule ^api/(.*)$ api/index.php [L]

# SPA fallback
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]

# Headers
Header always set X-Frame-Options "DENY"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

## Backup

- cPanel → Backup (full) semanal, com cópia baixada para fora do servidor.
- Cron do cPanel: `php -f $HOME/cron/backup_db.php` diário →
  `mysqldump` compactado em pasta fora de `public_html` +
  retenção de 14 dias.
- Teste de restore 1× por trimestre.

## Operação contínua

- Logs: `error_log` do PHP no cPanel; revisar após cada deploy.
- Auditoria trimestral de `usuarios` ativos e perfis.
- Monitorar quota de espaço (uploads crescem com fotos) e e-mail de
  deploy com falha do GitHub Actions.
