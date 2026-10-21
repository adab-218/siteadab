<?php
// Template de configuração da API.
// DEV: copie para api/config.php e preencha os valores locais.
// PROD: não criar manualmente — o workflow de deploy gera config.php
// a partir dos GitHub Secrets (ver dev/docs/11-deploy-e-operacao.md).
// Este arquivo NÃO deve conter segredos reais.

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'adab_dev',        // prod: sorte759_adab
        'user' => 'root',            // prod: sorte759_adabbd
        'pass' => '',
        'charset' => 'utf8mb4',
    ],

    // Segredo para assinar dados de sessão/CSRF. Gerar com:
    //   php -r "echo bin2hex(random_bytes(32));"
    'session_secret' => 'TROCAR-por-string-aleatoria-longa',

    // Token que autoriza POST /api/admin/migrate sem login (deploy).
    'migrate_token' => 'TROCAR-por-token-aleatorio',

    // Diretório de uploads fora do versionamento (fotos, banners).
    'uploads_dir' => dirname(__DIR__) . '/uploads',

    // URL base pública usada em e-mails (recuperação de senha).
    'app_url' => 'http://localhost:5173', // prod: https://adab.teo.br

    // SMTP para envio de e-mail (cPanel). null desativa envio.
    'smtp' => null,
    // 'smtp' => [
    //     'host' => 'mail.adab.teo.br',
    //     'port' => 587,
    //     'user' => 'no-reply@adab.teo.br',
    //     'pass' => '',
    //     'from' => 'no-reply@adab.teo.br',
    //     'from_name' => 'ADAB Sistema',
    // ],
];
