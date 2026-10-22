<?php

declare(strict_types=1);

namespace Adab\Api\Controllers;

use Adab\Api\Json;
use Adab\Api\Validacao;
use Adab\Api\Repositories\EbdRepository;
use Adab\Api\Repositories\UsuarioRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * M6 — EBD (Escola Bíblica Dominical).
 *
 * Decisões aplicadas: D2 uma matrícula ativa por aluno (422
 * JA_MATRICULADO); D3 só presença registrada, por professor da classe
 * ou ebd_admin; D4 períodos ano+trimestre; D8 aulas nos domingos do
 * período (+ aulas pontuais/transferidas em qualquer data).
 *
 * Escrita (classes/profs/alunos/períodos): ebd_admin (+admin automático).
 * Chamada: ebd_admin ou ebd_professor que leciona na classe.
 * Relatórios: ebd_admin (+dirigente na frequência).
 */
final class EbdController
{
    private EbdRepository $ebd;
    private UsuarioRepository $usuarios; // pessoaExiste

    public function __construct(private PDO $pdo)
    {
        $this->ebd = new EbdRepository($pdo);
        $this->usuarios = new UsuarioRepository($pdo);
    }

    // ===== classes =====

    public function listarClasses(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $apenas = isset($q['ativo']) ? filter_var($q['ativo'], FILTER_VALIDATE_BOOLEAN) : null;
        return Json::resposta($response, ['data' => $this->ebd->listarClasses($apenas)]);
    }

    public function criarClasse(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        if (trim((string) ($b['nome'] ?? '')) === '') {
            return Json::erro($response, 'VALIDACAO', 'nome é obrigatório.', 400);
        }
        try {
            $id = $this->ebd->criarClasse([
                'nome' => trim((string) $b['nome']),
                'faixa_etaria' => trim((string) ($b['faixa_etaria'] ?? '')) ?: null,
                'sala' => trim((string) ($b['sala'] ?? '')) ?: null,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return Json::erro($response, 'NOME_DUPLICADO', 'Classe já existe.', 422);
            }
            throw $e;
        }
        return Json::resposta($response, ['data' => $this->ebd->buscarClasse($id)], 201);
    }

    public function atualizarClasse(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->ebd->buscarClasse($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Classe não encontrada.', 404);
        }
        $b = (array) $request->getParsedBody();
        if (isset($b['nome']) && trim((string) $b['nome']) === '') {
            return Json::erro($response, 'VALIDACAO', 'nome não pode ser vazio.', 400);
        }
        try {
            $this->ebd->atualizarClasse($id, $b);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return Json::erro($response, 'NOME_DUPLICADO', 'Classe já existe.', 422);
            }
            throw $e;
        }
        return Json::resposta($response, ['data' => $this->ebd->buscarClasse($id)]);
    }

    public function professoresDaClasse(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->ebd->buscarClasse($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Classe não encontrada.', 404);
        }
        return Json::resposta($response, ['data' => $this->ebd->listarProfessores($id)]);
    }

    public function vincularProfessor(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->ebd->buscarClasse($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Classe não encontrada.', 404);
        }
        $b = (array) $request->getParsedBody();
        $pessoaId = (int) ($b['pessoa_id'] ?? 0);
        $papel = $b['papel'] ?? 'auxiliar';
        if (!$this->usuarios->pessoaExiste($pessoaId)) {
            return Json::erro($response, 'VALIDACAO', 'pessoa_id inválida.', 400);
        }
        if (!in_array($papel, EbdRepository::PAPEIS_PROF, true)) {
            return Json::erro($response, 'VALIDACAO', 'papel inválido (titular|auxiliar|substituto).', 400);
        }
        $this->ebd->vincularProfessor($id, $pessoaId, $papel);
        return Json::resposta($response, ['data' => $this->ebd->listarProfessores($id)], 201);
    }

    public function desvincularProfessor(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->ebd->buscarClasse($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Classe não encontrada.', 404);
        }
        $this->ebd->desvincularProfessor($id, (int) $args['pid']);
        return Json::resposta($response, ['data' => $this->ebd->listarProfessores($id)]);
    }

    // ===== alunos =====

    public function listarAlunos(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        return Json::resposta($response, ['data' => $this->ebd->listarAlunos([
            'classe_id' => $q['classe_id'] ?? null,
            'status' => in_array($q['status'] ?? '', EbdRepository::STATUS_ALUNO, true) ? $q['status'] : '',
            'q' => trim((string) ($q['q'] ?? '')),
        ])]);
    }

