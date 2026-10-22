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
        foreach (['ebd_frequencias', 'ebd_aulas', 'ebd_periodos', 'ebd_alunos', 'ebd_professores',
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

        $this->pdo->exec("INSERT INTO ebd_periodos (ano, trimestre, data_inicio, data_fim)
                          VALUES (2026, 1, '2026-01-01', '2026-03-31')");
        $perId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO ebd_aulas (periodo_id, numero_licao, data) VALUES (?, 1, "2026-01-04")')
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

    /** Executa request HTTP de verdade pela app Slim com sessão simulada. */
    private function request(string $metodo, string $uri, ?string $perfil = null, ?array $body = null): array
    {
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
        $req = $this->requests->createServerRequest('POST', '/membros');
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
        ];
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
