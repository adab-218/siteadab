<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Repositories\UsuarioRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * T2.2 — Gestão de usuários (somente admin — as rotas usam
 * PerfilMiddleware::para([]), que só deixa passar admin).
 *
 * Proteções: admin não pode rebaixar o próprio perfil nem desativar
 * a própria conta; duplicidades viram 422, não 500.
 */
final class UsuariosController
{
    private UsuarioRepository $usuarios;

    public function __construct(PDO $pdo)
    {
        $this->usuarios = new UsuarioRepository($pdo);
    }

    public function listar(Request $request, Response $response): Response
    {
        $q = trim((string) ($request->getQueryParams()['q'] ?? ''));
        return Json::resposta($response, ['data' => $this->usuarios->listar($q ?: null)]);
    }

    public function criar(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $pessoaId = (int) ($b['pessoa_id'] ?? 0);
        $email = strtolower(trim((string) ($b['email'] ?? '')));
        $senha = (string) ($b['senha'] ?? '');
        $perfil = (string) ($b['perfil'] ?? '');

        $erros = [];
        if (!$this->usuarios->pessoaExiste($pessoaId)) {
            $erros[] = 'pessoa_id inválida.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'email inválido.';
        }
        if (strlen($senha) < 10) {
            $erros[] = 'senha deve ter pelo menos 10 caracteres.';
        }
        if (!in_array($perfil, UsuarioRepository::PERFIS, true)) {
            $erros[] = 'perfil inválido.';
        }
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }

        try {
            $id = $this->usuarios->criar($pessoaId, $email, password_hash($senha, PASSWORD_DEFAULT), $perfil);
        } catch (\PDOException $e) {
            return $this->erroDuplicidade($response, $e);
        }

        return Json::resposta($response, ['data' => $this->usuarios->buscar($id)], 201);
    }

    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $alvo = $this->usuarios->buscar($id);
        if (!$alvo) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Usuário não encontrado.', 404);
        }

        $b = (array) $request->getParsedBody();
        $euMesmo = $id === (int) ($_SESSION['usuario']['id'] ?? 0);
        $dados = [];

        if (isset($b['email'])) {
            $email = strtolower(trim((string) $b['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return Json::erro($response, 'VALIDACAO', 'email inválido.', 400);
            }
            $dados['email'] = $email;
        }
        if (isset($b['perfil'])) {
            if (!in_array($b['perfil'], UsuarioRepository::PERFIS, true)) {
                return Json::erro($response, 'VALIDACAO', 'perfil inválido.', 400);
            }
            if ($euMesmo && $b['perfil'] !== 'admin') {
                return Json::erro($response, 'PROPRIO_USUARIO', 'Você não pode rebaixar o próprio perfil.', 422);
            }
            $dados['perfil'] = $b['perfil'];
        }
        if (isset($b['ativo'])) {
            if ($euMesmo && !$b['ativo']) {
                return Json::erro($response, 'PROPRIO_USUARIO', 'Você não pode desativar a própria conta.', 422);
            }
            $dados['ativo'] = $b['ativo'] ? 1 : 0;
        }

        try {
            $this->usuarios->atualizar($id, $dados);
        } catch (\PDOException $e) {
            return $this->erroDuplicidade($response, $e);
        }

        return Json::resposta($response, ['data' => $this->usuarios->buscar($id)]);
    }

    public function senha(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->usuarios->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Usuário não encontrado.', 404);
        }
        $body = (array) $request->getParsedBody();
        $senha = (string) ($body['senha'] ?? '');
        if (strlen($senha) < 10) {
            return Json::erro($response, 'VALIDACAO', 'senha deve ter pelo menos 10 caracteres.', 400);
        }
        $this->usuarios->definirSenha($id, password_hash($senha, PASSWORD_DEFAULT));
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    public function remover(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->usuarios->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Usuário não encontrado.', 404);
        }
        if ($id === (int) ($_SESSION['usuario']['id'] ?? 0)) {
            return Json::erro($response, 'PROPRIO_USUARIO', 'Você não pode desativar a própria conta.', 422);
        }
        $this->usuarios->desativar($id);
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    private function erroDuplicidade(Response $response, \PDOException $e): Response
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'uq_usuarios_pessoa')) {
            return Json::erro($response, 'PESSOA_COM_USUARIO', 'Esta pessoa já possui usuário.', 422);
        }
        if (str_contains($msg, 'Duplicate entry') || str_contains($msg, 'usuarios.email')) {
            return Json::erro($response, 'EMAIL_DUPLICADO', 'E-mail já cadastrado em outro usuário.', 422);
        }
        throw $e;
    }
}
