# 08 — Setup e Ambiente

## Pré-requisitos

- Node 20+ LTS e npm 10+ (build do frontend)
- PHP 8.2+ com PDO MySQL (local: XAMPP, Laragon ou `php -S`)
- Composer 2.x
- MySQL 8 local (XAMPP/Docker) — o banco remoto da Hostgator aceita
  apenas conexões localhost (via a API), então dev usa banco local

## Variáveis e configuração

Frontend — `.env.local` (gitignored):

```env
# em dev, aponta para o PHP local; em prod usa mesmo-origem
VITE_API_URL=http://localhost:8080/api
```

Backend — `api/config.php` (gitignored; em produção é gerado pelo
deploy a partir de GitHub Secrets). Template em `api/config.example.php`:

```php
<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'adab_dev',      // prod: sorte759_adab
        'user' => 'root',          // prod: sorte759_adabbd
        'pass' => '',              // prod: via GitHub Secret DB_PASSWORD
        'charset' => 'utf8mb4',
    ],
    'session_secret' => 'troque-por-string-aleatoria-longa',
    'uploads_dir' => __DIR__ . '/../uploads',
];
```

## Setup local

```bash
git clone https://github.com/adab-218/siteadab
cd siteadab
npm ci
cd api && composer install && cp config.example.php config.php  # editar

# banco local: criar schema 'adab_dev' e aplicar migrations
php api/migrate.php            # aplica api/migrations/*.sql

npm run dev                    # front :5173 (proxy /api → :8080)
php -S localhost:8080 -t api api/index.php   # API :8080 (index.php como router)
```

## Scripts

| Comando | Ação |
|---------|------|
| `npm run dev` / `build` / `lint` / `test` | frontend |
| `composer test` (api/) | PHPUnit |
| `php api/migrate.php [--seed]` | aplica migrations pendentes (+seed dev) |
| `php api/create-user.php <pessoa_id> <email> <senha> <perfil>` | cria usuário |
| `php -S localhost:8080 -t api api/index.php` | API local |

## Ambientes

| Ambiente | Frontend | API | Banco |
|----------|----------|-----|-------|
| dev | localhost:5173 | localhost:8080 | MySQL local `adab_dev` |
| prod | adab.teo.br | adab.teo.br/api | `sorte759_adab` (localhost no servidor) |

> Staging: se necessário, subdomínio `dev.adab.teo.br` na mesma conta
> com banco separado (cPanel permite bancos extras no plano M).

## Deploy

Automático via GitHub Actions → FTPS (ver doc 11). Credenciais em
GitHub Secrets — nunca no repositório.