    public function matricular(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $pessoaId = (int) ($b['pessoa_id'] ?? 0);
        $classeId = (int) ($b['classe_id'] ?? 0);

        if (!$this->usuarios->pessoaExiste($pessoaId)) {
            return Json::erro($response, 'VALIDACAO', 'pessoa_id inválida.', 400);
        }
        $classe = $this->ebd->buscarClasse($classeId);
        if (!$classe || !$classe['ativo']) {
            return Json::erro($response, 'VALIDACAO', 'classe_id inválida ou inativa.', 400);
        }

        // D2: uma matrícula ativa por vez
        $ativa = $this->ebd->matriculaAtiva($pessoaId);
        if ($ativa) {
            return Json::erro(
                $response,
                'JA_MATRICULADO',
                "Aluno já está matriculado em {$ativa['classe_nome']} (matrícula #{$ativa['id']}). Use transferir.",
                422
            );
        }

        $id = $this->ebd->matricular(
            $classeId,
            $pessoaId,
            trim((string) ($b['observacao'] ?? '')) ?: null,
            (int) ($_SESSION['usuario']['id'] ?? 0) ?: null
        );
        return Json::resposta($response, ['data' => $this->ebd->buscarAluno($id)], 201);
    }

    public function transferir(Request $request, Response $response, array $args): Response
    {
        $alunoId = (int) $args['id'];
        $aluno = $this->ebd->buscarAluno($alunoId);
        if (!$aluno || $aluno['status'] !== 'matriculado') {
            return Json::erro($response, 'VALIDACAO', 'Aluno sem matrícula ativa.', 400);
        }
        $classeDestino = $this->ebd->buscarClasse((int) ((array) $request->getParsedBody())['classe_id'] ?? 0);
        if (!$classeDestino || !$classeDestino['ativo']) {
            return Json::erro($response, 'VALIDACAO', 'classe de destino inválida ou inativa.', 400);
        }
        if ((int) $classeDestino['id'] === (int) $aluno['classe_id']) {
            return Json::erro($response, 'VALIDACAO', 'Aluno já está nesta classe.', 400);
        }
        $novoId = $this->ebd->transferir($alunoId, (int) $classeDestino['id'], (int) ($_SESSION['usuario']['id'] ?? 0) ?: null);
        return Json::resposta($response, ['data' => $this->ebd->buscarAluno($novoId)]);
    }

    public function desistir(Request $request, Response $response, array $args): Response
    {
        $alunoId = (int) $args['id'];
        $aluno = $this->ebd->buscarAluno($alunoId);
        if (!$aluno || $aluno['status'] !== 'matriculado') {
            return Json::erro($response, 'VALIDACAO', 'Aluno sem matrícula ativa.', 400);
        }
        $this->ebd->encerrarMatricula($alunoId, 'desistente');
        return Json::resposta($response, ['data' => ['ok' => true]]);
    }

    // ===== períodos e aulas =====

    public function listarPeriodos(Request $request, Response $response): Response
    {
        return Json::resposta($response, ['data' => $this->ebd->listarPeriodos()]);
    }

    public function criarPeriodo(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $ano = (int) ($b['ano'] ?? 0);
        $trimestre = (int) ($b['trimestre'] ?? 0);
        $inicio = $this->dataOuNull($b['data_inicio'] ?? null);
        $fim = $this->dataOuNull($b['data_fim'] ?? null);

        $erros = [];
        if ($ano < 2000 || $ano > 2100) {
            $erros[] = 'ano inválido.';
        }
        if ($trimestre < 1 || $trimestre > 4) {
            $erros[] = 'trimestre deve ser 1-4.';
        }
        if (!$inicio || !$fim) {
            $erros[] = 'data_inicio e data_fim são obrigatórias.';
        } elseif ($fim < $inicio) {
            $erros[] = 'data_fim não pode ser anterior a data_inicio.';
        }
        if ($erros) {
            return Json::erro($response, 'VALIDACAO', implode(' ', $erros), 400);
        }

        try {
            $id = $this->ebd->criarPeriodo([
                'ano' => $ano,
                'trimestre' => $trimestre,
                'tema_revista' => trim((string) ($b['tema_revista'] ?? '')) ?: null,
                'data_inicio' => $inicio,
                'data_fim' => $fim,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'uq_periodo')) {
                return Json::erro($response, 'PERIODO_DUPLICADO', "Período {$ano}-T{$trimestre} já existe.", 422);
            }
            throw $e;
        }
        return Json::resposta($response, ['data' => $this->ebd->buscarPeriodo($id)], 201);
    }

    public function aulasDoPeriodo(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->ebd->buscarPeriodo($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Período não encontrado.', 404);
        }
        return Json::resposta($response, ['data' => $this->ebd->listarAulas($id)]);
    }

    /** Aula pontual/transferida (D8) em qualquer data. */
    public function criarAula(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->ebd->buscarPeriodo($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Período não encontrado.', 404);
        }
        $b = (array) $request->getParsedBody();
        if (!$this->dataOuNull($b['data'] ?? null)) {
            return Json::erro($response, 'VALIDACAO', 'data é obrigatória (yyyy-mm-dd).', 400);
        }
        $aid = $this->ebd->criarAula($id, [
            'titulo_licao' => trim((string) ($b['titulo_licao'] ?? '')) ?: null,
            'data' => $this->dataOuNull($b['data']),
            'texto_aureo' => trim((string) ($b['texto_aureo'] ?? '')) ?: null,
            'leitura_biblica' => trim((string) ($b['leitura_biblica'] ?? '')) ?: null,
        ]);
        return Json::resposta($response, ['data' => $this->ebd->buscarAula($aid)], 201);
    }

