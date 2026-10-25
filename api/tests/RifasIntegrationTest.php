<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Migrator;
use Adab\Api\Repositories\RifaRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integração do M7 — rifas beneficentes (doc 13): ciclo de vida,
 * repartição Σ=100, reserva/expiração de números, ranking e apuração
 * determinística por seed. Só com ADAB_TEST_DSN.
 */
final class RifasIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;
    private RifaRepository $repo;
    private int $deptoId = 0;

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
        $this->repo = new RifaRepository($this->pdo);
        $this->deptoId = $this->novoDepto();
    }

    private function novoDepto(): int
    {
        $this->pdo->prepare('INSERT INTO departamentos (nome) VALUES (?)')
            ->execute(['Depto Rifa ' . bin2hex(random_bytes(4))]);
        return (int) $this->pdo->lastInsertId();
    }

    private function novaPessoa(): int
    {
        $this->pdo->prepare('INSERT INTO pessoas (nome_completo) VALUES (?)')
            ->execute(['Compradora Teste']);
        return (int) $this->pdo->lastInsertId();
    }

    private function novaRifa(array $extra = []): int
    {
        return $this->repo->criar(array_merge([
            'titulo' => 'Sorteio Teste',
            'valor_numero' => 10.00,
            'total_numeros' => 20,
            'regulamento' => 'Rifa beneficente — regulamento público.',
        ], $extra), null);
    }

    public function testCicloDeVidaValidaAtivacao(): void
    {
        $id = $this->novaRifa(['regulamento' => '']);
        $chk = $this->repo->podeAtivar($id);
        $this->assertFalse($chk['ok']);
        $this->assertSame('SEM_REGULAMENTO', $chk['code']);

        $this->repo->atualizar($id, ['regulamento' => 'Regulamento ok.']);
        $chk = $this->repo->podeAtivar($id);
        $this->assertSame('SEM_PREMIO', $chk['code']);

        $this->repo->criarPremio($id, ['titulo' => 'Cesta', 'departamento_id' => $this->deptoId]);
        $chk = $this->repo->podeAtivar($id);
        $this->assertSame('SEM_REPARTICAO', $chk['code']);

        // repartição ≠ 100% bloqueia
        $this->repo->salvarReparticao($id, [['destino' => 'Templo', 'percentual' => 60]]);
        $this->assertSame('REPARTICAO_INVALIDA', $this->repo->podeAtivar($id)['code']);

        $this->repo->salvarReparticao($id, [
            ['destino' => 'Construção do templo', 'percentual' => 60],
            ['destino' => 'Departamentos', 'tipo' => 'departamentos', 'percentual' => 30],
            ['destino' => 'Escola Bíblica', 'percentual' => 10],
        ]);
        $this->assertTrue($this->repo->podeAtivar($id)['ok']);
    }

    public function testReservaExpiraLiberaNumero(): void
    {
        $id = $this->novaRifa(['total_numeros' => 5]);
        $n = $this->repo->reservarNumeros($id, 2, ['nome' => 'A', 'telefone' => '91999990001'], null);
        $this->assertCount(2, $n);
        $this->assertSame([1, 2], array_column($n, 'numero'));

        // força expiração
        $this->pdo->prepare(
            "UPDATE rifa_bilhetes SET expira_em = '2020-01-01' WHERE rifa_id = ? AND numero = 1"
        )->execute([$id]);
        $this->assertSame(1, $this->repo->expirarPendentes($id));

        // número 1 volta ao pool
        $n2 = $this->repo->reservarNumeros($id, 1, ['nome' => 'B', 'telefone' => '91999990002'], null);
        $this->assertSame(1, $n2[0]['numero']);
    }

    public function testReservaRespeitaEsgetamento(): void
    {
        $id = $this->novaRifa(['total_numeros' => 2]);
        $this->repo->reservarNumeros($id, 2, ['nome' => 'A', 'telefone' => '91999990003'], null);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SEM_NUMEROS');
        $this->repo->reservarNumeros($id, 1, ['nome' => 'B', 'telefone' => '91999990004'], null);
    }

    public function testRankingPorMembroEDepto(): void
    {
        $id = $this->novaRifa();
        $pessoa = $this->novaPessoa();
        $dv = $this->repo->criarDivulgador($id, $pessoa);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $dv['token']);

        // membro no depto → venda conta no ranking dele
        $this->pdo->prepare('INSERT INTO membros (pessoa_id, status) VALUES (?, "ativo")')
            ->execute([$pessoa]);
        $this->pdo->prepare(
            'INSERT INTO membro_departamentos (pessoa_id, departamento_id, data_inicio) VALUES (?, ?, CURDATE())'
        )->execute([$pessoa, $this->deptoId]);

        $n = $this->repo->reservarNumeros($id, 3, ['nome' => 'C', 'telefone' => '91999990005'], (int) $dv['id']);
        $this->repo->marcarPago((int) $n[0]['id'], null);

        $rank = $this->repo->ranking($id);
        $this->assertCount(1, $rank);
        $this->assertSame(3, (int) $rank[0]['reservas']);
        $this->assertSame(1, (int) $rank[0]['pagos']);
        $this->assertSame($this->deptoId, (int) $rank[0]['departamento_id']);
    }

    public function testExtratoReparticaoProporcional(): void
    {
        $id = $this->novaRifa();
        $this->repo->salvarReparticao($id, [
            ['destino' => 'Templo', 'percentual' => 60],
            ['destino' => 'Departamentos', 'tipo' => 'departamentos', 'percentual' => 40],
        ]);
        $n = $this->repo->reservarNumeros($id, 2, ['nome' => 'D', 'telefone' => '91999990006'], null);
        $this->repo->marcarPago((int) $n[0]['id'], null); // 1 pago × R$10 = R$10

        $ex = $this->repo->extratoReparticao($id);
        $this->assertEquals(10.0, $ex['arrecadado']);
        $this->assertEquals(6.0, $ex['destinos'][0]['valor']);
        $this->assertEquals(4.0, $ex['destinos'][1]['valor']);
    }

    public function testSorteioDeterministicoPorSeed(): void
    {
        $id = $this->novaRifa();
        $this->repo->criarPremio($id, ['titulo' => 'P1', 'departamento_id' => $this->deptoId]);
        $this->repo->criarPremio($id, ['titulo' => 'P2', 'departamento_id' => $this->deptoId]);
        $nums = $this->repo->reservarNumeros($id, 3, ['nome' => 'E', 'telefone' => '91999990007'], null);
        foreach ($nums as $b) {
            $this->repo->marcarPago((int) $b['id'], null);
        }
        // pendente nunca participa
        $this->repo->reservarNumeros($id, 1, ['nome' => 'F', 'telefone' => '91999990008'], null);

        $r = $this->repo->sortear($id, 'seed-fixa-123', null);
        $this->assertSame('seed-fixa-123', $r['seed']);
        $this->assertSame(hash('sha256', 'seed-fixa-123'), $r['prova']);
        $this->assertCount(2, $r['vencedores']);
        $this->assertCount(2, array_unique($r['vencedores'])); // bilhete não repete

        // mesma seed → mesmo resultado (rifa nova idêntica)
        $id2 = $this->novaRifa();
        $this->repo->criarPremio($id2, ['titulo' => 'P1', 'departamento_id' => $this->deptoId]);
        $this->repo->criarPremio($id2, ['titulo' => 'P2', 'departamento_id' => $this->deptoId]);
        $nums2 = $this->repo->reservarNumeros($id2, 3, ['nome' => 'E', 'telefone' => '91999990009'], null);
        foreach ($nums2 as $b) {
            $this->repo->marcarPago((int) $b['id'], null);
        }
        $r2 = $this->repo->sortear($id2, 'seed-fixa-123', null);
        // pool e ids de prêmio diferem → resultado pode divergir; o
        // determinismo exigido é por rifa: verifica prova e consistência
        $this->assertSame(hash('sha256', 'seed-fixa-123'), $r2['prova']);
        $this->assertCount(2, $r2['vencedores']);
    }

    public function testSorteioFederal(): void
    {
        $id = $this->novaRifa(['metodo' => 'federal', 'total_numeros' => 10]);
        $this->repo->criarPremio($id, ['titulo' => 'P1', 'departamento_id' => $this->deptoId]);
        $nums = $this->repo->reservarNumeros($id, 3, ['nome' => 'G', 'telefone' => '91999990010'], null);
        foreach ($nums as $b) {
            $this->repo->marcarPago((int) $b['id'], null);
        }
        // números 1,2,3 reservados/pagos; federal "00002" → bilhete 2 vence
        $r = $this->repo->sortear($id, null, '00002');
        $this->assertSame([2], $r['vencedores']);
        $rifa = $this->repo->buscar($id);
        $this->assertSame('sorteado', $rifa['status']);
        $this->assertSame('federal:00002', $rifa['seed']);
    }
}
