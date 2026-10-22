<?php

declare(strict_types=1);

namespace Adab\Api\Middleware;

use Adab\Api\Json;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Exige sessão autenticada (401 caso contrário).
 *
 * - Timeout de inatividade (30min): expira sessões paradas;
 * - Rotação de session_id a cada 15min (fixação pós-roubo);
 * - Revalida `usuarios.ativo` e refresca o perfil a cada request:
 *   desativação ou troca de perfil passam a valer imediatamente.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    private const INATIVIDADE_MAX_SEG = 1800;   // 30 min
    private const ROTACAO_INTERVALO_SEG = 900;  // 15 min

    public function __construct(private PDO $pdo)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $usuario = $_SESSION['usuario'] ?? null;
        if (empty($usuario['id'])) {
            return Json::erro(new SlimResponse(), 'NAO_AUTENTICADO', 'Login necessário.', 401);
        }

        $agora = time();
        $ultimoAcesso = (int) ($_SESSION['ultimo_acesso'] ?? 0);
        if ($ultimoAcesso > 0 && ($agora - $ultimoAcesso) > self::INATIVIDADE_MAX_SEG) {
            $_SESSION = [];
            return Json::erro(new SlimResponse(), 'SESSAO_EXPIRADA', 'Sessão expirada por inatividade.', 401);
        }
        $_SESSION['ultimo_acesso'] = $agora;

        // Rotação periódica do id — janela curta p/ id vazado
        if (session_status() === PHP_SESSION_ACTIVE
            && ($agora - (int) ($_SESSION['regenerada_em'] ?? 0)) > self::ROTACAO_INTERVALO_SEG) {
            session_regenerate_id(true);
            $_SESSION['regenerada_em'] = $agora;
        }

        $stmt = $this->pdo->prepare('SELECT ativo, perfil FROM usuarios WHERE id = ?');
        $stmt->execute([$usuario['id']]);
        $linha = $stmt->fetch();

        if (!$linha || !$linha['ativo']) {
            $_SESSION = [];
            return Json::erro(new SlimResponse(), 'NAO_AUTENTICADO', 'Sessão encerrada.', 401);
        }

        // Mantém perfil da sessão sincronizado com o banco
        $_SESSION['usuario']['perfil'] = $linha['perfil'];

        return $handler->handle($request);
    }
}
