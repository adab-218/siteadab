<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Repositories\CultoRepository;
use Adab\Api\Repositories\DepartamentoRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * M3 — Cultos: agenda (cultos) + catálogo (tipos_culto).
 * uq_cultos_tipo_data: duplicidade tipo+data → 422 CULTO_DUPLICADO.
 * Escritas: secretaria/dirigente/admin; tipos: secretaria/admin.
 */
final class CultosController
{
    private CultoRepository $cultos;
    private DepartamentoRepository $pessoasCheck;

    public function __construct(PDO $pdo)
    {
        $this->cultos = new CultoRepository($pdo);
        $this->pessoasCheck = new DepartamentoRepository($pdo); // pessoaEhMembro
    }

    // ----- tipos_culto -----

    public function listarTipos(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $apenasAtivos = null;
        if (isset($q['ativo'])) {
            $apenasAtivos = filter_var($q['ativo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($apenasAtivos === null) {
                return Json::erro($response, 'VALIDACAO', 'Parâmetro ativo deve ser true/false.', 400);
            }
        }
        return Json::resposta($response, ['data' => $this->cultos->listarTipos($apenasAtivos)]);
    }

    public function criarTipo(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $nome = trim((string) ($b['nome'] ?? ''));
        if ($nome === '') {
            return Json::erro($response, 'VALIDACAO', 'nome é obrigatório.', 400);
        }
        $diaSemana = $this->intOuNull($b['dia_semana_sugerido'] ?? null);
        if ($diaSemana !== null && ($diaSemana < 0 || $diaSemana > 6)) {
            return Json::erro($response, 'VALIDACAO', 'dia_semana_sugerido deve ser 0-6.', 400);
        }
        $horario = $this->horaOuNull($b['horario_padrao'] ?? null);

        try {
            $id = $this->cultos->criarTipo([
                'nome' => $nome,
                'dia_semana_sugerido' => $diaSemana,
                'horario_padrao' => $horario,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return Json::erro($response, 'NOME_DUPLICADO', 'Tipo de culto já existe.', 422);
            }
            throw $e;
        }
        return Json::resposta($response, ['data' => $this->cultos->buscarTipo($id)], 201);
    }

    public function atualizarTipo(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->cultos->buscarTipo($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Tipo não encontrado.', 404);
        }
        $b = (array) $request->getParsedBody();
        if (array_key_exists('nome', $b) && trim((string) $b['nome']) === '') {
            return Json::erro($response, 'VALIDACAO', 'nome não pode ser vazio.', 400);
        }
        if (isset($b['nome'])) {
            $b['nome'] = trim((string) $b['nome']);
        }
        if (isset($b['horario_padrao'])) {
            $b['horario_padrao'] = $this->horaOuNull($b['horario_padrao']);
        }
        try {
            $this->cultos->atualizarTipo($id, $b);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return Json::erro($response, 'NOME_DUPLICADO', 'Tipo de culto já existe.', 422);
            }
            throw $e;
        }
        return Json::resposta($response, ['data' => $this->cultos->buscarTipo($id)]);
    }

    public function desativarTipo(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->cultos->buscarTipo($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Tipo não encontrado.', 404);
        }
        $this->cultos->atualizarTipo($id, ['ativo' => 0]);
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // ----- cultos -----

    public function listar(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        [$pagina, $porPagina] = Json::parametrosPaginacao($q);

        $filtros = [
            'de' => $this->dataOuNull($q['de'] ?? null),
            'ate' => $this->dataOuNull($q['ate'] ?? null),
            'tipo_culto_id' => $q['tipo_culto_id'] ?? null,
        ];
        $r = $this->cultos->listarPaginado($filtros, $pagina, $porPagina);
        return Json::paginada($response, $r['itens'], $r['total'], $pagina, $porPagina);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $culto = $this->cultos->buscar((int) $args['id']);
        if (!$culto) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Culto não encontrado.', 404);
        }
        return Json::resposta($response, ['data' => $culto]);
    }

    public function criar(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $erros = $this->validarCulto($b);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }

        $dados = $this->normalizarCulto($b);
        $dados['created_by'] = (int) ($_SESSION['usuario']['id'] ?? 0) ?: null;
        try {
            $id = $this->cultos->criar($dados, $dados['created_by']);
        } catch (\PDOException $e) {
            return $this->erroCulto($response, $e);
        }
        return Json::resposta($response, ['data' => $this->cultos->buscar($id)], 201);
    }

    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->cultos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Culto não encontrado.', 404);
        }
        $b = (array) $request->getParsedBody();
        $erros = $this->validarCulto($b, parcial: true);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }
        try {
            $this->cultos->atualizar($id, $this->normalizarCulto($b, parcial: true));
        } catch (\PDOException $e) {
            return $this->erroCulto($response, $e);
        }
        return Json::resposta($response, ['data' => $this->cultos->buscar($id)]);
    }

    public function remover(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->cultos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Culto não encontrado.', 404);
        }
        $this->cultos->remover($id);
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // ----- presença nominal -----

    public function listarPresencas(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->cultos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Culto não encontrado.', 404);
        }
        return Json::resposta($response, ['data' => $this->cultos->listarPresencas($id)]);
    }

    public function adicionarPresenca(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->cultos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Culto não encontrado.', 404);
        }
        $pessoaId = (int) (((array) $request->getParsedBody())['pessoa_id'] ?? 0);
        if (!$this->pessoasCheck->pessoaEhMembro($pessoaId)) {
            return Json::erro($response, 'VALIDACAO', 'pessoa_id deve ser um membro.', 400);
        }
        $this->cultos->adicionarPresenca($id, $pessoaId);
        return Json::resposta($response, ['data' => $this->cultos->listarPresencas($id)], 201);
    }

    public function removerPresenca(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->cultos->buscar($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Culto não encontrado.', 404);
        }
        $this->cultos->removerPresenca($id, (int) $args['pessoa_id']);
        return Json::resposta($response, ['data' => $this->cultos->listarPresencas($id)]);
    }

    // -----------------------------------------------------------------

    /** @return string[] */
    private function validarCulto(array $b, bool $parcial = false): array
    {
        $erros = [];
        if (!$parcial || array_key_exists('tipo_culto_id', $b)) {
            $tipo = (int) ($b['tipo_culto_id'] ?? 0);
            if (!$this->cultos->tipoExisteEAtivo($tipo)) {
                $erros[] = 'tipo_culto_id inválido ou inativo.';
            }
        }
        if (!$parcial || array_key_exists('data', $b)) {
            if (!$this->dataOuNull($b['data'] ?? null)) {
                $erros[] = 'data é obrigatória (yyyy-mm-dd).';
            }
        }
        foreach (['dirigente_id', 'pregador_id'] as $campo) {
            if (!empty($b[$campo]) && !$this->pessoasCheck->pessoaEhMembro((int) $b[$campo])) {
                $erros[] = "{$campo} deve ser um membro.";
            }
        }
        foreach (['total_presentes', 'total_visitantes', 'total_decisoes'] as $campo) {
            if (isset($b[$campo]) && (!is_numeric($b[$campo]) || (int) $b[$campo] < 0)) {
                $erros[] = "{$campo} deve ser número ≥ 0.";
            }
        }
        return $erros;
    }

    /** @return array<string,mixed> */
    private function normalizarCulto(array $b, bool $parcial = false): array
    {
        $dados = [
            'tipo_culto_id' => (int) ($b['tipo_culto_id'] ?? 0),
            'data' => $this->dataOuNull($b['data'] ?? null),
            'horario_inicio' => $this->horaOuNull($b['horario_inicio'] ?? null),
            'dirigente_id' => $this->intOuNull($b['dirigente_id'] ?? null),
            'pregador_id' => $this->intOuNull($b['pregador_id'] ?? null),
            'tema' => trim((string) ($b['tema'] ?? '')) ?: null,
            'total_presentes' => $this->intOuNull($b['total_presentes'] ?? null),
            'total_visitantes' => $this->intOuNull($b['total_visitantes'] ?? null),
            'total_decisoes' => $this->intOuNull($b['total_decisoes'] ?? null),
            'observacoes' => trim((string) ($b['observacoes'] ?? '')) ?: null,
        ];
        if ($parcial) {
            // PUT parcial: só envia o que veio no body
            return array_intersect_key($dados, $b);
        }
        return $dados;
    }

    private function erroCulto(Response $response, \PDOException $e): Response
    {
        if (str_contains($e->getMessage(), 'uq_cultos_tipo_data')) {
            return Json::erro($response, 'CULTO_DUPLICADO',
                'Já existe culto deste tipo nesta data.', 422);
        }
        throw $e;
    }

    private function dataOuNull(mixed $v): ?string
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private function horaOuNull(mixed $v): ?string
    {
        $v = trim((string) $v);
        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $v) ? $v : null;
    }

    private function intOuNull(mixed $v): ?int
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
        return (int) $v;
    }
}
