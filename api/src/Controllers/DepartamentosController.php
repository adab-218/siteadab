<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Validacao;
use Adab\Api\Repositories\DepartamentoRepository;
use Adab\Api\Repositories\MembroRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * M2 — Departamentos: CRUD + vínculo de membros.
 *
 * Escopo (D5): lider_departamento só vê/gerencia os departamentos que lidera.
 * Regra (D6): vínculo aceita apenas membros (FK + validação 422).
 */
final class DepartamentosController
{
    private DepartamentoRepository $deptos;
    private MembroRepository $membros;

    public function __construct(PDO $pdo)
    {
        $this->deptos = new DepartamentoRepository($pdo);
        $this->membros = new MembroRepository($pdo);
    }

    public function listar(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $apenasAtivos = null;
        if (isset($q['ativo'])) {
            $apenasAtivos = filter_var($q['ativo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($apenasAtivos === null) {
                return Json::erro($response, 'VALIDACAO', 'Parâmetro ativo deve ser true/false.', 400);
            }
        }

        $ids = null;
        $usuario = $_SESSION['usuario'];
        if ($usuario['perfil'] === 'lider_departamento') {
            $ids = $this->membros->departamentosLiderados((int) $usuario['pessoa_id']);
        }

        return Json::resposta($response, ['data' => $this->deptos->listar($ids, $apenasAtivos)]);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $depto = $this->deptos->buscar($id);
        if (!$depto) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Departamento não encontrado.', 404);
        }
        if (!$this->liderPodeVer($id)) {
            return Json::erro($response, 'SEM_PERMISSAO', 'Você não lidera este departamento.', 403);
        }
        $depto['membros'] = $this->deptos->membros($id);
        return Json::resposta($response, ['data' => $depto]);
    }

    public function criar(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $nome = trim((string) ($body['nome'] ?? ''));
        if ($nome === '') {
            return Json::erro($response, 'VALIDACAO', 'nome é obrigatório.', 400);
        }
        if (isset($body['lider_id']) && !$this->deptos->pessoaEhMembro((int) $body['lider_id'])) {
            return Json::erro($response, 'VALIDACAO', 'lider_id deve ser um membro.', 400);
        }

        try {
            $id = $this->deptos->criar($body + ['nome' => $nome]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return Json::erro($response, 'NOME_DUPLICADO', 'Já existe departamento com este nome.', 422);
            }
            throw $e;
        }

        return Json::resposta($response, ['data' => $this->deptos->buscar($id)], 201);
    }

    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->deptos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Departamento não encontrado.', 404);
        }

        $body = (array) $request->getParsedBody();
        if (array_key_exists('nome', $body) && trim((string) $body['nome']) === '') {
            return Json::erro($response, 'VALIDACAO', 'nome não pode ser vazio.', 400);
        }
        if (isset($body['lider_id']) && !$this->deptos->pessoaEhMembro((int) $body['lider_id'])) {
            return Json::erro($response, 'VALIDACAO', 'lider_id deve ser um membro.', 400);
        }
        if (isset($body['nome'])) {
            $body['nome'] = trim((string) $body['nome']);
        }

        try {
            $this->deptos->atualizar($id, $body);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return Json::erro($response, 'NOME_DUPLICADO', 'Já existe departamento com este nome.', 422);
            }
            throw $e;
        }

        return Json::resposta($response, ['data' => $this->deptos->buscar($id)]);
    }

    public function remover(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->deptos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Departamento não encontrado.', 404);
        }
        if ($this->deptos->contarMembrosAtivos($id) > 0) {
            return Json::erro($response, 'VINCULOS_ATIVOS',
                'Departamento tem membros ativos. Remova os vínculos antes de desativar.', 422);
        }
        $this->deptos->desativar($id);
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    public function adicionarMembro(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->deptos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Departamento não encontrado.', 404);
        }
        if (!$this->liderPodeGerenciar($id)) {
            return Json::erro($response, 'SEM_PERMISSAO', 'Você não lidera este departamento.', 403);
        }

        $body = (array) $request->getParsedBody();
        $pessoaId = (int) ($body['pessoa_id'] ?? 0);
        if (!$this->deptos->pessoaEhMembro($pessoaId)) {
            return Json::erro($response, 'VALIDACAO', 'pessoa_id deve ser um membro ativo (D6).', 400);
        }

        $dataInicio = $this->dataOuNull($body['data_inicio'] ?? null);
        $funcao = trim((string) ($body['funcao'] ?? '')) ?: null;

        try {
            $resultado = $this->deptos->adicionarMembro($id, $pessoaId, $funcao, $dataInicio);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'VINCULO_EXISTENTE') {
                return Json::erro($response, 'VINCULO_EXISTENTE', 'Membro já vinculado a este departamento.', 422);
            }
            throw $e;
        }

        $depto = $this->deptos->buscar($id);
        $depto['membros'] = $this->deptos->membros($id);
        $depto['vinculo'] = $resultado;
        return Json::resposta($response, ['data' => $depto], $resultado === 'inserido' ? 201 : 200);
    }

    public function removerMembro(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $pessoaId = (int) $args['pessoa_id'];
        if (!$this->deptos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Departamento não encontrado.', 404);
        }
        if (!$this->liderPodeGerenciar($id)) {
            return Json::erro($response, 'SEM_PERMISSAO', 'Você não lidera este departamento.', 403);
        }
        if (!$this->deptos->removerMembro($id, $pessoaId)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Vínculo ativo não encontrado.', 404);
        }
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // -----------------------------------------------------------------

    private function liderPodeVer(int $deptoId): bool
    {
        $usuario = $_SESSION['usuario'];
        if ($usuario['perfil'] !== 'lider_departamento') {
            return true;
        }
        return $this->deptos->lidera($deptoId, (int) $usuario['pessoa_id']);
    }

    /**
     * Escrita em vínculo: secretaria/admin sempre; lider_departamento
     * apenas no próprio departamento (D5). A rota já filtra por perfil;
     * aqui fica a verificação por registro.
     */
    private function liderPodeGerenciar(int $deptoId): bool
    {
        $usuario = $_SESSION['usuario'];
        if ($usuario['perfil'] !== 'lider_departamento') {
            return true;
        }
        return $this->deptos->lidera($deptoId, (int) $usuario['pessoa_id']);
    }

    private function dataOuNull(mixed $v): ?string
    {
        return Validacao::data($v);
    }
}
