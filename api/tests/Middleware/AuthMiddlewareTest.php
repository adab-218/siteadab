<?php

declare(strict_types=1);

namespace Adab\Api\Tests\Middleware;

use Adab\Api\Middleware\AuthMiddleware;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class AuthMiddlewareTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, ativo INTEGER NOT NULL, perfil TEXT NOT NULL)'
        );
        $this->pdo->exec("INSERT INTO usuarios (id, ativo, perfil) VALUES (1, 1, 'secretaria'), (2, 0, 'admin')");
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function handlerOk(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };
    }

    private function mw(): AuthMiddleware
    {
        return new AuthMiddleware($this->pdo);
    }

    private function request(): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', '/x');
    }

    public function testSemSessaoRetorna401(): void
    {
        $res = $this->mw()->process($this->request(), $this->handlerOk());
        $this->assertSame(401, $res->getStatusCode());
        $this->assertStringContainsString('NAO_AUTENTICADO', (string) $res->getBody());
    }

    public function testUsuarioAtivoPassa(): void
    {
        $_SESSION['usuario'] = ['id' => 1, 'perfil' => 'secretaria'];
        $res = $this->mw()->process($this->request(), $this->handlerOk());
        $this->assertSame(200, $res->getStatusCode());
    }

    public function testUsuarioInativoRetorna401ELimpaSessao(): void
    {
        $_SESSION['usuario'] = ['id' => 2, 'perfil' => 'admin'];
        $res = $this->mw()->process($this->request(), $this->handlerOk());
        $this->assertSame(401, $res->getStatusCode());
        $this->assertSame([], $_SESSION);
    }

    public function testUsuarioInexistenteRetorna401(): void
    {
        $_SESSION['usuario'] = ['id' => 99, 'perfil' => 'admin'];
        $res = $this->mw()->process($this->request(), $this->handlerOk());
        $this->assertSame(401, $res->getStatusCode());
    }

    public function testPerfilDaSessaoEhAtualizadoPeloBanco(): void
    {
        $_SESSION['usuario'] = ['id' => 1, 'perfil' => 'perfil_antigo'];
        $this->mw()->process($this->request(), $this->handlerOk());
        $this->assertSame('secretaria', $_SESSION['usuario']['perfil']);
    }
}