    /** Gera aulas nos domingos do período (D8). */
    public function gerarAulas(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->ebd->buscarPeriodo($id)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Período não encontrado.', 404);
        }
        $criadas = $this->ebd->gerarDomingos($id);
        return Json::resposta($response, [
            'data' => ['criadas' => $criadas, 'aulas' => $this->ebd->listarAulas($id)],
        ]);
    }

    // ===== chamada (D3) =====

    /** Gate: ebd_admin sempre; ebd_professor só se leciona na classe. */
    private function podeChamada(int $classeId): bool
    {
        $u = $_SESSION['usuario'] ?? [];
        if (in_array($u['perfil'] ?? '', ['admin', 'ebd_admin'], true)) {
            return true;
        }
        if (($u['perfil'] ?? '') === 'ebd_professor') {
            return $this->ebd->pessoaLecionaNaClasse((int) $u['pessoa_id'], $classeId);
        }
        return false;
    }

    public function verChamada(Request $request, Response $response, array $args): Response
    {
        $aulaId = (int) $args['id'];
        $classeId = (int) ($request->getQueryParams()['classe_id'] ?? 0);
        if (!$this->ebd->buscarAula($aulaId) || !$this->ebd->buscarClasse($classeId)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Aula ou classe não encontrada.', 404);
        }
        if (!$this->podeChamada($classeId)) {
            return Json::erro($response, 'SEM_PERMISSAO', 'Somente o professor da classe ou a administração EBD pode ver a chamada.', 403);
        }
        return Json::resposta($response, ['data' => [
            'aula' => $this->ebd->buscarAula($aulaId),
            'chamada' => $this->ebd->chamada($aulaId, $classeId),
        ]]);
    }

    public function salvarChamada(Request $request, Response $response, array $args): Response
    {
        $aulaId = (int) $args['id'];
        $b = (array) $request->getParsedBody();
        $classeId = (int) ($b['classe_id'] ?? 0);
        if (!$this->ebd->buscarAula($aulaId) || !$this->ebd->buscarClasse($classeId)) {
            return Json::erro($response, 'NAO_ENCONTRADO', 'Aula ou classe não encontrada.', 404);
        }
        if (!$this->podeChamada($classeId)) {
            return Json::erro($response, 'SEM_PERMISSAO', 'Somente o professor da classe ou a administração EBD pode registrar a chamada.', 403);
        }

        $lista = $b['frequencias'] ?? [];
        if (!is_array($lista)) {
            return Json::erro($response, 'VALIDACAO', 'frequencias deve ser uma lista.', 400);
        }
        // oferta é DECIMAL — valida antes do loop (senão PDOException a
        // meio-caminho gravaria chamada parcial)
        foreach ($lista as $f) {
            if (isset($f['oferta']) && $f['oferta'] !== '' && $f['oferta'] !== null
                && !is_numeric($f['oferta'])) {
                return Json::erro($response, 'VALIDACAO', 'oferta deve ser numérica.', 400);
            }
        }

        // alunos válidos = matriculados na classe
        $matriculados = array_column($this->ebd->chamada($aulaId, $classeId), 'aluno_id');
        $uid = (int) ($_SESSION['usuario']['id'] ?? 0) ?: null;
        $salvos = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($lista as $f) {
                $alunoId = (int) ($f['aluno_id'] ?? 0);
                if (!in_array($alunoId, $matriculados, true)) {
                    continue; // ignora aluno não matriculado (sem 422 — idempotente)
                }
                $this->ebd->registrarFrequencia($aulaId, $alunoId, (array) $f, $uid);
                $salvos++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return Json::resposta($response, ['data' => [
            'registrados' => $salvos,
            'chamada' => $this->ebd->chamada($aulaId, $classeId),
        ]]);
    }

    // ===== relatórios =====

    public function relatorioFrequencia(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        return Json::resposta($response, ['data' => $this->ebd->frequenciaResumo(
            isset($q['classe_id']) ? (int) $q['classe_id'] : null,
            isset($q['periodo_id']) ? (int) $q['periodo_id'] : null
        )]);
    }

    public function relatorioFaltosos(Request $request, Response $response): Response
    {
        $semanas = max(1, min(12, (int) ($request->getQueryParams()['semanas'] ?? 4)));
        return Json::resposta($response, ['data' => $this->ebd->faltosos($semanas)]);
    }

    // -----------------------------------------------------------------

    private function dataOuNull(mixed $v): ?string
    {
        return Validacao::data($v);
    }
}
