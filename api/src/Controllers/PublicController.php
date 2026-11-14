<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Validacao;
use Adab\Api\Repositories\ProgramacaoRepository;
use Adab\Api\Repositories\RateLimitRepository;
use Adab\Api\Repositories\RifaRepository;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Endpoints públicos do site — sem autenticação.
 * Retornam apenas dados marcados como públicos (ver doc 05).
 * Sorteios: doc 13 — reserva pública de números, consulta por telefone
 * (LGPD: expõe só número+status, nunca nome do comprador/divulgador).
 */
final class PublicController
{
    /** Limites por IP (T2.8). */
    // CGNAT/wifi da igreja compartilham IP: limites folgados, foco em bot
    public const LIMITE_RESERVAS = 30;          // requisições de reserva / 10 min
    public const LIMITE_NUMEROS_HORA = 300;     // números reservados / 60 min
    public const LIMITE_CONSULTAS = 30;         // consultas por telefone / 10 min

    private PDO $pdoConn;
    private RateLimitRepository $limite;
    private ProgramacaoRepository $programacao;
    private RifaRepository $rifas;

    public function __construct(PDO $pdo)
    {
        $this->pdoConn = $pdo;
        $this->programacao = new ProgramacaoRepository($pdo);
        $this->rifas = new RifaRepository($pdo);
        $this->limite = new RateLimitRepository($pdo);
    }

