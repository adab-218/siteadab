<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Repositories\TentativaLoginRepository;
use Adab\Api\Repositories\UsuarioRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Autenticação por sessão: login, logout, me, recuperação de senha.
 */
final class AuthController
{
    private const MAX_TENTATIVAS = 5;
    private const MAX_TENTATIVAS_IP = 30;
    private const JANELA_MINUTOS = 15;
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    private UsuarioRepository $usuarios;
    private TentativaLoginRepository $tentativas;

    public function __construct(PDO $pdo, private array $config)
    {
        $this->usuarios = new UsuarioRepository($pdo);
        $this->tentativas = new TentativaLoginRepository($pdo);
    }

    public function login(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $email = trim((string) ($body['email'] ?? ''));
        $senha = (string) ($body['senha'] ?? '');

        if ($email === '' || $senha === '') {
            return Json::erro($response, 'VALIDACAO', 'Informe e-mail e senha.', 400);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($this->bloqueadoPorRateLimit($email, $ip) || $this->bloqueadoPorIp($ip)) {
            return Json::erro($response, 'RATE_LIMIT',
                'Muitas tentativas. Aguarde ' . self::JANELA_MINUTOS . ' minutos.', 429);
        }

        $usuario = $this->usuarios->buscarPorEmail($email);

        // Hash dummy quando usuário não existe — paridade de tempo
        // para não revelar se o e-mail está cadastrado.
        $senhaOk = $usuario
            ? password_verify($senha, $usuario['senha_hash'])
            : password_verify($senha, self::DUMMY_HASH) && false;

        if (!$usuario || !$usuario['ativo'] || !$senhaOk) {
            $this->registrarTentativa($email, $ip);
            return Json::erro($response, 'CREDENCIAIS', 'E-mail ou senha inválidos.', 401);
        }

        $this->limparTentativas($email, $ip);
        $this->usuarios->registrarLogin((int) $usuario['id']);

        session_regenerate_id(true);
        $csrf = bin2hex(random_bytes(32));
        $_SESSION['csrf'] = $csrf;
        $_SESSION['usuario'] = [
            'id' => (int) $usuario['id'],
            'pessoa_id' => (int) $usuario['pessoa_id'],
            'nome' => $usuario['nome_completo'],
            'email' => $usuario['email'],
            'perfil' => $usuario['perfil'],
        ];

        return Json::resposta($response, ['data' => [
            'usuario' => $_SESSION['usuario'],
            'csrf' => $csrf,
        ]]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }
        session_destroy();
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    public function me(Request $request, Response $response): Response
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return Json::resposta($response, ['data' => [
            'usuario' => $_SESSION['usuario'],
            'csrf' => $_SESSION['csrf'],
        ]]);
    }

    public function recuperar(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $email = trim((string) ($body['email'] ?? ''));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($this->bloqueadoPorRateLimit($email, $ip) || $this->bloqueadoPorIp($ip)) {
            return Json::erro($response, 'RATE_LIMIT',
                'Muitas tentativas. Aguarde ' . self::JANELA_MINUTOS . ' minutos.', 429);
        }

        if ($email !== '') {
            $this->registrarTentativa($email, $ip);
            if ($id = $this->usuarios->idAtivoPorEmail($email)) {
                $token = bin2hex(random_bytes(32));
                $this->usuarios->definirResetToken($id, hash('sha256', $token));
                $this->enviarEmailRecuperacao($email, $token);
            }
        }

        // Resposta neutra — não revela se o e-mail existe
        return Json::resposta($response, ['data' => [
            'mensagem' => 'Se o e-mail existir, enviaremos as instruções de redefinição.',
        ]]);
    }

    public function redefinir(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $token = (string) ($body['token'] ?? '');
        $senha = (string) ($body['senha'] ?? '');

        if ($token === '' || strlen($senha) < 10) {
            return Json::erro($response, 'VALIDACAO', 'Token inválido ou senha menor que 10 caracteres.', 400);
        }

        $ok = $this->usuarios->consumirResetToken(
            hash('sha256', $token),
            password_hash($senha, PASSWORD_DEFAULT)
        );

        if (!$ok) {
            return Json::erro($response, 'TOKEN_INVALIDO', 'Token inválido ou expirado.', 400);
        }
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // -----------------------------------------------------------------

    private function bloqueadoPorRateLimit(string $email, string $ip): bool
    {
        return $this->tentativas->contarPorEmailEIp(
            $this->hashEmail($email), $ip, self::JANELA_MINUTOS
        ) >= self::MAX_TENTATIVAS;
    }

    private function bloqueadoPorIp(string $ip): bool
    {
        return $this->tentativas->contarPorIp($ip, self::JANELA_MINUTOS)
            >= self::MAX_TENTATIVAS_IP;
    }

    private function registrarTentativa(string $email, string $ip): void
    {
        $this->tentativas->registrar($this->hashEmail($email), $ip);
        // Purge probabilístico (~5%) de linhas antigas — mantém a tabela pequena
        if (random_int(1, 20) === 1) {
            $this->tentativas->purgarAntigas();
        }
    }

    private function limparTentativas(string $email, string $ip): void
    {
        $this->tentativas->limpar($this->hashEmail($email), $ip);
    }

    private function hashEmail(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    private function enviarEmailRecuperacao(string $email, string $token): void
    {
        $smtp = $this->config['smtp'] ?? null;
        if (!$smtp) {
            // Nunca logar o token — equivale a uma credencial válida por 1h.
            error_log("[adab-api] SMTP não configurado — e-mail de recuperação NÃO enviado para {$email}");
            return;
        }

        // PHPMailer é carregado via composer autoload quando SMTP configurado
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $smtp['host'];
            $mail->Port = $smtp['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['user'];
            $mail->Password = $smtp['pass'];
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($smtp['from'], $smtp['from_name'] ?? 'ADAB');
            $mail->addAddress($email);
            $mail->Subject = 'Redefinição de senha — ADAB';
            $link = rtrim($this->config['app_url'] ?? '', '/') . '/redefinir-senha?token=' . urlencode($token);
            $mail->Body = "Use este link para redefinir sua senha (válido por 1 hora):\n\n{$link}";
            $mail->send();
        } catch (\Throwable $e) {
            error_log('[adab-api] Falha ao enviar e-mail de recuperação: ' . $e->getMessage());
        }
    }
}
