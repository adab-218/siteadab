<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Validacao;
use Adab\Api\Repositories\DepartamentoRepository;
use Adab\Api\Repositories\EventoRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * M4 — Eventos: festividades/congressos/cultos especiais + programação
 * interna (atividades). publico=1 alimenta o site sem ação extra.
 * Soft delete (deleted_at). Escritas: secretaria/dirigente/admin.
 */
final class EventosController
{
    private EventoRepository $eventos;
    private DepartamentoRepository $pessoasCheck; // pessoaEhMembro

    public function __construct(PDO $pdo)
    {
        $this->eventos = new EventoRepository($pdo);
        $this->pessoasCheck = new DepartamentoRepository($pdo);
    }

    public function listar(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        [$pagina, $porPagina] = Json::parametrosPaginacao($q);

        $filtros = [
            'de' => $this->dataOuNull($q['de'] ?? null),
            'ate' => $this->dataOuNull($q['ate'] ?? null),
            'tipo' => in_array($q['tipo'] ?? '', EventoRepository::TIPOS, true) ? $q['tipo'] : '',
            'status' => in_array($q['status'] ?? '', EventoRepository::STATUS, true) ? $q['status'] : '',
        ];
        if (isset($q['publico'])) {
            $filtros['publico'] = filter_var($q['publico'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        $r = $this->eventos->listarPaginado($filtros, $pagina, $porPagina);
        return Json::paginada($response, $r['itens'], $r['total'], $pagina, $porPagina);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $evento = $this->eventos->buscar((int) $args['id']);
        if (!$evento) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Evento não encontrado.', 404);
        }
        $evento['atividades'] = $this->eventos->listarAtividades($evento['id']);
        return Json::resposta($response, ['data' => $evento]);
    }

    public function criar(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $erros = $this->validar($b);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }
        $id = $this->eventos->criar(
            $this->normalizar($b),
            (int) ($_SESSION['usuario']['id'] ?? 0) ?: null
        );
        return Json::resposta($response, ['data' => $this->eventos->buscar($id)], 201);
    }

    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->eventos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Evento não encontrado.', 404);
        }
        $b = (array) $request->getParsedBody();
        $erros = $this->validar($b, parcial: true, atual: $id);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }
        $this->eventos->atualizar($id, $this->normalizar($b, parcial: true));
        return Json::resposta($response, ['data' => $this->eventos->buscar($id)]);
    }

    public function remover(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->eventos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Evento não encontrado.', 404);
        }
        $this->eventos->remover($id);
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // ----- atividades -----

    public function criarAtividade(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->eventos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Evento não encontrado.', 404);
        }
        $b = (array) $request->getParsedBody();
        $erros = $this->validarAtividade($b);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }
        $aid = $this->eventos->criarAtividade($id, $this->normalizarAtividade($b));
        return Json::resposta($response, ['data' => $this->eventos->buscarAtividade($aid)], 201);
    }

    public function atualizarAtividade(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $aid = (int) $args['aid'];
        $atividade = $this->eventos->buscarAtividade($aid);
        if (!$this->eventos->buscar($id) || !$atividade || (int) $atividade['evento_id'] !== $id) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Atividade não encontrada.', 404);
        }
        $b = (array) $request->getParsedBody();
        if (isset($b['titulo']) && trim((string) $b['titulo']) === '') {
            return Json::erro($response, 'VALIDACAO', 'titulo não pode ser vazio.', 400);
        }
        if (!empty($b['responsavel_id']) && !$this->pessoasCheck->pessoaEhMembro((int) $b['responsavel_id'])) {
            return Json::erro($response, 'VALIDACAO', 'responsavel_id deve ser um membro.', 400);
        }
        $dados = array_intersect_key($this->normalizarAtividade($b), $b);
        $this->eventos->atualizarAtividade($aid, $dados);
        return Json::resposta($response, ['data' => $this->eventos->buscarAtividade($aid)]);
    }

    public function removerAtividade(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $aid = (int) $args['aid'];
        $atividade = $this->eventos->buscarAtividade($aid);
        if (!$atividade || (int) $atividade['evento_id'] !== $id) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Atividade não encontrada.', 404);
        }
        $this->eventos->removerAtividade($aid);
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // -----------------------------------------------------------------

    /** @return string[] */
    private function validar(array $b, bool $parcial = false, ?int $atual = null): array
    {
        $erros = [];
        if (!$parcial || array_key_exists('nome', $b)) {
            if (trim((string) ($b['nome'] ?? '')) === '') {
                $erros[] = 'nome é obrigatório.';
            } elseif (!Validacao::maxLen($b['nome'], 160)) {
                $erros[] = 'nome deve ter até 160 caracteres.';
            }
        }
        if (isset($b['tipo']) && !in_array($b['tipo'], EventoRepository::TIPOS, true)) {
            $erros[] = 'tipo inválido.';
        }
        if (isset($b['status']) && !in_array($b['status'], EventoRepository::STATUS, true)) {
            $erros[] = 'status inválido.';
        }
        if (!$parcial && !$this->dataOuNull($b['data_inicio'] ?? null)) {
            $erros[] = 'data_inicio é obrigatória (yyyy-mm-dd).';
        }
        // array_key_exists: data_inicio=null/'' explícito também é inválida
        // (coluna NOT NULL — null passaria a validação e estouraria no banco)
        if (array_key_exists('data_inicio', $b) && !$this->dataOuNull($b['data_inicio'])) {
            $erros[] = 'data_inicio inválida.';
        }
        if (isset($b['data_fim']) && $b['data_fim'] !== null && $b['data_fim'] !== '' && !$this->dataOuNull($b['data_fim'])) {
            $erros[] = 'data_fim inválida.';
        }
        // coerência inicio ≤ fim (considera valor atual em parcial)
        $atualRow = $parcial && $atual ? $this->eventos->buscar($atual) : null;
        $ini = $this->dataOuNull($b['data_inicio'] ?? ($atualRow['data_inicio'] ?? null));
        $fim = isset($b['data_fim']) ? $this->dataOuNull($b['data_fim']) : ($atualRow['data_fim'] ?? null);
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
            'tipo' => $b['tipo'] ?? 'outro',
            'data_inicio' => $this->dataOuNull($b['data_inicio'] ?? null),
            'data_fim' => $this->dataOuNull($b['data_fim'] ?? null),
            'local' => trim((string) ($b['local'] ?? '')) ?: null,
            'descricao' => trim((string) ($b['descricao'] ?? '')) ?: null,
            'banner_url' => trim((string) ($b['banner_url'] ?? '')) ?: null,
            'publico' => !empty($b['publico']) ? 1 : 0,
            'status' => $b['status'] ?? 'planejado',
        ];
        return $parcial ? array_intersect_key($dados, $b) : $dados;
    }

    /** @return string[] */
    private function validarAtividade(array $b): array
    {
        $erros = [];
        if (trim((string) ($b['titulo'] ?? '')) === '') {
            $erros[] = 'titulo é obrigatório.';
        } elseif (!Validacao::maxLen($b['titulo'], 200)) {
            $erros[] = 'titulo deve ter até 200 caracteres.';
        }
        if (!$this->dataOuNull($b['data'] ?? null)) {
            $erros[] = 'data é obrigatória (yyyy-mm-dd).';
        }
        if (!empty($b['responsavel_id']) && !$this->pessoasCheck->pessoaEhMembro((int) $b['responsavel_id'])) {
            $erros[] = 'responsavel_id deve ser um membro.';
        }
        return $erros;
    }

    /** @return array<string,mixed> */
    private function normalizarAtividade(array $b): array
    {
        return [
            'titulo' => trim((string) ($b['titulo'] ?? '')),
            'data' => $this->dataOuNull($b['data'] ?? null),
            'horario_inicio' => $this->horaOuNull($b['horario_inicio'] ?? null),
            'horario_fim' => $this->horaOuNull($b['horario_fim'] ?? null),
            'responsavel_id' => is_numeric($b['responsavel_id'] ?? null) ? (int) $b['responsavel_id'] : null,
            'descricao' => trim((string) ($b['descricao'] ?? '')) ?: null,
        ];
    }

    private function dataOuNull(mixed $v): ?string
    {
        return Validacao::data($v);
    }

    private function horaOuNull(mixed $v): ?string
    {
        return Validacao::hora($v);
    }
}
