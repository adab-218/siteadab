<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Uploads;
use Adab\Api\Validacao;
use Adab\Api\Repositories\MembroRepository;
use Adab\Api\Repositories\PessoaRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * M1 — CRUD de membros (pessoa + ficha de membro).
 *
 * Escopo (D5): lider_departamento só vê membros dos departamentos que lidera.
 * Desvinculação (D1): mudar status p/ fora de 'ativo' encerra vínculos.
 */
final class MembrosController
{
    private PessoaRepository $pessoas;
    private MembroRepository $membros;
    private Uploads $uploads;

    public function __construct(private PDO $pdo, string $uploadsDir)
    {
        $this->pessoas = new PessoaRepository($pdo);
        $this->membros = new MembroRepository($pdo);
        $this->uploads = new Uploads($uploadsDir);
    }

    public function listar(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        [$pagina, $porPagina] = Json::parametrosPaginacao($q);

        $filtros = [
            'q' => trim((string) ($q['q'] ?? '')),
            'status' => trim((string) ($q['status'] ?? '')),
            'cargo_id' => $q['cargo_id'] ?? null,
            'departamento_id' => $q['departamento_id'] ?? null,
        ];
        if ($filtros['status'] !== '' && !in_array($filtros['status'], MembroRepository::statusValidos(), true)) {
            return Json::erro($response, 'VALIDACAO', 'Status inválido.', 400);
        }

        // D5: líder só enxerga membros dos próprios departamentos
        $usuario = $_SESSION['usuario'];
        if ($usuario['perfil'] === 'lider_departamento') {
            $filtros['departamento_ids'] = $this->membros->departamentosLiderados((int) $usuario['pessoa_id']);
            if ($filtros['departamento_ids'] === []) {
                return Json::paginada($response, [], 0, $pagina, $porPagina);
            }
        }

        $r = $this->membros->listarPaginado($filtros, $pagina, $porPagina);
        return Json::paginada($response, $r['itens'], $r['total'], $pagina, $porPagina);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $membro = $this->membros->buscarComPessoa($id);
        if (!$membro) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Membro não encontrado.', 404);
        }

        if (!$this->liderPodeVer($id)) {
            return Json::erro($response, 'SEM_PERMISSAO', 'Membro fora do seu departamento.', 403);
        }

