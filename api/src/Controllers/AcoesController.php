<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Repositories\AcaoRepository;
use Adab\Api\Repositories\UsuarioRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * M5 — Ações sociais/evangelísticas + voluntários (pessoas) +
 * beneficiários (texto livre). Soft delete.
 * Escritas: secretaria/dirigente/admin.
 */
final class AcoesController
{
    private AcaoRepository $acoes;
    private UsuarioRepository $usuarios; // pessoaExiste

    public function __construct(PDO $pdo)
    {
        $this->acoes = new AcaoRepository($pdo);
        $this->usuarios = new UsuarioRepository($pdo);
    }

    public function listar(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        [$pagina, $porPagina] = Json::parametrosPaginacao($q);

        $filtros = [
            'de' => $this->dataOuNull($q['de'] ?? null),
            'ate' => $this->dataOuNull($q['ate'] ?? null),
            'tipo' => in_array($q['tipo'] ?? '', AcaoRepository::TIPOS, true) ? $q['tipo'] : '',
            'status' => in_array($q['status'] ?? '', AcaoRepository::STATUS, true) ? $q['status'] : '',
            'departamento_id' => $q['departamento_id'] ?? null,
        ];
        $r = $this->acoes->listarPaginado($filtros, $pagina, $porPagina);
        return Json::paginada($response, $r['itens'], $r['total'], $pagina, $porPagina);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $acao = $this->acoes->buscar((int) $args['id']);
        if (!$acao) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Ação não encontrada.', 404);
        }
        $acao['voluntarios'] = $this->acoes->listarVoluntarios($acao['id']);
        $acao['beneficiarios'] = $this->acoes->listarBeneficiarios($acao['id']);
        return Json::resposta($response, ['data' => $acao]);
    }

    public function criar(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $erros = $this->validar($b);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }
        $id = $this->acoes->criar(
            $this->normalizar($b),
            (int) ($_SESSION['usuario']['id'] ?? 0) ?: null
        );
        return Json::resposta($response, ['data' => $this->acoes->buscar($id)], 201);
    }

    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $acaoAtual = $this->acoes->buscar($id);
        if (!$acaoAtual) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Ação não encontrada.', 404);
        }
        $b = (array) $request->getParsedBody();
        $erros = $this->validar($b, parcial: true, atual: $acaoAtual);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }
        $this->acoes->atualizar($id, $this->normalizar($b, parcial: true));
        return Json::resposta($response, ['data' => $this->acoes->buscar($id)]);
    }

    public function remover(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->acoes->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Ação não encontrada.', 404);
        }
        $this->acoes->remover($id);
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // ----- voluntários -----

    public function vincularVoluntario(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->acoes->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Ação não encontrada.', 404);
        }
        $b = (array) $request->getParsedBody();
        $pessoaId = (int) ($b['pessoa_id'] ?? 0);
        $papel = $b['papel'] ?? 'voluntario';

        if (!$this->usuarios->pessoaExiste($pessoaId)) {
            return Json::erro($response, 'VALIDACAO', 'pessoa_id inválida.', 400);
        }
        if (!in_array($papel, AcaoRepository::PAPEIS, true)) {
            return Json::erro($response, 'VALIDACAO', 'papel inválido (coordenador|voluntario).', 400);
        }
        $this->acoes->vincularVoluntario($id, $pessoaId, $papel);
        return Json::resposta($response, ['data' => $this->acoes->listarVoluntarios($id)], 201);
    }

    public function desvincularVoluntario(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->acoes->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Ação não encontrada.', 404);
        }
        $this->acoes->desvincularVoluntario($id, (int) $args['pessoa_id']);
        return Json::resposta($response, ['data' => $this->acoes->listarVoluntarios($id)]);
    }

    // ----- beneficiários -----

    public function criarBeneficiario(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->acoes->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Ação não encontrada.', 404);
        }
        $b = (array) $request->getParsedBody();
        if (trim((string) ($b['nome'] ?? '')) === '') {
            return Json::erro($response, 'VALIDACAO', 'nome é obrigatório.', 400);
        }
        $bid = $this->acoes->criarBeneficiario($id, [
            'nome' => trim((string) $b['nome']),
            'contato' => trim((string) ($b['contato'] ?? '')) ?: null,
            'observacao' => trim((string) ($b['observacao'] ?? '')) ?: null,
        ]);
        return Json::resposta($response, ['data' => $this->acoes->listarBeneficiarios($id)], 201);
    }

    public function removerBeneficiario(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->acoes->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Ação não encontrada.', 404);
        }
        $this->acoes->removerBeneficiario((int) $args['bid']);
        return Json::resposta($response, ['data' => $this->acoes->listarBeneficiarios($id)]);
    }

    // -----------------------------------------------------------------

    /** @return string[] */
    private function validar(array $b, bool $parcial = false, ?array $atual = null): array
    {
        $erros = [];
        if (!$parcial || array_key_exists('nome', $b)) {
            if (trim((string) ($b['nome'] ?? '')) === '') {
                $erros[] = 'nome é obrigatório.';
            }
        }
        if (isset($b['tipo']) && !in_array($b['tipo'], AcaoRepository::TIPOS, true)) {
            $erros[] = 'tipo inválido.';
        }
        if (isset($b['status']) && !in_array($b['status'], AcaoRepository::STATUS, true)) {
            $erros[] = 'status inválido.';
        }
        foreach (['data_inicio', 'data_fim'] as $campo) {
            if (isset($b[$campo]) && $b[$campo] !== null && $b[$campo] !== '' && !$this->dataOuNull($b[$campo])) {
                $erros[] = "{$campo} inválida (yyyy-mm-dd).";
            }
        }
        $ini = $this->dataOuNull($b['data_inicio'] ?? ($atual['data_inicio'] ?? null));
        $fim = array_key_exists('data_fim', $b)
            ? $this->dataOuNull($b['data_fim'])
            : ($atual['data_fim'] ?? null);
        if ($ini && $fim && $fim < $ini) {
            $erros[] = 'data_fim não pode ser anterior a data_inicio.';
        }
        return $erros;
    }

    /** @return array<string,mixed> */
    private function normalizar(array $b, bool $parcial = false): array
    {
        $dados = [
            'nome' => trim((string) ($b['nome'] ?? '')),
            'tipo' => $b['tipo'] ?? 'outra',
            'data_inicio' => $this->dataOuNull($b['data_inicio'] ?? null),
            'data_fim' => $this->dataOuNull($b['data_fim'] ?? null),
            'local' => trim((string) ($b['local'] ?? '')) ?: null,
            'objetivo' => trim((string) ($b['objetivo'] ?? '')) ?: null,
            'resultado' => trim((string) ($b['resultado'] ?? '')) ?: null,
            'departamento_id' => is_numeric($b['departamento_id'] ?? null) ? (int) $b['departamento_id'] : null,
            'status' => $b['status'] ?? 'planejada',
        ];
        return $parcial ? array_intersect_key($dados, $b) : $dados;
    }

    private function dataOuNull(mixed $v): ?string
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