    /** IP do cliente; IPv6 agrupado no prefixo /64 (rotação dentro do bloco não burla o limite). */
    private function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (str_contains($ip, ':')) {
            $bin = @inet_pton($ip);
            if ($bin !== false && strlen($bin) === 16) {
                return (string) inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
            }
        }
        return substr($ip, 0, 45);
    }

    private function muitasTentativas(Response $response): Response
    {
        return Json::erro($response, 'MUITAS_TENTATIVAS',
            'Muitas tentativas a partir desta rede. Aguarde alguns minutos e tente novamente.', 429);
    }

    public function programacao(Request $request, Response $response): Response
    {
        try {
            $rows = $this->programacao->programacaoPublica();
        } catch (PDOException $e) {
            error_log('[adab-api] programacao pública: ' . $e->getMessage());
            return Json::erro($response, 'INDISPONIVEL', 'Programação indisponível no momento.', 503);
        }

        return Json::resposta($response, ['data' => $rows]);
    }

    public function eventos(Request $request, Response $response): Response
    {
        try {
            $rows = $this->programacao->eventosPublicos();
        } catch (PDOException $e) {
            error_log('[adab-api] eventos públicos: ' . $e->getMessage());
            return Json::erro($response, 'INDISPONIVEL', 'Eventos indisponíveis no momento.', 503);
        }

        return Json::resposta($response, ['data' => $rows]);
    }

    // ==================== sorteios beneficentes (M7) ====================

    public function sorteios(Request $request, Response $response): Response
    {
        return Json::resposta($response, ['data' => $this->rifas->listarPublico()]);
    }

    public function sorteio(Request $request, Response $response, array $args): Response
    {
        $rifa = $this->rifas->buscarPublicoPorSlug((string) $args['slug']);
        if (!$rifa) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Sorteio não encontrado.', 404);
        }
        return Json::resposta($response, ['data' => $rifa]);
    }

    /**
     * Reserva pública de números. Body: nome, telefone, quantidade, v?.
     * Regras: rifa ativa, dentro do prazo, max por pessoa, números
     * disponíveis; token ?v atribui divulgador (inválido não bloqueia).
     */
    public function reservar(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        // honeypot: campo 'website' preenchido = bot
        if (trim((string) ($b['website'] ?? '')) !== '') {
            return Json::resposta($response, ['data' => ['ok' => true]], 201);
        }

        $ip = $this->ip();
        if ($this->limite->somar('sorteio_reserva', $ip, 10) >= self::LIMITE_RESERVAS) {
            return $this->muitasTentativas($response);
        }
        $this->limite->registrar('sorteio_reserva', $ip);

        // rifa interna por slug — a view expõe só o que é público;
        // aqui precisamos do id e status real
        $stmt = $this->pdoStmt('SELECT id, status, data_sorteio, max_por_pessoa, valor_numero FROM rifas WHERE slug = ? AND deleted_at IS NULL', [(string) $args['slug']]);
        $rifa = $stmt->fetch();
        if (!$rifa || $rifa['status'] !== 'ativo') {
            return Json::erro($response, 'INDISPONIVEL', 'Sorteio indisponível para reservas.', 422);
        }
        if ($rifa['data_sorteio'] && strtotime((string) $rifa['data_sorteio']) < time()) {
            return Json::erro($response, 'ENCERRADO', 'Vendas encerradas para este sorteio.', 422);
        }

        $nome = trim((string) ($b['nome'] ?? ''));
        $telefone = preg_replace('/\D/', '', (string) ($b['telefone'] ?? ''));
        $email = trim((string) ($b['email'] ?? ''));
        $quantidade = (int) ($b['quantidade'] ?? 1);

        if ($nome === '' || !Validacao::maxLen($nome, 200)) {
            return Json::erro($response, 'VALIDACAO', 'Informe seu nome.', 400);
        }
        if (strlen($telefone) < 10 || strlen($telefone) > 11) {
            return Json::erro($response, 'VALIDACAO', 'Informe um telefone válido com DDD.', 400);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Json::erro($response, 'VALIDACAO', 'E-mail inválido.', 400);
        }
        $max = (int) $rifa['max_por_pessoa'];
        if ($quantidade < 1 || $quantidade > $max) {
            return Json::erro($response, 'VALIDACAO', "Você pode reservar entre 1 e {$max} números por vez.", 400);
        }

        // limite de números por IP (evita esgotar a rifa variando telefones)
        if ($this->limite->somar('sorteio_numeros', $ip, 60) + $quantidade > self::LIMITE_NUMEROS_HORA) {
            return $this->muitasTentativas($response);
        }

        // limite por pessoa: conta reservas ativas do telefone
        $rifaId = (int) $rifa['id'];
        $this->rifas->expirarPendentes($rifaId);
        $stmt = $this->pdoStmt(
            "SELECT COUNT(*) FROM rifa_bilhetes
              WHERE rifa_id = ? AND telefone = ? AND status_pag IN ('pendente','pago','gratuito')",
            [$rifaId, $telefone]
        );
        $jaTem = (int) $stmt->fetchColumn();
        if ($jaTem + $quantidade > $max) {
            $restante = max(0, $max - $jaTem);
            return Json::erro($response, 'LIMITE',
                "Limite de {$max} números por telefone." . ($restante > 0 ? " Restam {$restante}." : ''), 422);
        }

        // divulgador pelo token (?v=) — inválido/inativo não bloqueia (R8)
        $divulgadorId = null;
        $token = trim((string) ($b['v'] ?? ''));
        if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
            $dv = $this->rifas->divulgadorPorToken($token);
            if ($dv && (int) $dv['rifa_id'] === $rifaId) {
                $divulgadorId = (int) $dv['id'];
            }
        }

        try {
            $numeros = $this->rifas->reservarNumeros($rifaId, $quantidade, [
                'nome' => $nome, 'telefone' => $telefone, 'email' => $email ?: null,
            ], $divulgadorId);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SEM_NUMEROS') {
                return Json::erro($response, 'ESGOTADO', 'Números esgotados para este sorteio.', 422);
            }
            throw $e;
        }
        $this->limite->registrar('sorteio_numeros', $ip, $quantidade);

        $total = round($quantidade * (float) $rifa['valor_numero'], 2);
        return Json::resposta($response, ['data' => [
            'numeros' => array_column($numeros, 'numero'),
            'total' => $total,
            'expira_em' => 24,
        ]], 201);
    }

    /** Consulta de bilhetes por telefone — só número+status (LGPD). */
    public function meusBilhetes(Request $request, Response $response, array $args): Response
    {
        $telefone = preg_replace('/\D/', '', (string) ($request->getQueryParams()['telefone'] ?? ''));
        if (strlen($telefone) < 10) {
            return Json::erro($response, 'VALIDACAO', 'Informe o telefone com DDD.', 400);
        }
        // anti-enumeração de telefones
        $ip = $this->ip();
        if ($this->limite->somar('sorteio_consulta', $ip, 10) >= self::LIMITE_CONSULTAS) {
            return $this->muitasTentativas($response);
        }
        $this->limite->registrar('sorteio_consulta', $ip);
        // só sorteios públicos (rascunho/cancelado não existem para o público)
        $stmt = $this->pdoStmt(
            "SELECT id FROM rifas WHERE slug = ? AND deleted_at IS NULL
                AND status IN ('ativo','encerrado','sorteado')",
            [(string) $args['slug']]
        );
        $rifaId = (int) ($stmt->fetchColumn() ?: 0);
        if (!$rifaId) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Sorteio não encontrado.', 404);
        }
        return Json::resposta($response, ['data' => $this->rifas->bilhetesPorTelefone($rifaId, $telefone)]);
    }

    /** Helper: prepared statement direto (uso interno do controller). */
    private function pdoStmt(string $sql, array $params): \PDOStatement
    {
        $stmt = $this->pdoConn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

}
