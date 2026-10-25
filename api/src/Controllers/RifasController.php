<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Validacao;
use Adab\Api\Repositories\DepartamentoRepository;
use Adab\Api\Repositories\RifaRepository;
use Adab\Api\Repositories\UsuarioRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * M7 — Sorteios beneficentes (doc 13): rifa multiprêmios com prêmios
 * doados por departamento, repartição percentual da arrecadação, links
 * de divulgação por membro e bilhetes com PIX manual (pendente expira
 * em 24h — liberação preguiçosa no repositório).
 * Escritas: secretaria/admin. Ciclo de vida: secretaria/admin.
 */
final class RifasController
{
    private RifaRepository $rifas;
    private DepartamentoRepository $deptos;
    private UsuarioRepository $usuarios;

    public function __construct(PDO $pdo)
    {
        $this->rifas = new RifaRepository($pdo);
        $this->deptos = new DepartamentoRepository($pdo);
        $this->usuarios = new UsuarioRepository($pdo);
    }

    private function uid(): ?int
    {
        return (int) ($_SESSION['usuario']['id'] ?? 0) ?: null;
    }

    // ==================== rifas ====================

    public function listar(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        [$pagina, $porPagina] = Json::parametrosPaginacao($q, 100);
        $filtros = [
            'status' => in_array($q['status'] ?? '', RifaRepository::STATUS, true) ? $q['status'] : '',
            'q' => trim((string) ($q['q'] ?? '')),
        ];
        $r = $this->rifas->listarPaginado($filtros, $pagina, $porPagina);
        return Json::paginada($response, $r['itens'], $r['total'], $pagina, $porPagina);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $rifa = $this->rifas->detalhe((int) $args['id']);
        if (!$rifa) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Rifa não encontrada.', 404);
        }
        return Json::resposta($response, ['data' => $rifa]);
    }

    public function criar(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $erros = $this->validar($b);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }
        $id = $this->rifas->criar($this->normalizar($b), $this->uid());
        return Json::resposta($response, ['data' => $this->rifas->buscar($id)], 201);
    }

    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $atual = $this->rifas->buscar($id);
        if (!$atual) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Rifa não encontrada.', 404);
        }
        if (in_array($atual['status'], ['sorteado', 'cancelado'], true)) {
            return Json::erro($response, 'IMUTAVEL', 'Rifa sorteada ou cancelada não pode ser editada.', 422);
        }
        $b = (array) $request->getParsedBody();
        $erros = $this->validar($b, parcial: true);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }
        $this->rifas->atualizar($id, $this->normalizar($b, parcial: true));
        return Json::resposta($response, ['data' => $this->rifas->buscar($id)]);
    }

    public function remover(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $rifa = $this->rifas->buscar($id);
        if (!$rifa) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Rifa não encontrada.', 404);
        }
        if ($rifa['status'] !== 'rascunho') {
            return Json::erro($response, 'STATUS_INVALIDO', 'Só rifas em rascunho podem ser excluídas. Use cancelar.', 422);
        }
        $this->rifas->softDelete($id);
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // ==================== ciclo de vida ====================

    public function ativar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $rifa = $this->rifas->buscar($id);
        if (!$rifa) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Rifa não encontrada.', 404);
        }
        if ($rifa['status'] !== 'rascunho') {
            return Json::erro($response, 'STATUS_INVALIDO', 'Só rifas em rascunho podem ser ativadas.', 422);
        }
        $chk = $this->rifas->podeAtivar($id);
        if (!$chk['ok']) {
            return Json::erro($response, $chk['code'] ?? 'VALIDACAO', $chk['erro'], 422);
        }
        $this->rifas->mudarStatus($id, 'ativo');
        return Json::resposta($response, ['data' => $this->rifas->buscar($id)]);
    }

    public function encerrar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $rifa = $this->rifas->buscar($id);
        if (!$rifa || $rifa['status'] !== 'ativo') {
            return Json::erro($response, 'STATUS_INVALIDO', 'Só rifas ativas podem ser encerradas.', 422);
        }
        $this->rifas->mudarStatus($id, 'encerrado');
        return Json::resposta($response, ['data' => $this->rifas->buscar($id)]);
    }

    public function cancelar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $rifa = $this->rifas->buscar($id);
        if (!$rifa) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Rifa não encontrada.', 404);
        }
        if (in_array($rifa['status'], ['sorteado', 'cancelado'], true)) {
            return Json::erro($response, 'STATUS_INVALIDO', 'Rifa sorteada ou já cancelada não pode ser cancelada.', 422);
        }
        $motivo = trim((string) (((array) $request->getParsedBody())['motivo'] ?? ''));
        if ($motivo === '' || !Validacao::maxLen($motivo, 300)) {
            return Json::erro($response, 'VALIDACAO', 'Informe o motivo do cancelamento (até 300 caracteres).', 400);
        }
        $this->rifas->salvarCancelamento($id, $motivo);
        return Json::resposta($response, ['data' => $this->rifas->buscar($id)]);
    }

    // ==================== prêmios ====================

    public function criarPremio(Request $request, Response $response, array $args): Response
    {
        $rifaId = (int) $args['id'];
        if (!$this->editavel($response, $rifaId)) {
            return $this->editavelResp ?? $response;
        }
        $b = (array) $request->getParsedBody();
        $erros = $this->validarPremio($b);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }
        $pid = $this->rifas->criarPremio($rifaId, [
            'ordem' => (int) ($b['ordem'] ?? 1),
            'titulo' => trim((string) $b['titulo']),
            'descricao' => trim((string) ($b['descricao'] ?? '')) ?: null,
            'valor_estimado' => $b['valor_estimado'] !== null && $b['valor_estimado'] !== ''
                ? (float) $b['valor_estimado'] : null,
            'departamento_id' => (int) $b['departamento_id'],
        ]);
        return Json::resposta($response, ['data' => $this->rifas->buscarPremio($pid)], 201);
    }

    public function atualizarPremio(Request $request, Response $response, array $args): Response
    {
        $premio = $this->rifas->buscarPremio((int) $args['premioId']);
        if (!$premio) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Prêmio não encontrado.', 404);
        }
        if (!$this->editavel($response, (int) $premio['rifa_id'])) {
            return $this->editavelResp ?? $response;
        }
        $b = (array) $request->getParsedBody();
        $erros = $this->validarPremio($b, parcial: true);
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }
        $limpo = [];
        foreach (['ordem', 'titulo', 'descricao', 'valor_estimado', 'departamento_id', 'imagem_url'] as $c) {
            if (array_key_exists($c, $b)) {
                $limpo[$c] = $b[$c] === '' ? null : $b[$c];
            }
        }
        $this->rifas->atualizarPremio((int) $args['premioId'], $limpo);
        return Json::resposta($response, ['data' => $this->rifas->buscarPremio((int) $args['premioId'])]);
    }

    public function removerPremio(Request $request, Response $response, array $args): Response
    {
        $premio = $this->rifas->buscarPremio((int) $args['premioId']);
        if (!$premio) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Prêmio não encontrado.', 404);
        }
        if (!$this->editavel($response, (int) $premio['rifa_id'])) {
            return $this->editavelResp ?? $response;
        }
        $this->rifas->removerPremio((int) $args['premioId']);
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // ==================== repartição ====================

    public function salvarReparticao(Request $request, Response $response, array $args): Response
    {
        $rifaId = (int) $args['id'];
        if (!$this->editavel($response, $rifaId)) {
            return $this->editavelResp ?? $response;
        }
        $b = (array) $request->getParsedBody();
        $linhas = $b['linhas'] ?? null;
        if (!is_array($linhas) || $linhas === []) {
            return Json::erro($response, 'VALIDACAO', 'Informe as linhas de repartição.', 400);
        }
        $soma = 0.0;
        $ok = [];
        foreach ($linhas as $l) {
            $destino = trim((string) ($l['destino'] ?? ''));
            $pct = is_numeric($l['percentual'] ?? null) ? (float) $l['percentual'] : null;
            if ($destino === '' || !Validacao::maxLen($destino, 120) || $pct === null || $pct <= 0 || $pct > 100) {
                return Json::erro($response, 'VALIDACAO', 'Cada linha precisa de destino (≤120) e percentual (0-100).', 400);
            }
            $soma += $pct;
            $ok[] = ['destino' => $destino, 'percentual' => $pct,
                     'tipo' => ($l['tipo'] ?? '') === 'departamentos' ? 'departamentos' : 'livre'];
        }
        if (abs($soma - 100.0) > 0.01) {
            return Json::erro($response, 'REPARTICAO_INVALIDA', "Repartição deve somar 100% (atual: {$soma}%).", 422);
        }
        $this->rifas->salvarReparticao($rifaId, $ok);
        return Json::resposta($response, ['data' => $this->rifas->listarReparticao($rifaId)]);
    }

    // ==================== divulgadores ====================

    public function listarDivulgadores(Request $request, Response $response, array $args): Response
    {
        return Json::resposta($response, ['data' => $this->rifas->listarDivulgadores((int) $args['id'])]);
    }

    public function criarDivulgador(Request $request, Response $response, array $args): Response
    {
        $rifaId = (int) $args['id'];
        $rifa = $this->rifas->buscar($rifaId);
        if (!$rifa) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Rifa não encontrada.', 404);
        }
        if (!in_array($rifa['status'], ['rascunho', 'ativo'], true)) {
            return Json::erro($response, 'STATUS_INVALIDO', 'Divulgadores só podem ser gerados em rifa rascunho/ativa.', 422);
        }
        $b = (array) $request->getParsedBody();
        $pessoaId = (int) ($b['pessoa_id'] ?? 0);
        if (!$this->usuarios->pessoaExiste($pessoaId)) {
            return Json::erro($response, 'VALIDACAO', 'pessoa_id inválida.', 400);
        }
        $dv = $this->rifas->criarDivulgador($rifaId, $pessoaId);
        return Json::resposta($response, ['data' => $dv], 201);
    }

    public function desativarDivulgador(Request $request, Response $response, array $args): Response
    {
        $this->rifas->desativarDivulgador((int) $args['id'], (int) $args['divulgadorId']);
        return Json::resposta($response, ['data' => $this->rifas->listarDivulgadores((int) $args['id'])]);
    }

    // ==================== bilhetes ====================

    public function listarBilhetes(Request $request, Response $response, array $args): Response
    {
        $q = $request->getQueryParams();
        [$pagina, $porPagina] = Json::parametrosPaginacao($q, 200);
        $filtros = [
            'status_pag' => in_array($q['status_pag'] ?? '', RifaRepository::STATUS_PAG, true) ? $q['status_pag'] : '',
            'q' => trim((string) ($q['q'] ?? '')),
            'divulgador_id' => $q['divulgador_id'] ?? null,
            'departamento_id' => $q['departamento_id'] ?? null,
        ];
        $r = $this->rifas->listarBilhetes((int) $args['id'], $filtros, $pagina, $porPagina);
        return Json::paginada($response, $r['itens'], $r['total'], $pagina, $porPagina);
    }

    public function pagarBilhete(Request $request, Response $response, array $args): Response
    {
        $b = $this->rifas->buscarBilhete((int) $args['bilheteId']);
        if (!$b || (int) $b['rifa_id'] !== (int) $args['id']) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Bilhete não encontrado.', 404);
        }
        if ($b['status_pag'] !== 'pendente') {
            return Json::erro($response, 'STATUS_INVALIDO', 'Só bilhetes pendentes podem ser marcados como pagos.', 422);
        }
        $this->rifas->marcarPago((int) $b['id'], $this->uid());
        return Json::resposta($response, ['data' => $this->rifas->buscarBilhete((int) $b['id'])]);
    }

    public function estornarBilhete(Request $request, Response $response, array $args): Response
    {
        $b = $this->rifas->buscarBilhete((int) $args['bilheteId']);
        if (!$b || (int) $b['rifa_id'] !== (int) $args['id']) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Bilhete não encontrado.', 404);
        }
        $this->rifas->estornar((int) $b['id']);
        return Json::resposta($response, ['data' => $this->rifas->buscarBilhete((int) $b['id'])]);
    }

    // ==================== ranking / extrato / sorteio ====================

    public function ranking(Request $request, Response $response, array $args): Response
    {
        return Json::resposta($response, ['data' => $this->rifas->ranking((int) $args['id'])]);
    }

    public function extrato(Request $request, Response $response, array $args): Response
    {
        return Json::resposta($response, ['data' => $this->rifas->extratoReparticao((int) $args['id'])]);
    }

    public function sortear(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $rifa = $this->rifas->buscar($id);
        if (!$rifa) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Rifa não encontrada.', 404);
        }
        if (!in_array($rifa['status'], ['ativo', 'encerrado'], true)) {
            return Json::erro($response, 'STATUS_INVALIDO', 'Só rifas ativas/encerradas podem ser sorteadas.', 422);
        }
        if ($this->rifas->listarPremios($id) === []) {
            return Json::erro($response, 'SEM_PREMIO', 'Cadastre ao menos 1 prêmio.', 422);
        }
        $b = (array) $request->getParsedBody();
        $seed = trim((string) ($b['seed'] ?? '')) ?: null;
        $federal = null;
        if ($rifa['metodo'] === 'federal') {
            $federal = trim((string) ($b['federal_numeros'] ?? ''));
            if ($federal === '' || !Validacao::maxLen($federal, 200)) {
                return Json::erro($response, 'VALIDACAO', 'Informe os números da Loteria Federal (ex.: 04589, 12345).', 400);
            }
        }
        try {
            $resultado = $this->rifas->sortear($id, $seed, $federal);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SEM_ELEGIVEIS') {
                return Json::erro($response, 'SEM_ELEGIVEIS', 'Nenhum bilhete pago/gratuito elegível para o sorteio.', 422);
            }
            throw $e;
        }
        return Json::resposta($response, ['data' => ['rifa' => $this->rifas->buscar($id), 'resultado' => $resultado]]);
    }

    // -----------------------------------------------------------------
    // helpers

    /** @var Response|null resposta de erro do último editavel() */
    private ?Response $editavelResp = null;

    private function editavel(Response $res, int $rifaId): bool
    {
        $rifa = $this->rifas->buscar($rifaId);
        if (!$rifa) {
            $this->editavelResp = Json::erro($res, 'NAO_ENCONTRADO', 'Rifa não encontrada.', 404);
            return false;
        }
        if (in_array($rifa['status'], ['sorteado', 'cancelado'], true)) {
            $this->editavelResp = Json::erro($res, 'IMUTAVEL', 'Rifa sorteada ou cancelada não pode ser editada.', 422);
            return false;
        }
        $this->editavelResp = null;
        return true;
    }

    /** @return string[] */
    private function validar(array $b, bool $parcial = false): array
    {
        $erros = [];
        if (!$parcial || array_key_exists('titulo', $b)) {
            if (trim((string) ($b['titulo'] ?? '')) === '') {
                $erros[] = 'titulo é obrigatório.';
            } elseif (!Validacao::maxLen($b['titulo'], 200)) {
                $erros[] = 'titulo até 200 caracteres.';
            }
        }
        if (!$parcial || array_key_exists('valor_numero', $b)) {
            if (!is_numeric($b['valor_numero'] ?? null) || (float) $b['valor_numero'] <= 0) {
                $erros[] = 'valor_numero inválido (maior que zero).';
            }
        }
        if (!$parcial || array_key_exists('total_numeros', $b)) {
            $t = (int) ($b['total_numeros'] ?? 0);
            if ($t < 1 || $t > 100000) {
                $erros[] = 'total_numeros inválido (1-100000).';
            }
        }
        if (isset($b['metodo']) && !in_array($b['metodo'], RifaRepository::METODOS, true)) {
            $erros[] = 'metodo inválido (sistema|federal).';
        }
        if (isset($b['data_sorteio']) && $b['data_sorteio'] !== null && $b['data_sorteio'] !== ''
            && !$this->dataHoraOuNull($b['data_sorteio'])) {
            $erros[] = 'data_sorteio inválida (yyyy-mm-dd hh:mm).';
        }
        if (isset($b['max_por_pessoa']) && ((int) $b['max_por_pessoa'] < 1 || (int) $b['max_por_pessoa'] > 1000)) {
            $erros[] = 'max_por_pessoa inválido (1-1000).';
        }
        return $erros;
    }

    /** @return array<string,mixed> */
    private function normalizar(array $b, bool $parcial = false): array
    {
        $limpo = [];
        foreach (['titulo', 'descricao', 'regulamento', 'pix_instrucoes'] as $c) {
            if (array_key_exists($c, $b)) {
                $limpo[$c] = trim((string) $b[$c]);
            }
        }
        if (array_key_exists('valor_numero', $b)) {
            $limpo['valor_numero'] = (float) $b['valor_numero'];
        }
        if (array_key_exists('total_numeros', $b)) {
            $limpo['total_numeros'] = (int) $b['total_numeros'];
        }
        if (array_key_exists('max_por_pessoa', $b)) {
            $limpo['max_por_pessoa'] = (int) $b['max_por_pessoa'];
        }
        if (array_key_exists('data_sorteio', $b)) {
            $limpo['data_sorteio'] = $this->dataHoraOuNull($b['data_sorteio']);
        }
        if (array_key_exists('metodo', $b)) {
            $limpo['metodo'] = $b['metodo'];
        }
        return $limpo;
    }

    /** @return string[] */
    private function validarPremio(array $b, bool $parcial = false): array
    {
        $erros = [];
        if (!$parcial || array_key_exists('titulo', $b)) {
            if (trim((string) ($b['titulo'] ?? '')) === '') {
                $erros[] = 'titulo do prêmio é obrigatório.';
            } elseif (!Validacao::maxLen($b['titulo'], 160)) {
                $erros[] = 'titulo do prêmio até 160 caracteres.';
            }
        }
        if (!$parcial || array_key_exists('departamento_id', $b)) {
            if (empty($b['departamento_id'])) {
                $erros[] = 'departamento_id doador é obrigatório.';
            } elseif (!$this->deptos->buscar((int) $b['departamento_id'])) {
                $erros[] = 'departamento_id inexistente.';
            }
        }
        if (isset($b['valor_estimado']) && $b['valor_estimado'] !== null && $b['valor_estimado'] !== ''
            && !is_numeric($b['valor_estimado'])) {
            $erros[] = 'valor_estimado inválido.';
        }
        return $erros;
    }

    private function dataHoraOuNull(mixed $v): ?string
    {
        $v = trim((string) ($v ?? ''));
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2})(?::\d{2})?)?$/', $v, $m)) {
            return null;
        }
        $data = Validacao::data($m[1]);
        if (!$data) {
            return null;
        }
        $hora = isset($m[2]) ? Validacao::hora($m[2]) : null;
        return $data . ' ' . ($hora ?? '00:00');
    }
}
