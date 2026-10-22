<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Migrator;
use Adab\Api\Repositories\EbdRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integração do M6 — D2 (matrícula única), D3 (só presença, upsert),
 * D8 (domingos do período). Só com ADAB_TEST_DSN.
 */
final class EbdIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;
    private EbdRepository $repo;

    protected function setUp(): void
    {
        $dsn = getenv('ADAB_TEST_DSN');
        if (!$dsn) {
            $this->markTestSkipped('ADAB_TEST_DSN não configurado.');
        }
        $this->pdo = new PDO($dsn, getenv('ADAB_TEST_USER') ?: null, getenv('ADAB_TEST_PASS') ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        (new Migrator($this->pdo, dirname(__DIR__) . '/migrations'))->run();
        $this->repo = new EbdRepository($this->pdo);
    }

    private function novaPessoa(string $nome): int
    {
        $this->pdo->prepare('INSERT INTO pessoas (nome_completo) VALUES (?)')->execute([$nome]);
        return (int) $this->pdo->lastInsertId();
    }

    public function testD2UmaMatriculaAtivaPorVez(): void
    {
        $c1 = $this->repo->criarClasse(['nome' => 'EBD-Jovens', 'faixa_etaria' => '14-17']);
        $c2 = $this->repo->criarClasse(['nome' => 'EBD-Adultos']);
        $pid = $this->novaPessoa('Aluno Teste');

        $id1 = $this->repo->matricular($c1, $pid, null, null);
        $this->assertGreaterThan(0, $id1);
        $this->assertSame('EBD-Jovens', $this->repo->matriculaAtiva($pid)['classe_nome']);

        // transferir → encerra atual + cria nova (D2 preservada)
        $id2 = $this->repo->transferir($id1, $c2, null);
        $this->assertSame('EBD-Adultos', $this->repo->matriculaAtiva($pid)['classe_nome']);
        $this->assertSame('transferido', $this->repo->buscarAluno($id1)['status']);

        // desistir → sem matrícula ativa
        $this->repo->encerrarMatricula($id2, 'desistente');
        $this->assertNull($this->repo->matriculaAtiva($pid));

        // re-matricular na MESMA classe → reativa linha terminal (sem dup)
        $id3 = $this->repo->matricular($c1, $pid, null, null);
        $this->assertSame($id1, $id3);
        $this->assertSame('matriculado', $this->repo->buscarAluno($id1)['status']);
    }

    public function testD8GerarDomingosDoPeriodo(): void
    {
        // 2026-01-01 (qui) a 2026-03-31 — período T1
        $pid = $this->repo->criarPeriodo([
            'ano' => 2026, 'trimestre' => 1,
            'data_inicio' => '2026-01-01', 'data_fim' => '2026-03-31',
        ]);
        $criadas = $this->repo->gerarDomingos($pid);
        $aulas = $this->repo->listarAulas($pid);

        $this->assertGreaterThan(0, $criadas);
        $this->assertSame($criadas, count($aulas));
        foreach ($aulas as $a) {
            $this->assertSame(0, (int) (new \DateTime($a['data']))->format('w'), "{$a['data']} não é domingo");
        }
        // números sequenciais 1..N
        $this->assertSame(range(1, $criadas), array_column($aulas, 'numero_licao'));

        // re-gerar é idempotente (domingos já existentes pulados)
        $this->assertSame(0, $this->repo->gerarDomingos($pid));

        // aula pontual em dia não-domingo (aula transferida) → nº seguinte
        $aid = $this->repo->criarAula($pid, ['data' => '2026-02-11']); // quarta
        $aula = $this->repo->buscarAula($aid);
        $this->assertSame($criadas + 1, (int) $aula['numero_licao']);
        $this->assertNotSame(0, (int) (new \DateTime('2026-02-11'))->format('w'));
    }

    public function testD3ChamadaSoPresencaUpsert(): void
    {
        $cid = $this->repo->criarClasse(['nome' => 'EBD-Crianças']);
        $pid = $this->novaPessoa('Aluna Criança');
        $alunoId = $this->repo->matricular($cid, $pid, null, null);

        $perId = $this->repo->criarPeriodo([
            'ano' => 2026, 'trimestre' => 2,
            'data_inicio' => '2026-04-01', 'data_fim' => '2026-06-30',
        ]);
        $aulaId = $this->repo->criarAula($perId, ['data' => '2026-04-05']); // domingo

        // chamada: lista só matriculados
        $lista = $this->repo->chamada($aulaId, $cid);
        $this->assertCount(1, $lista);
        $this->assertSame('Aluna Criança', $lista[0]['nome_completo']);
        $this->assertNull($lista[0]['presente']); // sem registro ainda

        // registra presença + bíblia (sem campo de falta)
        $this->repo->registrarFrequencia($aulaId, $alunoId, [
            'presente' => 1, 'trouxe_biblia' => 1, 'oferta' => 5.00,
        ], null);
        $lista = $this->repo->chamada($aulaId, $cid);
        $this->assertSame(1, (int) $lista[0]['presente']);
        $this->assertSame(1, (int) $lista[0]['trouxe_biblia']);

        // upsert: retira presença (não registra "falta" explícita — marca presente=0)
        $this->repo->registrarFrequencia($aulaId, $alunoId, ['presente' => 0], null);
        $this->assertSame(0, (int) $this->repo->chamada($aulaId, $cid)[0]['presente']);

        // view: presentes conta só presente=1
        $resumo = $this->pdo->query(
            "SELECT presentes, biblias FROM v_ebd_frequencia_resumo WHERE aula_id = {$aulaId} AND classe_id = {$cid}"
        )->fetch();
        $this->assertSame(0, (int) $resumo['presentes']);
    }

    public function testProfessorLecionaNaClasse(): void
    {
        $cid = $this->repo->criarClasse(['nome' => 'EBD-Adolescentes']);
        $prof = $this->novaPessoa('Prof Teste');
        $outro = $this->novaPessoa('Outra Pessoa');

        $this->assertFalse($this->repo->pessoaLecionaNaClasse($prof, $cid));
        $this->repo->vincularProfessor($cid, $prof, 'titular');
        $this->assertTrue($this->repo->pessoaLecionaNaClasse($prof, $cid));
        $this->assertFalse($this->repo->pessoaLecionaNaClasse($outro, $cid));
    }
}
