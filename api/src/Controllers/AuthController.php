<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Autenticação por sessão: login, logout, me, recuperação de senha.
 */
final class AuthController
{
    private const MAX_TENTATIVAS = 5;
    private const JANELA_MINUTOS = 15;

    public function __construct(private PDO $pdo, private array $config)
    {
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
        if ($this->bloqueadoPorRateLimit($email, $ip)) {
            return Json::erro($response, 'RATE_LIMIT',
                'Muitas tentativas. Aguarde ' . self::JANELA_MINUTOS . ' minutos.', 429);
        }

        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.pessoa_id, u.email, u.senha_hash, u.perfil, u.ativo, p.nome_completo
               FROM usuarios u
               JOIN pessoas p ON p.id = u.pessoa_id
              WHERE u.email = :email
              LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !$usuario['ativo'] || !password_verify($senha, $usuario['senha_hash'])) {
            $this->registrarTentativa($email, $ip);
            return Json::erro($response, 'CREDENCIAIS', 'E-mail ou senha inválidos.', 401);
        }

        $this->limparTentativas($email, $ip);
        $this->pdo->prepare('UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = ?')
                  ->execute([$usuario['id']]);

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
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
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

        if ($email !== '') {
            $stmt = $this->pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND ativo = 1');
            $stmt->execute([$email]);
            if ($id = $stmt->fetchColumn()) {
                $token = bin2hex(random_bytes(32));
                $this->pdo->prepare(
                    'UPDATE usuarios SET reset_token = ?, reset_expira = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?'
                )->execute([$token, $id]);
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

        $stmt = $this->pdo->prepare(
            'UPDATE usuarios
                SET senha_hash = ?, reset_token = NULL, reset_expira = NULL
              WHERE reset_token = ? AND reset_expira > NOW()'
        );
        $stmt->execute([password_hash($senha, PASSWORD_DEFAULT), $token]);

        if ($stmt->rowCount() === 0) {
            return Json::erro($response, 'TOKEN_INVALIDO', 'Token inválido ou expirado.', 400);
        }
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // -----------------------------------------------------------------

    private function bloqueadoPorRateLimit(string $email, string $ip): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_tentativas
              WHERE email = :email AND ip = :ip
                AND criado_em > DATE_SUB(NOW(), INTERVAL ' . self::JANELA_MINUTOS . ' MINUTE)'
        );
        $stmt->execute(['email' => $email, 'ip' => $ip]);
        return (int) $stmt->fetchColumn() >= self::MAX_TENTATIVAS;
    }

    private function registrarTentativa(string $email, string $ip): void
    {
        $this->pdo->prepare('INSERT INTO login_tentativas (email, ip) VALUES (?, ?)')
                  ->execute([$email, $ip]);
    }

    private function limparTentativas(string $email, string $ip): void
    {
        $this->pdo->prepare('DELETE FROM login_tentativas WHERE email = ? AND ip = ?')
                  ->execute([$email, $ip]);
    }

    private function enviarEmailRecuperacao(string $email, string $token): void
    {
        $smtp = $this->config['smtp'] ?? null;
        if (!$smtp) {
            error_log("[adab-api] SMTP não configurado — reset token para {$email}: {$token}");
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
