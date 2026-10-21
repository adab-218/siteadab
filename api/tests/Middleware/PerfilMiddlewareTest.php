<?php

declare(strict_types=1);

namespace Adab\Api\Tests\Middleware;

use Adab\Api\Middleware\PerfilMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class PerfilMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
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

    private function request(): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', '/x');
    }

    public function testSemPerfilRetorna403(): void
    {
        $res = PerfilMiddleware::para(['secretaria'])->process($this->request(), $this->handlerOk());
        $this->assertSame(403, $res->getStatusCode());
        $this->assertStringContainsString('SEM_PERMISSAO', (string) $res->getBody());
    }

    public function testPerfilNaoPermitidoRetorna403(): void
    {
        $_SESSION['usuario'] = ['perfil' => 'ebd_professor'];
        $res = PerfilMiddleware::para(['secretaria'])->process($this->request(), $this->handlerOk());
        $this->assertSame(403, $res->getStatusCode());
    }

    public function testPerfilPermitidoPassa(): void
    {
        $_SESSION['usuario'] = ['perfil' => 'secretaria'];
        $res = PerfilMiddleware::para(['secretaria'])->process($this->request(), $this->handlerOk());
        $this->assertSame(200, $res->getStatusCode());
    }

    public function testAdminSemprePassa(): void
    {
        $_SESSION['usuario'] = ['perfil' => 'admin'];
        $res = PerfilMiddleware::para(['ebd_admin'])->process($this->request(), $this->handlerOk());
        $this->assertSame(200, $res->getStatusCode());
    }
}
