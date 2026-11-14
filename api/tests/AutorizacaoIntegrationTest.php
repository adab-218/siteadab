<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

use function Adab\Api\criarApp;

/**
 * T2.5 — Autorização negativa por perfil (nível HTTP: Auth → CSRF →
 * PerfilMiddleware → gate do controller).
 *
 * Simula sessão via $_SESSION (id do usuário real no banco — o
 * AuthMiddleware revalida ativo/perfil contra `usuarios`).
 * Só com ADAB_TEST_DSN.
 */
final class AutorizacaoIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?App $app = null;
    private ServerRequestFactory $requests;

    /** @var array<string,int> perfil => usuario_id */
    private array $usuarios = [];

    /** @var array<string,int> perfil => pessoa_id */
    private array $pessoas = [];

    private int $classeComProf;
    private int $classeSemProf;
    private int $aulaId;
    private int $deptoComLider;
    private int $deptoSemLider;
    private int $membroId;

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
        $this->app = criarApp($this->pdo, []);
        $this->requests = new ServerRequestFactory();
        $this->fixtures();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function fixtures(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['rifa_bilhetes', 'rifa_divulgadores', 'rifa_premios', 'rifa_reparticao', 'rifas',
                  'ebd_frequencias', 'ebd_aulas', 'ebd_periodos', 'ebd_alunos', 'ebd_professores',
                  'ebd_classes', 'membro_departamentos', 'departamentos', 'membros',
                  'usuarios', 'pessoas'] as $t) {
            $this->pdo->exec("TRUNCATE {$t}");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        foreach (['admin', 'secretaria', 'dirigente', 'ebd_admin', 'ebd_professor', 'lider_departamento'] as $p) {
            $pid = $this->novaPessoa("Pessoa {$p}");
            $this->pdo->prepare(
                'INSERT INTO usuarios (pessoa_id, email, senha_hash, perfil, ativo) VALUES (?, ?, ?, ?, 1)'
            )->execute([$pid, "auth.{$p}@test.local", password_hash('x', PASSWORD_BCRYPT), $p]);
            $this->usuarios[$p] = (int) $this->pdo->lastInsertId();
            $this->pessoas[$p] = $pid;
        }

        // EBD: professor leciona só em classeComProf
        $this->pdo->exec("INSERT INTO ebd_classes (nome) VALUES ('A-Jovens'), ('B-Adultos')");
        $this->classeComProf = (int) $this->pdo->query('SELECT id FROM ebd_classes WHERE nome = "A-Jovens"')->fetchColumn();
        $this->classeSemProf = (int) $this->pdo->query('SELECT id FROM ebd_classes WHERE nome = "B-Adultos"')->fetchColumn();
        $this->pdo->prepare('INSERT INTO ebd_professores (classe_id, pessoa_id, papel) VALUES (?, ?, "titular")')
            ->execute([$this->classeComProf, $this->pessoas['ebd_professor']]);

        // Período fictício 2030-T1 — não colide com os períodos usados
        // pelo EbdIntegrationTest (uq_periodo)
        $this->pdo->exec("INSERT INTO ebd_periodos (ano, trimestre, data_inicio, data_fim)
                          VALUES (2030, 1, '2030-01-01', '2030-03-31')");
        $perId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO ebd_aulas (periodo_id, numero_licao, data) VALUES (?, 1, "2030-01-06")')
            ->execute([$perId]);
        $this->aulaId = (int) $this->pdo->lastInsertId();

        // Deptos: lider_departamento lidera só deptoComLider (FK p/ membro)
        $liderMembroPessoa = $this->novaPessoa('Líder Membro');
        $this->pdo->exec("INSERT INTO departamentos (nome) VALUES ('D-Jovens'), ('D-Juvenis')");
        $this->deptoComLider = (int) $this->pdo->query('SELECT id FROM departamentos WHERE nome = "D-Jovens"')->fetchColumn();
        $this->deptoSemLider = (int) $this->pdo->query('SELECT id FROM departamentos WHERE nome = "D-Juvenis"')->fetchColumn();
        $this->pdo->prepare('UPDATE departamentos SET lider_id = ? WHERE id = ?')
            ->execute([$liderMembroPessoa, $this->deptoComLider]);
        // usuário lider_departamento vinculado à pessoa líder
        $this->pdo->prepare('UPDATE usuarios SET pessoa_id = ? WHERE perfil = "lider_departamento"')
            ->execute([$liderMembroPessoa]);
        $this->pessoas['lider_departamento'] = $liderMembroPessoa;

        // Membro disponível para vínculo
        $pessoa = $this->novaPessoa('Membro para Vínculo');
        $this->pdo->prepare('INSERT INTO membros (pessoa_id, status) VALUES (?, "ativo")')->execute([$pessoa]);
        $this->membroId = $pessoa;
    }

    private function novaPessoa(string $nome): int
    {
        $this->pdo->prepare('INSERT INTO pessoas (nome_completo) VALUES (?)')->execute([$nome]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Executa request HTTP de verdade pela app Slim com sessão
     * simulada. $uri sempre começa com /api (basePath da app).
     */
    private function request(string $metodo, string $uri, ?string $perfil = null, ?array $body = null): array
    {
        $uri = '/api' . $uri;
        $_SESSION = [];
        if ($perfil !== null) {
            $_SESSION['usuario'] = [
                'id' => $this->usuarios[$perfil],
                'pessoa_id' => $this->pessoas[$perfil],
                'perfil' => $perfil,
            ];
            $_SESSION['csrf'] = 'tok';
            $_SESSION['ultimo_acesso'] = time();
        }
        $req = $this->requests->createServerRequest($metodo, $uri);
        if ($perfil !== null) {
            $req = $req->withHeader('X-CSRF-Token', 'tok');
        }
        if ($body !== null) {
            $req = $req->withHeader('Content-Type', 'application/json')->withParsedBody($body);
        }
        $res = $this->app->handle($req);
        return [$res->getStatusCode(), json_decode((string) $res->getBody(), true)];
    }

    // ===================== negativos =====================

    public function testSemSessao401(): void
    {
        [$status, $body] = $this->request('GET', '/membros');
        $this->assertSame(401, $status);
        $this->assertSame('NAO_AUTENTICADO', $body['error']['code']);
    }

    public function testPostSemCsrf403(): void
    {
        // sessão válida mas sem token CSRF no header
        $_SESSION['usuario'] = ['id' => $this->usuarios['admin'], 'pessoa_id' => $this->pessoas['admin'], 'perfil' => 'admin'];
        $_SESSION['csrf'] = 'tok';
        $_SESSION['ultimo_acesso'] = time();
        $req = $this->requests->createServerRequest('POST', '/api/membros');
        $res = $this->app->handle($req);
        $this->assertSame(403, $res->getStatusCode());
        $this->assertSame('CSRF_INVALIDO', json_decode((string) $res->getBody(), true)['error']['code']);
    }

    /**
     * Matriz de rotas protegidas × perfil sem permissão → 403.
     * @dataProvider matrixNegativa
     */
    public function testMatrixNegativa(string $perfil, string $metodo, string $uri, ?array $body): void
    {
        [$status, $body] = $this->request($metodo, $uri, $perfil, $body);
        $this->assertSame(403, $status, "{$perfil} {$metodo} {$uri} deveria dar 403");
        $this->assertSame('SEM_PERMISSAO', $body['error']['code']);
    }

    public static function matrixNegativa(): array
    {
        $json = ['nome' => 'x'];
        return [
            // só-admin (grupo /usuarios inteiro)
            'secretaria GET /usuarios' => ['secretaria', 'GET', '/usuarios', null],
            'dirigente GET /usuarios' => ['dirigente', 'GET', '/usuarios', null],
            'ebd_admin GET /usuarios' => ['ebd_admin', 'GET', '/usuarios', null],
            // só-admin (delete /membros lista vazia)
            'secretaria DELETE /membros' => ['secretaria', 'DELETE', '/membros/1', null],
            // só-secretaria (membros, deptos, tipos-culto)
            'ebd_professor POST /membros' => ['ebd_professor', 'POST', '/membros', $json],
            'dirigente POST /membros' => ['dirigente', 'POST', '/membros', $json],
            'lider POST /departamentos' => ['lider_departamento', 'POST', '/departamentos', $json],
            'dirigente POST /tipos-culto' => ['dirigente', 'POST', '/tipos-culto', $json],
            // cultos/eventos/acoes: secretaria+dirigente
            'ebd_admin POST /cultos' => ['ebd_admin', 'POST', '/cultos', $json],
            'lider POST /eventos' => ['lider_departamento', 'POST', '/eventos', $json],
            'ebd_professor POST /acoes' => ['ebd_professor', 'POST', '/acoes', $json],
            // EBD catálogo/alunos: só ebd_admin
            'ebd_professor POST /ebd/classes' => ['ebd_professor', 'POST', '/ebd/classes', $json],
            'ebd_professor POST /ebd/alunos' => ['ebd_professor', 'POST', '/ebd/alunos', $json],
            'secretaria POST /ebd/periodos' => ['secretaria', 'POST', '/ebd/periodos', $json],
            'dirigente POST /ebd/alunos/1/desistir' => ['dirigente', 'POST', '/ebd/alunos/1/desistir', null],
            // relatórios EBD
            'ebd_professor GET /ebd/relatorios/faltosos' => ['ebd_professor', 'GET', '/ebd/relatorios/faltosos', null],
            'secretaria GET /ebd/relatorios/frequencia' => ['secretaria', 'GET', '/ebd/relatorios/frequencia', null],
            // GET restrito a ebd_admin+ebd_professor
            'secretaria GET /ebd/alunos' => ['secretaria', 'GET', '/ebd/alunos', null],
            'lider GET /ebd/alunos' => ['lider_departamento', 'GET', '/ebd/alunos', null],
            // M7: bilhetes (telefones) e divulgadores (tokens) só secretaria/líder
            'dirigente GET /rifas/1/bilhetes' => ['dirigente', 'GET', '/rifas/1/bilhetes', null],
            'ebd_professor GET /rifas/1/bilhetes' => ['ebd_professor', 'GET', '/rifas/1/bilhetes', null],
            'ebd_admin GET /rifas/1/divulgadores' => ['ebd_admin', 'GET', '/rifas/1/divulgadores', null],
            'dirigente PATCH pagar' => ['dirigente', 'PATCH', '/rifas/1/bilhetes/1/pagar', null],
            'lider PATCH estornar' => ['lider_departamento', 'PATCH', '/rifas/1/bilhetes/1/estornar', null],
        ];
    }

    /**
     * Cria rifa ativa com 2 divulgadores (um membro do depto do líder,
     * outro de depto alheio) e 1 bilhete pendente de cada.
     *
     * @return array{rifa:int, dvProprio:int, dvAlheio:int, bProprio:int, bAlheio:int,
     *               pessoaPropria:int, pessoaAlheia:int}
     */
    private function fixturesRifa(): array
    {
        $propria = $this->novaPessoa('Divulgador Jovens');
        $alheia = $this->novaPessoa('Divulgador Juvenis');
        foreach ([[$propria, $this->deptoComLider], [$alheia, $this->deptoSemLider]] as [$p, $d]) {
            $this->pdo->prepare('INSERT INTO membros (pessoa_id, status) VALUES (?, "ativo")')->execute([$p]);
            $this->pdo->prepare('INSERT INTO membro_departamentos (pessoa_id, departamento_id) VALUES (?, ?)')
                ->execute([$p, $d]);
        }
        $this->pdo->exec("INSERT INTO rifas (titulo, slug, valor_numero, total_numeros, status)
                          VALUES ('Sorteio Auth', 'sorteio-auth', 10.00, 100, 'ativo')");
        $rifa = (int) $this->pdo->lastInsertId();

        $ids = [];
        $deptoDoLink = ['proprio' => $this->deptoComLider, 'alheio' => $this->deptoSemLider];
        foreach (['proprio' => $propria, 'alheio' => $alheia] as $k => $p) {
            $this->pdo->prepare(
                'INSERT INTO rifa_divulgadores (rifa_id, pessoa_id, departamento_id, token) VALUES (?, ?, ?, ?)'
            )->execute([$rifa, $p, $deptoDoLink[$k], bin2hex(random_bytes(16))]);
            $ids['dv' . ucfirst($k)] = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare(
                'INSERT INTO rifa_bilhetes (rifa_id, numero, nome, telefone, status_pag, divulgador_id)
                 VALUES (?, ?, ?, "91999990000", "pendente", ?)'
            )->execute([$rifa, $k === 'proprio' ? 1 : 2, "Comprador {$k}", $ids['dv' . ucfirst($k)]]);
            $ids['b' . ucfirst($k)] = (int) $this->pdo->lastInsertId();
        }
        return $ids + ['rifa' => $rifa, 'pessoaPropria' => $propria, 'pessoaAlheia' => $alheia];
    }

    /** D5/R1 no M7: líder só gera/desativa links, vê e confirma bilhetes do próprio depto. */
    public function testD5LiderSomenteNoProprioDeptoNosSorteios(): void
    {
        $f = $this->fixturesRifa();
        $rifa = $f['rifa'];

        // listas escopadas
        [$s, $body] = $this->request('GET', "/rifas/{$rifa}/divulgadores", 'lider_departamento');
        $this->assertSame(200, $s);
        $this->assertSame([$f['dvProprio']], array_map(fn($d) => (int) $d['id'], $body['data']));

        [$s, $body] = $this->request('GET', "/rifas/{$rifa}/bilhetes", 'lider_departamento');
        $this->assertSame(200, $s);
        $this->assertSame([$f['bProprio']], array_map(fn($b) => (int) $b['id'], $body['data']['itens']));

        [$s, $body] = $this->request('GET', "/rifas/{$rifa}", 'lider_departamento');
        $this->assertSame(200, $s);
        $this->assertCount(1, $body['data']['divulgadores']);

        // detalhe para perfil sem acesso não expõe tokens
        [$s, $body] = $this->request('GET', "/rifas/{$rifa}", 'dirigente');
        $this->assertSame(200, $s);
        $this->assertSame([], $body['data']['divulgadores']);

        // gerar link: membro de outro depto → 403; do próprio → 201
        $outro = $this->novaPessoa('Outro Juvenil');
        $this->pdo->prepare('INSERT INTO membros (pessoa_id, status) VALUES (?, "ativo")')->execute([$outro]);
        $this->pdo->prepare('INSERT INTO membro_departamentos (pessoa_id, departamento_id) VALUES (?, ?)')
            ->execute([$outro, $this->deptoSemLider]);
        [$s, $body] = $this->request('POST', "/rifas/{$rifa}/divulgadores", 'lider_departamento', ['pessoa_id' => $outro]);
        $this->assertSame(403, $s);
        $this->assertSame('SEM_PERMISSAO', $body['error']['code']);

        $meu = $this->novaPessoa('Novo Jovem');
        $this->pdo->prepare('INSERT INTO membros (pessoa_id, status) VALUES (?, "ativo")')->execute([$meu]);
        $this->pdo->prepare('INSERT INTO membro_departamentos (pessoa_id, departamento_id) VALUES (?, ?)')
            ->execute([$meu, $this->deptoComLider]);
        [$s, ] = $this->request('POST', "/rifas/{$rifa}/divulgadores", 'lider_departamento', ['pessoa_id' => $meu]);
        $this->assertSame(201, $s);

        // desativar link alheio → 403; divulgador de outra rifa → 404
        [$s, ] = $this->request('DELETE', "/rifas/{$rifa}/divulgadores/{$f['dvAlheio']}", 'lider_departamento');
        $this->assertSame(403, $s);
        [$s, ] = $this->request('DELETE', '/rifas/999999/divulgadores/' . $f['dvProprio'], 'lider_departamento');
        $this->assertSame(404, $s);
        [$s, ] = $this->request('DELETE', "/rifas/{$rifa}/divulgadores/{$f['dvProprio']}", 'lider_departamento');
        $this->assertSame(200, $s);

        // confirmar pagamento: alheio → 403; próprio → 200
        [$s, ] = $this->request('PATCH', "/rifas/{$rifa}/bilhetes/{$f['bAlheio']}/pagar", 'lider_departamento');
        $this->assertSame(403, $s);
        [$s, $body] = $this->request('PATCH', "/rifas/{$rifa}/bilhetes/{$f['bProprio']}/pagar", 'lider_departamento');
        $this->assertSame(200, $s);
        $this->assertSame('pago', $body['data']['status_pag']);

        // secretaria continua vendo tudo
        [$s, $body] = $this->request('GET', "/rifas/{$rifa}/divulgadores", 'secretaria');
        $this->assertSame(200, $s);
        $this->assertCount(3, $body['data']);
    }

    /**
     * Regressão (auditoria): líder inclui o divulgador alheio no próprio
     * depto — o escopo usa o depto congelado no link, então nada muda.
     */
    public function testLiderNaoAmpliaEscopoIncluindoDivulgadorNoProprioDepto(): void
    {
        $f = $this->fixturesRifa();
        $rifa = $f['rifa'];

        [$s, ] = $this->request('POST', "/departamentos/{$this->deptoComLider}/membros", 'lider_departamento', [
            'pessoa_id' => $f['pessoaAlheia'],
        ]);
        $this->assertSame(201, $s, 'pré-condição: líder vincula membro ao próprio depto');

        [$s, $body] = $this->request('GET', "/rifas/{$rifa}/bilhetes", 'lider_departamento');
        $this->assertSame(200, $s);
        $this->assertSame([$f['bProprio']], array_map(fn($b) => (int) $b['id'], $body['data']['itens']));

        [$s, $body] = $this->request('GET', "/rifas/{$rifa}/divulgadores", 'lider_departamento');
        $this->assertSame([$f['dvProprio']], array_map(fn($d) => (int) $d['id'], $body['data']));

        [$s, ] = $this->request('PATCH', "/rifas/{$rifa}/bilhetes/{$f['bAlheio']}/pagar", 'lider_departamento');
        $this->assertSame(403, $s);
        // reaproveitar/reativar o link alheio também é barrado
        [$s, ] = $this->request('POST', "/rifas/{$rifa}/divulgadores", 'lider_departamento', [
            'pessoa_id' => $f['pessoaAlheia'],
        ]);
        $this->assertSame(403, $s);
        [$s, ] = $this->request('DELETE', "/rifas/{$rifa}/divulgadores/{$f['dvAlheio']}", 'lider_departamento');
        $this->assertSame(403, $s);
    }

    /** Pagamento: reserva vencida (líder) e sorteio cancelado (todos) → 422. */
    public function testPagarRespeitaPrazoEStatusDaRifa(): void
    {
        $f = $this->fixturesRifa();
        $rifa = $f['rifa'];

        $this->pdo->prepare('UPDATE rifa_bilhetes SET expira_em = NOW() - INTERVAL 1 HOUR WHERE id = ?')
            ->execute([$f['bProprio']]);
        [$s, $body] = $this->request('PATCH', "/rifas/{$rifa}/bilhetes/{$f['bProprio']}/pagar", 'lider_departamento');
        $this->assertSame(422, $s);
        $this->assertSame('RESERVA_EXPIRADA', $body['error']['code']);

        $this->pdo->prepare("UPDATE rifas SET status = 'cancelado' WHERE id = ?")->execute([$rifa]);
        [$s, $body] = $this->request('PATCH', "/rifas/{$rifa}/bilhetes/{$f['bAlheio']}/pagar", 'secretaria');
        $this->assertSame(422, $s);
        $this->assertSame('STATUS_INVALIDO', $body['error']['code']);
    }

    /** D3: ebd_professor chama só a classe onde leciona (gate do controller). */
    public function testD3ChamadaSomenteNaPropriaClasse(): void
    {
        [$status, ] = $this->request('PUT', "/ebd/aulas/{$this->aulaId}/chamada", 'ebd_professor', [
            'classe_id' => $this->classeComProf,
            'frequencias' => [],
        ]);
        $this->assertSame(200, $status, 'professor da classe deveria passar');

        [$status, $body] = $this->request('PUT', "/ebd/aulas/{$this->aulaId}/chamada", 'ebd_professor', [
            'classe_id' => $this->classeSemProf,
            'frequencias' => [],
        ]);
        $this->assertSame(403, $status);
        $this->assertSame('SEM_PERMISSAO', $body['error']['code']);

        // ebd_admin passa em qualquer classe
        [$status, ] = $this->request('PUT', "/ebd/aulas/{$this->aulaId}/chamada", 'ebd_admin', [
            'classe_id' => $this->classeSemProf,
            'frequencias' => [],
        ]);
        $this->assertSame(200, $status);
    }

    /** D5: líder gerencia só o próprio departamento (gate do controller). */
    public function testD5LiderSomenteNoProprioDepto(): void
    {
        [$status, ] = $this->request('POST', "/departamentos/{$this->deptoSemLider}/membros", 'lider_departamento', [
            'pessoa_id' => $this->membroId,
        ]);
        $this->assertSame(403, $status, 'líder de outro depto deve dar 403');

        [$status, ] = $this->request('POST', "/departamentos/{$this->deptoComLider}/membros", 'lider_departamento', [
            'pessoa_id' => $this->membroId,
        ]);
        $this->assertNotSame(403, $status, 'líder do depto deve passar pelo gate');
        $this->assertSame(201, $status);
    }

    /** Controles positivos: cada perfil no que PODE. */
    public function testControlesPositivos(): void
    {
        [$s1, ] = $this->request('GET', '/membros', 'ebd_professor');
        $this->assertSame(200, $s1, 'GET aberto a todo autenticado');

        [$s2, ] = $this->request('GET', '/ebd/alunos', 'ebd_professor');
        $this->assertSame(200, $s2);

        [$s3, ] = $this->request('GET', '/usuarios', 'admin');
        $this->assertSame(200, $s3);

        [$s4, ] = $this->request('GET', '/ebd/relatorios/frequencia', 'dirigente');
        $this->assertSame(200, $s4);
    }
}