        $membro['departamentos'] = $this->membros->departamentosDoMembro($id);
        return Json::resposta($response, ['data' => $membro]);
    }

    public function criar(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $pessoa = (array) ($body['pessoa'] ?? []);
        $membro = (array) ($body['membro'] ?? []);

        $erros = array_merge(
            $this->validarPessoa($pessoa),
            $this->validarMembro($membro)
        );
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }

        $uid = (int) ($_SESSION['usuario']['id'] ?? 0) ?: null;
        $this->pdo->beginTransaction();
        try {
            $pessoaId = $this->pessoas->criar($pessoa, $uid);
            $this->membros->criar($pessoaId, $membro, $uid);
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            if (str_contains($e->getMessage(), 'uq_pessoas_cpf')) {
                return Json::erro($response, 'CPF_DUPLICADO', 'Já existe pessoa cadastrada com este CPF.', 422);
            }
            if (str_contains($e->getMessage(), 'numero_carteirinha')) {
                return Json::erro($response, 'CARTEIRINHA_DUPLICADA', 'Número de carteirinha já em uso.', 422);
            }
            throw $e;
        }

        return Json::resposta($response, ['data' => $this->membros->buscarComPessoa($pessoaId)], 201);
    }

    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->membros->buscarComPessoa($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Membro não encontrado.', 404);
        }

        $body = (array) $request->getParsedBody();
        $pessoa = (array) ($body['pessoa'] ?? []);
        $membro = (array) ($body['membro'] ?? []);

        $erros = array_merge(
            $this->validarPessoa($pessoa, parcial: true),
            $this->validarMembro($membro)
        );
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }

        try {
            $this->pdo->beginTransaction();
            $this->pessoas->atualizar($id, $pessoa);
            $this->membros->atualizar($id, $membro);
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            if (str_contains($e->getMessage(), 'uq_pessoas_cpf')) {
                return Json::erro($response, 'CPF_DUPLICADO', 'Já existe pessoa cadastrada com este CPF.', 422);
            }
            throw $e;
        }

        return Json::resposta($response, ['data' => $this->membros->buscarComPessoa($id)]);
    }

    public function status(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->membros->buscarComPessoa($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Membro não encontrado.', 404);
        }

        $body = (array) $request->getParsedBody();
        $status = (string) ($body['status'] ?? '');
        if (!in_array($status, MembroRepository::statusValidos(), true)) {
            return Json::erro($response, 'VALIDACAO',
                'Status inválido: ' . implode(', ', MembroRepository::statusValidos()) . '.', 400);
        }
        $dataStatus = $this->dataOuNull($body['data_status'] ?? null);
        if (array_key_exists('data_status', $body) && $body['data_status'] !== null
            && $body['data_status'] !== '' && $dataStatus === null) {
            return Json::erro($response, 'VALIDACAO', 'data_status inválida (yyyy-mm-dd).', 400);
        }

        $this->membros->mudarStatus($id, $status, $dataStatus);
        return Json::resposta($response, ['data' => $this->membros->buscarComPessoa($id)]);
    }

    /** GET /membros/aniversariantes?mes=N — padrão: mês corrente. */
    public function aniversariantes(Request $request, Response $response): Response
    {
        $mes = (int) ($request->getQueryParams()['mes'] ?? date('n'));
        if ($mes < 1 || $mes > 12) {
            return Json::erro($response, 'VALIDACAO', 'mes deve ser 1-12.', 400);
        }
        return Json::resposta($response, ['data' => [
            'mes' => $mes,
            'itens' => $this->membros->aniversariantes($mes),
        ]]);
    }

    public function remover(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->membros->buscarComPessoa($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Membro não encontrado.', 404);
        }
        $this->membros->softDelete($id);
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // ----- foto do membro -----

    public function foto(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $membro = $this->membros->buscarComPessoa($id);
        if (!$membro) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Membro não encontrado.', 404);
        }
        $arquivo = ($request->getUploadedFiles())['arquivo'] ?? null;
        if (!$arquivo) {
            return Json::erro($response, 'VALIDACAO', 'Envie a imagem no campo "arquivo".', 400);
        }
        try {
            $url = $this->uploads->salvarImagem($arquivo, 'membros', "membro_{$id}");
        } catch (\RuntimeException $e) {
            return Json::erro($response, $e->getMessage(), Uploads::mensagem($e->getMessage()), 400);
        }
        $this->uploads->remover($membro['foto_url']);
        $this->pessoas->atualizar($id, ['foto_url' => $url]);
        return Json::resposta($response, ['data' => $this->membros->buscarComPessoa($id)]);
    }

    public function removerFoto(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $membro = $this->membros->buscarComPessoa($id);
        if (!$membro) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Membro não encontrado.', 404);
        }
        $this->uploads->remover($membro['foto_url']);
        $this->pessoas->atualizar($id, ['foto_url' => null]);
        return Json::resposta($response, ['data' => $this->membros->buscarComPessoa($id)]);
    }

    // -----------------------------------------------------------------

    /** D5: líder só acessa membro dentro dos departamentos que lidera. */
    private function liderPodeVer(int $pessoaId): bool
    {
        $usuario = $_SESSION['usuario'];
        if ($usuario['perfil'] !== 'lider_departamento') {
            return true;
        }
        $deptos = $this->membros->departamentosLiderados((int) $usuario['pessoa_id']);
        return $this->membros->pertenceAAlgumDepartamento($pessoaId, $deptos);
    }

    /** @return string[] erros de validação */
    private function validarPessoa(array $p, bool $parcial = false): array
    {
        $erros = [];
        if (!$parcial || array_key_exists('nome_completo', $p)) {
            if (trim((string) ($p['nome_completo'] ?? '')) === '') {
                $erros[] = 'nome_completo é obrigatório.';
            }
        }
        if (isset($p['sexo']) && $p['sexo'] !== null && !in_array($p['sexo'], ['M', 'F'], true)) {
            $erros[] = 'sexo deve ser M ou F.';
        }
        if (!empty($p['email']) && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'email inválido.';
        }
        if (!empty($p['uf']) && !preg_match('/^[A-Z]{2}$/', $p['uf'])) {
            $erros[] = 'uf deve ter 2 letras maiúsculas.';
        }
        if (!empty($p['cpf']) && !$this->cpfValido((string) $p['cpf'])) {
            $erros[] = 'cpf inválido.';
        }
        if (isset($p['data_nascimento']) && $p['data_nascimento'] !== null
            && $p['data_nascimento'] !== '' && !$this->dataOuNull($p['data_nascimento'])) {
            $erros[] = 'data_nascimento inválida (yyyy-mm-dd).';
        }
        if (!empty($p['conjuge_id']) && !$this->pessoas->buscar((int) $p['conjuge_id'])) {
            $erros[] = 'conjuge_id inexistente.';
        }
        foreach ([
            'nome_completo' => 200, 'nome_social' => 200, 'estado_civil' => 20,
            'cpf' => 14, 'telefone' => 30, 'email' => 120, 'cep' => 9,
            'logradouro' => 200, 'numero' => 20, 'complemento' => 100,
            'bairro' => 100, 'cidade' => 100, 'foto_url' => 255,
        ] as $campo => $max) {
            if (isset($p[$campo]) && !Validacao::maxLen($p[$campo], $max)) {
                $erros[] = "{$campo} deve ter até {$max} caracteres.";
            }
        }
        return $erros;
    }

    /** @return string[] erros de validação da ficha de membro */
    private function validarMembro(array $m): array
    {
        $erros = [];
        if (isset($m['status']) && !in_array($m['status'], MembroRepository::statusValidos(), true)) {
            $erros[] = 'status inválido.';
        }
        foreach (['data_conversao', 'data_batismo', 'data_status'] as $campo) {
            if (isset($m[$campo]) && $m[$campo] !== null && $m[$campo] !== ''
                && !$this->dataOuNull($m[$campo])) {
                $erros[] = "{$campo} inválida (yyyy-mm-dd).";
            }
        }
        if (!empty($m['cargo_id']) && !$this->membros->cargoExiste((int) $m['cargo_id'])) {
            $erros[] = 'cargo_id inexistente.';
        }
        foreach ([
            'numero_carteirinha' => 30, 'congregacao' => 120, 'procedencia' => 200,
        ] as $campo => $max) {
            if (isset($m[$campo]) && !Validacao::maxLen($m[$campo], $max)) {
                $erros[] = "{$campo} deve ter até {$max} caracteres.";
            }
        }
        return $erros;
    }

    private function cpfValido(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        foreach ([9, 10] as $pos) {
            $soma = 0;
            for ($i = 0; $i < $pos; $i++) {
                $soma += (int) $cpf[$i] * ($pos + 1 - $i);
            }
            $digito = (11 - ($soma % 11)) % 11 % 10;
            if ((int) $cpf[$pos] !== $digito) {
                return false;
            }
        }
        return true;
    }

    private function dataOuNull(mixed $v): ?string
    {
        return Validacao::data($v);
    }
}
